@extends('relatorios.layouts.master')

@section('title', 'Detalhamento de Vendas')

@section('content')
    @if(isset($isFirstPage) && $isFirstPage)
        <div class="text-center" style="margin-bottom: 5mm;">
            <h2 style="font-size: 14pt; font-weight: bold;">Resumo Executivo - Transações Agrupadas por Venda</h2>
        </div>

        <div class="kpi-container" style="margin-bottom: 3mm;">
            <div class="kpi-card">
                <span class="label">Quantidade Total de Vendas</span>
                <span class="value">{{ number_format($kpis['totalVendas'], 0, ',', '.') }}</span>
            </div>
            <div class="kpi-card">
                <span class="label">Valor Total Computado</span>
                <span class="value">R$ {{ number_format($kpis['totalArrecadado'], 2, ',', '.') }}</span>
            </div>
        </div>

        <div class="kpi-card" style="width: 100%; margin-bottom: 3mm;">
            <span class="label">Ticket Médio por Venda</span>
            <span class="value">R$ {{ number_format($kpis['ticketMedio'], 2, ',', '.') }}</span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            @if(isset($chartVendasDiariasFilename))
                <div style="width: 48%;" class="chart-container">
                    <h3 style="font-size: 9pt; font-weight: bold; margin-bottom: 2pt;">Volume Financeiro (Vendas/Dia)</h3>
                    <img src="{{ $chartVendasDiariasFilename }}" alt="Volume de Vendas por Dia">
                </div>
            @endif
            @if(isset($chartRepresentantesFilename))
                <div style="width: 48%;" class="chart-container">
                    <h3 style="font-size: 9pt; font-weight: bold; margin-bottom: 2pt;">Faturamento por Representante</h3>
                    <img src="{{ $chartRepresentantesFilename }}" alt="Vendas por Representante">
                </div>
            @endif
        </div>

        <div class="page-break"></div>
    @endif

    <div class="text-center" style="margin-bottom: 5mm;">
        <h2 style="font-size: 12pt; font-weight: bold;">Detalhamento de Pagamentos por Venda</h2>
    </div>

    @foreach($vendas as $venda)
        <table style="margin-bottom: 0;">
            <tr class="bg-slate-200">
                <td class="font-bold" style="width: 25%;">VENDA: {{ $venda->sell_number }}</td>
                <td class="font-bold" style="width: 25%;">DATA: {{ \Carbon\Carbon::parse($venda->date_hour)->format('d/m/Y H:i:s') }}</td>
                <td class="font-bold" style="width: 15%;">CAIXA: {{ $venda->box }}</td>
                <td class="font-bold" style="width: 35%;">VALOR COMPUTADO: R$ {{ number_format($venda->valor_total, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td colspan="4" style="font-size: 7pt;">LIVRO(S): {{ $venda->livros }}</td>
            </tr>
        </table>

        @php
            $pagamentos = $getPagamentos($venda->sell_number);
        @endphp

        @if($pagamentos->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th style="width: 25%;">Forma de Pagamento (Caixa)</th>
                        <th style="width: 55%;">Identificação Detalhada</th>
                        <th style="width: 20%;">Valor Computado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pagamentos as $pg)
                        <tr>
                            <td>{{ strtoupper($pg->payment_way) }}</td>
                            <td>
                                @if(str_contains(strtoupper($pg->payment_way), 'DESCONTO'))
                                    Desconto Operacional no Caixa
                                @elseif($pg->tag_code)
                                    Tag: {{ $pg->tag_code }} [ALUNO: {{ $pg->payment_group ?? 'N/A' }}]
                                @else
                                    Pagamento Externo [{{ $pg->payment_group }}]
                                @endif
                            </td>
                            <td class="text-right">R$ {{ number_format($pg->valor, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="padding: 5pt; border: 0.5pt solid #e2e8f0; font-style: italic; font-size: 8pt; margin-bottom: 5mm;">
                Nenhum detalhe de pagamento oficial encontrado.
            </div>
        @endif

        <div style="height: 4mm;"></div>
    @endforeach
@endsection
