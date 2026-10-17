<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Feira;
use App\Enums\FeiraStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeiraUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_feira_name(): void
    {
        $user = User::factory()->create();
        $feira = Feira::create([
            'nome' => 'Feira Antiga',
            'data_inicio' => '2026-08-01',
            'data_fim' => '2026-08-10',
            'endpoint_url' => 'https://api.nowigo.com.br/v1',
            'evento_id_api' => '123',
            'user_id_api' => '456',
            'status' => FeiraStatus::PLANEJADA,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('feiras.update', $feira->id), [
                'nome' => 'Feira Nova E Editada',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $feira->refresh();
        $this->assertEquals('Feira Nova E Editada', $feira->nome);
    }

    public function test_authenticated_user_can_update_all_feira_fields(): void
    {
        $user = User::factory()->create();
        $feira = Feira::create([
            'nome' => 'Feira Antiga',
            'data_inicio' => '2026-08-01',
            'data_fim' => '2026-08-10',
            'endpoint_url' => 'https://api.nowigo.com.br/v1',
            'evento_id_api' => '123',
            'user_id_api' => '456',
            'status' => FeiraStatus::PLANEJADA,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('feiras.update', $feira->id), [
                'nome' => 'Feira Internacional do Livro',
                'data_inicio' => '2026-09-01',
                'data_fim' => '2026-09-07',
                'endpoint_url' => 'https://nova-api.nowigo.com.br/v2',
                'evento_id_api' => 'EVT-999',
                'user_id_api' => 'USR-888',
                'status' => FeiraStatus::EM_ANDAMENTO->value,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $feira->refresh();
        $this->assertEquals('Feira Internacional do Livro', $feira->nome);
        $this->assertEquals('2026-09-01', $feira->data_inicio->format('Y-m-d'));
        $this->assertEquals('2026-09-07', $feira->data_fim->format('Y-m-d'));
        $this->assertEquals('https://nova-api.nowigo.com.br/v2', $feira->endpoint_url);
        $this->assertEquals('EVT-999', $feira->evento_id_api);
        $this->assertEquals('USR-888', $feira->user_id_api);
        $this->assertEquals(FeiraStatus::EM_ANDAMENTO, $feira->status);
    }

    public function test_guest_cannot_update_feira(): void
    {
        $feira = Feira::create([
            'nome' => 'Feira Antiga',
            'data_inicio' => '2026-08-01',
            'data_fim' => '2026-08-10',
            'endpoint_url' => 'https://api.nowigo.com.br/v1',
            'evento_id_api' => '123',
            'user_id_api' => '456',
            'status' => FeiraStatus::PLANEJADA,
        ]);

        $response = $this->patch(route('feiras.update', $feira->id), [
            'nome' => 'Feira Nova',
        ]);

        $response->assertRedirect(route('login'));
        $feira->refresh();
        $this->assertEquals('Feira Antiga', $feira->nome);
    }

    public function test_validation_fails_on_invalid_data(): void
    {
        $user = User::factory()->create();
        $feira = Feira::create([
            'nome' => 'Feira Antiga',
            'data_inicio' => '2026-08-01',
            'data_fim' => '2026-08-10',
            'endpoint_url' => 'https://api.nowigo.com.br/v1',
            'evento_id_api' => '123',
            'user_id_api' => '456',
            'status' => FeiraStatus::PLANEJADA,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('feiras.auditoria', $feira->id))
            ->patch(route('feiras.update', $feira->id), [
                'nome' => '',
                'endpoint_url' => 'url-invalida',
                'data_inicio' => '2026-08-10',
                'data_fim' => '2026-08-05',
                'status' => 'STATUS_INEXISTENTE',
            ]);

        $response->assertSessionHasErrors(['nome', 'endpoint_url', 'data_fim', 'status']);
        $feira->refresh();
        $this->assertEquals('Feira Antiga', $feira->nome);
    }

    public function test_cannot_update_feira_when_is_sincronizando(): void
    {
        $user = User::factory()->create();
        $feira = Feira::create([
            'nome' => 'Feira em Sincronizacao',
            'data_inicio' => '2026-08-01',
            'data_fim' => '2026-08-10',
            'endpoint_url' => 'https://api.nowigo.com.br/v1',
            'evento_id_api' => '123',
            'user_id_api' => '456',
            'status' => FeiraStatus::PLANEJADA,
            'is_sincronizando' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('feiras.update', $feira->id), [
                'nome' => 'Tentativa de Alteracao',
            ]);

        $response->assertSessionHasErrors('feira');
        $feira->refresh();
        $this->assertEquals('Feira em Sincronizacao', $feira->nome);
    }
}
