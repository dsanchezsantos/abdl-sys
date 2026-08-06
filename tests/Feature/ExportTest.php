<?php
 
namespace Tests\Feature;
 
use App\Models\User;
use App\Models\Feira;
use App\Models\Livro;
use App\Models\Cartao;
use App\Models\VendaHeader;
use App\Models\Pagamento;
use App\Enums\CartaoClassificacao;
use App\Enums\FeiraStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
 
class ExportTest extends TestCase
{
    use RefreshDatabase;
 
    protected User $user;
    protected Feira $feira;
 
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->feira = Feira::create([
            'nome' => 'Feira de Teste',
            'evento_id_api' => '123',
            'user_id_api' => '456',
            'data_inicio' => '2026-08-01 00:00:00',
            'data_fim' => '2026-08-10 23:59:59',
            'status' => FeiraStatus::PLANEJADA,
        ]);
    }
 
    public function test_export_routes_require_authentication(): void
    {
        $this->get(route('feiras.export.livros', $this->feira->id))
            ->assertRedirect(route('login'));
 
        $this->get(route('feiras.export.cartoes', $this->feira->id))
            ->assertRedirect(route('login'));
 
        $this->get(route('feiras.export.vendas', $this->feira->id))
            ->assertRedirect(route('login'));
    }
 
    public function test_export_livros_works(): void
    {
        Livro::create([
            'id_feira' => $this->feira->id,
            'produto_id_api' => 101,
            'produto' => 'Livro de Teste 1',
            'valor' => 49.90,
            'editora' => 'Editora A',
            'representante' => 'Rep A',
            'categoria' => 'Infantil',
        ]);
 
        $response = $this->actingAs($this->user)
            ->get(route('feiras.export.livros', $this->feira->id));
 
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('content-disposition', 'attachment; filename=livros_feira_' . $this->feira->id . '_feira-de-teste.xlsx');
    }
 
    public function test_export_cartoes_works(): void
    {
        Cartao::create([
            'id_feira' => $this->feira->id,
            'tag_code' => 'TAG123',
            'grupo' => 'Escola Teste',
            'classificacao' => CartaoClassificacao::ALUNO,
            'identificacao_aluno' => 'Aluno Teste',
        ]);
 
        $response = $this->actingAs($this->user)
            ->get(route('feiras.export.cartoes', $this->feira->id));
 
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('content-disposition', 'attachment; filename=cartoes_feira_' . $this->feira->id . '_feira-de-teste.xlsx');
    }
 
    public function test_export_vendas_e_transacoes_works(): void
    {
        $venda = VendaHeader::create([
            'id_feira' => $this->feira->id,
            'sell_number' => 'VEND123',
            'sale_type' => 1,
            'total_value' => 250.00,
            'date_hour' => now(),
            'box' => 'Caixa 01',
            'processado' => true,
        ]);
 
        $cartao = Cartao::create([
            'id_feira' => $this->feira->id,
            'tag_code' => 'TAG123',
            'grupo' => 'Escola Teste',
            'classificacao' => CartaoClassificacao::ALUNO,
            'identificacao_aluno' => 'Aluno Teste',
        ]);
 
        Pagamento::create([
            'id_feira' => $this->feira->id,
            'sell_number' => $venda->sell_number,
            'pagamento_id_api' => 202,
            'tag_code' => $cartao->tag_code,
            'payment_way' => 'Cartão',
            'value' => 250.00,
            'payment_group' => 'Escola Teste',
        ]);
 
        $response = $this->actingAs($this->user)
            ->get(route('feiras.export.vendas', $this->feira->id));
 
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('content-disposition', 'attachment; filename=vendas_e_transacoes_feira_' . $this->feira->id . '_feira-de-teste.xlsx');
    }
}
