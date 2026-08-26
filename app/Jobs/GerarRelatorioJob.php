<?php

namespace App\Jobs;

use App\Models\Relatorio;
use App\Enums\RelatorioStatus;
use App\Services\RelatorioDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class GerarRelatorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * O timeout do job (1 hora para relatórios massivos).
     */
    public $timeout = 3600;

    protected $relatorio;

    /**
     * Create a new job instance.
     */
    public function __construct(Relatorio $relatorio)
    {
        $this->relatorio = $relatorio;
    }

    /**
     * Execute the job.
     */
    public function handle(RelatorioDataService $dataService): void
    {
        try {
            $this->relatorio->update(['status' => RelatorioStatus::PROCESSANDO]);

            $sellNumbers = $dataService->getSellNumbersValidos($this->relatorio->id_feira);
            
            if ($sellNumbers->isEmpty()) {
                $this->relatorio->update([
                    'status' => RelatorioStatus::FALHA,
                    'mensagem_erro' => 'Nenhuma venda válida encontrada para os critérios do Filtro de Ouro.'
                ]);
                return;
            }

            // Chunking Strategy: 500 vendas por PDF parcial
            $chunks = $sellNumbers->chunk(500);
            $jobs = [];
            $chunkPaths = [];

            foreach ($chunks as $index => $chunk) {
                $chunkFilename = "relatorio_{$this->relatorio->id}_chunk_{$index}.pdf";
                $chunkPath = storage_path("app/temp/{$chunkFilename}");
                
                // Garantir diretório temp
                if (!file_exists(dirname($chunkPath))) {
                    mkdir(dirname($chunkPath), 0775, true);
                }

                $chunkPaths[] = $chunkPath;

                $jobs[] = new GerarChunkRelatorioJob(
                    $this->relatorio,
                    $chunk->toArray(),
                    $index,
                    $index === 0, // isFirstPage
                    $index === ($chunks->count() - 1), // isLastPage
                    $chunkPath
                );
            }

            // Encadear: Chunks em paralelo (ou sequência se o worker for único) -> Merge final
            Bus::chain(array_merge($jobs, [
                new MergePdfsRelatorioJob($this->relatorio, $chunkPaths)
            ]))->dispatch();

            Log::info("Pipeline de relatório #{$this->relatorio->id} iniciado com " . count($jobs) . " chunks.");

        } catch (\Throwable $e) {
            Log::error("Falha ao orquestrar relatório #{$this->relatorio->id}: " . $e->getMessage());
            $this->relatorio->update([
                'status' => RelatorioStatus::FALHA,
                'mensagem_erro' => "Erro na orquestração: " . $e->getMessage()
            ]);

            if ($this->relatorio->usuario) {
                $this->relatorio->usuario->notify(new \App\Notifications\RelatorioFalhaNotification($this->relatorio));
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job GerarRelatorioJob falhou para relatório #{$this->relatorio->id}: " . $exception->getMessage());
        
        $this->relatorio->update([
            'status' => RelatorioStatus::FALHA,
            'mensagem_erro' => "Erro na orquestração inicial: " . $exception->getMessage()
        ]);

        if ($this->relatorio->usuario) {
            $this->relatorio->usuario->notify(new \App\Notifications\RelatorioFalhaNotification($this->relatorio));
        }
    }
}
