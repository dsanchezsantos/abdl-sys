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
        $this->baseUrl = config('services.nowigo.base_url');
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
     * Executa a requisição HTTP com retry e tratamento de erro.
     */
    protected function request(array $params): array
    {
        try {
            $response = Http::retry(3, 1000)
                ->timeout(30)
                ->get($this->baseUrl, $params);

            if ($response->failed()) {
                throw new NowigoApiException("API Nowigo retornou erro {$response->status()}: {$response->body()}");
            }

            $data = $response->json();

            if (!isset($data['data'])) {
                throw new NowigoApiException("API Nowigo retornou formato inesperado: " . json_encode($data));
            }

            return $data;
        } catch (\Exception $e) {
            Log::error("ERRO na Requisição Nowigo: " . $e->getMessage());
            $this->notifyFailure($e->getMessage());
            throw $e;
        }
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
