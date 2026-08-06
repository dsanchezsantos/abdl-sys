<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Convite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConviteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_convite_generation_requires_authentication(): void
    {
        $response = $this->post(route('usuarios.convites.store'), [
            'email' => 'convidado@abdl.com'
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_generate_invite(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('usuarios.convites.store'), [
                'email' => 'convidado@abdl.com'
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('convites', [
            'email' => 'convidado@abdl.com',
            'used_at' => null
        ]);
    }

    public function test_valid_invite_token_shows_registration_form(): void
    {
        $convite = Convite::create([
            'email' => 'convidado@abdl.com',
            'token' => 'VALIDTOKEN123',
            'expires_at' => now()->addHours(6)
        ]);

        $response = $this->get(route('convite.show', $convite->token));

        $response->assertStatus(200);
        $response->assertSee('convidado@abdl.com');
    }

    public function test_expired_or_used_invite_returns_invalid_view(): void
    {
        $conviteExpirado = Convite::create([
            'email' => 'convidado1@abdl.com',
            'token' => 'EXPIREDTOKEN',
            'expires_at' => now()->subMinutes(1)
        ]);
 
        $responseExpirado = $this->get(route('convite.show', $conviteExpirado->token));
        $responseExpirado->assertStatus(200);
        $responseExpirado->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Auth/InviteInvalid'));
 
        $conviteUsado = Convite::create([
            'email' => 'convidado2@abdl.com',
            'token' => 'USEDTOKEN',
            'expires_at' => now()->addHours(6),
            'used_at' => now()
        ]);
 
        $responseUsado = $this->get(route('convite.show', $conviteUsado->token));
        $responseUsado->assertStatus(200);
        $responseUsado->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('Auth/InviteInvalid'));
    }

    public function test_registration_via_invite_creates_user_and_marks_used(): void
    {
        $convite = Convite::create([
            'email' => 'convidado@abdl.com',
            'token' => 'REGTOKEN',
            'expires_at' => now()->addHours(6)
        ]);

        $response = $this->post(route('convite.register', $convite->token), [
            'name' => 'Diogo Sanchez Santos',
            'email' => 'convidado@abdl.com',
            'cpf' => '123.456.789-00',
            'apelido' => 'Diogo Sanchez',
            'password' => 'Pass1234!',
            'password_confirmation' => 'Pass1234!'
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'convidado@abdl.com',
            'cpf' => '12345678900',
            'apelido' => 'Diogo Sanchez'
        ]);

        $this->assertNotNull($convite->fresh()->used_at);
        $this->assertTrue($convite->fresh()->isUsed());
    }

    public function test_registration_with_duplicate_cpf_is_blocked(): void
    {
        // Cria usuário existente
        User::factory()->create([
            'name' => 'Usuario Existente',
            'email' => 'existente@abdl.com',
            'cpf' => '12345678900',
            'apelido' => 'Existente'
        ]);

        $convite = Convite::create([
            'email' => 'novo@abdl.com',
            'token' => 'REGTOKEN2',
            'expires_at' => now()->addHours(6)
        ]);

        $response = $this->from(route('convite.show', $convite->token))
            ->post(route('convite.register', $convite->token), [
                'name' => 'Novo Usuario',
                'email' => 'novo@abdl.com',
                'cpf' => '123.456.789-00', // CPF Duplicado
                'apelido' => 'Novo Usu',
                'password' => 'Pass1234!',
                'password_confirmation' => 'Pass1234!'
            ]);

        $response->assertRedirect(route('convite.show', $convite->token));
        $response->assertSessionHasErrors('cpf');
    }

    public function test_forgot_password_identity_validation_resets_password(): void
    {
        $user = User::factory()->create([
            'name' => 'Diogo Sanchez Santos',
            'email' => 'diogo@abdl.com',
            'cpf' => '12345678900',
            'password' => Hash::make('Antiga123!')
        ]);

        // 1. Dados incorretos de CPF
        $responseErradaCpf = $this->post(route('password.email'), [
            'email' => 'diogo@abdl.com',
            'cpf' => '99999999999',
            'ultimo_sobrenome' => 'Santos',
            'password' => 'Nova1234!',
            'password_confirmation' => 'Nova1234!'
        ]);
        $responseErradaCpf->assertSessionHasErrors('cpf');

        // 2. Sobrenome incorreto
        $responseErradaSobrenome = $this->post(route('password.email'), [
            'email' => 'diogo@abdl.com',
            'cpf' => '123.456.789-00',
            'ultimo_sobrenome' => 'Silva', // Errado (correto é Santos)
            'password' => 'Nova1234!',
            'password_confirmation' => 'Nova1234!'
        ]);
        $responseErradaSobrenome->assertSessionHasErrors('ultimo_sobrenome');

        // 3. Sucesso (ignora maiúsculas e acentos)
        $responseSucesso = $this->post(route('password.email'), [
            'email' => 'diogo@abdl.com',
            'cpf' => '123.456.789-00',
            'ultimo_sobrenome' => 'sÁntos', // Normalização testa acento e caixa
            'password' => 'Nova1234!',
            'password_confirmation' => 'Nova1234!'
        ]);

        $responseSucesso->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('Nova1234!', $user->fresh()->password));
    }
}
