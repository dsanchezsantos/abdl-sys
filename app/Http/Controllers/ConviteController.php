<?php

namespace App\Http\Controllers;

use App\Models\Convite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Auth\Events\Registered;
use Inertia\Inertia;

class ConviteController extends Controller
{
    /**
     * Gera um novo convite.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255|unique:users,email',
        ], [
            'email.unique' => 'Este e-mail já possui uma conta ativa no sistema.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
        ]);

        // Cancela convites anteriores não utilizados do mesmo e-mail para manter limpo
        Convite::where('email', $request->email)
            ->whereNull('used_at')
            ->delete();

        $token = Str::random(40);

        $convite = Convite::create([
            'email' => $request->email,
            'token' => $token,
            'expires_at' => now()->addHours(6), // Expira em 6 horas conforme solicitado
        ]);

        return back()->with('success', [
            'email' => $convite->email,
            'link' => route('convite.show', ['token' => $token]),
        ]);
    }

    /**
     * Exibe o formulário de cadastro do convite.
     */
    public function showRegistrationForm($token)
    {
        $convite = Convite::where('token', $token)->first();

        if (!$convite || !$convite->isValid()) {
            return Inertia::render('Auth/InviteInvalid');
        }

        return Inertia::render('Auth/RegisterInvite', [
            'email' => $convite->email,
            'token' => $token,
        ]);
    }

    /**
     * Conclui o registro a partir de um convite.
     */
    public function register(Request $request, $token)
    {
        $convite = Convite::where('token', $token)->first();

        if (!$convite || !$convite->isValid()) {
            return redirect()->route('login')
                ->with('error', 'Este link de convite é inválido ou já expirou.');
        }

        // Sanitização de CPF antes da validação
        $cpfLimpo = preg_replace('/[^0-9]/', '', $request->cpf);
        $request->merge(['cpf' => $cpfLimpo]);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'cpf' => 'required|string|size:11|unique:users,cpf',
            'apelido' => 'required|string|max:255',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols()
            ],
        ], [
            'name.required' => 'O nome completo é obrigatório.',
            'email.unique' => 'Este e-mail já está cadastrado no sistema.',
            'cpf.unique' => 'Este CPF já está cadastrado no sistema.',
            'cpf.size' => 'O CPF deve conter exatamente 11 dígitos.',
            'apelido.required' => 'O apelido é obrigatório.',
            'password.required' => 'A senha é obrigatória.',
            'password.confirmed' => 'A confirmação de senha não confere.',
        ]);

        if ($request->email !== $convite->email) {
            return back()->withErrors(['email' => 'O e-mail digitado não corresponde ao e-mail convidado.']);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $convite->email,
            'cpf' => $cpfLimpo,
            'apelido' => $request->apelido,
            'password' => Hash::make($request->password),
        ]);

        // Marcar convite como utilizado
        $convite->update(['used_at' => now()]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
