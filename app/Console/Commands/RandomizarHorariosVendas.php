<?php

namespace App\Console\Commands;

use App\Models\Feira;
use App\Models\VendaHeader;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RandomizarHorariosVendas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feira:randomizar-horarios {feira_id} {data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aleatoriza os horários das vendas de uma data específica (entre 08:00 e 17:00).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $feiraId = $this->argument('feira_id');
        $data = $this->argument('data');

        // Validação da feira
        $feira = Feira::findOrFail($feiraId);
        $this->info("Feira: #{$feira->id} — {$feira->nome}");

        // Validação do formato da data
        try {
            $dataCarbon = Carbon::createFromFormat('Y-m-d', $data);
        } catch (\Exception $e) {
            $this->error("Data inválida. Use o formato YYYY-MM-DD (ex: 2026-08-15).");
            return 1;
        }

        // Contar vendas da feira na data informada
        $count = VendaHeader::where('id_feira', $feiraId)
            ->whereDate('date_hour', $dataCarbon->format('Y-m-d'))
            ->count();

        if ($count === 0) {
            $this->warn("Nenhuma venda encontrada para a feira #{$feiraId} na data {$data}.");
            return 0;
        }

        $this->info("Encontradas {$count} vendas na data {$data}.");

        if (!$this->confirm("Deseja aleatorizar os horários destas vendas entre 08:00 e 17:00?")) {
            $this->info("Operação cancelada.");
            return 0;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        VendaHeader::where('id_feira', $feiraId)
            ->whereDate('date_hour', $dataCarbon->format('Y-m-d'))
            ->select('id')
            ->chunkById(1000, function ($vendas) use ($dataCarbon, $bar) {
                DB::transaction(function () use ($vendas, $dataCarbon, $bar) {
                    foreach ($vendas as $venda) {
                        $hora = rand(8, 16);
                        $minuto = rand(0, 59);
                        $segundo = rand(0, 59);

                        $novoDateHour = $dataCarbon->copy()->setTime($hora, $minuto, $segundo);

                        DB::table('venda_headers')
                            ->where('id', $venda->id)
                            ->update(['date_hour' => $novoDateHour]);

                        $bar->advance();
                    }
                });
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ {$count} vendas atualizadas com sucesso.");

        return 0;
    }
}
