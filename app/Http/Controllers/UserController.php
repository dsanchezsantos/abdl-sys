<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Convite;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Exibe a listagem de usuários e convites.
     */
    public function index()
    {
        $users = User::orderBy('name')
            ->paginate(10, ['*'], 'users_page')
            ->withQueryString();

        $convites = Convite::orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'invites_page')
            ->through(function ($convite) {
                return [
                    'id' => $convite->id,
                    'email' => $convite->email,
                    'token' => $convite->token,
                    'expires_at' => $convite->expires_at->toDateTimeString(),
                    'used_at' => $convite->used_at ? $convite->used_at->toDateTimeString() : null,
                    'status' => $convite->isUsed() 
                        ? 'usado' 
                        : ($convite->isExpired() ? 'expirado' : 'ativo'),
                    'link' => route('convite.show', ['token' => $convite->token]),
                ];
            })
            ->withQueryString();

        return Inertia::render('Usuarios/Index', [
            'users' => $users,
            'convites' => $convites,
        ]);
    }

    /**
     * Remove um usuário do sistema.
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'Você não pode excluir a sua própria conta.']);
        }

        $user->delete();

        return back();
    }
}
