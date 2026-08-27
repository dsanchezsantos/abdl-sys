<?php

namespace App\Jobs;

use App\Models\Relatorio;
use App\Enums\RelatorioStatus;
use App\Services\GotenbergService;
use App\Notifications\RelatorioConcluidoNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MergePdfsRelatorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 1;

    protected $relatorio;
    protected $totalChunks;

    /**
     * Create a new job instance.
     */
    public function __construct(Relatorio $relatorio, int $totalChunks)
    {
        $this->relatorio = $relatorio;
        $this->totalChunks = $totalChunks;
    }

    /**
     * Execute the job.
     */
    public function handle(GotenbergService $gotenberg): void
    {
        $startTime = microtime(true);
        $chunkPaths = [];

        for ($i = 0; $i < $this->totalChunks; $i++) {
            $chunkPaths[] = storage_path("app/temp/relatorio_{$this->relatorio->id}_chunk_{$i}.pdf");
        }

        try {
            Log::info("Iniciando Merge de {$this->totalChunks} chunks determinísticos para relatório #{$this->relatorio->id}");

            // 1. Merge via Gotenberg
            $finalPdfContent = $gotenberg->mergePdfs($chunkPaths);

            // 2. Salvar no Storage Público (para download)
            $filename = "relatorio_{$this->relatorio->id}_" . now()->format('Ymd_His') . ".pdf";
            $storagePath = "relatorios/{$filename}";
            
            Storage::disk('public')->put($storagePath, $finalPdfContent);

            // 3. Atualizar Model
            $duration = (int) ceil(microtime(true) - $startTime);
            $this->relatorio->update([
                'status' => RelatorioStatus::CONCLUIDO,
                'caminho_arquivo' => $storagePath,
                'tamanho_bytes' => strlen($finalPdfContent),
                'tempo_execucao_segundos' => max(0, $duration),
            ]);

            // 4. Notificar Usuário
            if ($this->relatorio->usuario) {
                $this->relatorio->usuario->notify(new RelatorioConcluidoNotification($this->relatorio));
            }

            Log::info("Relatório #{$this->relatorio->id} finalizado com sucesso. Arquivo: {$storagePath}");

        } catch (\Throwable $e) {
            Log::error("Erro no Merge do relatório #{$this->relatorio->id}: " . $e->getMessage());
            $this->relatorio->update([
                'status' => RelatorioStatus::FALHA,
                'mensagem_erro' => "Erro no merge final: " . $e->getMessage()
            ]);

            if ($this->relatorio->usuario) {
                $this->relatorio->usuario->notify(new \App\Notifications\RelatorioFalhaNotification($this->relatorio));
            }
            throw $e;
        } finally {
            // LIMPEZA EM CASO DE SUCESSO OU FALHA: Apagar os PDFs parciais (chunks) e cache
            $this->limparChunks($chunkPaths);
            Cache::forget("relatorio:{$this->relatorio->id}:sell_numbers");
        }
    }

    /**
     * Limpa os arquivos PDFs parciais (chunks) do disco.
     */
    protected function limparChunks(array $chunkPaths): void
    {
        foreach ($chunkPaths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job MergePdfsRelatorioJob falhou para relatório #{$this->relatorio->id}: " . $exception->getMessage());
        
        $this->relatorio->update([
            'status' => RelatorioStatus::FALHA,
            'mensagem_erro' => "Erro no merge final: " . $exception->getMessage()
        ]);

        if ($this->relatorio->usuario) {
            $this->relatorio->usuario->notify(new \App\Notifications\RelatorioFalhaNotification($this->relatorio));
        }

        // LIMPEZA EM CASO DE FALHA DEFINITIVA
        $chunkPaths = [];
        for ($i = 0; $i < $this->totalChunks; $i++) {
            $chunkPaths[] = storage_path("app/temp/relatorio_{$this->relatorio->id}_chunk_{$i}.pdf");
        }
        $this->limparChunks($chunkPaths);
        Cache::forget("relatorio:{$this->relatorio->id}:sell_numbers");
    }
}
