<?php

namespace Tests\Feature;

use App\Models\Feira;
use App\Models\Livro;
use App\Models\Cartao;
use App\Models\VendaHeader;
use App\Models\ItemVenda;
use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowHistoryFiltrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_filtrar_historico_de_vendas_do_livro()
    {
        $user = User::factory()->create();
        $feira = Feira::create([
            'nome' => 'Feira Teste',
            'data_inicio' => now()->subDays(5),
            'data_fim' => now()->addDays(5),
            'status' => \App\Enums\FeiraStatus::PLANEJADA,
            'evento_id_api' => 12,
            'user_id_api' => 34,
        ]);

        $livro = Livro::create([
            'id_feira' => $feira->id,
            'produto_id_api' => 101,
            'produto' => 'Livro Teste',
            'valor' => '50.00',
        ]);

        // Venda 1: R$ 50.00, Ontem, 1 item, Caixa 1
        $v1 = VendaHeader::create([
            'id_feira' => $feira->id,
            'sell_number' => 'V1',
            'total_value' => '50.00',
            'date_hour' => '2026-08-04 10:00:00',
            'box' => 'Caixa 1',
            'sale_type' => -1,
            'processado' => true,
        ]);
        ItemVenda::create([
            'id_feira' => $feira->id,
            'sell_number' => 'V1',
            'produto_id_api' => 101,
            'name' => 'Livro Teste',
            'amount' => 1,
            'unit_value' => '50.00',
            'total_value' => '50.00',
        ]);

        // Venda 2: R$ 150.00, Hoje, 3 itens, Caixa 2
        $v2 = VendaHeader::create([
            'id_feira' => $feira->id,
            'sell_number' => 'V2',
            'total_value' => '150.00',
            'date_hour' => '2026-08-05 11:00:00',
            'box' => 'Caixa 2',
            'sale_type' => 1,
            'processado' => true,
        ]);
        ItemVenda::create([
            'id_feira' => $feira->id,
            'sell_number' => 'V2',
            'produto_id_api' => 101,
            'name' => 'Livro Teste',
            'amount' => 3,
            'unit_value' => '50.00',
            'total_value' => '150.00',
        ]);

        // Testar filtro por valor (min_value=100)
        $response = $this->actingAs($user)->get(route('catalogo.show', [
            'livro' => $livro->id,
            'min_value' => '100.00',
        ]));
        $response->assertStatus(200);
        $vendas = $response->original->getData()['page']['props']['vendas']['data'];
        $this->assertCount(1, $vendas);
        $this->assertEquals('V2', $vendas[0]['sell_number']);

        // Testar filtro por data (start_date=2026-08-05)
        $response = $this->actingAs($user)->get(route('catalogo.show', [
            'livro' => $livro->id,
            'start_date' => '2026-08-05',
        ]));
        $vendas = $response->original->getData()['page']['props']['vendas']['data'];
        $this->assertCount(1, $vendas);
        $this->assertEquals('V2', $vendas[0]['sell_number']);

        // Testar filtro por método de pagamento (sale_type=-1)
        $response = $this->actingAs($user)->get(route('catalogo.show', [
            'livro' => $livro->id,
            'sale_type' => '-1',
        ]));
        $vendas = $response->original->getData()['page']['props']['vendas']['data'];
        $this->assertCount(1, $vendas);
        $this->assertEquals('V1', $vendas[0]['sell_number']);

        // Testar filtro por caixa (box=Caixa 2)
        $response = $this->actingAs($user)->get(route('catalogo.show', [
            'livro' => $livro->id,
            'box' => 'Caixa 2',
        ]));
        $vendas = $response->original->getData()['page']['props']['vendas']['data'];
        $this->assertCount(1, $vendas);
        $this->assertEquals('V2', $vendas[0]['sell_number']);

        // Testar filtro por quantidade de itens (min_items=2)
        $response = $this->actingAs($user)->get(route('catalogo.show', [
            'livro' => $livro->id,
            'min_items' => 2,
        ]));
        $vendas = $response->original->getData()['page']['props']['vendas']['data'];
        $this->assertCount(1, $vendas);
        $this->assertEquals('V2', $vendas[0]['sell_number']);
    }

    public function test_pode_filtrar_historico_de_compras_do_cartao()
    {
        $user = User::factory()->create();
        $feira = Feira::create([
            'nome' => 'Feira Teste',
            'data_inicio' => now()->subDays(5),
            'data_fim' => now()->addDays(5),
            'status' => \App\Enums\FeiraStatus::PLANEJADA,
            'evento_id_api' => 12,
            'user_id_api' => 34,
        ]);

        $cartao = Cartao::create([
            'id_feira' => $feira->id,
            'tag_code' => 'T1000',
            'grupo' => 'Grupo A',
            'classificacao' => 'ALUNO',
        ]);

        // Venda 1: R$ 50.00, Ontem, Caixa 1
        VendaHeader::create([
            'id_feira' => $feira->id,
            'sell_number' => 'V1',
            'total_value' => '50.00',
            'date_hour' => '2026-08-04 10:00:00',
            'box' => 'Caixa 1',
            'sale_type' => -1,
            'processado' => true,
        ]);
        Pagamento::create([
            'id_feira' => $feira->id,
            'sell_number' => 'V1',
            'pagamento_id_api' => 201,
            'payment_way' => 'CARD',
            'tag_code' => 'T1000',
            'value' => '50.00',
        ]);

        // Venda 2: R$ 150.00, Hoje, Caixa 2
        VendaHeader::create([
            'id_feira' => $feira->id,
            'sell_number' => 'V2',
            'total_value' => '150.00',
            'date_hour' => '2026-08-05 11:00:00',
            'box' => 'Caixa 2',
            'sale_type' => 1,
            'processado' => true,
        ]);
        Pagamento::create([
            'id_feira' => $feira->id,
            'sell_number' => 'V2',
            'pagamento_id_api' => 202,
            'payment_way' => 'CARD',
            'tag_code' => 'T1000',
            'value' => '150.00',
        ]);

        // Testar filtro por valor (max_value=80)
        $response = $this->actingAs($user)->get(route('cartoes.show', [
            'cartao' => $cartao->id,
            'max_value' => '80.00',
        ]));
        $response->assertStatus(200);
        $vendas = $response->original->getData()['page']['props']['vendas']['data'];
        $this->assertCount(1, $vendas);
        $this->assertEquals('V1', $vendas[0]['sell_number']);
    }
}
