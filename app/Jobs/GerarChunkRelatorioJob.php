<?php

namespace App\Jobs;

use App\Models\Relatorio;
use App\Services\RelatorioDataService;
use App\Services\QuickChartService;
use App\Services\GotenbergService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GerarChunkRelatorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    protected $relatorio;
    protected $sellNumbers;
    protected $chunkIndex;
    protected $isFirstPage;
    protected $isLastPage;
    protected $savePath;

    /**
     * Create a new job instance.
     */
    public function __construct(Relatorio $relatorio, array $sellNumbers, int $chunkIndex, bool $isFirstPage, bool $isLastPage, string $savePath)
    {
        $this->relatorio = $relatorio;
        $this->sellNumbers = $sellNumbers;
        $this->chunkIndex = $chunkIndex;
        $this->isFirstPage = $isFirstPage;
        $this->isLastPage = $isLastPage;
        $this->savePath = $savePath;
    }

    /**
     * Execute the job.
     */
    public function handle(
        RelatorioDataService $dataService,
        QuickChartService $chartService,
        GotenbergService $gotenberg
    ): void {
        $chartFiles = [];

        try {
            // 1. Coleta de Dados via Service
            $viewData = $this->prepareData($dataService, $chartService, $chartFiles);

            // 2. Renderização da View Blade -> HTML
            $viewName = $this->getViewName();
            $html = view($viewName, $viewData)->render();

            // Renderizar Header e Footer nativos para o Gotenberg
            $headerHtml = view('relatorios.layouts.header', [
                'titulo' => strtoupper($this->relatorio->tipo),
                'subtitulo' => $this->relatorio->feira->nome ?? 'Relatório de Auditoria'
            ])->render();
            
            $footerHtml = view('relatorios.layouts.footer')->render();

            // 3. Preparar anexos para o Gotenberg (Gráficos + Logo)
            $attachments = $chartFiles;
            $attachments[] = [
                'filename' => 'abdl_logo.png',
                'path'     => public_path('abdl_logo.png')
            ];

            // 4. Conversão HTML -> PDF via Gotenberg
            $pdfContent = $gotenberg->htmlToPdf($html, $attachments, 'Landscape', $headerHtml, $footerHtml);

            // 5. Persistência do Chunk
            file_put_contents($this->savePath, $pdfContent);

        } catch (\Throwable $e) {
            Log::error("Erro no chunk {$this->chunkIndex} do relatório #{$this->relatorio->id}: " . $e->getMessage());
            throw $e; 
        } finally {
            // LIMPEZA IMEDIATA DE DISCO: Apenas dos PNGs temporários (sem apagar a logo!)
            $chartService->limparTemporarios($chartFiles);
        }
    }

    /**
     * Prepara o array de dados para a View conforme o tipo de relatório.
     */
    private function prepareData(RelatorioDataService $dataService, QuickChartService $chartService, array &$chartFiles): array
    {
        $data = [
            'titulo'      => $this->relatorio->tipo,
            'subtitulo'   => $this->relatorio->feira->nome ?? 'Relatório de Auditoria',
            'isFirstPage' => $this->isFirstPage,
            'isLastPage'  => $this->isLastPage,
        ];

        switch ($this->relatorio->tipo) {
            case 'cartao':
                if ($this->isFirstPage) {
                    $data['kpis'] = $dataService->getKpisTransacoes($this->relatorio->id_feira, $this->sellNumbers);
                    $gastosDiarios = $dataService->getGastosDiarios($this->relatorio->id_feira, $this->sellNumbers);
                    
                    $chart = $chartService->gerarBarChartDiario($gastosDiarios['labels'], $gastosDiarios['values']);
                    $chartFiles[] = $chart;
                    $data['chartDiarioFilename'] = $chart['filename'];
                }
                
                $data['cartoes'] = $dataService->getCartoesDetalhamento($this->relatorio->id_feira, $this->sellNumbers);
                $data['getTransacoes'] = fn($tag) => $dataService->getTransacoesPorCartao($this->relatorio->id_feira, $tag, $this->sellNumbers);
                break;

            case 'vendas':
                if ($this->isFirstPage) {
                    $data['kpis'] = $dataService->getKpisVendas($this->relatorio->id_feira, $this->sellNumbers);
                    $vendasDiarias = $dataService->getVendasDiarias($this->relatorio->id_feira, $this->sellNumbers);
                    $repDiario = $dataService->getVendasDiariasPorRepresentante($this->relatorio->id_feira, $this->sellNumbers);
                    
                    $chart1 = $chartService->gerarBarChartVendasDiarias($vendasDiarias['labels'], $vendasDiarias['values']);
                    $chart2 = $chartService->gerarLineChartRepresentantes($repDiario['labels'], $repDiario['datasets']);
                    
                    $chartFiles[] = $chart1;
                    $chartFiles[] = $chart2;
                    
                    $data['chartVendasDiariasFilename'] = $chart1['filename'];
                    $data['chartRepresentantesFilename'] = $chart2['filename'];
                }
                
                $data['vendas'] = $dataService->getVendasDetalhamento($this->relatorio->id_feira, $this->sellNumbers);
                $data['getPagamentos'] = fn($sell) => $dataService->getPagamentosPorVenda($this->relatorio->id_feira, $sell);
                break;

            case 'editoras':
                if ($this->isFirstPage) {
                    $data['kpis'] = $dataService->getKpisEditoras($this->relatorio->id_feira, $this->sellNumbers);
                    $resumo = $dataService->getEditorasResumoComAlocacao($this->relatorio->id_feira, $this->sellNumbers);
                    $data['editorasResumo'] = $resumo;
                    $data['campeas'] = $dataService->getCampeasPorRepresentante($resumo);
                    
                    $marketShare = [
                        'labels' => $resumo->groupBy('representante')->map(fn($g) => $g->first()->representante)->values()->toArray(),
                        'values' => $resumo->groupBy('representante')->map(fn($g) => (float) $g->sum('faturamento_cartao'))->values()->toArray()
                    ];
                    $evolucao = $dataService->getEvolucaoDiariaPorRepresentante($this->relatorio->id_feira, $this->sellNumbers);
                    
                    $chart1 = $chartService->gerarDonutMarketShare($marketShare['labels'], $marketShare['values']);
                    $chart2 = $chartService->gerarLineChartEvolucaoDiaria($evolucao['labels'], $evolucao['datasets']);
                    
                    $chartFiles[] = $chart1;
                    $chartFiles[] = $chart2;
                    
                    $data['chartMarketShareFilename'] = $chart1['filename'];
                    $data['chartEvolucaoFilename'] = $chart2['filename'];
                } else {
                    // For chunks > 0, we still need the base summary to know the representatives
                    $data['editorasResumo'] = $dataService->getEditorasResumoComAlocacao($this->relatorio->id_feira, $this->sellNumbers);
                }

                if ($this->isLastPage) {
                    $data['inconsistencias'] = $dataService->getInconsistenciasCatalogo($this->relatorio->id_feira, $this->sellNumbers);
                }
                
                $data['getLivrosPorEditora'] = fn($rep, $cat) => $dataService->getLivrosDetalhePorEditora($this->relatorio->id_feira, $this->sellNumbers)
                    ->filter(fn($l) => $l->representante === $rep && $l->categoria === $cat);
                break;
        }

        return $data;
    }

    private function getViewName(): string
    {
        return match($this->relatorio->tipo) {
            'cartao'   => 'relatorios.transacoes',
            'vendas'   => 'relatorios.vendas',
            'editoras' => 'relatorios.editoras',
            default => throw new \Exception("Tipo de relatório desconhecido: " . $this->relatorio->tipo)
        };
    }
}
