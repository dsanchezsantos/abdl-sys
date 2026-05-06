@extends('relatorios.layouts.master')

@section('title', 'Desempenho Comercial: Editoras e Representantes')

@section('content')
    @if(isset($isFirstPage) && $isFirstPage)
        <div class="text-center" style="margin-bottom: 5mm;">
            <h2 style="font-size: 14pt; font-weight: bold;">Resumo Executivo de Vendas Oficiais (Apenas Valores dos Cartões)</h2>
        </div>

        <div class="kpi-container">
            <div class="kpi-card">
                <span class="label">Quantidade Total de Livros Vendidos</span>
                <span class="value">{{ number_format($kpis['total_livros'], 0, ',', '.') }} unidades</span>
            </div>
            <div class="kpi-card">
                <span class="label">Valor Oficial Repassado (Cartões)</span>
                <span class="value">R$ {{ number_format($kpis['total_arrecadado'], 2, ',', '.') }}</span>
            </div>
        </div>

        <div class="bg-slate-200" style="padding: 8pt; border: 0.5pt solid #cbd5e1; margin-bottom: 5mm;">
            <span class="font-bold" style="font-size: 10pt;">Top Editoras (Campeãs de Faturamento por Representante):</span>
            <ul style="margin: 5pt 0 0 15pt; font-size: 9pt;">
                @foreach($campeas as $campea)
                    <li><strong>{{ $campea['representante'] }}:</strong> {{ $campea['categoria'] }} (R$ {{ number_format($campea['faturamento'], 2, ',', '.') }})</li>
                @endforeach
            </ul>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            @if(isset($chartMarketShareFilename))
                <div style="width: 40%;" class="chart-container">
                    <h3 style="font-size: 9pt; font-weight: bold; margin-bottom: 2pt;">Market Share (Representantes)</h3>
                    <img src="{{ $chartMarketShareFilename }}" alt="Market Share">
                </div>
            @endif
            @if(isset($chartEvolucaoFilename))
                <div style="width: 58%;" class="chart-container">
                    <h3 style="font-size: 9pt; font-weight: bold; margin-bottom: 2pt;">Evolução Financeira Diária</h3>
                    <img src="{{ $chartEvolucaoFilename }}" alt="Evolução Financeira Diária">
                </div>
            @endif
        </div>

        <div class="page-break"></div>
    @endif

    {{-- Seções por Representante --}}
    @foreach($editorasResumo->groupBy('representante') as $representante => $editoras)
        <div style="background-color: #1e293b; color: #ffffff; padding: 8pt; font-size: 12pt; font-weight: bold; margin-bottom: 3mm;">
            REPRESENTANTE: {{ $representante }} 
            | SUBTOTAL FATURAMENTO: R$ {{ number_format($editoras->sum('faturamento_cartao'), 2, ',', '.') }}
            | LIVROS: {{ number_format($editoras->sum('qtd_livros'), 0, ',', '.') }} un
        </div>

        @foreach($editoras as $ed)
            <div class="bg-slate-200" style="padding: 5pt; border: 0.5pt solid #cbd5e1; font-weight: bold; font-size: 8pt;">
                EDITORA: {{ $ed->categoria }} 
                | VALOR DE CAPA (BRUTO): R$ {{ number_format($ed->valor_bruto_capa, 2, ',', '.') }}
                | VALOR PAGO NO CARTÃO: R$ {{ number_format($ed->faturamento_cartao, 2, ',', '.') }}
            </div>

            @php
                $livros = $getLivrosPorEditora($representante, $ed->categoria);
            @endphp

            @if($livros->isNotEmpty())
                <table>
                    <thead>
                        <tr>
                            <th style="width: 45%;">Nome do Livro</th>
                            <th style="width: 8%;">Qtd</th>
                            <th style="width: 12%;">Valor Médio Capa</th>
                            <th style="width: 15%;">Total Bruto (Etiqueta)</th>
                            <th style="width: 20%;">Computado (Pago Cartão)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($livros as $lr)
                            <tr>
                                <td>{{ $lr->nome_livro }}</td>
                                <td class="text-center">{{ number_format($lr->qtd, 0, ',', '.') }}</td>
                                <td class="text-right">R$ {{ number_format($lr->preco_unit_bruto, 2, ',', '.') }}</td>
                                <td class="text-right">R$ {{ number_format($lr->bruto_total, 2, ',', '.') }}</td>
                                <td class="text-right font-bold">R$ {{ number_format($lr->faturamento_cartao, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div style="padding: 5pt; border: 0.5pt solid #e2e8f0; font-style: italic; font-size: 7pt; margin-bottom: 3mm;">
                    Nenhum detalhe de livro encontrado.
                </div>
            @endif
        @endforeach

        <div style="height: 5mm;"></div>
    @endforeach

    {{-- Anexo de Inconsistências (Se houver e for a última página/chunk) --}}
    @if(isset($isLastPage) && $isLastPage && isset($inconsistencias) && $inconsistencias->isNotEmpty())
        <div class="page-break"></div>
        <div style="background-color: #dc2626; color: #ffffff; padding: 8pt; font-size: 12pt; font-weight: bold; margin-bottom: 3mm;">
            ANEXO DE AUDITORIA: LIVROS VENDIDOS MAS NÃO CATALOGADOS
        </div>
        <p style="font-size: 9pt; margin-bottom: 5mm;">
            Os títulos abaixo constam nos logs de transações dos caixas (itens_venda), porém não possuem correspondência na tabela oficial de catálogo do evento (livros). 
            Para garantir que a matemática do faturamento permaneça 100% exata com o valor dos cartões, o sistema os consolidou automaticamente na seção do Representante 'NÃO INFORMADO', sob a Categoria 'AVULSO'.
        </p>

        <table>
            <thead>
                <tr>
                    <th style="width: 60%;">Nome Original Capturado no Caixa</th>
                    <th style="width: 15%;">Qtd Vendida</th>
                    <th style="width: 25%;">Valor Computado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inconsistencias as $inc)
                    <tr>
                        <td>{{ $inc->nome_livro }}</td>
                        <td class="text-center">{{ number_format($inc->qtd, 0, ',', '.') }}</td>
                        <td class="text-right">R$ {{ number_format($inc->faturamento_cartao, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
