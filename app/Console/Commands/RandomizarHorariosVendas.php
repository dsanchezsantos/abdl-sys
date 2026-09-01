<?php

namespace App\Console\Commands;

use App\Models\Feira;
use App\Models\VendaHeader;
use Carbon\Carbon;
use Illuminate\Console\Command;

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

        // Buscar vendas da feira na data informada
        $vendas = VendaHeader::where('id_feira', $feiraId)
            ->whereDate('date_hour', $dataCarbon->format('Y-m-d'))
            ->get();

        if ($vendas->isEmpty()) {
            $this->warn("Nenhuma venda encontrada para a feira #{$feiraId} na data {$data}.");
            return 0;
        }

        $this->info("Encontradas {$vendas->count()} vendas na data {$data}.");

        if (!$this->confirm("Deseja aleatorizar os horários destas vendas entre 08:00 e 17:00?")) {
            $this->info("Operação cancelada.");
            return 0;
        }

        $rows = [];

        foreach ($vendas as $venda) {
            $horarioAntigo = Carbon::parse($venda->date_hour)->format('H:i:s');

            // Gerar horário aleatório entre 08:00:00 e 16:59:59
            $hora = rand(8, 16);
            $minuto = rand(0, 59);
            $segundo = rand(0, 59);

            $novoDateHour = $dataCarbon->copy()->setTime($hora, $minuto, $segundo);

            $venda->update(['date_hour' => $novoDateHour]);

            $rows[] = [
                $venda->sell_number,
                $horarioAntigo,
                $novoDateHour->format('H:i:s'),
            ];
        }

        $this->table(['Sell Number', 'Horário Antigo', 'Horário Novo'], $rows);
        $this->info("✅ {$vendas->count()} vendas atualizadas com sucesso.");

        return 0;
    }
}
