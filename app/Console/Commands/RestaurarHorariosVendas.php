<?php

namespace App\Console\Commands;

use App\Models\Feira;
use App\Models\VendaHeader;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RestaurarHorariosVendas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feira:restaurar-horarios {feira_id} {data} {horario}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restaura o horário de todas as vendas de uma data específica para um horário uniforme.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $feiraId = $this->argument('feira_id');
        $data = $this->argument('data');
        $horario = $this->argument('horario');

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

        // Validação do formato do horário
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $horario)) {
            $this->error("Horário inválido. Use o formato HH:MM:SS (ex: 00:00:00 ou 12:00:00).");
            return 1;
        }

        $novoDateHour = Carbon::parse("{$data} {$horario}");

        // Contar vendas da feira na data informada
        $count = VendaHeader::where('id_feira', $feiraId)
            ->whereDate('date_hour', $dataCarbon->format('Y-m-d'))
            ->count();

        if ($count === 0) {
            $this->warn("Nenhuma venda encontrada para a feira #{$feiraId} na data {$data}.");
            return 0;
        }

        $this->info("Encontradas {$count} vendas na data {$data}.");
        $this->info("Todas serão restauradas para: {$data} {$horario}");

        if (!$this->confirm("Deseja prosseguir?")) {
            $this->info("Operação cancelada.");
            return 0;
        }

        $count = VendaHeader::where('id_feira', $feiraId)
            ->whereDate('date_hour', $dataCarbon->format('Y-m-d'))
            ->update(['date_hour' => $novoDateHour]);

        $this->info("✅ {$count} vendas restauradas para {$data} {$horario}.");

        return 0;
    }
}
