<?php

namespace App\Services;

use App\Models\Feira;
use App\Exceptions\NowigoApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NowigoService
{
    protected Feira $feira;
    protected string $baseUrl;

    public function __construct(Feira $feira)
    {
        $this->feira = $feira;
        $this->baseUrl = $feira->endpoint_url ?? config('services.nowigo.base_url');
    }

    /**
     * Busca uma página de vendas (list).
     */
    public function buscarPagina(int $page, int $perPage = 100): array
    {
        $params = [
            'action' => 'list',
            'eventId' => $this->feira->evento_id_api,
            'userId' => $this->feira->user_id_api,
            'perPage' => $perPage,
            'dateTimeBegin' => $this->feira->data_inicio->format('d/m/Y H:i:s'),
            'dateTimeEnd' => $this->feira->data_fim->format('d/m/Y H:i:s'),
            'search' => '',
            'page' => $page,
        ];

        return $this->request($params);
    }

    /**
     * Busca os detalhes de uma venda (detail).
     */
    public function buscarDetalhe(string $saleId, ?int $saleType): array
    {
        $params = [
            'action' => 'detail',
            'saleId' => $saleId,
            'saleType' => $saleType,
        ];

        return $this->request($params);
    }

    /**
     * Pausa entre chamadas consecutivas para evitar rate limiting da API.
     * Abordagem conservadora: 500ms (2 req/s max).
     */
    public function throttle(): void
    {
        usleep(500_000);
    }

    /**
     * Executa a requisição HTTP com retry inteligente e tratamento de 403 (rate limit).
     *
     * Estratégia de backoff:
     * - Erros 403/429 (rate limit): espera 30s × tentativa (30s, 60s, 90s)
     * - Erros 5xx (servidor):        espera 5s × tentativa (5s, 10s, 15s)
     * - Erros 4xx (cliente):         não retenta (ex: 400, 404)
     */
    /**
     * Executa a requisição HTTP com retry inteligente e tratamento de 403 (rate limit).
     */
    protected function request(array $params): array
    {
        $primaryUrl = $this->baseUrl;
        $proxyUrl = config('services.nowigo.proxy_url');

        try {
            // 1. Tenta a chamada direta oficial
            return $this->executeRequest($primaryUrl, $params);
        } catch (\Exception $e) {
            // Se falhou e foi por bloqueio de IP (403 ou 429) E temos um proxy configurado
            $isRateLimited = ($e instanceof NowigoApiException && $e->isRateLimited()) 
                || ($e instanceof \Illuminate\Http\Client\RequestException && in_array($e->response?->status(), [403, 429]));

            if ($isRateLimited && !empty($proxyUrl)) {
                $status = $e instanceof NowigoApiException ? $e->getHttpStatus() : $e->response?->status();
                Log::warning("IP bloqueado ou rate limit na URL direta (Status: {$status}). Redirecionando requisição via Cloudflare Proxy...");
                try {
                    // 2. Fallback automático silencioso para o Cloudflare Worker
                    return $this->executeRequest($proxyUrl, $params);
                } catch (\Exception $proxyException) {
                    Log::error("FALHA no Proxy da Cloudflare: " . $proxyException->getMessage());
                    $this->notifyFailure("Direta e Proxy falharam. Erro proxy: " . $proxyException->getMessage());
                    throw $proxyException;
                }
            }

            Log::error("ERRO na Requisição Nowigo: " . $e->getMessage());
            $this->notifyFailure($e->getMessage());
            throw $e;
        }
    }

    /**
     * Realiza a chamada HTTP de fato para a URL fornecida com a lógica de retentativas.
     */
    protected function executeRequest(string $url, array $params): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
        ])->retry(
            times: 3,
            sleepMilliseconds: function (int $attempt, ?\Exception $exception) {
                // Se for rate limit (403/429), backoff agressivo
                if ($exception instanceof \Illuminate\Http\Client\RequestException
                    && in_array($exception->response?->status(), [403, 429])
                ) {
                    $delay = $attempt * 30000; // 30s, 60s, 90s
                    Log::warning("Rate limit detectado. Aguardando {$delay}ms antes da tentativa {$attempt}...");
                    return $delay;
                }

                // Demais erros: backoff padrão
                return $attempt * 5000; // 5s, 10s, 15s
            },
            when: function (\Exception $exception) {
                // Erros de conexão/timeout: sempre retenta
                if (! $exception instanceof \Illuminate\Http\Client\RequestException) {
                    return true;
                }

                $status = $exception->response?->status();

                // Rate limit (403/429) e erros de servidor (5xx): retenta
                if (in_array($status, [403, 429]) || $status >= 500) {
                    return true;
                }

                // Demais erros de cliente (400, 404, etc): não retenta
                return false;
            },
            throw: true
        )
            ->timeout(30)
            ->get($url, $params);

        if ($response->failed()) {
            throw new NowigoApiException(
                "API Nowigo retornou erro {$response->status()}: {$response->body()}",
                $response->status()
            );
        }

        $data = $response->json();

        if (!isset($data['data'])) {
            throw new NowigoApiException(
                "API Nowigo retornou formato inesperado: " . json_encode($data)
            );
        }

        return $data;
    }

    /**
     * Envia notificação de falha com throttling (cache lock).
     */
    protected function notifyFailure(string $message): void
    {
        $cacheKey = "nowigo_alert_sent_{$this->feira->id}";

        if (!Cache::has($cacheKey)) {
            Log::error("Falha na API Nowigo para a Feira #{$this->feira->id}: {$message}");

            // TODO: Implementar envio de e-mail real aqui se necessário
            // Mail::to(config('mail.admin_address'))->send(new \App\Mail\ApiFailureAlert($this->feira, $message));

            Cache::put($cacheKey, true, now()->addMinutes(30));
        }
    }

    /**
     * Normaliza texto para UPPERCASE e remove espaços extras.
     */
    public static function normalizeText(?string $value): ?string
    {
        if (is_null($value)) return null;

        $text = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\0", "", $text);
        $text = trim($text);

        if ($text === '') return null;

        return Str::upper($text);
    }

    /**
     * Converte string monetária para float/decimal.
     */
    public static function parseMoney($value): ?float
    {
        if (is_null($value)) return null;

        $text = str_replace("\0", "", (string)$value);
        $text = trim($text);

        if ($text === '') return null;

        $text = str_replace(['R$', ' '], '', $text);
        
        if (str_contains($text, ',')) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        }

        $text = preg_replace("/[^0-9\-.]/", "", $text);

        if ($text === '') return null;

        return (float) $text;
    }
}
