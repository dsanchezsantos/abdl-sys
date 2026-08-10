<?php

namespace App\Jobs;

use App\Models\Feira;
use App\Services\NowigoService;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SincronizarFeiraMaestroJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $feiraId;
    protected ?int $usuarioId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $feiraId, ?int $usuarioId = null)
    {
        $this->feiraId = $feiraId;
        $this->usuarioId = $usuarioId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $feira = Feira::findOrFail($this->feiraId);

        try {
            // Limpar erros de integração anteriores para iniciar com o estado limpo
            DB::table('erros_integracao')->where('id_feira', $this->feiraId)->delete();

            $nowigo = new NowigoService($feira);

            Log::info("Iniciando Maestro para a Feira #{$feira->id}");

            // 1. Smart Probe (Página 1)
            $startTime = microtime(true);
            $page1 = $nowigo->buscarPagina(1, 100);
            $duration = (microtime(true) - $startTime) * 1000;

            $pagination = $page1['pagination'] ?? [];
            $totalItems = (int) ($pagination['totalItems'] ?? 0);
            $totalPagesApi = (int) ($pagination['totalPages'] ?? 1);

            Log::info("Feira #{$feira->id}: Total de itens: {$totalItems}, Páginas na API: {$totalPagesApi}. Tempo resposta P1: {$duration}ms");

            // 2. Tamanho de página fixado em 100 para manter a integridade com o Probe e a API
            $perPage = 100;
            $totalPages = $totalPagesApi;

            // APLICAÇÃO DE LIMITE DE TESTE (via .env)
            $limit = config('services.nowigo.limit_pages');
            if ($limit && $totalPages > $limit) {
                Log::warning("MODO DE TESTE ATIVO: Limitando sincronização de {$totalPages} para {$limit} páginas.");
                $totalPages = $limit;
            }

            Log::info("Feira #{$feira->id}: Definido perPage={$perPage}. Total de páginas: {$totalPages}");

            $usuarioId = $this->usuarioId;

            // 3. Criar o Lote (Batch) Inicial
            $batch = Bus::batch([])
                ->onQueue('sync-nowigo')
                ->name("Sincronização Feira #{$feira->id}: {$feira->nome}")
                ->allowFailures()
                ->then(function (Batch $batch) use ($feira, $usuarioId) {
                    Log::info("Lote de sincronização finalizado com SUCESSO para a Feira #{$feira->id}");
                    CalcularEstatisticasFeiraJob::dispatch($feira->id);

                    if ($usuarioId) {
                        $usuario = \App\Models\User::find($usuarioId);
                        if ($usuario) {
                            $usuario->notify(new \App\Notifications\SincronizacaoFeiraNotification($feira, 'sucesso'));
                        }
                    }
                })
                ->catch(function (Batch $batch, Throwable $e) use ($feira) {
                    Log::error("ERRO crítico no lote de sincronização para a Feira #{$feira->id}: " . $e->getMessage());
                })
                ->finally(function (Batch $batch) use ($feira, $usuarioId) {
                    $statusIntegridade = $batch->hasFailures() ? 'FALHA_PARCIAL' : 'INTEGRO';
                    
                    $feira->update([
                        'is_sincronizando' => false,
                        'ultima_sincronizacao_em' => now(),
                        'status_integridade' => $statusIntegridade,
                    ]);
                    
                    Log::info("Lote de sincronização CONCLUÍDO ({$statusIntegridade}) para a Feira #{$feira->id}");

                    if ($statusIntegridade === 'FALHA_PARCIAL' && $usuarioId) {
                        $usuario = \App\Models\User::find($usuarioId);
                        if ($usuario) {
                            $usuario->notify(new \App\Notifications\SincronizacaoFeiraNotification($feira, 'falha_parcial'));
                        }
                    }
                })
                ->dispatch();

            // Salvar ID do Lote para Repescagem
            $feira->update(['ultimo_batch_id' => $batch->id]);

            // 4. Enfileiramento em Chunks (Memória Controlada)
            $jobs = [];
            $chunkSize = 50;
            $memoryThreshold = 80 * 1024 * 1024; // 80MB

            for ($i = 1; $i <= $totalPages; $i++) {
                $jobs[] = new ProcessarPaginaVendaJob($this->feiraId, $i, $perPage);

                // Se atingir o tamanho do chunk ou o limite de memória, adiciona ao batch e limpa
                if (count($jobs) >= $chunkSize || memory_get_usage(true) > $memoryThreshold) {
                    $batch->add($jobs);
                    $jobs = [];
                    
                    // Sugere ao GC para limpar se necessário
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }
            }

            // Adiciona o restante se houver
            if (!empty($jobs)) {
                $batch->add($jobs);
            }
        } catch (Throwable $e) {
            Log::error("FALHA NO MAESTRO (Feira #{$feira->id}): " . $e->getMessage());
            
            // Registrar falha na auditoria para visibilidade na UI
            DB::table('erros_integracao')->insert([
                'id_feira' => $this->feiraId,
                'pagina' => 0, // 0 indica erro no Maestro, não em uma página específica
                'payload_recebido' => json_encode(['context' => 'Falha na orquestração do Maestro']),
                'mensagem_erro' => "ERRO NO MAESTRO: " . $e->getMessage(),
                'status' => 'PENDENTE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $feira->update(['is_sincronizando' => false, 'status_integridade' => 'FALHA_PARCIAL']);

            if ($this->usuarioId) {
                $usuario = \App\Models\User::find($this->usuarioId);
                if ($usuario) {
                    $usuario->notify(new \App\Notifications\SincronizacaoFeiraNotification($feira, 'erro_critico', $e->getMessage()));
                }
            }

            throw $e;
        }
    }
}
