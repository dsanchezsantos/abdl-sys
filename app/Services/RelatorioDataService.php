<?php

namespace App\Services;

use App\Models\Pagamento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * RelatorioDataService
 *
 * Centraliza todas as queries com o Filtro de Ouro aplicado.
 * É o equivalente Laravel do preparar_tabelas_para_pdfs() do Python (fase5_pdfs.py).
 *
 * REGRA DE OURO: Nunca retorna mais registros do que o necessário.
 * Toda query parte dos sell_numbers validados pela Barreira de Contenção.
 */
class RelatorioDataService
{
    /** Saldo inicial fixo por cartão — campo de contrato, não do banco de dados */
    public const SALDO_INICIAL_CARTAO = 250.00;

    // =========================================================================
    // A BARREIRA DE CONTENÇÃO
    // Réplica do Python: df_pag_v = df_pagamentos.merge(df_cart_v, how='inner')
    // =========================================================================

    /**
     * Retorna a subquery de sell_numbers válidos para a Verba Pública.
     * Esta é a "Barreira de Contenção" que alimenta TODOS os relatórios.
     *
     * Não executa a query — apenas monta o Builder para ser injetado
     * como subquery (whereIn) nas demais queries.
     */
    public function getVendasValidasQuery(int $feiraId): \Illuminate\Database\Eloquent\Builder
    {
        return Pagamento::where('pagamentos.id_feira', $feiraId)
            ->validosParaRateio()
            ->select('sell_number')
            ->distinct();
    }

    /**
     * Retorna os sell_numbers válidos como uma coleção de strings.
     * Usado para dividir em chunks para a estratégia anti-memory-leak.
     */
    public function getSellNumbersValidos(int $feiraId): \Illuminate\Support\Collection
    {
        return $this->getVendasValidasQuery($feiraId)->pluck('sell_number');
    }

    // =========================================================================
    // RELATÓRIO 1: TRANSAÇÕES POR CARTÃO
    // Réplica do Python: tr, ca = preparar_tabelas_para_pdfs()[0,1]
    // =========================================================================

