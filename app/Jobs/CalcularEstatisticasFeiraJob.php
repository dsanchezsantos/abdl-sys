<?php

namespace App\Jobs;

use App\Models\Feira;
use App\Models\FeiraEstatistica;
use App\Models\VendaHeader;
use App\Models\Pagamento;
use App\Models\ItemVenda;
use App\Models\Livro;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalcularEstatisticasFeiraJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $feiraId;

    public function __construct(int $feiraId)
    {
        $this->feiraId = $feiraId;
    }

    public function handle(): void
    {
        $feira = Feira::findOrFail($this->feiraId);
        
        Log::info("Calculando estatísticas de AUDITORIA PÚBLICA para a Feira #{$feira->id}");

        // A BARREIRA DE CONTENÇÃO: Pega apenas os IDs de vendas da Verba Pública
        // Isso não executa a query ainda, apenas monta a instrução SQL para ser injetada depois.
        $vendasValidasQuery = Pagamento::where('id_feira', $this->feiraId)
            ->validosParaRateio()
            ->select('sell_number');

        // 1. Faturamento Único (O valor oficial da prestação de contas)
        $faturamentoLiquido = Pagamento::where('id_feira', $this->feiraId)
            ->validosParaRateio()
            ->sum('value');

        // 2. Qtd Vendas Válidas & Ticket Médio
        $qtdVendasValidas = Pagamento::where('id_feira', $this->feiraId)
            ->validosParaRateio()
            ->count(DB::raw('DISTINCT sell_number'));

        $ticketMedio = $qtdVendasValidas > 0 ? $faturamentoLiquido / $qtdVendasValidas : 0;

        // 3. Total de Livros Vendidos (APENAS da Verba Pública)
        $totalLivros = ItemVenda::where('id_feira', $this->feiraId)
            ->whereIn('sell_number', $vendasValidasQuery)
            ->sum('amount');

        // 4. Inconsistências de Catálogo (Mantido global, pois o catálogo é único)
        $inconsistencias = Livro::where('id_feira', $this->feiraId)
            ->where(function($q) {
                $q->where('representante', 'NAO INFORMADO')
                  ->orWhere('editora', 'NAO INFORMADO');
            })
            ->count();

        // 5. Dados para Gráficos: Formas de Pagamento (Apenas vouchers/cartões públicos)
        $formasPagamento = Pagamento::where('id_feira', $this->feiraId)
            ->validosParaRateio() // Proteção aplicada aqui também
            ->select('payment_way', DB::raw('SUM(value) as total'))
            ->groupBy('payment_way')
            ->get()
            ->pluck('total', 'payment_way')
            ->toArray();

        // 6. Dados para Gráficos: Top 5 Representantes (Baseado apenas em compras públicas)
        $topRepresentantes = ItemVenda::where('itens_venda.id_feira', $this->feiraId)
            ->whereIn('itens_venda.sell_number', $vendasValidasQuery) // Proteção aplicada
            ->join('livros', function($join) {
                $join->on('itens_venda.id_feira', '=', 'livros.id_feira')
                     ->on('itens_venda.name', '=', 'livros.produto');
            })
            ->select('livros.representante', DB::raw('SUM(itens_venda.total_value) as total'))
            ->groupBy('livros.representante')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->pluck('total', 'representante')
            ->toArray();

        // 7. Dados para Gráficos: Picos de Venda (Apenas horários de transações públicas)
        $picosVenda = VendaHeader::where('id_feira', $this->feiraId)
            ->whereIn('sell_number', $vendasValidasQuery) // Proteção aplicada
            ->select(DB::raw('CAST(EXTRACT(HOUR FROM date_hour) AS INTEGER) as hora'), DB::raw('SUM(total_value) as total'))
            ->groupBy('hora')
            ->orderBy('hora')
            ->get()
            ->pluck('total', 'hora')
            ->toArray();

        // Atualizar Snapshot no Banco
        FeiraEstatistica::updateOrCreate(
            ['id_feira' => $this->feiraId],
            [
                // Faturamento bruto preenchido com o mesmo valor para não quebrar o banco, 
                // ou você pode remover esta coluna do banco posteriormente.
                'faturamento_bruto' => $faturamentoLiquido, 
                'faturamento_liquido_valido' => $faturamentoLiquido,
                'ticket_medio' => $ticketMedio,
                'total_livros_vendidos' => $totalLivros,
                'qtd_inconsistencias_catalogo' => $inconsistencias,
                'dados_graficos' => [
                    'formas_pagamento' => $formasPagamento,
                    'top_representantes' => $topRepresentantes,
                    'picos_venda' => $picosVenda,
                ],
                'atualizado_em' => now(),
            ]
        );

        Log::info("Estatísticas de Auditoria finalizadas para a Feira #{$feira->id}");
    }
}