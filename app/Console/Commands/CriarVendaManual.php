<?php

namespace App\Console\Commands;

use App\Enums\CartaoClassificacao;
use App\Models\Cartao;
use App\Models\Feira;
use App\Models\ItemVenda;
use App\Models\Livro;
use App\Models\Pagamento;
use App\Models\VendaHeader;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CriarVendaManual extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feira:criar-venda-manual {feira_id} {livro_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria uma venda manual com distribuição automática de pagamento entre os 2 cartões com menor uso.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $feiraId = (int) $this->argument('feira_id');
        $livroId = (int) $this->argument('livro_id');

        // 1. Validação
        $feira = Feira::findOrFail($feiraId);
        $this->info("Feira: #{$feira->id} — {$feira->nome}");

        $livro = Livro::where('id', $livroId)
            ->where('id_feira', $feiraId)
            ->firstOrFail();

        $valorTotal = (float) $livro->valor;
        $this->info("Livro: {$livro->produto} — R$ " . number_format($valorTotal, 2, ',', '.'));

        // 2. Selecionar os 2 cartões com menor uso (classificação ≠ TESTE)
        $cartoes = DB::table('cartoes as c')
            ->leftJoin('pagamentos as p', function ($join) use ($feiraId) {
                $join->on('c.tag_code', '=', 'p.tag_code')
                     ->on('c.id_feira', '=', 'p.id_feira');
            })
            ->where('c.id_feira', $feiraId)
            ->where('c.classificacao', '!=', CartaoClassificacao::TESTE->value)
            ->select('c.id', 'c.tag_code', 'c.grupo', 'c.classificacao')
            ->selectRaw('COUNT(p.id) AS qtd_pagamentos')
            ->groupBy('c.id', 'c.tag_code', 'c.grupo', 'c.classificacao')
            ->orderBy('qtd_pagamentos', 'asc')
            ->limit(2)
            ->get();

        if ($cartoes->count() < 2) {
            $this->error("Não há cartões válidos suficientes na feira (necessário pelo menos 2, encontrados: {$cartoes->count()}).");
            return 1;
        }

        $valorPorCartao = round($valorTotal / 2, 2);
        // O segundo cartão fica com o residual para garantir exatidão
        $valorCartao1 = $valorPorCartao;
        $valorCartao2 = round($valorTotal - $valorCartao1, 2);

        // Mostrar resumo antes de confirmar
        $this->newLine();
        $this->info("=== RESUMO DA VENDA MANUAL ===");
        $this->table(
            ['Cartão', 'Tag Code', 'Grupo', 'Pagamentos Existentes', 'Parcela'],
            $cartoes->map(function ($c, $index) use ($valorCartao1, $valorCartao2) {
                return [
                    $index + 1,
                    $c->tag_code,
                    $c->grupo,
                    $c->qtd_pagamentos,
                    'R$ ' . number_format($index === 0 ? $valorCartao1 : $valorCartao2, 2, ',', '.'),
                ];
            })->toArray()
        );

        if (!$this->confirm("Deseja criar esta venda manual?")) {
            $this->info("Operação cancelada.");
            return 0;
        }

        // 3. Executar tudo em transação
        $vendaId = DB::transaction(function () use ($feira, $livro, $cartoes, $valorTotal, $valorCartao1, $valorCartao2, $feiraId) {
            // Gerar sell_number único
            $sellNumber = 'MANUAL-' . $feiraId . '-' . time();

            // Determinar data/hora: horário aleatório entre 08:00–17:00 do último dia de vendas
            $ultimaVenda = VendaHeader::where('id_feira', $feiraId)
                ->orderBy('date_hour', 'desc')
                ->first();

            if ($ultimaVenda) {
                $dataBase = Carbon::parse($ultimaVenda->date_hour)->startOfDay();
            } else {
                $dataBase = Carbon::today();
            }

            $dateHour = $dataBase->copy()->setTime(rand(8, 16), rand(0, 59), rand(0, 59));

            // Criar VendaHeader
            $venda = VendaHeader::create([
                'id_feira'    => $feiraId,
                'sell_number' => $sellNumber,
                'sale_type'   => null,
                'total_value' => $valorTotal,
                'date_hour'   => $dateHour,
                'box'         => 'MANUAL',
                'processado'  => true,
                'raw_payload' => null,
            ]);

            // Criar ItemVenda
            ItemVenda::create([
                'id_feira'       => $feiraId,
                'sell_number'    => $sellNumber,
                'produto_id_api' => $livro->produto_id_api,
                'name'           => $livro->produto,
                'amount'         => 1,
                'unit_value'     => $livro->valor,
                'total_value'    => $valorTotal,
                'raw_payload'    => null,
            ]);

            // Criar Pagamentos (1 por cartão)
            $valores = [$valorCartao1, $valorCartao2];
            foreach ($cartoes as $index => $cartao) {
                Pagamento::create([
                    'id_feira'         => $feiraId,
                    'sell_number'      => $sellNumber,
                    'pagamento_id_api' => 0,
                    'tag_code'         => $cartao->tag_code,
                    'cpf'              => null,
                    'payment_way'      => 'CARTAO',
                    'value'            => $valores[$index],
                    'payment_group'    => $cartao->grupo,
                    'raw_payload'      => null,
                ]);
            }

            return $venda->id;
        });

        $this->newLine();
        $this->info("✅ Venda manual criada com sucesso!");
        $this->info("   ID da Venda: {$vendaId}");
        $this->info("   Valor Total: R$ " . number_format($valorTotal, 2, ',', '.'));
        $this->warn("   ⚠️  Guarde o ID ({$vendaId}) para poder remover esta venda depois com:");
        $this->line("   php artisan feira:remover-venda-manual {$vendaId}");

        return 0;
    }
}
