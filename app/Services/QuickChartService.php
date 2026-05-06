<?php

namespace App\Services;

use QuickChart;
use Illuminate\Support\Facades\Log;

/**
 * QuickChartService
 *
 * Gera gráficos estáticos (PNG) usando o container self-hosted do QuickChart.
 * A biblioteca PHP aponta para a URL interna da rede Docker, garantindo que
 * nenhum dado financeiro trafegue pela internet.
 *
 * Estratégia de Injeção: Cada método retorna um array com:
 *   - 'filename': o nome de referência usado no <img src="chart_N.png"> do Blade
 *   - 'path':     o caminho absoluto do PNG temporário em disco
 *
 * Os arquivos temporários devem ser deletados pelo Job após o envio ao Gotenberg.
 */
class QuickChartService
{
    /** Paleta de cores replicada do script Python original (fase5_pdfs.py) */
    private array $cores = ['#10B981', '#3B82F6', '#94A3B8', '#F59E0B', '#8B5CF6'];

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.quickchart.url', 'http://quickchart:3400'), '/');
    }

    /**
     * Cria uma instância QuickChart configurada para o servidor self-hosted.
     */
    private function makeChart(int $width = 900, int $height = 400): QuickChart
    {
        $urlParts = parse_url($this->baseUrl);
        $chart = new QuickChart([
            'width' => $width, 
            'height' => $height,
            'protocol' => $urlParts['scheme'] ?? 'http',
            'host' => $urlParts['host'] ?? 'quickchart',
            'port' => $urlParts['port'] ?? 3400,
        ]);
        
        return $chart;
    }

    /**
     * Gera um arquivo PNG temporário e retorna o array de referência.
     */
    private function saveToTemp(QuickChart $chart, string $prefix): array
    {
        $tmpPath = tempnam(sys_get_temp_dir(), "qc_{$prefix}_") . '.png';
        $chart->toFile($tmpPath);

        return [
            'filename' => basename($tmpPath),
            'path'     => $tmpPath,
        ];
    }

    // =========================================================================
    // RELATÓRIO 1: TRANSAÇÕES POR CARTÃO
    // Replica: gastos_dia.plot(kind='bar', color='#334155')
    // =========================================================================

    /**
     * Gráfico de barras: Volume financeiro por dia.
     *
     * @param array $labels  Ex: ['11/11', '12/11', '13/11']
     * @param array $values  Ex: [125000.50, 98500.00, 210000.75]
     * @return array ['filename' => '...png', 'path' => '/tmp/...png']
     */
    public function gerarBarChartDiario(array $labels, array $values, string $titulo = 'Volume Financeiro Gasto por Dia'): array
    {
        $chart = $this->makeChart(900, 400);
        $chart->setConfig(json_encode([
            'type' => 'bar',
            'data' => [
                'labels'   => $labels,
                'datasets' => [[
                    'label'           => $titulo,
                    'data'            => $values,
                    'backgroundColor' => '#334155',
                    'borderColor'     => '#1e293b',
                    'borderWidth'     => 1,
                ]],
            ],
            'options' => [
                'plugins' => [
                    'title' => ['display' => true, 'text' => $titulo, 'font' => ['size' => 14]],
                    'legend' => ['display' => false],
                    'datalabels' => ['display' => false],
                ],
                'scales' => [
                    'y' => [
                        'grid' => ['color' => '#e2e8f0'],
                        'ticks' => ['callback' => "function(v){ return 'R$ ' + v.toLocaleString('pt-BR', {minimumFractionDigits:2}); }"],
                    ],
                ],
            ],
        ]));

        return $this->saveToTemp($chart, 'bar');
    }

    // =========================================================================
    // RELATÓRIO 2: VENDAS AGRUPADAS
    // Replica: gráfico duplo — barras de vendas + line chart de representantes
    // =========================================================================

    /**
     * Gráfico de barras: Volume financeiro das vendas por dia.
     * (Subplot esquerdo do Python: ax1.bar — cor verde #10B981)
     */
    public function gerarBarChartVendasDiarias(array $labels, array $values): array
    {
        $chart = $this->makeChart(700, 400);
        $chart->setConfig(json_encode([
            'type' => 'bar',
            'data' => [
                'labels'   => $labels,
                'datasets' => [[
                    'label'           => 'Faturamento Total',
                    'data'            => $values,
                    'backgroundColor' => '#10B981',
                    'borderColor'     => '#059669',
                    'borderWidth'     => 1,
                ]],
            ],
            'options' => [
                'plugins' => [
                    'title' => ['display' => true, 'text' => 'Volume Financeiro das Vendas por Dia', 'font' => ['size' => 13]],
                    'legend' => ['display' => true, 'position' => 'bottom'],
                ],
                'scales' => [
                    'y' => ['grid' => ['color' => '#e2e8f0']],
                ],
            ],
        ]));

        return $this->saveToTemp($chart, 'vendas_bar');
    }

    /**
     * Line chart: Vendas diárias por representante.
     * (Subplot direito do Python: ax2.plot — uma linha por representante)
     *
     * @param array $labels   Ex: ['11/11', '12/11', ...]
     * @param array $datasets Ex: [['label' => 'FLORESCER', 'data' => [...]]]
     */
    public function gerarLineChartRepresentantes(array $labels, array $datasets): array
    {
        $chartDatasets = array_map(function ($ds, $i) {
            return [
                'label'       => $ds['label'],
                'data'        => $ds['data'],
                'borderColor' => $this->cores[$i % count($this->cores)],
                'fill'        => false,
                'tension'     => 0.3,
                'pointRadius' => 4,
            ];
        }, $datasets, array_keys($datasets));

        $chart = $this->makeChart(700, 400);
        $chart->setConfig(json_encode([
            'type' => 'line',
            'data' => ['labels' => $labels, 'datasets' => $chartDatasets],
            'options' => [
                'plugins' => [
                    'title' => ['display' => true, 'text' => 'Vendas Diárias por Representante', 'font' => ['size' => 13]],
                    'legend' => ['display' => true, 'position' => 'top'],
                ],
                'scales' => ['y' => ['grid' => ['color' => '#e2e8f0']]],
            ],
        ]));

        return $this->saveToTemp($chart, 'rep_line');
    }

    // =========================================================================
    // RELATÓRIO 3: EDITORAS / REPRESENTANTES
    // Replica: Donut de Market Share + Line Chart de Evolução Diária
    // =========================================================================

    /**
     * Donut chart: Market Share por Representante.
     * Replica: ax1.pie(df_rep_share, wedgeprops=dict(width=0.4))
     *
     * @param array $labels  Ex: ['FLORESCER', 'PROLEZO', 'NÃO INFORMADO']
     * @param array $values  Ex: [3500000.00, 2100000.00, 352715.00]
     */
    public function gerarDonutMarketShare(array $labels, array $values): array
    {
        $chart = $this->makeChart(500, 420);
        $chart->setConfig(json_encode([
            'type' => 'doughnut',
            'data' => [
                'labels'   => $labels,
                'datasets' => [[
                    'data'            => $values,
                    'backgroundColor' => array_slice($this->cores, 0, count($values)),
                    'borderWidth'     => 2,
                    'borderColor'     => '#ffffff',
                ]],
            ],
            'options' => [
                'cutout' => '40%',
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text'    => 'Market Share (Fatia do Faturamento)',
                        'font'    => ['size' => 13, 'weight' => 'bold'],
                        'padding' => ['bottom' => 15],
                    ],
                    'legend' => ['position' => 'bottom'],
                ],
            ],
        ]));

        return $this->saveToTemp($chart, 'donut');
    }

    /**
     * Line chart: Evolução financeira diária por Representante.
     * Replica: ax2.plot() — múltiplas linhas com marcadores de ponto
     */
    public function gerarLineChartEvolucaoDiaria(array $labels, array $datasets): array
    {
        $chartDatasets = array_map(function ($ds, $i) {
            return [
                'label'           => $ds['label'],
                'data'            => $ds['data'],
                'borderColor'     => $this->cores[$i % count($this->cores)],
                'backgroundColor' => $this->cores[$i % count($this->cores)] . '20',
                'fill'            => false,
                'tension'         => 0.3,
                'pointRadius'     => 5,
                'pointHoverRadius' => 7,
            ];
        }, $datasets, array_keys($datasets));

        $chart = $this->makeChart(700, 420);
        $chart->setConfig(json_encode([
            'type' => 'line',
            'data' => ['labels' => $labels, 'datasets' => $chartDatasets],
            'options' => [
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text'    => 'Evolução Financeira Diária',
                        'font'    => ['size' => 13, 'weight' => 'bold'],
                        'padding' => ['bottom' => 15],
                    ],
                    'legend' => ['display' => true, 'position' => 'top'],
                ],
                'scales' => [
                    'y' => [
                        'grid' => ['color' => '#e2e8f0'],
                        'ticks' => ['callback' => "function(v){ return 'R$ ' + v.toLocaleString('pt-BR', {minimumFractionDigits:2}); }"],
                    ],
                ],
            ],
        ]));

        return $this->saveToTemp($chart, 'evo_line');
    }

    /**
     * Limpa os arquivos PNG temporários gerados nesta sessão.
     * Chamar após o Gotenberg ter recebido os arquivos.
     *
     * @param array $chartFiles Array de ['filename' => '...', 'path' => '...']
     */
    public function limparTemporarios(array $chartFiles): void
    {
        foreach ($chartFiles as $chart) {
            if (isset($chart['path']) && file_exists($chart['path'])) {
                try {
                    unlink($chart['path']);
                } catch (\Throwable $e) {
                    Log::warning("QuickChart: não foi possível remover temporário: {$chart['path']}");
                }
            }
        }
    }
}
