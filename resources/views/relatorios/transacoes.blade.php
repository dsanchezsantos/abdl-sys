@extends('relatorios.layouts.master')

@section('title', 'Detalhamento de Transações por Cartão')

@section('content')
    {{-- Resumo Executivo (Só aparece na primeira página/primeiro chunk se for o caso) --}}
    @if(isset($isFirstPage) && $isFirstPage)
        <div class="text-center" style="margin-bottom: 5mm;">
            <h2 style="font-size: 14pt; font-weight: bold;">Resumo Executivo - Transações por Cartão</h2>
        </div>

        <div class="kpi-container" style="margin-bottom: 3mm;">
            <div class="kpi-card">
                <span class="label">Total Financeiro Movimentado</span>
                <span class="value">R$ {{ number_format($kpis['total_gasto'], 2, ',', '.') }}</span>
            </div>
            {{-- TEMPORARIAMENTE DESATIVADO — KPI oculta para apresentação
            <div class="kpi-card">
                <span class="label">Média de Gasto por Cartão</span>
                <span class="value">R$ {{ number_format($kpis['media_gasto'], 2, ',', '.') }}</span>
            </div>
            --}}

            <div class="kpi-card">
                <span class="label">Total de Cartões Utilizados</span>
                <span class="value">{{ number_format($kpis['total_cartoes'], 0, ',', '.') }}</span>
            </div>
            {{-- TEMPORARIAMENTE DESATIVADO — KPI oculta para apresentação
            <div class="kpi-card">
                <span class="label">Cartões que Gastaram Todo o Saldo</span>
                <span class="value">{{ number_format($kpis['cartoes_zerados'], 0, ',', '.') }}</span>
            </div>
            --}}
        </div>

        @if(isset($chartDiarioFilename))
            <div class="chart-container" style="margin: 2mm auto;">
                <h3 style="font-size: 9pt; font-weight: bold; margin-bottom: 2pt;">Evolução de Gasto Financeiro Diário</h3>
                <img src="{{ $chartDiarioFilename }}" alt="Gráfico de Gastos Diários">
            </div>
        @endif

        <div class="page-break"></div>
    @endif

    <div class="text-center" style="margin-bottom: 5mm;">
        <h2 style="font-size: 12pt; font-weight: bold;">Detalhamento de Transações por Cartão</h2>
    </div>

    @foreach($cartoes as $cartao)
        <table style="margin-bottom: 0;">
            <tr class="bg-slate-200">
                <td class="font-bold" style="width: 30%;">CARTÃO: {{ $cartao->codigo }}</td>
                <td class="font-bold" style="width: 50%;">ESCOLA: {{ $cartao->grupo }}</td>
                {{-- TEMPORARIAMENTE DESATIVADO — Valor Inicial oculto para relatórios específicos
                <td class="font-bold" style="width: 15%;">INICIAL: R$ {{ number_format($cartao->valor_inicial, 2, ',', '.') }}</td>
                --}}
                <td class="font-bold" style="width: 20%;">GASTO: R$ {{ number_format($cartao->valor_gasto, 2, ',', '.') }}</td>
                {{-- TEMPORARIAMENTE DESATIVADO — Saldo Final oculto para relatórios específicos
                <td class="font-bold" style="width: 20%;">SALDO FINAL: R$ {{ number_format($cartao->saldo_restante, 2, ',', '.') }}</td>
                --}}
            </tr>
        </table>

        @php
            // Nota: No Chunking, as transações já virão carregadas ou o LazyCollection será iterado.
            // Aqui assumimos que o Job orquestrou a passagem das transações vinculadas.
            $transacoes = $getTransacoes($cartao->codigo);
        @endphp

        @if($transacoes->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th style="width: 12%;">Data e Hora</th>
                        <th style="width: 8%;">Venda</th>
                        <th style="width: 8%;">Caixa</th>
                        <th style="width: 54%;">Livro(s) da Compra</th>
                        <th style="width: 6%;">Uso</th>
                        <th style="width: 12%;">Valor Debitado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transacoes as $tr)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($tr->data_hora)->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $tr->venda }}</td>
                            <td>{{ $tr->caixa }}</td>
                            <td>{{ $tr->livros }}</td>
                            <td>{{ $tr->uso_cartoes }}</td>
                            <td class="text-right">R$ {{ number_format($tr->valor, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="padding: 5pt; border: 0.5pt solid #e2e8f0; font-style: italic; font-size: 8pt; margin-bottom: 5mm;">
                Nenhuma transação isolada encontrada para este cartão.
            </div>
        @endif

        <div style="height: 4mm;"></div>
    @endforeach
@endsection
