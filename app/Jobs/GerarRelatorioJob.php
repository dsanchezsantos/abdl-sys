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
use Illuminate\Support\Facades\Redis;
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
        ini_set('memory_limit', '512M');

        try {
            $this->relatorio->update(['status' => RelatorioStatus::PROCESSANDO]);

            $redis = Redis::connection();
            $key = "relatorio:{$this->relatorio->id}:items";
            $redis->del($key);

            // Garantir diretório temp
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0775, true);
            }

            if ($this->relatorio->tipo === 'cartao') {
                // Estratégia de Cartões: Paginar por tag_code
                $tagCodes = $dataService->getCartoesValidos($this->relatorio->id_feira);
                
                if ($tagCodes->isEmpty()) {
                    $this->relatorio->update([
                        'status' => RelatorioStatus::FALHA,
                        'mensagem_erro' => 'Nenhum cartão válido encontrado para os critérios do Filtro de Ouro.'
                    ]);
                    return;
                }

                $chunks = $tagCodes->chunk(100);
                $totalChunks = $chunks->count();

                // Gravar os tag_codes no Redis
                foreach ($tagCodes->chunk(1000) as $chunk) {
                    $redis->rpush($key, ...$chunk->values()->toArray());
                }
                $redis->expire($key, 3600);

                // Primeiro chunk
                $firstChunk = $chunks->first();
                GerarChunkRelatorioJob::dispatch(
                    $this->relatorio,
                    $firstChunk->toArray(),
                    0,                  // chunkIndex
                    true,               // isFirstPage
                    $totalChunks === 1, // isLastPage
                    $totalChunks
                );

                Log::info("Pipeline de relatório de cartões #{$this->relatorio->id} iniciado com {$totalChunks} chunks.");

            } elseif ($this->relatorio->tipo === 'vendas') {
                // Estratégia de Vendas: Paginar por sell_number
                $sellNumbers = $dataService->getSellNumbersValidos($this->relatorio->id_feira);

                if ($sellNumbers->isEmpty()) {
                    $this->relatorio->update([
                        'status' => RelatorioStatus::FALHA,
                        'mensagem_erro' => 'Nenhuma venda válida encontrada para os critérios do Filtro de Ouro.'
                    ]);
                    return;
                }

                $chunks = $sellNumbers->chunk(100);
                $totalChunks = $chunks->count();

                // Gravar os sell_numbers no Redis
                foreach ($sellNumbers->chunk(1000) as $chunk) {
                    $redis->rpush($key, ...$chunk->values()->toArray());
                }
                $redis->expire($key, 3600);

                // Primeiro chunk
                $firstChunk = $chunks->first();
                GerarChunkRelatorioJob::dispatch(
                    $this->relatorio,
                    $firstChunk->toArray(),
                    0,                  // chunkIndex
                    true,               // isFirstPage
                    $totalChunks === 1, // isLastPage
                    $totalChunks
                );

                Log::info("Pipeline de relatório de vendas #{$this->relatorio->id} iniciado com {$totalChunks} chunks.");

            } elseif ($this->relatorio->tipo === 'editoras') {
                // Estratégia de Editoras: Sem fatiamento (1 único chunk geral consolidado)
                GerarChunkRelatorioJob::dispatch(
                    $this->relatorio,
                    [],                 // sellNumbers/tagCodes não necessários
                    0,                  // chunkIndex
                    true,               // isFirstPage
                    true,               // isLastPage (é o único)
                    1                   // totalChunks
                );

                Log::info("Pipeline de relatório de editoras #{$this->relatorio->id} iniciado como único chunk consolidado.");
            }

        } catch (\Throwable $e) {
            Log::error("Falha ao orquestrar relatório #{$this->relatorio->id}: " . $e->getMessage());
            
            Redis::connection()->del("relatorio:{$this->relatorio->id}:items");

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
        
        Redis::connection()->del("relatorio:{$this->relatorio->id}:items");

        $this->relatorio->update([
            'status' => RelatorioStatus::FALHA,
            'mensagem_erro' => "Erro na orquestração inicial: " . $exception->getMessage()
        ]);

        if ($this->relatorio->usuario) {
            $this->relatorio->usuario->notify(new \App\Notifications\RelatorioFalhaNotification($this->relatorio));
        }
    }
}
