<?php

namespace App\Jobs;

use App\Models\Feira;
use App\Models\Cartao;
use App\Models\VendaHeader;
use App\Models\ItemVenda;
use App\Models\Pagamento;
use App\Models\Livro;
use App\Services\NowigoService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessarPaginaVendaJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $feiraId;
    protected int $page;
    protected int $perPage;

    /**
     * O número de segundos que o job pode ser executado antes de expirar.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(int $feiraId, int $page, int $perPage = 100)
    {
        $this->feiraId = $feiraId;
        $this->page = $page;
        $this->perPage = $perPage;
        $this->onQueue('sync-nowigo');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            $feira = Feira::findOrFail($this->feiraId);
            $nowigo = new NowigoService($feira);

            Log::debug("Processando Página {$this->page} para a Feira #{$this->feiraId}");

            $response = $nowigo->buscarPagina($this->page, $this->perPage);
            $sales = $response['data'] ?? [];

            foreach ($sales as $saleHeader) {
                $this->processarVenda($nowigo, $saleHeader);
                $nowigo->throttle();
            }

            // Limpar registro de erro de integração anterior no sucesso desta página
            DB::table('erros_integracao')
                ->where('id_feira', $this->feiraId)
                ->where('pagina', $this->page)
                ->delete();

            Log::debug("Página {$this->page} finalizada para a Feira #{$this->feiraId}");
        } catch (\Throwable $e) {
            Log::error("FALHA CRÍTICA na Página {$this->page} (Feira #{$this->feiraId}): " . $e->getMessage());

            // Auditoria de Erro
            DB::table('erros_integracao')->insert([
                'id_feira' => $this->feiraId,
                'pagina' => $this->page,
                'payload_recebido' => json_encode($response ?? ['raw' => 'Falha na requisição ou parsing']),
                'mensagem_erro' => $e->getMessage(),
                'status' => 'PENDENTE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail($e);
        }
    }

    protected function processarVenda(NowigoService $nowigo, array $header): void
    {
        $sellNumber = (string) $header['sellNumber'];
        $saleType = $header['type'] ?? null;

        // Otimização Crucial: Evitar requisições de detalhes duplicadas para vendas já processadas
        $alreadyProcessed = VendaHeader::where('id_feira', $this->feiraId)
            ->where('sell_number', $sellNumber)
            ->where('processado', true)
            ->exists();

        if ($alreadyProcessed) {
            return;
        }

        // Buscar detalhes da venda
        $response = $nowigo->buscarDetalhe($sellNumber, $saleType);
        $detail = $response['data'] ?? [];
        
        // Calcular o valor total real a partir dos pagamentos ou produtos no detalhe
        $totalValue = 0.0;
        if (!empty($detail['payments'])) {
            foreach ($detail['payments'] as $p) {
                $totalValue += NowigoService::parseMoney($p['value'] ?? 0);
            }
        } elseif (!empty($detail['products'])) {
            foreach ($detail['products'] as $pr) {
                $totalValue += NowigoService::parseMoney($pr['totalValue'] ?? 0);
            }
        } else {
            $totalValue = NowigoService::parseMoney($header['totalValue'] ?? 0);
        }

        try {
            DB::transaction(function () use ($header, $detail, $sellNumber, $totalValue) {
                // 1. Descoberta de Cartões
                $this->upsertCartoes($detail['payments'] ?? []);

                // 2. Upsert VendaHeader
                VendaHeader::updateOrCreate(
                    ['id_feira' => $this->feiraId, 'sell_number' => $sellNumber],
                    [
                        'sale_type' => $header['type'] ?? null,
                        'total_value' => $totalValue,
                        'date_hour' => !empty($header['dateHour']) 
                            ? ($this->parseBrazilianDate($header['dateHour']))
                            : null,
                        'box' => NowigoService::normalizeText($header['box']),
                        'processado' => true,
                        'raw_payload' => $header,
                    ]
                );

                // 3. Limpeza de Detalhes (Idempotência)
                Pagamento::where('id_feira', $this->feiraId)->where('sell_number', $sellNumber)->delete();
                ItemVenda::where('id_feira', $this->feiraId)->where('sell_number', $sellNumber)->delete();

                // 4. Inserção de Pagamentos
                $this->insertPagamentos($sellNumber, $detail['payments'] ?? []);

                // 5. Inserção de Itens e Descoberta de Livros
                $this->insertItens($sellNumber, $detail['products'] ?? []);
            });
        } catch (\Exception $e) {
            Log::error("ERRO ao processar venda {$sellNumber}: " . $e->getMessage());
            throw $e;
        }
    }

    protected function upsertCartoes(array $payments): void
    {
        $cartoes = [];
        foreach ($payments as $p) {
            $tag = NowigoService::normalizeText($p['tagCode'] ?? null);
            $group = NowigoService::normalizeText($p['group'] ?? null);
            if ($tag && !in_array($tag, ['NÃO DISPONÍVEL', 'NAO DISPONIVEL', 'N/A']) && $group !== 'PAGAMENTO SEM GRUPO') {
                $cartoes[] = [
                    'id_feira' => $this->feiraId,
                    'tag_code' => $tag,
                    'grupo' => $group,
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($cartoes)) {
            Cartao::upsert($cartoes, ['id_feira', 'tag_code'], ['grupo', 'updated_at']);
        }
    }

    protected function insertPagamentos(string $sellNumber, array $payments): void
    {
        $records = [];
        foreach ($payments as $p) {
            $records[] = [
                'id_feira' => $this->feiraId,
                'sell_number' => $sellNumber,
                'pagamento_id_api' => $p['id'] ?? null,
                'tag_code' => NowigoService::normalizeText($p['tagCode'] ?? null),
                'cpf' => NowigoService::normalizeText($p['cpf'] ?? null),
                'payment_way' => NowigoService::normalizeText($p['paymentWay'] ?? 'NÃO INFORMADO'),
                'value' => NowigoService::parseMoney($p['value']),
                'payment_group' => NowigoService::normalizeText($p['group'] ?? null),
                'raw_payload' => json_encode($p),
                'created_at' => now(),
            ];
        }

        if (!empty($records)) {
            Pagamento::insert($records);
        }
    }

    protected function insertItens(string $sellNumber, array $products): void
    {
        $itemRecords = [];
        $bookRecords = [];

        foreach ($products as $pr) {
            $unitValue = NowigoService::parseMoney($pr['unitValue']);
            $name = NowigoService::normalizeText($pr['name']);
            $apiId = $pr['id'] ?? null;

            $itemRecords[] = [
                'id_feira' => $this->feiraId,
                'sell_number' => $sellNumber,
                'produto_id_api' => $apiId,
                'name' => $name,
                'amount' => $pr['amount'] ?? 0,
                'unit_value' => $unitValue,
                'total_value' => NowigoService::parseMoney($pr['totalValue']),
                'raw_payload' => json_encode($pr),
                'created_at' => now(),
            ];

            if ($apiId) {
                // Usando o nome como chave para agrupar duplicados da mesma transação antes do upsert
                $bookRecords[$name] = [
                    'id_feira' => $this->feiraId,
                    'produto_id_api' => $apiId,
                    'produto' => $name,
                    'valor' => $unitValue,
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($itemRecords)) {
            ItemVenda::insert($itemRecords);
        }

        if (!empty($bookRecords)) {
            // Upsert livros usando a chave única ['id_feira', 'produto'] (nome do livro)
            // Preserva as colunas manuais 'editora' e 'representante' não incluindo-as no terceiro argumento
            Livro::upsert(array_values($bookRecords), ['id_feira', 'produto'], ['valor', 'produto_id_api', 'updated_at']);
        }
    }

    protected function parseBrazilianDate(string $date): ?string
    {
        // Limpeza agressiva: remove qualquer caractere que não seja número, barra, dois pontos ou espaço
        $date = preg_replace('/[^0-9\/\: ]/', '', $date);
        $date = trim($date);

        if (empty($date)) {
            return null;
        }

        try {
            // Tenta o formato brasileiro comum vindo da API
            return \Illuminate\Support\Carbon::createFromFormat('d/m/Y H:i:s', $date)->toDateTimeString();
        } catch (\Throwable $e) {
            try {
                // Fallback para o parse automático caso o formato mude
                return \Illuminate\Support\Carbon::parse($date)->toDateTimeString();
            } catch (\Throwable $e2) {
                Log::warning("Falha ao processar data (Hex: " . bin2hex($date) . "): {$date}. Erro: " . $e2->getMessage());
                return null;
            }
        }
    }
}
