<?php

namespace Tests\Feature;

use App\Models\Feira;
use App\Models\Livro;
use App\Models\EditoraRepresentante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CatalogoEditoraRepresentanteTest extends TestCase
{
    use RefreshDatabase;

    public function test_remove_categoria_column_is_correct()
    {
        $this->assertFalse(\Schema::hasColumn('livros', 'categoria'));
    }

    public function test_pode_adicionar_editora_representante_manualmente()
    {
        $user = User::factory()->create();
        $feira = Feira::create([
            'nome' => 'Feira Teste',
            'data_inicio' => now(),
            'data_fim' => now()->addDays(5),
            'status' => \App\Enums\FeiraStatus::PLANEJADA,
            'evento_id_api' => 123,
            'user_id_api' => 456,
        ]);

        $response = $this->actingAs($user)->post(route('feiras.editoras.store', $feira->id), [
            'editora' => 'Editora Teste',
            'representante' => 'Representante Teste',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('editora_representantes', [
            'id_feira' => $feira->id,
            'editora' => 'Editora Teste',
            'representante' => 'Representante Teste',
        ]);
    }

    public function test_adicionar_editora_existente_atualiza_representante()
    {
        $user = User::factory()->create();
        $feira = Feira::create([
            'nome' => 'Feira Teste',
            'data_inicio' => now(),
            'data_fim' => now()->addDays(5),
            'status' => \App\Enums\FeiraStatus::PLANEJADA,
            'evento_id_api' => 123,
            'user_id_api' => 456,
        ]);

        EditoraRepresentante::create([
            'id_feira' => $feira->id,
            'editora' => 'Editora Teste',
            'representante' => 'Representante Antigo',
        ]);

        // Segunda tentativa com mesmo ID de feira e Editora deve atualizar o representante
        $response = $this->actingAs($user)->post(route('feiras.editoras.store', $feira->id), [
            'editora' => 'Editora Teste',
            'representante' => 'Representante Novo',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('editora_representantes', [
            'id_feira' => $feira->id,
            'editora' => 'Editora Teste',
            'representante' => 'Representante Novo',
        ]);
        $this->assertDatabaseCount('editora_representantes', 1);
    }

    public function test_pode_excluir_editora_representante()
    {
        $user = User::factory()->create();
        $feira = Feira::create([
            'nome' => 'Feira Teste',
            'data_inicio' => now(),
            'data_fim' => now()->addDays(5),
            'status' => \App\Enums\FeiraStatus::PLANEJADA,
            'evento_id_api' => 123,
            'user_id_api' => 456,
        ]);

        $er = EditoraRepresentante::create([
            'id_feira' => $feira->id,
            'editora' => 'Editora Teste',
            'representante' => 'Representante Teste',
        ]);

        $response = $this->actingAs($user)->delete(route('feiras.editoras.destroy', ['feira' => $feira->id, 'id' => $er->id]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('editora_representantes', [
            'id' => $er->id,
        ]);
    }

    public function test_atualizar_editora_do_livro_resolve_representante_automaticamente()
    {
        $user = User::factory()->create();
        $feira = Feira::create([
            'nome' => 'Feira Teste',
            'data_inicio' => now(),
            'data_fim' => now()->addDays(5),
            'status' => \App\Enums\FeiraStatus::PLANEJADA,
            'evento_id_api' => 123,
            'user_id_api' => 456,
        ]);

        EditoraRepresentante::create([
            'id_feira' => $feira->id,
            'editora' => 'Editora Resolvida',
            'representante' => 'Rep Resolvido',
        ]);

        $livro = Livro::create([
            'id_feira' => $feira->id,
            'produto_id_api' => 12345,
            'produto' => 'Livro Teste',
            'valor' => '50.00',
            'editora' => 'Editora Antiga',
            'representante' => 'Rep Antigo',
        ]);

        // Atualização passa APENAS a editora
        $response = $this->actingAs($user)->patch(route('catalogo.livros.update', $livro->id), [
            'editora' => 'Editora Resolvida',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('livros', [
            'id' => $livro->id,
            'editora' => 'Editora Resolvida',
            'representante' => 'Rep Resolvido', // Resolvido automaticamente do banco
        ]);
    }

    public function test_atualizar_editora_em_massa_resolve_representante_automaticamente()
    {
        $user = User::factory()->create();
        $feira = Feira::create([
            'nome' => 'Feira Teste',
            'data_inicio' => now(),
            'data_fim' => now()->addDays(5),
            'status' => \App\Enums\FeiraStatus::PLANEJADA,
            'evento_id_api' => 123,
            'user_id_api' => 456,
        ]);

        EditoraRepresentante::create([
            'id_feira' => $feira->id,
            'editora' => 'Editora Lote',
            'representante' => 'Rep Lote',
        ]);
        
        $livro1 = Livro::create([
            'id_feira' => $feira->id,
            'produto_id_api' => 12345,
            'produto' => 'Livro 1',
            'valor' => '50.00',
            'editora' => 'Editora Antiga',
            'representante' => 'Rep Antigo',
        ]);

        $livro2 = Livro::create([
            'id_feira' => $feira->id,
            'produto_id_api' => 12346,
            'produto' => 'Livro 2',
            'valor' => '60.00',
            'editora' => 'Editora Antiga',
            'representante' => 'Rep Antigo',
        ]);

        // Envia apenas ids e editora
        $response = $this->actingAs($user)->patch(route('catalogo.livros.bulk'), [
            'ids' => [$livro1->id, $livro2->id],
            'editora' => 'Editora Lote',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('livros', [
            'id' => $livro1->id,
            'editora' => 'Editora Lote',
            'representante' => 'Rep Lote', // Resolvido automaticamente
        ]);
        $this->assertDatabaseHas('livros', [
            'id' => $livro2->id,
            'editora' => 'Editora Lote',
            'representante' => 'Rep Lote', // Resolvido automaticamente
        ]);
    }

    public function test_pode_importar_arquivo_csv_editora_representante()
    {
        $user = User::factory()->create();
        $feira = Feira::create([
            'nome' => 'Feira Teste',
            'data_inicio' => now(),
            'data_fim' => now()->addDays(5),
            'status' => \App\Enums\FeiraStatus::PLANEJADA,
            'evento_id_api' => 123,
            'user_id_api' => 456,
        ]);

        // Criar conteúdo do CSV em memória com colunas válidas
        $csvContent = "editoras,representante\nEditora Importada 1,Representante Importado 1\nEditora Importada 2,Representante Importado 2\n";
        
        $file = UploadedFile::fake()->createWithContent('import.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('feiras.editoras.import', $feira->id), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('editora_representantes', [
            'id_feira' => $feira->id,
            'editora' => 'Editora Importada 1',
            'representante' => 'Representante Importado 1',
        ]);
        $this->assertDatabaseHas('editora_representantes', [
            'id_feira' => $feira->id,
            'editora' => 'Editora Importada 2',
            'representante' => 'Representante Importado 2',
        ]);
    }
}
