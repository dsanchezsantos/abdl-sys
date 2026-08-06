<?php

namespace Tests\Feature;

use App\Models\Feira;
use App\Models\VendaHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendasFiltrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_filtrar_vendas_por_data_especifica_e_periodo()
    {
        $user = User::factory()->create();
        $feira = Feira::create([
            'nome' => 'Feira Teste',
            'data_inicio' => now()->subDays(10),
            'data_fim' => now()->addDays(5),
            'status' => \App\Enums\FeiraStatus::PLANEJADA,
            'evento_id_api' => 123,
            'user_id_api' => 456,
        ]);

        // Criar venda ontem
        $vendaOntem = VendaHeader::create([
            'id_feira' => $feira->id,
            'sell_number' => 'V001',
            'total_value' => '100.00',
            'date_hour' => '2026-08-04 12:00:00',
            'processado' => true,
        ]);

        // Criar venda hoje
        $vendaHoje = VendaHeader::create([
            'id_feira' => $feira->id,
            'sell_number' => 'V002',
            'total_value' => '200.00',
            'date_hour' => '2026-08-05 10:00:00',
            'processado' => true,
        ]);

        // Criar venda amanhã
        $vendaAmanha = VendaHeader::create([
            'id_feira' => $feira->id,
            'sell_number' => 'V003',
            'total_value' => '300.00',
            'date_hour' => '2026-08-06 15:00:00',
            'processado' => true,
        ]);

        // 1. Filtrar período que contém apenas ontem e hoje
        $response = $this->actingAs($user)->get(route('feiras.vendas', [
            'feira' => $feira->id,
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-05',
        ]));

        $response->assertStatus(200);
        $vendasData = $response->original->getData()['page']['props']['vendas']['data'];
        $this->assertCount(2, $vendasData);
        $this->assertEquals('V002', $vendasData[0]['sell_number']); // Mais recente primeiro
        $this->assertEquals('V001', $vendasData[1]['sell_number']);

        // 2. Filtrar apenas a partir de hoje (start_date)
        $responseStart = $this->actingAs($user)->get(route('feiras.vendas', [
            'feira' => $feira->id,
            'start_date' => '2026-08-05',
        ]));
        $vendasStartData = $responseStart->original->getData()['page']['props']['vendas']['data'];
        $this->assertCount(2, $vendasStartData); // Hoje e amanhã
        $this->assertEquals('V003', $vendasStartData[0]['sell_number']);
        $this->assertEquals('V002', $vendasStartData[1]['sell_number']);

        // 3. Filtrar apenas até ontem (end_date)
        $responseEnd = $this->actingAs($user)->get(route('feiras.vendas', [
            'feira' => $feira->id,
            'end_date' => '2026-08-04',
        ]));
        $vendasEndData = $responseEnd->original->getData()['page']['props']['vendas']['data'];
        $this->assertCount(1, $vendasEndData); // Apenas ontem
        $this->assertEquals('V001', $vendasEndData[0]['sell_number']);
    }
}
