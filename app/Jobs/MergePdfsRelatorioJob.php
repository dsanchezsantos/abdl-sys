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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MergePdfsRelatorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    protected $relatorio;
    protected $chunkPaths;

    /**
     * Create a new job instance.
     */
    public function __construct(Relatorio $relatorio, array $chunkPaths)
    {
        $this->relatorio = $relatorio;
        $this->chunkPaths = $chunkPaths;
    }

    /**
     * Execute the job.
     */
    public function handle(GotenbergService $gotenberg): void
    {
        $startTime = microtime(true);

        try {
            Log::info("Iniciando Merge de " . count($this->chunkPaths) . " chunks para relatório #{$this->relatorio->id}");

            // 1. Merge via Gotenberg
            $finalPdfContent = $gotenberg->mergePdfs($this->chunkPaths);

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
            throw $e;
        } finally {
            // LIMPEZA FINAL: Apagar os PDFs parciais (chunks)
            foreach ($this->chunkPaths as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }
}
