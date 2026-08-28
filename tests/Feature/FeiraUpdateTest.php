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

    public function test_guest_cannot_update_feira_name(): void
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

    public function test_feira_name_must_be_provided(): void
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
            ]);

        $response->assertSessionHasErrors('nome');
        $feira->refresh();
        $this->assertEquals('Feira Antiga', $feira->nome);
    }
}
