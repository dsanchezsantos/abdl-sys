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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GerarRelatorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * O timeout do job.
     */
    public $timeout = 300;

    /**
     * O número de tentativas permitidas para o job.
     */
    public $tries = 1;

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

            // Chunking Strategy: 100 vendas por PDF parcial
            $chunks = $sellNumbers->chunk(100);
            $totalChunks = $chunks->count();

            // Salvar no Cache do Redis com expiração de 1 hora
            Cache::put(
                "relatorio:{$this->relatorio->id}:sell_numbers",
                $sellNumbers->toArray(),
                3600
            );

            // Garantir diretório temp
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0775, true);
            }

            // Despachar o primeiro chunk de forma extremamente leve
            $firstChunk = $chunks->first();
            GerarChunkRelatorioJob::dispatch(
                $this->relatorio,
                $firstChunk->toArray(),
                0,                  // chunkIndex
                true,               // isFirstPage
                $totalChunks === 1, // isLastPage
                $totalChunks
            );

            Log::info("Pipeline de relatório #{$this->relatorio->id} iniciado com {$totalChunks} chunks (despacho sequencial otimizado com cache).");

        } catch (\Throwable $e) {
            Log::error("Falha ao orquestrar relatório #{$this->relatorio->id}: " . $e->getMessage());
            
            // Limpar cache em caso de erro na orquestração inicial
            Cache::forget("relatorio:{$this->relatorio->id}:sell_numbers");

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
        
        Cache::forget("relatorio:{$this->relatorio->id}:sell_numbers");

        $this->relatorio->update([
            'status' => RelatorioStatus::FALHA,
            'mensagem_erro' => "Erro na orquestração inicial: " . $exception->getMessage()
        ]);

        if ($this->relatorio->usuario) {
            $this->relatorio->usuario->notify(new \App\Notifications\RelatorioFalhaNotification($this->relatorio));
        }
    }
}
