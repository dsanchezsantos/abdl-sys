<?php

namespace App\Console\Commands;

use App\Models\Feira;
use App\Models\VendaHeader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RedistribuirCaixaLancamento extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feira:redistribuir-caixa-lancamento 
                            {feira_id? : ID específico da feira (opcional)} 
                            {--boxes= : Caixas normais separados por vírgula (ex: "LIVRO 1,LIVRO 2,LIVRO 3")}
                            {--count= : Quantidade de caixas a gerar automaticamente (ex: 12 gera LIVRO 1 até LIVRO 12)}
                            {--dry-run : Simular a redistribuição sem alterar os dados no banco}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Redistribui vendas do caixa LANÇAMENTO entre os caixas normais (LIVRO ...) de forma equilibrada.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $feiraId = $this->argument('feira_id');
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn("🔍 MODO SIMULAÇÃO (--dry-run) ATIVADO. Nenhuma alteração será salva no banco de dados.");
            $this->newLine();
        }

        // Determinar quais feiras processar
        if ($feiraId) {
            $feira = Feira::find($feiraId);
            if (!$feira) {
                $this->error("Feira #{$feiraId} não foi encontrada.");
                return 1;
            }
            $feirasIds = [$feira->id];
        } else {
            $feirasIds = VendaHeader::whereRaw("UPPER(TRIM(box)) = 'LANÇAMENTO'")
                ->distinct()
                ->pluck('id_feira')
                ->filter()
                ->values()
                ->toArray();

            if (empty($feirasIds)) {
                $this->info("Nenhuma venda encontrada no caixa 'LANÇAMENTO'.");
                return 0;
            }
        }

        foreach ($feirasIds as $fId) {
            $this->processarFeira($fId, $isDryRun);
        }

        return 0;
    }

    /**
     * Processa a redistribuição de caixas de uma feira específica.
     */
    private function processarFeira(int $feiraId, bool $isDryRun): void
    {
        $feira = Feira::find($feiraId);
        $nomeFeira = $feira ? $feira->nome : "ID #{$feiraId}";

        $this->info("==================================================");
        $this->info("Processando Feira: {$nomeFeira} (ID: {$feiraId})");
        $this->info("==================================================");

        // 1. Contar vendas no caixa LANÇAMENTO (sem carregar registros em memória)
        $totalLancamento = VendaHeader::where('id_feira', $feiraId)
            ->whereRaw("UPPER(TRIM(box)) = 'LANÇAMENTO'")
            ->count();

        if ($totalLancamento === 0) {
            $this->info("Nenhuma venda no caixa 'LANÇAMENTO' encontrada para esta feira.");
            $this->newLine();
            return;
        }

        $this->info("Total de vendas no caixa 'LANÇAMENTO': {$totalLancamento}");

        // 2. Determinar a lista de caixas normais alvo
        $caixasNormais = $this->obterCaixasAlvo($feiraId);

        $totalCaixas = count($caixasNormais);

        if ($totalCaixas === 0) {
            $this->warn("⚠️  Nenhum caixa normal (padrão 'LIVRO %') foi encontrado nesta feira nem informado via opções (--boxes ou --count).");
            $this->warn("Dica: Use --count=12 (gera LIVRO 1 a LIVRO 12) ou --boxes=\"LIVRO 1,LIVRO 2\"");
            $this->newLine();
            return;
        }

        $this->info("Caixas normais alvo ({$totalCaixas}): " . implode(', ', $caixasNormais));

        // 3. Cálculo de Distribuição (Quociente e Resto)
        $quociente = intdiv($totalLancamento, $totalCaixas);
        $resto = $totalLancamento % $totalCaixas;

        $distribuicao = [];
        $headers = ['Caixa Alvo', 'Vendas a Receber'];

        foreach ($caixasNormais as $index => $caixa) {
            // Os primeiros $resto caixas recebem 1 venda a mais do resto da divisão
            $qtd = $quociente + ($index < $resto ? 1 : 0);
            $distribuicao[] = [
                'caixa' => $caixa,
                'qtd' => $qtd,
            ];
        }

        // Exibir tabela de planejamento da distribuição
        $tableRows = array_map(fn($item) => [$item['caixa'], $item['qtd']], $distribuicao);
        $this->table($headers, $tableRows);

        if ($isDryRun) {
            $this->info("Simulação concluída para a Feira #{$feiraId}. Nenhuma alteração efetuada.");
            $this->newLine();
            return;
        }

        if (!$this->confirm("Deseja confirmar e aplicar a redistribuição das {$totalLancamento} vendas para a Feira #{$feiraId}?")) {
            $this->warn("Operação cancelada pelo usuário para a Feira #{$feiraId}.");
            $this->newLine();
            return;
        }

        // 4. Aplicar a atualização no banco de dados em lotes (Chunking para controle estrito de memória)
        $this->info("Aplicando atualizações no banco de dados...");
        $bar = $this->output->createProgressBar($totalLancamento);
        $bar->start();

        $chunkSize = 1000; // Tamanho máximo do lote para preservar memória

        foreach ($distribuicao as $item) {
            $caixaAlvo = $item['caixa'];
            $restanteDoCaixa = $item['qtd'];

            while ($restanteDoCaixa > 0) {
                $tamanhoLote = min($chunkSize, $restanteDoCaixa);

                // Buscar apenas os IDs do próximo lote da feira que ainda estão como 'LANÇAMENTO'
                $batchIds = VendaHeader::where('id_feira', $feiraId)
                    ->whereRaw("UPPER(TRIM(box)) = 'LANÇAMENTO'")
                    ->orderBy('id')
                    ->limit($tamanhoLote)
                    ->pluck('id')
                    ->toArray();

                if (empty($batchIds)) {
                    break;
                }

                DB::transaction(function () use ($batchIds, $caixaAlvo) {
                    DB::table('venda_headers')
                        ->whereIn('id', $batchIds)
                        ->update(['box' => $caixaAlvo]);
                });

                $qtdProcessada = count($batchIds);
                $restanteDoCaixa -= $qtdProcessada;
                $bar->advance($qtdProcessada);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Redistribuição concluída com sucesso para a Feira #{$feiraId}!");
        $this->newLine();
    }

    /**
     * Obtém a lista de caixas normais das opções do comando ou consulta no banco.
     */
    private function obterCaixasAlvo(int $feiraId): array
    {
        // Se a opção --boxes foi informada
        if ($boxesOption = $this->option('boxes')) {
            $boxes = array_map('trim', explode(',', $boxesOption));
            return array_values(array_filter($boxes));
        }

        // Se a opção --count foi informada
        if ($countOption = $this->option('count')) {
            $count = (int) $countOption;
            $boxes = [];
            for ($i = 1; $i <= $count; $i++) {
                $boxes[] = "LIVRO {$i}";
            }
            return $boxes;
        }

        // Caso contrário, buscar do banco de dados na mesma feira
        $caixasNormais = VendaHeader::where('id_feira', $feiraId)
            ->whereRaw("UPPER(TRIM(box)) LIKE 'LIVRO %'")
            ->distinct()
            ->pluck('box')
            ->toArray();

        usort($caixasNormais, 'strnatcmp');

        return $caixasNormais;
    }
}
