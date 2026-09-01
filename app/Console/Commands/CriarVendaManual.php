<?php

namespace App\Console\Commands;

use App\Enums\CartaoClassificacao;
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
    protected $signature = 'feira:criar-venda-manual {feira_id} {livro_id} {--cartoes=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria uma venda manual em cartão(ões) com gasto total inferior a R$ 100,00.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $feiraId = (int) $this->argument('feira_id');
        $livroId = (int) $this->argument('livro_id');
        $numCartoesDesejado = max(1, (int) $this->option('cartoes'));

        // 1. Validação da Feira e do Livro (utilizando o ID interno do banco)
        $feira = Feira::findOrFail($feiraId);
        $this->info("Feira: #{$feira->id} — {$feira->nome}");

        $livro = Livro::where('id', $livroId)
            ->where('id_feira', $feiraId)
            ->firstOrFail();

        $valorTotal = (float) $livro->valor;
        $this->info("Livro: {$livro->produto} — R$ " . number_format($valorTotal, 2, ',', '.'));

        // 2. Buscar cartões válidos com total de gasto INFERIOR a R$ 100,00
        $cartoesCandidate = DB::table('cartoes as c')
            ->leftJoin('pagamentos as p', function ($join) use ($feiraId) {
                $join->on('c.tag_code', '=', 'p.tag_code')
                     ->on('c.id_feira', '=', 'p.id_feira');
            })
            ->where('c.id_feira', $feiraId)
            ->where('c.classificacao', '!=', CartaoClassificacao::TESTE->value)
            ->where('c.grupo', '!=', 'PAGAMENTO SEM GRUPO')
            ->select('c.id', 'c.tag_code', 'c.grupo', 'c.classificacao')
            ->selectRaw('COALESCE(SUM(p.value), 0) AS total_gasto')
            ->groupBy('c.id', 'c.tag_code', 'c.grupo', 'c.classificacao')
            ->havingRaw('COALESCE(SUM(p.value), 0) < 100.00')
            ->orderBy('total_gasto', 'asc')
            ->limit($numCartoesDesejado)
            ->get();

        if ($cartoesCandidate->isEmpty()) {
            $this->error("Nenhum cartão válido encontrado com gasto total < R$ 100,00 na feira #{$feiraId}.");
            return 1;
        }

        $numCartoesUsados = $cartoesCandidate->count();

        // 3. Distribuir o valor de forma inteira entre os cartões selecionados
        $parcelas = [];
        $valorBase = floor($valorTotal / $numCartoesUsados);
        $resto = (int) round($valorTotal - ($valorBase * $numCartoesUsados));

        foreach ($cartoesCandidate as $i => $c) {
            $valorParcela = $valorBase + ($i < $resto ? 1 : 0);
            $parcelas[] = [
                'cartao' => $c,
                'valor'  => $valorParcela,
            ];
        }

        // Determinar sale_type (-1 para Pagamento Único, 1 para Múltiplos Pagamentos)
        $saleType = ($numCartoesUsados === 1) ? -1 : 1;
        $saleTypeLabel = ($saleType === 1) ? 'Múltiplos Pagamentos' : 'Pagamento Único';

        // 4. Mostrar resumo antes de confirmar
        $this->newLine();
        $this->info("=== RESUMO DA VENDA MANUAL ===");
        $this->info("   Método de Venda (sale_type): {$saleType} ({$saleTypeLabel})");
        $this->table(
            ['Cartão', 'Tag Code', 'Grupo', 'Gasto Atual', 'Nova Parcela', 'Novo Gasto Total'],
            collect($parcelas)->map(function ($p, $index) {
                $c = $p['cartao'];
                $atual = (float) $c->total_gasto;
                $nova = (float) $p['valor'];
                return [
                    $index + 1,
                    $c->tag_code,
                    $c->grupo,
                    'R$ ' . number_format($atual, 2, ',', '.'),
                    'R$ ' . number_format($nova, 2, ',', '.'),
                    'R$ ' . number_format($atual + $nova, 2, ',', '.'),
                ];
            })->toArray()
        );

        if (!$this->confirm("Deseja criar esta venda manual?")) {
            $this->info("Operação cancelada.");
            return 0;
        }

        // 5. Executar em transação
        $result = DB::transaction(function () use ($feiraId, $livro, $parcelas, $valorTotal, $saleType) {
            // Gerar sell_number sequencial numérico (uniforme com a API)
            $maxSellNumber = DB::table('venda_headers')
                ->where('id_feira', $feiraId)
                ->get()
                ->filter(fn($v) => ctype_digit((string)$v->sell_number))
                ->max(fn($v) => (int)$v->sell_number);

            $sellNumber = (string) (($maxSellNumber ?: 100000) + 1);

            // Determinar data/hora: horário aleatório entre 08:00–17:00 do último dia de vendas
            $ultimaVenda = VendaHeader::where('id_feira', $feiraId)
                ->orderBy('date_hour', 'desc')
                ->first();

            $dataBase = $ultimaVenda ? Carbon::parse($ultimaVenda->date_hour)->startOfDay() : Carbon::today();
            $dateHour = $dataBase->copy()->setTime(rand(8, 16), rand(0, 59), rand(0, 59));

            // Criar VendaHeader com sale_type configurado e box padronizado
            $venda = VendaHeader::create([
                'id_feira'    => $feiraId,
                'sell_number' => $sellNumber,
                'sale_type'   => $saleType,
                'total_value' => $valorTotal,
                'date_hour'   => $dateHour,
                'box'         => 'LIVRO 103',
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

            // Criar Pagamentos
            foreach ($parcelas as $p) {
                $cartao = $p['cartao'];
                Pagamento::create([
                    'id_feira'         => $feiraId,
                    'sell_number'      => $sellNumber,
                    'pagamento_id_api' => rand(1000000, 9999999),
                    'tag_code'         => $cartao->tag_code,
                    'cpf'              => null,
                    'payment_way'      => 'CARTAO',
                    'value'            => $p['valor'],
                    'payment_group'    => $cartao->grupo,
                    'raw_payload'      => null,
                ]);
            }

            return $venda;
        });

        $this->newLine();
        $this->info("✅ Venda manual criada com sucesso!");
        $this->info("   ID no Banco: {$result->id}");
        $this->info("   Sell Number: {$result->sell_number}");
        $this->info("   Método: {$saleTypeLabel} (sale_type: {$result->sale_type})");
        $this->info("   Valor Total: R$ " . number_format($valorTotal, 2, ',', '.'));
        $this->warn("   ⚠️  Guarde o ID ({$result->id}) para poder remover esta venda depois se necessário com:");
        $this->line("   php artisan feira:remover-venda-manual {$result->id}");

        return 0;
    }
}