    /**
     * KPIs do Resumo Executivo — Página 1 do relatório de Transações.
     * Réplica de: total_gasto, media_gasto, qtd_cartoes_zerados, total_cartoes
     */
    public function getKpisTransacoes(int $feiraId, array $sellNumbers): array
    {
        $resultado = DB::table('pagamentos as p')
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->whereIn('p.sell_number', $sellNumbers)
            ->where('p.id_feira', $feiraId)
            ->selectRaw('
                SUM(p.value)::numeric                           AS total_gasto,
                AVG(gastos_por_cartao.total_cartao)::numeric    AS media_gasto,
                COUNT(DISTINCT p.tag_code)                      AS total_cartoes
            ')
            ->crossJoin(DB::raw('(
                SELECT tag_code, SUM(value) as total_cartao
                FROM pagamentos
                WHERE id_feira = ' . $feiraId . '
                  AND sell_number = ANY(ARRAY[\'' . implode("','", $sellNumbers) . '\'])
                GROUP BY tag_code
            ) as gastos_por_cartao'))
            ->first();

        // Conta cartões que esgotaram o saldo inicial de R$ 250,00
        $cartoesZerados = DB::table('pagamentos')
            ->select('tag_code')
            ->whereIn('sell_number', $sellNumbers)
            ->where('id_feira', $feiraId)
            ->groupBy('tag_code')
            ->havingRaw('SUM(value) >= ?', [self::SALDO_INICIAL_CARTAO])
            ->get()
            ->count();

        return [
            'total_gasto'         => (float) ($resultado->total_gasto ?? 0),
            'media_gasto'         => (float) ($resultado->media_gasto ?? 0),
            'total_cartoes'       => (int) ($resultado->total_cartoes ?? 0),
            'cartoes_zerados'     => $cartoesZerados,
        ];
    }

    /**
     * Dados do gráfico de barras: Volume financeiro por dia.
     * Réplica de: gastos_dia = df_tr.groupby('Data_Apenas')['Valor_R$'].sum()
     *
     * @return array ['labels' => [...], 'values' => [...]]
     */
    public function getGastosDiarios(int $feiraId, array $sellNumbers): array
    {
        $rows = DB::table('pagamentos as p')
            ->join('venda_headers as vh', function ($join) {
                $join->on('p.sell_number', '=', 'vh.sell_number')
                     ->on('p.id_feira', '=', 'vh.id_feira');
            })
            ->whereIn('p.sell_number', $sellNumbers)
            ->where('p.id_feira', $feiraId)
            ->selectRaw("TO_CHAR(vh.date_hour, 'DD/MM/YYYY') AS dia, SUM(p.value) AS total")
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        return [
            'labels' => $rows->pluck('dia')->toArray(),
            'values' => $rows->pluck('total')->map(fn($v) => (float) $v)->toArray(),
        ];
    }

    /**
     * Dados de cartões para a tabela de detalhamento (Relatório de Transações).
     * Réplica de: ca = p_validos_df.groupby(['Código', 'Grupo']).agg(Gasto=sum)
     *
     * Usa LazyCollection + cursor() para não explodir RAM em cartões massivos.
     */
    public function getCartoesDetalhamento(int $feiraId, array $sellNumbers): LazyCollection
    {
        return DB::table('pagamentos as p')
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->whereIn('p.sell_number', $sellNumbers)
            ->where('p.id_feira', $feiraId)
            ->selectRaw('
                p.tag_code                          AS codigo,
                p.payment_group                     AS grupo,
                SUM(p.value)::numeric               AS valor_gasto,
                ? AS valor_inicial,
                (? - SUM(p.value))::numeric         AS saldo_restante
            ', [self::SALDO_INICIAL_CARTAO, self::SALDO_INICIAL_CARTAO])
            ->groupBy('p.tag_code', 'p.payment_group')
            ->orderBy('p.tag_code')
            ->cursor();
    }

    /**
     * Transações de um cartão específico (dentro do loop de detalhamento).
     * Réplica de: tr_cartao = df_tr[df_tr['Codigo'] == cod]
     */
    public function getTransacoesPorCartao(int $feiraId, string $tagCode, array $sellNumbers): \Illuminate\Support\Collection
    {
        return DB::table('pagamentos as p')
            ->join('venda_headers as vh', function ($join) {
                $join->on('p.sell_number', '=', 'vh.sell_number')
                     ->on('p.id_feira', '=', 'vh.id_feira');
            })
            ->leftJoin(DB::raw('(
                SELECT iv.sell_number, STRING_AGG(DISTINCT iv.name, \', \') AS livros
                FROM itens_venda iv
                WHERE iv.id_feira = ' . $feiraId . '
                GROUP BY iv.sell_number
            ) AS itens_agg'), 'p.sell_number', '=', 'itens_agg.sell_number')
            ->where('p.tag_code', $tagCode)
            ->whereIn('p.sell_number', $sellNumbers)
            ->where('p.id_feira', $feiraId)
            ->selectRaw("
                vh.date_hour    AS data_hora,
                p.sell_number   AS venda,
                vh.box          AS caixa,
                COALESCE(itens_agg.livros, 'N/A') AS livros,
                '1C'            AS uso_cartoes,
                p.value::numeric AS valor
            ")
            ->orderBy('vh.date_hour')
            ->get();
    }

    // =========================================================================
    // RELATÓRIO 2: VENDAS AGRUPADAS
    // Réplica do Python: vd, df_pag_todos = preparar_tabelas_para_pdfs()[2,3]
    // =========================================================================

    /**
     * KPIs do Resumo Executivo — Página 1 do relatório de Vendas.
     * Réplica de: total_vendas, total_arrecadado, ticket_medio
     */
    public function getKpisVendas(int $feiraId, array $sellNumbers): array
    {
        $resultado = DB::table('pagamentos as p')
            ->whereIn('p.sell_number', $sellNumbers)
            ->where('p.id_feira', $feiraId)
            ->selectRaw('
                COUNT(DISTINCT p.sell_number)::int AS total_vendas,
                SUM(p.value)::numeric              AS total_arrecadado
            ')
            ->first();

        $totalVendas       = (int) ($resultado->total_vendas ?? 0);
        $totalArrecadado   = (float) ($resultado->total_arrecadado ?? 0);
        $ticketMedio       = $totalVendas > 0 ? $totalArrecadado / $totalVendas : 0;

        return compact('totalVendas', 'totalArrecadado', 'ticketMedio');
    }

    /**
     * Volume de vendas por dia para o gráfico de barras (verde).
     */
    public function getVendasDiarias(int $feiraId, array $sellNumbers): array
    {
        $rows = DB::table('pagamentos as p')
            ->join('venda_headers as vh', function ($join) {
                $join->on('p.sell_number', '=', 'vh.sell_number')
                     ->on('p.id_feira', '=', 'vh.id_feira');
            })
            ->whereIn('p.sell_number', $sellNumbers)
            ->where('p.id_feira', $feiraId)
            ->selectRaw("TO_CHAR(vh.date_hour, 'DD/MM') AS dia, SUM(p.value) AS total")
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        return [
            'labels' => $rows->pluck('dia')->toArray(),
            'values' => $rows->pluck('total')->map(fn($v) => (float) $v)->toArray(),
        ];
    }

    /**
     * Vendas diárias agrupadas por Representante — para o line chart.
     * Réplica de: ed_diario.groupby(['Data_DT', 'Representante'])['Faturamento_Cartao'].sum()
     */
    public function getVendasDiariasPorRepresentante(int $feiraId, array $sellNumbers): array
    {
        $rows = DB::table('pagamentos as p')
            ->join('venda_headers as vh', function ($join) {
                $join->on('p.sell_number', '=', 'vh.sell_number')
                     ->on('p.id_feira', '=', 'vh.id_feira');
            })
            ->join('itens_venda as iv', function ($join) {
                $join->on('p.sell_number', '=', 'iv.sell_number')
                     ->on('p.id_feira', '=', 'iv.id_feira');
            })
            ->leftJoin('livros as l', function ($join) {
                $join->on('iv.produto_id_api', '=', 'l.produto_id_api')
                     ->on('iv.id_feira', '=', 'l.id_feira');
            })
            ->whereIn('p.sell_number', $sellNumbers)
            ->where('p.id_feira', $feiraId)
            ->selectRaw("
                TO_CHAR(vh.date_hour, 'DD/MM') AS dia,
                COALESCE(l.representante, 'NÃO INFORMADO') AS representante,
                SUM(p.value) AS total
            ")
            ->groupBy('dia', 'representante')
            ->orderBy('dia')
            ->get();

        // Pivotar: [['label' => 'FLORESCER', 'data' => [dia1, dia2, ...]], ...]
        $dias = $rows->pluck('dia')->unique()->sort()->values();
        $representantes = $rows->groupBy('representante');

        $datasets = $representantes->map(function ($items, $rep) use ($dias) {
            $porDia = $items->keyBy('dia');
            return [
                'label' => $rep,
                'data'  => $dias->map(fn($d) => (float) ($porDia->get($d)?->total ?? 0))->toArray(),
            ];
        })->values()->toArray();

        return ['labels' => $dias->toArray(), 'datasets' => $datasets];
    }

    /**
     * Vendas para o detalhamento (tabela de venddas por NF/sell_number).
     * Réplica de: vd = df_vd[['sellNumber', 'dateHour', 'box', 'Livros', 'Valor_Total_R$']]
     *
     * Usa cursor() para não explodir RAM.
     */
    public function getVendasDetalhamento(int $feiraId, array $sellNumbers): LazyCollection
    {
        return DB::table('venda_headers as vh')
            ->whereIn('vh.sell_number', $sellNumbers)
            ->where('vh.id_feira', $feiraId)
            ->leftJoin(DB::raw('(
                SELECT sell_number, STRING_AGG(DISTINCT name, \', \') AS livros
                FROM itens_venda WHERE id_feira = ' . $feiraId . '
                GROUP BY sell_number
            ) AS itens_agg'), 'vh.sell_number', '=', 'itens_agg.sell_number')
            ->leftJoin(DB::raw('(
                SELECT sell_number, SUM(value) AS total_pago
                FROM pagamentos WHERE id_feira = ' . $feiraId . '
                GROUP BY sell_number
            ) AS pag_agg'), 'vh.sell_number', '=', 'pag_agg.sell_number')
            ->selectRaw("
                vh.sell_number,
                vh.date_hour,
                vh.box,
                COALESCE(itens_agg.livros, 'N/A') AS livros,
                COALESCE(pag_agg.total_pago, 0)::numeric AS valor_total
            ")
            ->orderBy('vh.date_hour')
            ->cursor();
    }

    /**
     * Pagamentos de uma venda específica (para o sub-detalhamento dentro do loop).
     * Réplica de: pags_venda = df_pag_todos[df_pag_todos['sellNumber'] == venda_id]
     */
    public function getPagamentosPorVenda(int $feiraId, string $sellNumber): \Illuminate\Support\Collection
    {
        return DB::table('pagamentos as p')
            ->leftJoin('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->where('p.sell_number', $sellNumber)
            ->where('p.id_feira', $feiraId)
            ->selectRaw('
                p.payment_way,
                p.tag_code,
                p.payment_group,
                c.classificacao,
                p.value::numeric AS valor
            ')
            ->get();
    }

    // =========================================================================
    // RELATÓRIO 3: EDITORAS / REPRESENTANTES (com Alocação Proporcional)
    // Réplica EXATA do Python:
    //   df_itens_aloc['Proporcao'] = Total_Pago_Cartao / Total_Bruto_Livros
    //   df_itens_aloc['Faturamento_Cartao'] = valor_total_num * Proporcao
    // =========================================================================

    /**
     * KPIs do Resumo Executivo — Página 1 do relatório de Editoras.
     * Réplica de: total_livros, total_arrecadado
     */
    public function getKpisEditoras(int $feiraId, array $sellNumbers): array
    {
        $resultado = DB::table('itens_venda as iv')
            ->whereIn('iv.sell_number', $sellNumbers)
            ->where('iv.id_feira', $feiraId)
            ->selectRaw('SUM(iv.amount)::int AS total_livros')
            ->first();

        $totalArrecadado = DB::table('pagamentos')
            ->whereIn('sell_number', $sellNumbers)
            ->where('id_feira', $feiraId)
            ->sum('value');

        return [
            'total_livros'     => (int) ($resultado->total_livros ?? 0),
            'total_arrecadado' => (float) $totalArrecadado,
        ];
    }

    /**
     * Resumo por Representante → Editora (Categoria) com Alocação Proporcional.
     * Réplica EXATA do Python — usa SQL Window Functions para calcular
     * a proporção sem carregar os dados na memória da aplicação.
     *
     * Python original:
     *   Proporcao = Total_Pago_Cartao / Total_Bruto_Livros
     *   Faturamento_Cartao = valor_total_num * Proporcao
     *
     * @return \Illuminate\Support\Collection (agrupada por Representante → Categoria)
     */
    public function getEditorasResumoComAlocacao(int $feiraId, array $sellNumbers): \Illuminate\Support\Collection
    {
        /*
         * A alocação proporcional em SQL puro (Window Functions).
         * Particionamos por sell_number para calcular a proporção
         * de cada item dentro da sua própria venda.
         */
        $alocacao = DB::table('itens_venda as iv')
            ->whereIn('iv.sell_number', $sellNumbers)
            ->where('iv.id_feira', $feiraId)
            ->leftJoin('livros as l', function ($join) {
                $join->on('iv.produto_id_api', '=', 'l.produto_id_api')
                     ->on('iv.id_feira', '=', 'l.id_feira');
            })
            ->selectRaw("
                COALESCE(l.representante, 'NÃO INFORMADO')   AS representante,
                COALESCE(l.editora, 'AVULSO')                 AS categoria,
                SUM(iv.amount)                                AS qtd_livros,
                SUM(iv.total_value)::numeric                  AS valor_bruto_capa,

                -- Alocação Proporcional (réplica exata do Python):
                -- Para cada item, calculamos qual fração do total bruto da
                -- venda ele representa, e aplicamos essa fração no valor pago.
                SUM(
                    iv.total_value
                    * (
                        pag_venda.total_pago_cartao::numeric
                        / NULLIF(bruto_venda.total_bruto::numeric, 0)
                    )
                )::numeric AS faturamento_cartao
            ")
            ->join(DB::raw('(
                SELECT sell_number, SUM(value) AS total_pago_cartao
                FROM pagamentos
                WHERE id_feira = ' . $feiraId . '
                GROUP BY sell_number
            ) AS pag_venda'), 'iv.sell_number', '=', 'pag_venda.sell_number')
            ->join(DB::raw('(
                SELECT sell_number, SUM(total_value) AS total_bruto
                FROM itens_venda
                WHERE id_feira = ' . $feiraId . '
                GROUP BY sell_number
            ) AS bruto_venda'), 'iv.sell_number', '=', 'bruto_venda.sell_number')
            ->groupBy(
                DB::raw("COALESCE(l.representante, 'NÃO INFORMADO')"),
                DB::raw("COALESCE(l.editora, 'AVULSO')")
            )
            ->orderByRaw("representante ASC, faturamento_cartao DESC")
            ->get();

        return $alocacao;
    }

    /**
     * Campeã de cada Representante (Editora com maior faturamento).
     * Réplica de: campeas[rep] = df_rep.loc[df_rep['Faturamento_Cartao'].idxmax()]
     */
    public function getCampeasPorRepresentante(\Illuminate\Support\Collection $resumo): array
    {
        return $resumo
            ->groupBy('representante')
            ->map(function ($items, $rep) {
                $melhor = $items->sortByDesc('faturamento_cartao')->first();
                return [
                    'representante' => $rep,
                    'categoria'     => $melhor->categoria,
                    'faturamento'   => (float) $melhor->faturamento_cartao,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Evolução diária do faturamento por Representante — para o Line Chart.
     * Réplica de: ed_diario.groupby(['Data_DT', 'Representante'])['Faturamento_Cartao'].sum()
     */
    public function getEvolucaoDiariaPorRepresentante(int $feiraId, array $sellNumbers): array
    {
        $rows = DB::table('itens_venda as iv')
            ->whereIn('iv.sell_number', $sellNumbers)
            ->where('iv.id_feira', $feiraId)
            ->join('venda_headers as vh', function ($join) {
                $join->on('iv.sell_number', '=', 'vh.sell_number')
                     ->on('iv.id_feira', '=', 'vh.id_feira');
            })
            ->leftJoin('livros as l', function ($join) {
                $join->on('iv.produto_id_api', '=', 'l.produto_id_api')
                     ->on('iv.id_feira', '=', 'l.id_feira');
            })
            ->join(DB::raw('(
                SELECT sell_number, SUM(value) AS total_pago_cartao
                FROM pagamentos WHERE id_feira = ' . $feiraId . ' GROUP BY sell_number
            ) AS pag_venda'), 'iv.sell_number', '=', 'pag_venda.sell_number')
            ->join(DB::raw('(
                SELECT sell_number, SUM(total_value) AS total_bruto
                FROM itens_venda WHERE id_feira = ' . $feiraId . ' GROUP BY sell_number
            ) AS bruto_venda'), 'iv.sell_number', '=', 'bruto_venda.sell_number')
            ->selectRaw("
                TO_CHAR(vh.date_hour, 'DD/MM') AS dia,
                COALESCE(l.representante, 'NÃO INFORMADO') AS representante,
                SUM(
                    iv.total_value
                    * (pag_venda.total_pago_cartao::numeric / NULLIF(bruto_venda.total_bruto::numeric, 0))
                )::numeric AS faturamento_cartao
            ")
            ->groupBy(
                DB::raw("TO_CHAR(vh.date_hour, 'DD/MM')"),
                DB::raw("COALESCE(l.representante, 'NÃO INFORMADO')")
            )
            ->orderBy('dia')
            ->get();

        $dias = $rows->pluck('dia')->unique()->sort()->values();
        $representantes = $rows->groupBy('representante');

        $datasets = $representantes->map(function ($items, $rep) use ($dias) {
            $porDia = $items->keyBy('dia');
            return [
                'label' => $rep,
                'data'  => $dias->map(fn($d) => (float) ($porDia->get($d)?->faturamento_cartao ?? 0))->toArray(),
            ];
        })->values()->toArray();

        return ['labels' => $dias->toArray(), 'datasets' => $datasets];
    }

    /**
     * Detalhe de livros por Editora/Representante para a tabela do relatório.
     * Réplica de: ed_livros = ed.groupby(['Representante', 'Categoria', 'name_up'])
     *
     * Usa cursor() para não explodir RAM em feiras com muitos títulos.
     */
    public function getLivrosDetalhePorEditora(int $feiraId, array $sellNumbers): LazyCollection
    {
        return DB::table('itens_venda as iv')
            ->whereIn('iv.sell_number', $sellNumbers)
            ->where('iv.id_feira', $feiraId)
            ->leftJoin('livros as l', function ($join) {
                $join->on('iv.produto_id_api', '=', 'l.produto_id_api')
                     ->on('iv.id_feira', '=', 'l.id_feira');
            })
            ->join(DB::raw('(
                SELECT sell_number, SUM(value) AS total_pago_cartao
                FROM pagamentos WHERE id_feira = ' . $feiraId . ' GROUP BY sell_number
            ) AS pag_venda'), 'iv.sell_number', '=', 'pag_venda.sell_number')
            ->join(DB::raw('(
                SELECT sell_number, SUM(total_value) AS total_bruto
                FROM itens_venda WHERE id_feira = ' . $feiraId . ' GROUP BY sell_number
            ) AS bruto_venda'), 'iv.sell_number', '=', 'bruto_venda.sell_number')
            ->selectRaw("
                COALESCE(l.representante, 'NÃO INFORMADO')   AS representante,
                COALESCE(l.editora, 'AVULSO')                 AS categoria,
                UPPER(COALESCE(l.produto, iv.name))           AS nome_livro,
                SUM(iv.amount)                                AS qtd,
                SUM(iv.total_value)::numeric                  AS bruto_total,
                (SUM(iv.total_value) / NULLIF(SUM(iv.amount), 0))::numeric AS preco_unit_bruto,
                SUM(
                    iv.total_value
                    * (pag_venda.total_pago_cartao::numeric / NULLIF(bruto_venda.total_bruto::numeric, 0))
                )::numeric AS faturamento_cartao
            ")
            ->groupBy(
                DB::raw("COALESCE(l.representante, 'NÃO INFORMADO')"),
                DB::raw("COALESCE(l.editora, 'AVULSO')"),
                DB::raw("UPPER(COALESCE(l.produto, iv.name))")
            )
            ->orderByRaw('representante ASC, categoria ASC, faturamento_cartao DESC')
            ->cursor();
    }

    /**
     * Inconsistências de catálogo — ANEXO de Auditoria.
     * Réplica de: df_inconsistencias = ed[ed['Produto'].isna()]
     * Livros que constam em itens_venda mas NÃO têm correspondência em livros.
     */
    public function getInconsistenciasCatalogo(int $feiraId, array $sellNumbers): \Illuminate\Support\Collection
    {
        return DB::table('itens_venda as iv')
            ->whereIn('iv.sell_number', $sellNumbers)
            ->where('iv.id_feira', $feiraId)
            ->leftJoin('livros as l', function ($join) {
                $join->on('iv.produto_id_api', '=', 'l.produto_id_api')
                     ->on('iv.id_feira', '=', 'l.id_feira');
            })
            ->join(DB::raw('(
                SELECT sell_number, SUM(value) AS total_pago_cartao
                FROM pagamentos WHERE id_feira = ' . $feiraId . ' GROUP BY sell_number
            ) AS pag_venda'), 'iv.sell_number', '=', 'pag_venda.sell_number')
            ->join(DB::raw('(
                SELECT sell_number, SUM(total_value) AS total_bruto
                FROM itens_venda WHERE id_feira = ' . $feiraId . ' GROUP BY sell_number
            ) AS bruto_venda'), 'iv.sell_number', '=', 'bruto_venda.sell_number')
            ->whereNull('l.id') // Só os que NÃO têm match no catálogo
            ->selectRaw("
                UPPER(iv.name) AS nome_livro,
                SUM(iv.amount) AS qtd,
                SUM(
                    iv.total_value
                    * (pag_venda.total_pago_cartao::numeric / NULLIF(bruto_venda.total_bruto::numeric, 0))
                )::numeric AS faturamento_cartao
            ")
            ->groupBy('nome_livro')
            ->orderByRaw('faturamento_cartao DESC')
            ->get();
    }
}
