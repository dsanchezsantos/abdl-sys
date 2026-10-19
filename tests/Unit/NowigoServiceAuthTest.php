<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Feira;
use App\Services\NowigoService;
use App\Exceptions\NowigoApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class NowigoServiceAuthTest extends TestCase
{
    use RefreshDatabase;

    protected Feira $feira;

    protected function setUp(): void
    {
        parent::setUp();

        $this->feira = Feira::create([
            'nome' => 'Feira Teste',
            'data_inicio' => '2026-10-01 08:00:00',
            'data_fim' => '2026-10-10 20:00:00',
            'endpoint_url' => 'https://feiradolivro-teste.nowigo.com.br/app/sale.mdl',
            'api_username' => 'feiramaster',
            'api_password' => 'segredo123',
            'evento_id_api' => '1104',
            'user_id_api' => '55240',
        ]);
    }

    public function test_feira_model_encrypts_api_password_and_hides_it_from_array(): void
    {
        $this->assertEquals('segredo123', $this->feira->api_password);

        // No banco de dados, o campo não deve estar em texto puro
        $rawPassword = \Illuminate\Support\Facades\DB::table('feiras')
            ->where('id', $this->feira->id)
            ->value('api_password');

        $this->assertNotEquals('segredo123', $rawPassword);

        // No toArray(), api_password deve estar oculto
        $this->assertArrayNotHasKey('api_password', $this->feira->toArray());
    }

    public function test_feira_base_url_accessor_extracts_scheme_and_host(): void
    {
        $this->assertEquals('https://feiradolivro-teste.nowigo.com.br', $this->feira->base_url);
    }

    public function test_nowigo_service_authenticates_and_fetches_page_with_jsessionid(): void
    {
        Cache::flush();

        Http::fake([
            'https://feiradolivro-teste.nowigo.com.br/app/signin.mdl' => Http::response('OK', 200, [
                'Set-Cookie' => 'JSESSIONID=TEST_SESSION_12345; Path=/; HttpOnly',
            ]),
            'https://feiradolivro-teste.nowigo.com.br/app/sale.mdl*' => function (\Illuminate\Http\Client\Request $request) {
                // Valida que o cookie foi enviado no cabeçalho
                $this->assertStringContainsString('JSESSIONID=TEST_SESSION_12345', $request->header('Cookie')[0] ?? '');

                return Http::response([
                    'pagination' => ['totalItems' => 1, 'totalPages' => 1],
                    'data' => [
                        ['sellNumber' => '1001', 'type' => 1],
                    ],
                ], 200);
            },
        ]);

        $service = new NowigoService($this->feira);
        $result = $service->buscarPagina(1, 10);

        $this->assertCount(1, $result['data']);
        $this->assertEquals('1001', $result['data'][0]['sellNumber']);

        // Deve ter salvo em cache
        $this->assertEquals('TEST_SESSION_12345', Cache::get("nowigo_session_feira_{$this->feira->id}"));
    }

    public function test_nowigo_service_reauthenticates_transparently_when_session_expired(): void
    {
        Cache::flush();

        // Inicializa com uma sessão velha no cache
        Cache::put("nowigo_session_feira_{$this->feira->id}", 'EXPIRED_SESSION_OLD');

        $requestCount = 0;

        Http::fake([
            'https://feiradolivro-teste.nowigo.com.br/app/signin.mdl' => Http::response('OK', 200, [
                'Set-Cookie' => 'JSESSIONID=NEW_FRESH_SESSION_999; Path=/; HttpOnly',
            ]),
            'https://feiradolivro-teste.nowigo.com.br/app/sale.mdl*' => function (\Illuminate\Http\Client\Request $request) use (&$requestCount) {
                $requestCount++;

                // Na 1ª requisição (com sessão velha), retorna o JSON de erro descrito pelo usuário
                if ($requestCount === 1) {
                    $this->assertStringContainsString('JSESSIONID=EXPIRED_SESSION_OLD', $request->header('Cookie')[0] ?? '');
                    return Http::response([
                        'success' => false,
                        'message' => 'Sessão não autenticada.',
                    ], 200);
                }

                // Na 2ª requisição (após reautenticação), recebe o novo cookie e retorna sucesso
                $this->assertStringContainsString('JSESSIONID=NEW_FRESH_SESSION_999', $request->header('Cookie')[0] ?? '');
                return Http::response([
                    'pagination' => ['totalItems' => 1, 'totalPages' => 1],
                    'data' => [
                        ['sellNumber' => '2002', 'type' => 1],
                    ],
                ], 200);
            },
        ]);

        $service = new NowigoService($this->feira);
        $result = $service->buscarPagina(1, 10);

        // O resultado deve ser bem-sucedido e transparente
        $this->assertCount(1, $result['data']);
        $this->assertEquals('2002', $result['data'][0]['sellNumber']);
        $this->assertEquals(2, $requestCount);

        // O cache deve ter sido atualizado com o novo cookie
        $this->assertEquals('NEW_FRESH_SESSION_999', Cache::get("nowigo_session_feira_{$this->feira->id}"));
    }

    public function test_nowigo_service_activates_cooldown_on_rate_limit(): void
    {
        Cache::flush();

        Http::fake([
            'https://feiradolivro-teste.nowigo.com.br/app/signin.mdl' => Http::response('OK', 200, [
                'Set-Cookie' => 'JSESSIONID=TEST_SESSION_12345; Path=/; HttpOnly',
            ]),
            'https://feiradolivro-teste.nowigo.com.br/app/sale.mdl*' => Http::response('Too Many Requests', 429),
        ]);

        $service = new NowigoService($this->feira);

        try {
            $service->buscarPagina(1, 10);
            $this->fail('Deveria ter lançado NowigoApiException de rate limit');
        } catch (NowigoApiException $e) {
            $this->assertTrue($e->isRateLimited());
            $this->assertEquals(429, $e->getHttpStatus());
        }

        // Deve ter ativado o cooldown no cache
        $this->assertTrue(Cache::has("nowigo_cooldown_feira_{$this->feira->id}"));
    }
}
