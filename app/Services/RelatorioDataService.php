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

    /**
     * Retorna os códigos de cartões válidos da feira (Filtro de Ouro).
     * Usado para fatiar o relatório de cartões por tag_code.
     */
    public function getCartoesValidos(int $feiraId): \Illuminate\Support\Collection
    {
        return DB::table('pagamentos as p')
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->where('p.id_feira', $feiraId)
            ->whereRaw('UPPER(p.payment_way) NOT LIKE ?', ['%DESCONTO%'])
            ->whereRaw('UPPER(p.payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            ->where('c.classificacao', '!=', \App\Enums\CartaoClassificacao::TESTE->value)
            ->select('p.tag_code')
            ->distinct()
            ->pluck('p.tag_code');
    }

    // =========================================================================
    // RELATÓRIO 1: TRANSAÇÕES POR CARTÃO
    // Réplica do Python: tr, ca = preparar_tabelas_para_pdfs()[0,1]
    // =========================================================================

    /**
     * KPIs do Resumo Executivo — Página 1 do relatório de Transações.
     * Réplica de: total_gasto, media_gasto, qtd_cartoes_zerados, total_cartoes
     */
    public function getKpisTransacoes(int $feiraId, ?array $sellNumbers = null): array
    {
        $query = DB::table('pagamentos as p')
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->where('p.id_feira', $feiraId)
            ->whereRaw('UPPER(p.payment_way) NOT LIKE ?', ['%DESCONTO%'])
            ->whereRaw('UPPER(p.payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            ->where('c.classificacao', '!=', \App\Enums\CartaoClassificacao::TESTE->value);

        if ($sellNumbers !== null) {
            $query->whereIn('p.sell_number', $sellNumbers);
        } else {
            $query->whereIn('p.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        $resultado = $query
            ->selectRaw('
                SUM(p.value)::numeric                           AS total_gasto,
                COUNT(DISTINCT p.tag_code)                      AS total_cartoes
            ')
            ->first();

        // Conta cartões que esgotaram o saldo inicial de R$ 250,00
        $cartoesZeradosQuery = DB::table('pagamentos as p')
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->where('p.id_feira', $feiraId)
            ->whereRaw('UPPER(p.payment_way) NOT LIKE ?', ['%DESCONTO%'])
            ->whereRaw('UPPER(p.payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            ->where('c.classificacao', '!=', \App\Enums\CartaoClassificacao::TESTE->value);

        if ($sellNumbers !== null) {
            $cartoesZeradosQuery->whereIn('p.sell_number', $sellNumbers);
        } else {
            $cartoesZeradosQuery->whereIn('p.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        $cartoesZerados = $cartoesZeradosQuery
            ->select('p.tag_code')
            ->groupBy('p.tag_code')
            ->havingRaw('SUM(p.value) >= ?', [self::SALDO_INICIAL_CARTAO])
            ->get()
            ->count();

        $totalGasto = (float) ($resultado->total_gasto ?? 0);
        $totalCartoes = (int) ($resultado->total_cartoes ?? 0);
        $mediaGasto = $totalCartoes > 0 ? $totalGasto / $totalCartoes : 0.0;

        return [
            'total_gasto'         => $totalGasto,
            'media_gasto'         => $mediaGasto,
            'total_cartoes'       => $totalCartoes,
            'cartoes_zerados'     => $cartoesZerados,
        ];
    }

    /**
     * Dados do gráfico de barras: Volume financeiro por dia.
     * Réplica de: gastos_dia = df_tr.groupby('Data_Apenas')['Valor_R$'].sum()
     */
    public function getGastosDiarios(int $feiraId, ?array $sellNumbers = null): array
    {
        $query = DB::table('pagamentos as p')
            ->join('venda_headers as vh', function ($join) {
                $join->on('p.sell_number', '=', 'vh.sell_number')
                     ->on('p.id_feira', '=', 'vh.id_feira');
            })
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->where('p.id_feira', $feiraId)
            ->whereRaw('UPPER(p.payment_way) NOT LIKE ?', ['%DESCONTO%'])
            ->whereRaw('UPPER(p.payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            ->where('c.classificacao', '!=', \App\Enums\CartaoClassificacao::TESTE->value);

        if ($sellNumbers !== null) {
            $query->whereIn('p.sell_number', $sellNumbers);
        } else {
            $query->whereIn('p.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        $rows = $query
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
     */
    public function getCartoesDetalhamento(int $feiraId, ?array $tagCodes = null): LazyCollection
    {
        $query = DB::table('pagamentos as p')
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->where('p.id_feira', $feiraId)
            ->whereRaw('UPPER(p.payment_way) NOT LIKE ?', ['%DESCONTO%'])
            ->whereRaw('UPPER(p.payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            ->where('c.classificacao', '!=', \App\Enums\CartaoClassificacao::TESTE->value);

        if ($tagCodes !== null) {
            $query->whereIn('p.tag_code', $tagCodes);
        } else {
            $query->whereIn('p.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        return $query
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
     */
    public function getTransacoesPorCartao(int $feiraId, string $tagCode, ?array $sellNumbers = null): \Illuminate\Support\Collection
    {
        $query = DB::table('pagamentos as p')
            ->join('venda_headers as vh', function ($join) {
                $join->on('p.sell_number', '=', 'vh.sell_number')
                     ->on('p.id_feira', '=', 'vh.id_feira');
            })
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->leftJoin(DB::raw('(
                SELECT iv.sell_number, STRING_AGG(DISTINCT iv.name, \', \') AS livros
                FROM itens_venda iv
                WHERE iv.id_feira = ' . $feiraId . '
                GROUP BY iv.sell_number
            ) AS itens_agg'), 'p.sell_number', '=', 'itens_agg.sell_number')
            ->where('p.tag_code', $tagCode)
            ->where('p.id_feira', $feiraId)
            ->whereRaw('UPPER(p.payment_way) NOT LIKE ?', ['%DESCONTO%'])
            ->whereRaw('UPPER(p.payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            ->where('c.classificacao', '!=', \App\Enums\CartaoClassificacao::TESTE->value);

        if ($sellNumbers !== null) {
            $query->whereIn('p.sell_number', $sellNumbers);
        } else {
            $query->whereIn('p.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        return $query->selectRaw("
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

    /**
     * Otimização Anti-N+1: Retorna todas as transações para os cartões específicos do chunk agrupados.
     */
    public function getTransacoesPorCartaoChunk(int $feiraId, ?array $tagCodes = null): \Illuminate\Support\Collection
    {
        $query = DB::table('pagamentos as p')
            ->join('venda_headers as vh', function ($join) {
                $join->on('p.sell_number', '=', 'vh.sell_number')
                     ->on('p.id_feira', '=', 'vh.id_feira');
            })
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->leftJoin(DB::raw('(
                SELECT iv.sell_number, STRING_AGG(DISTINCT iv.name, \', \') AS livros
                FROM itens_venda iv
                WHERE iv.id_feira = ' . $feiraId . '
                GROUP BY iv.sell_number
            ) AS itens_agg'), 'p.sell_number', '=', 'itens_agg.sell_number')
            ->where('p.id_feira', $feiraId)
            ->whereRaw('UPPER(p.payment_way) NOT LIKE ?', ['%DESCONTO%'])
            ->whereRaw('UPPER(p.payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            ->where('c.classificacao', '!=', \App\Enums\CartaoClassificacao::TESTE->value);

        if ($tagCodes !== null) {
            $query->whereIn('p.tag_code', $tagCodes);
        } else {
            $query->whereIn('p.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        return $query->selectRaw("
                p.tag_code      AS tag_code,
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
     */
    public function getKpisVendas(int $feiraId, ?array $sellNumbers = null): array
    {
        $query = DB::table('pagamentos as p')
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->where('p.id_feira', $feiraId)
            ->whereRaw('UPPER(p.payment_way) NOT LIKE ?', ['%DESCONTO%'])
            ->whereRaw('UPPER(p.payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            ->where('c.classificacao', '!=', \App\Enums\CartaoClassificacao::TESTE->value);

        if ($sellNumbers !== null) {
            $query->whereIn('p.sell_number', $sellNumbers);
        } else {
            $query->whereIn('p.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        $resultado = $query
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
    public function getVendasDiarias(int $feiraId, ?array $sellNumbers = null): array
    {
        $query = DB::table('pagamentos as p')
            ->join('venda_headers as vh', function ($join) {
                $join->on('p.sell_number', '=', 'vh.sell_number')
                     ->on('p.id_feira', '=', 'vh.id_feira');
            })
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->where('p.id_feira', $feiraId)
            ->whereRaw('UPPER(p.payment_way) NOT LIKE ?', ['%DESCONTO%'])
            ->whereRaw('UPPER(p.payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            ->where('c.classificacao', '!=', \App\Enums\CartaoClassificacao::TESTE->value);

        if ($sellNumbers !== null) {
            $query->whereIn('p.sell_number', $sellNumbers);
        } else {
            $query->whereIn('p.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        $rows = $query
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
     */
    public function getVendasDiariasPorRepresentante(int $feiraId, ?array $sellNumbers = null): array
    {
        $query = DB::table('pagamentos as p')
            ->join('venda_headers as vh', function ($join) {
                $join->on('p.sell_number', '=', 'vh.sell_number')
                     ->on('p.id_feira', '=', 'vh.id_feira');
            })
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->join('itens_venda as iv', function ($join) {
                $join->on('p.sell_number', '=', 'iv.sell_number')
                     ->on('p.id_feira', '=', 'iv.id_feira');
            })
            ->leftJoin('livros as l', function ($join) {
                $join->on('iv.name', '=', 'l.produto')
                     ->on('iv.id_feira', '=', 'l.id_feira');
            })
            ->where('p.id_feira', $feiraId)
            ->whereRaw('UPPER(p.payment_way) NOT LIKE ?', ['%DESCONTO%'])
            ->whereRaw('UPPER(p.payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            ->where('c.classificacao', '!=', \App\Enums\CartaoClassificacao::TESTE->value);

        if ($sellNumbers !== null) {
            $query->whereIn('p.sell_number', $sellNumbers);
        } else {
            $query->whereIn('p.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        $rows = $query
            ->selectRaw("
                TO_CHAR(vh.date_hour, 'DD/MM') AS dia,
                COALESCE(l.representante, 'NÃO INFORMADO') AS representante,
                SUM(p.value) AS total
            ")
            ->groupBy('dia', 'representante')
            ->orderBy('dia')
            ->get();

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
     * Vendas para o detalhamento (tabela de vendas por NF/sell_number).
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
                SELECT p.sell_number, SUM(p.value) AS total_pago
                FROM pagamentos p
                JOIN cartoes c ON p.tag_code = c.tag_code AND p.id_feira = c.id_feira
                WHERE p.id_feira = ' . $feiraId . '
                  AND UPPER(p.payment_way) NOT LIKE \'%DESCONTO%\'
                  AND UPPER(p.payment_group) NOT LIKE \'%PAGAMENTO SEM GRUPO%\'
                  AND c.classificacao != \'' . \App\Enums\CartaoClassificacao::TESTE->value . '\'
                GROUP BY p.sell_number
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
     */
    public function getPagamentosPorVenda(int $feiraId, string $sellNumber): \Illuminate\Support\Collection
    {
        return DB::table('pagamentos as p')
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->where('p.sell_number', $sellNumber)
            ->where('p.id_feira', $feiraId)
            ->whereRaw('UPPER(p.payment_way) NOT LIKE ?', ['%DESCONTO%'])
            ->whereRaw('UPPER(p.payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            ->where('c.classificacao', '!=', \App\Enums\CartaoClassificacao::TESTE->value)
            ->selectRaw('
                p.payment_way,
                p.tag_code,
                p.payment_group,
                c.classificacao,
                p.value::numeric AS valor
            ')
            ->get();
    }

    /**
     * Otimização Anti-N+1: Retorna todos os pagamentos para os sell_numbers do chunk agrupados.
     */
    public function getPagamentosPorVendasChunk(int $feiraId, array $sellNumbers): \Illuminate\Support\Collection
    {
        return DB::table('pagamentos as p')
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->whereIn('p.sell_number', $sellNumbers)
            ->where('p.id_feira', $feiraId)
            ->whereRaw('UPPER(p.payment_way) NOT LIKE ?', ['%DESCONTO%'])
            ->whereRaw('UPPER(p.payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            ->where('c.classificacao', '!=', \App\Enums\CartaoClassificacao::TESTE->value)
            ->selectRaw('
                p.sell_number,
                p.payment_way,
                p.tag_code,
                p.payment_group,
                c.classificacao,
                p.value::numeric AS valor
            ')
            ->get();
    }

    // =========================================================================
    // HELPER METHODS FOR SQL SUBQUERIES (BIND-FREE FOR SQL INTEGRITY)
    // =========================================================================

    protected function getPagVendaSql(int $feiraId, ?array $sellNumbers): string
    {
        $vendasValidasSql = '
            SELECT DISTINCT p3.sell_number
            FROM pagamentos p3
            JOIN cartoes c3 ON p3.tag_code = c3.tag_code AND p3.id_feira = c3.id_feira
            WHERE p3.id_feira = ' . (int) $feiraId . '
              AND UPPER(p3.payment_way) NOT LIKE \'%DESCONTO%\'
              AND UPPER(p3.payment_group) NOT LIKE \'%PAGAMENTO SEM GRUPO%\'
              AND c3.classificacao != \'' . \App\Enums\CartaoClassificacao::TESTE->value . '\'
        ';

        $sql = '
            SELECT p.sell_number, SUM(p.value) AS total_pago_cartao
            FROM pagamentos p
            JOIN cartoes c ON p.tag_code = c.tag_code AND p.id_feira = c.id_feira
            WHERE p.id_feira = ' . (int) $feiraId . '
              AND UPPER(p.payment_way) NOT LIKE \'%DESCONTO%\'
              AND UPPER(p.payment_group) NOT LIKE \'%PAGAMENTO SEM GRUPO%\'
              AND c.classificacao != \'' . \App\Enums\CartaoClassificacao::TESTE->value . '\'
        ';

        if ($sellNumbers !== null) {
            $sanitize = fn($val) => preg_replace('/[^a-zA-Z0-9_-]/', '', $val);
            $escaped = array_map(fn($val) => "'" . $sanitize($val) . "'", $sellNumbers);
            $sql .= ' AND p.sell_number IN (' . implode(',', $escaped) . ')';
        } else {
            $sql .= ' AND p.sell_number IN (' . $vendasValidasSql . ')';
        }

        $sql .= ' GROUP BY p.sell_number';

        return $sql;
    }

    protected function getBrutoVendaSql(int $feiraId, ?array $sellNumbers): string
    {
        $vendasValidasSql = '
            SELECT DISTINCT p3.sell_number
            FROM pagamentos p3
            JOIN cartoes c3 ON p3.tag_code = c3.tag_code AND p3.id_feira = c3.id_feira
            WHERE p3.id_feira = ' . (int) $feiraId . '
              AND UPPER(p3.payment_way) NOT LIKE \'%DESCONTO%\'
              AND UPPER(p3.payment_group) NOT LIKE \'%PAGAMENTO SEM GRUPO%\'
              AND c3.classificacao != \'' . \App\Enums\CartaoClassificacao::TESTE->value . '\'
        ';

        $sql = '
            SELECT sell_number, SUM(total_value) AS total_bruto
            FROM itens_venda
            WHERE id_feira = ' . (int) $feiraId . '
        ';

        if ($sellNumbers !== null) {
            $sanitize = fn($val) => preg_replace('/[^a-zA-Z0-9_-]/', '', $val);
            $escaped = array_map(fn($val) => "'" . $sanitize($val) . "'", $sellNumbers);
            $sql .= ' AND sell_number IN (' . implode(',', $escaped) . ')';
        } else {
            $sql .= ' AND sell_number IN (' . $vendasValidasSql . ')';
        }

        $sql .= ' GROUP BY sell_number';

        return $sql;
    }

    // =========================================================================
    // RELATÓRIO 3: EDITORAS / REPRESENTANTES (com Alocação Proporcional)
    // =========================================================================

    /**
     * KPIs do Resumo Executivo — Página 1 do relatório de Editoras.
     */
    public function getKpisEditoras(int $feiraId, ?array $sellNumbers = null): array
    {
        $query1 = DB::table('itens_venda as iv')
            ->where('iv.id_feira', $feiraId);

        if ($sellNumbers !== null) {
            $query1->whereIn('iv.sell_number', $sellNumbers);
        } else {
            $query1->whereIn('iv.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        $resultado = $query1->selectRaw('SUM(iv.amount)::int AS total_livros')->first();

        $query2 = DB::table('pagamentos as p')
            ->join('cartoes as c', function ($join) {
                $join->on('p.tag_code', '=', 'c.tag_code')
                     ->on('p.id_feira', '=', 'c.id_feira');
            })
            ->where('p.id_feira', $feiraId)
            ->whereRaw('UPPER(p.payment_way) NOT LIKE ?', ['%DESCONTO%'])
            ->whereRaw('UPPER(p.payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            ->where('c.classificacao', '!=', \App\Enums\CartaoClassificacao::TESTE->value);

        if ($sellNumbers !== null) {
            $query2->whereIn('p.sell_number', $sellNumbers);
        } else {
            $query2->whereIn('p.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        $totalArrecadado = $query2->sum('p.value');

        return [
            'total_livros'     => (int) ($resultado->total_livros ?? 0),
            'total_arrecadado' => (float) $totalArrecadado,
        ];
    }

    /**
     * Resumo por Representante → Editora (Categoria) com Alocação Proporcional.
     */
    public function getEditorasResumoComAlocacao(int $feiraId, ?array $sellNumbers = null): \Illuminate\Support\Collection
    {
        $pagVendaSql = $this->getPagVendaSql($feiraId, $sellNumbers);
        $brutoVendaSql = $this->getBrutoVendaSql($feiraId, $sellNumbers);

        $query = DB::table('itens_venda as iv')
            ->where('iv.id_feira', $feiraId)
            ->leftJoin('livros as l', function ($join) {
                $join->on('iv.name', '=', 'l.produto')
                     ->on('iv.id_feira', '=', 'l.id_feira');
            });

        if ($sellNumbers !== null) {
            $query->whereIn('iv.sell_number', $sellNumbers);
        } else {
            $query->whereIn('iv.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        $query->selectRaw("
            COALESCE(l.representante, 'NÃO INFORMADO')   AS representante,
            COALESCE(l.editora, 'AVULSO')                 AS categoria,
            SUM(iv.amount)                                AS qtd_livros,
            SUM(iv.total_value)::numeric                  AS valor_bruto_capa,

            SUM(
                iv.total_value
                * (
                    pag_venda.total_pago_cartao::numeric
                    / NULLIF(bruto_venda.total_bruto::numeric, 0)
                )
            )::numeric AS faturamento_cartao
        ");

        $query->join(DB::raw("({$pagVendaSql}) as pag_venda"), 'iv.sell_number', '=', 'pag_venda.sell_number')
              ->join(DB::raw("({$brutoVendaSql}) as bruto_venda"), 'iv.sell_number', '=', 'bruto_venda.sell_number');

        return $query->groupBy(
                DB::raw("COALESCE(l.representante, 'NÃO INFORMADO')"),
                DB::raw("COALESCE(l.editora, 'AVULSO')")
            )
            ->orderByRaw("representante ASC, faturamento_cartao DESC")
            ->get();
    }

    /**
     * Campeã de cada Representante (Editora com maior faturamento).
     */
    public function getCampeasPorRepresentante(\Illuminate\Support\Collection $resumo): array
    {
        return $resumo
            ->groupBy('representante')
            ->map(function ($items, $rep) {
                $melhor = $items->sortByDesc('faturamento_cartao')->first();
                return [
                    'representante' => $rep,
                    'categoria'     => $melhor->categoria ?? 'N/A',
                    'faturamento'   => (float) ($melhor->faturamento_cartao ?? 0),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Evolução diária do faturamento por Representante — para o Line Chart.
     */
    public function getEvolucaoDiariaPorRepresentante(int $feiraId, ?array $sellNumbers = null): array
    {
        $pagVendaSql = $this->getPagVendaSql($feiraId, $sellNumbers);
        $brutoVendaSql = $this->getBrutoVendaSql($feiraId, $sellNumbers);

        $query = DB::table('itens_venda as iv')
            ->where('iv.id_feira', $feiraId)
            ->join('venda_headers as vh', function ($join) {
                $join->on('iv.sell_number', '=', 'vh.sell_number')
                     ->on('iv.id_feira', '=', 'vh.id_feira');
            })
            ->leftJoin('livros as l', function ($join) {
                $join->on('iv.name', '=', 'l.produto')
                     ->on('iv.id_feira', '=', 'l.id_feira');
            });

        if ($sellNumbers !== null) {
            $query->whereIn('iv.sell_number', $sellNumbers);
        } else {
            $query->whereIn('iv.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        $query->join(DB::raw("({$pagVendaSql}) as pag_venda"), 'iv.sell_number', '=', 'pag_venda.sell_number')
              ->join(DB::raw("({$brutoVendaSql}) as bruto_venda"), 'iv.sell_number', '=', 'bruto_venda.sell_number');

        $rows = $query->selectRaw("
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
     */
    public function getLivrosDetalhePorEditora(int $feiraId, ?array $sellNumbers = null): LazyCollection
    {
        $pagVendaSql = $this->getPagVendaSql($feiraId, $sellNumbers);
        $brutoVendaSql = $this->getBrutoVendaSql($feiraId, $sellNumbers);

        $query = DB::table('itens_venda as iv')
            ->where('iv.id_feira', $feiraId)
            ->leftJoin('livros as l', function ($join) {
                $join->on('iv.name', '=', 'l.produto')
                     ->on('iv.id_feira', '=', 'l.id_feira');
            });

        if ($sellNumbers !== null) {
            $query->whereIn('iv.sell_number', $sellNumbers);
        } else {
            $query->whereIn('iv.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        $query->join(DB::raw("({$pagVendaSql}) as pag_venda"), 'iv.sell_number', '=', 'pag_venda.sell_number')
              ->join(DB::raw("({$brutoVendaSql}) as bruto_venda"), 'iv.sell_number', '=', 'bruto_venda.sell_number');

        return $query->selectRaw("
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
     */
    public function getInconsistenciasCatalogo(int $feiraId, ?array $sellNumbers = null): \Illuminate\Support\Collection
    {
        $pagVendaSql = $this->getPagVendaSql($feiraId, $sellNumbers);
        $brutoVendaSql = $this->getBrutoVendaSql($feiraId, $sellNumbers);

        $query = DB::table('itens_venda as iv')
            ->where('iv.id_feira', $feiraId)
            ->leftJoin('livros as l', function ($join) {
                $join->on('iv.name', '=', 'l.produto')
                     ->on('iv.id_feira', '=', 'l.id_feira');
            });

        if ($sellNumbers !== null) {
            $query->whereIn('iv.sell_number', $sellNumbers);
        } else {
            $query->whereIn('iv.sell_number', $this->getVendasValidasQuery($feiraId));
        }

        $query->join(DB::raw("({$pagVendaSql}) as pag_venda"), 'iv.sell_number', '=', 'pag_venda.sell_number')
              ->join(DB::raw("({$brutoVendaSql}) as bruto_venda"), 'iv.sell_number', '=', 'bruto_venda.sell_number');

        return $query->whereNull('l.id') // Só os que NÃO têm match no catálogo
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
