<?php

namespace App\Console\Commands;

use App\Models\ItemVenda;
use App\Models\Pagamento;
use App\Models\VendaHeader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoverVendaManual extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feira:remover-venda-manual {venda_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove uma venda e todos os registros associados (itens e pagamentos).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $vendaId = (int) $this->argument('venda_id');

        // 1. Buscar a venda
        $venda = VendaHeader::findOrFail($vendaId);

        $sellNumber = $venda->sell_number;
        $feiraId = $venda->id_feira;

        // 2. Mostrar detalhes para confirmação
        $this->info("=== DETALHES DA VENDA ===");
        $this->info("   ID: {$venda->id}");
        $this->info("   Sell Number: {$sellNumber}");
        $this->info("   Feira ID: {$feiraId}");
        $this->info("   Valor Total: R$ " . number_format($venda->total_value, 2, ',', '.'));
        $this->info("   Data/Hora: {$venda->date_hour}");
        $this->info("   Box: {$venda->box}");

        // Contar registros relacionados
        $qtdItens = ItemVenda::where('sell_number', $sellNumber)
            ->where('id_feira', $feiraId)
            ->count();

        $qtdPagamentos = Pagamento::where('sell_number', $sellNumber)
            ->where('id_feira', $feiraId)
            ->count();

        $this->newLine();
        $this->info("   Itens de venda associados: {$qtdItens}");
        $this->info("   Pagamentos associados: {$qtdPagamentos}");

        $this->newLine();
        $this->warn("⚠️  Esta operação é IRREVERSÍVEL e removerá TODOS os registros acima.");

        if (!$this->confirm("Deseja realmente remover esta venda e todos os registros associados?")) {
            $this->info("Operação cancelada.");
            return 0;
        }

        // 3. Remover tudo em transação
        DB::transaction(function () use ($sellNumber, $feiraId, $venda) {
            Pagamento::where('sell_number', $sellNumber)
                ->where('id_feira', $feiraId)
                ->delete();

            ItemVenda::where('sell_number', $sellNumber)
                ->where('id_feira', $feiraId)
                ->delete();

            $venda->delete();
        });

        $this->newLine();
        $this->info("✅ Venda #{$vendaId} removida com sucesso!");
        $this->info("   Pagamentos removidos: {$qtdPagamentos}");
        $this->info("   Itens removidos: {$qtdItens}");
        $this->info("   VendaHeader removida: 1");

        return 0;
    }
}
