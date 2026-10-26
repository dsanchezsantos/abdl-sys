<?php

namespace App\Services;

use App\Models\Feira;
use App\Exceptions\NowigoApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NowigoService
{
    protected Feira $feira;
    protected string $baseUrl;
    protected string $endpointUrl;

    public function __construct(Feira $feira)
    {
        $this->feira = $feira;
        $this->baseUrl = $feira->base_url;
        $this->endpointUrl = $feira->endpoint_url ?? config('services.nowigo.base_url');
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
     * Pausa entre chamadas consecutivas para evitar sobrecarga da API.
     * Abordagem conservadora: 500ms (2 req/s max).
     */
    public function throttle(): void
    {
        usleep(500_000);
    }

    /**
     * Obtém o cookie de sessão JSESSIONID.
     * Utiliza cache com bloqueio atômico para evitar múltiplos logins simultâneos (Cache Stampede).
     */
    public function obterJSessionId(bool $forceRefresh = false): string
    {
        $cacheKey = "nowigo_session_feira_{$this->feira->id}";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        } else {
            $cachedSession = Cache::get($cacheKey);
            if (!empty($cachedSession)) {
                return (string) $cachedSession;
            }
        }

        // Lock atômico: apenas 1 worker faz o login por vez; os outros aguardam até 10 segundos
        $lockKey = "nowigo_lock_login_feira_{$this->feira->id}";
        return Cache::lock($lockKey, 15)->block(10, function () use ($cacheKey) {
            // Dupla checagem: o worker que acabou de adquirir o lock verifica se outro worker já renovou a sessão
            $cachedSession = Cache::get($cacheKey);
            if (!empty($cachedSession)) {
                return (string) $cachedSession;
            }

            $sessionId = $this->autenticar();

            // Guarda em cache por 25 minutos (renovação proativa)
            Cache::put($cacheKey, $sessionId, now()->addMinutes(25));

            return $sessionId;
        });
    }

    /**
     * Realiza o login direto no endpoint /app/signin.mdl e extrai o JSESSIONID.
     */
    public function autenticar(): string
    {
        $username = $this->feira->api_username ?? config('services.nowigo.username');
        $password = $this->feira->api_password ?? config('services.nowigo.password');

        if (empty($username) || empty($password)) {
            throw new NowigoApiException(
                "Credenciais de acesso à API Nowigo (usuário e senha) não estão configuradas para a Feira #{$this->feira->id} ({$this->feira->nome}).",
                401
            );
        }

        $loginUrl = rtrim($this->baseUrl, '/') . '/app/signin.mdl';

        Log::info("Iniciando autenticação na API Nowigo para a Feira #{$this->feira->id} em {$loginUrl}");

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => '*/*',
            ])
            ->timeout(20)
            ->asForm()
            ->post($loginUrl, [
                'action' => 'signin',
                'username' => $username,
                'password' => $password,
            ]);
        } catch (\Throwable $e) {
            throw new NowigoApiException(
                "Falha na conexão ao autenticar na API Nowigo ({$loginUrl}): " . $e->getMessage(),
                500,
                $e
            );
        }

        if ($response->status() === 429 || $response->status() === 403) {
            $this->ativarCooldownGlobal(60);
            throw new NowigoApiException(
                "Endpoint de autenticação da Nowigo retornou bloqueio/rate limit (Status {$response->status()}).",
                $response->status()
            );
        }

        // 1. Tenta extrair o JSESSIONID dos cookies gerenciados pelo cliente HTTP
        $sessionId = null;
        $cookie = $response->cookies()->getCookieByName('JSESSIONID');
        if ($cookie) {
            $sessionId = $cookie->getValue();
        }

        // 2. Fallback: extrair diretamente do header Set-Cookie se não veio no CookieJar
        if (!$sessionId) {
            $setCookieHeader = $response->header('Set-Cookie') ?? '';
            if (preg_match('/JSESSIONID=([^;]+)/', $setCookieHeader, $matches)) {
                $sessionId = trim($matches[1]);
            }
        }

        // 3. Validação crucial: a Nowigo sempre retorna um JSESSIONID mesmo com senha errada,
        // mas quando o login falha ela recarrega a tela de login contendo o formulário de signin.
        $body = $response->body();
        $isLoginForm = Str::contains($body, ['.signin-form', 'name="password"']) && Str::contains($body, ['signin.mdl', 'action="signin"']);

        if (empty($sessionId) || $isLoginForm) {
            Log::error("Login na Nowigo rejeitado para a Feira #{$this->feira->id}. Credenciais inválidas ou conta bloqueada.");
            throw new NowigoApiException(
                "Falha na autenticação da Nowigo: Usuário ou senha incorretos para a Feira #{$this->feira->id} ({$this->feira->nome}). Por favor, verifique as credenciais configuradas na feira.",
                401
            );
        }

        Log::info("Autenticação na Nowigo realizada com sucesso para a Feira #{$this->feira->id}.");

        return $sessionId;
    }

    /**
     * Executa a requisição com verificação de Cooldown, Auto-Healing de Sessão e Retry.
     */
    protected function request(array $params, int $authRetries = 0): array
    {
        $this->verificarCooldownGlobal();

        $sessionId = $this->obterJSessionId();

        try {
            $data = $this->executeRequest($this->endpointUrl, $params, $sessionId);
        } catch (NowigoApiException $e) {
            // Se foi Rate Limit (429 ou 403), registra o cooldown global imediatamente
            if ($e->isRateLimited()) {
                $this->ativarCooldownGlobal(60);
            }
            throw $e;
        }

        // Verificação Reativa: se a API retornou indício de sessão inválida no payload JSON
        if ($this->isSessaoNaoAutenticada($data)) {
            if ($authRetries < 2) {
                $mensagem = $data['message'] ?? 'Sessão não autenticada.';
                Log::warning("Sessão da Nowigo inválida/expirada detectada no payload ({$mensagem}). Reautenticando e repetindo requisição (Tentativa " . ($authRetries + 1) . " de 2)...");
                
                // Força obtenção de novo cookie e reexecuta
                $this->obterJSessionId(forceRefresh: true);
                return $this->request($params, $authRetries + 1);
            }

            throw new NowigoApiException(
                "Não foi possível autenticar na API Nowigo após renovar sessão. Retorno: " . ($data['message'] ?? json_encode($data)),
                401
            );
        }

        // Validação da estrutura esperada de retorno
        if (!isset($data['data'])) {
            throw new NowigoApiException(
                "API Nowigo retornou formato inesperado (chave 'data' ausente): " . json_encode($data)
            );
        }

        return $data;
    }

    /**
     * Realiza a chamada HTTP com os headers de sessão e retentativas exponenciais com Jitter.
     */
    protected function executeRequest(string $url, array $params, string $sessionId): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cookie' => "JSESSIONID={$sessionId}",
        ])->retry(
            times: 3,
            sleepMilliseconds: function (int $attempt, ?\Exception $exception) {
                // Jitter aleatório entre 200ms e 800ms para evitar colisões
                $jitter = rand(200, 800);

                if ($exception instanceof \Illuminate\Http\Client\RequestException
                    && in_array($exception->response?->status(), [403, 429])
                ) {
                    $delay = ($attempt * 20000) + $jitter; // 20s, 40s, 60s
                    Log::warning("Rate limit detectado na API Nowigo. Aguardando {$delay}ms antes da tentativa {$attempt}...");
                    return $delay;
                }

                // Erros de servidor (5xx) ou rede: 5s, 10s, 15s + jitter
                return ($attempt * 5000) + $jitter;
            },
            when: function (\Exception $exception) {
                if (! $exception instanceof \Illuminate\Http\Client\RequestException) {
                    return true; // Erros de timeout e conexão: sempre retenta
                }

                $status = $exception->response?->status();

                // Rate limit (403/429) e erros de servidor (5xx): retenta
                if (in_array($status, [403, 429]) || $status >= 500) {
                    return true;
                }

                // Erros de cliente permanentes (400, 404, etc.): não retenta
                return false;
            },
            throw: false
        )
        ->timeout(30)
        ->get($url, $params);

        if ($response->status() === 429 || $response->status() === 403) {
            throw new NowigoApiException(
                "API Nowigo retornou Rate Limit / Bloqueio temporário (Status {$response->status()}).",
                $response->status()
            );
        }

        if ($response->failed()) {
            throw new NowigoApiException(
                "API Nowigo retornou erro {$response->status()}: " . Str::limit($response->body(), 300),
                $response->status()
            );
        }

        $data = $response->json();

        if (!is_array($data)) {
            // Se não for JSON (ex: página HTML de erro ou tela de login), tratamos como sessão inválida se contiver signin
            if (Str::contains($response->body(), ['signin', 'login', 'Sessão'])) {
                return [
                    'success' => false,
                    'message' => 'Sessão não autenticada (HTML retornado).',
                ];
            }

            throw new NowigoApiException(
                "API Nowigo retornou resposta que não é JSON: " . Str::limit($response->body(), 300)
            );
        }

        return $data;
    }

    /**
     * Identifica se a resposta sinaliza sessão ausente, expirada ou inválida.
     */
    protected function isSessaoNaoAutenticada(array $data): bool
    {
        if (isset($data['success']) && $data['success'] === false) {
            $message = Str::lower((string) ($data['message'] ?? ''));
            if (Str::contains($message, ['sessão', 'sessao', 'autenticad', 'login', 'unauthorized', 'acesso'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se existe um Cooldown Global ativo no Cache para esta Feira.
     */
    protected function verificarCooldownGlobal(): void
    {
        $cooldownKey = "nowigo_cooldown_feira_{$this->feira->id}";
        if (Cache::has($cooldownKey)) {
            throw new NowigoApiException(
                "API Nowigo em período de cooldown defensivo devido a rate limit recente. Aguardando liberação da fila.",
                429
            );
        }
    }

    /**
     * Ativa Cooldown Global para que todos os workers parem de bater na Nowigo temporariamente.
     */
    public function ativarCooldownGlobal(int $segundos = 60): void
    {
        $cooldownKey = "nowigo_cooldown_feira_{$this->feira->id}";
        Cache::put($cooldownKey, true, now()->addSeconds($segundos));
        Log::warning("Cooldown global de {$segundos}s ativado para a Feira #{$this->feira->id} (API Nowigo).");
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
