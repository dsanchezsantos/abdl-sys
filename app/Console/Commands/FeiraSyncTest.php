<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FeiraSyncTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feira:sync-test {feira_id} {--limit=5}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza uma feira com limite de páginas para teste.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $feiraId = $this->argument('feira_id');
        $limit = (int) $this->option('limit');

        $feira = \App\Models\Feira::findOrFail($feiraId);
        $nowigo = new \App\Services\NowigoService($feira);

        $this->info("Iniciando Smoke Test para a Feira #{$feira->id} ({$feira->nome})");

        // Smart Probe
        $page1 = $nowigo->buscarPagina(1, 100);
        $pagination = $page1['pagination'] ?? [];
        $totalItems = (int) ($pagination['totalItems'] ?? 0);
        
        $perPage = 100;
        $totalPagesApi = ceil($totalItems / $perPage);
        $totalPages = min($totalPagesApi, $limit);

        $this->info("Total de itens: {$totalItems}. Páginas na API: {$totalPagesApi}. Limitando a: {$totalPages} páginas.");

        $jobs = [];
        for ($i = 1; $i <= $totalPages; $i++) {
            $jobs[] = new \App\Jobs\ProcessarPaginaVendaJob($feira->id, $i, $perPage);
        }

        if (empty($jobs)) {
            $this->warn("Nenhum job para processar.");
            return;
        }

        $feira->update(['is_sincronizando' => true]);

        \Illuminate\Support\Facades\Bus::batch($jobs)
            ->name("TESTE Sincronização Feira #{$feira->id} (Limit: {$limit})")
            ->then(function (\Illuminate\Bus\Batch $batch) use ($feira) {
                \Illuminate\Support\Facades\Log::info("TESTE finalizado com SUCESSO para a Feira #{$feira->id}");
                \App\Jobs\CalcularEstatisticasFeiraJob::dispatch($feira->id);
            })
            ->finally(function (\Illuminate\Bus\Batch $batch) use ($feira) {
                $feira->update(['is_sincronizando' => false]);
                \Illuminate\Support\Facades\Log::info("TESTE CONCLUÍDO para a Feira #{$feira->id}");
            })
            ->dispatch();

        $this->info("Lote de teste despachado para a fila. Monitore os logs ou o dashboard.");
    }
}
