<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Exibe o formulário de redefinição de senha.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Valida os dados cadastrais e redefine a senha do usuário imediatamente.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Sanitização de CPF antes de validar
        $cpfLimpo = preg_replace('/[^0-9]/', '', $request->cpf);
        $request->merge(['cpf' => $cpfLimpo]);

        $request->validate([
            'email' => 'required|email',
            'cpf' => 'required|string|size:11',
            'ultimo_sobrenome' => 'required|string|max:255',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols()
            ],
        ], [
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'cpf.required' => 'O CPF é obrigatório.',
            'cpf.size' => 'O CPF deve conter exatamente 11 dígitos.',
            'ultimo_sobrenome.required' => 'O último sobrenome é obrigatório.',
            'password.required' => 'A nova senha é obrigatória.',
            'password.confirmed' => 'A confirmação de senha não confere.',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['O e-mail informado não está cadastrado no sistema.'],
            ]);
        }

        if ($user->cpf !== $cpfLimpo) {
            throw ValidationException::withMessages([
                'cpf' => ['O CPF informado não corresponde ao cadastrado para este e-mail.'],
            ]);
        }

        // Extrai o último sobrenome do banco
        $nameParts = array_filter(explode(' ', trim($user->name)));
        if (empty($nameParts)) {
            throw ValidationException::withMessages([
                'ultimo_sobrenome' => ['Não foi possível validar o sobrenome para este cadastro.'],
            ]);
        }

        $lastSurnameDb = end($nameParts);

        // Faz a comparação com normalização de acentos e caixa
        if (!$this->compareNormalizedStrings($lastSurnameDb, $request->ultimo_sobrenome)) {
            throw ValidationException::withMessages([
                'ultimo_sobrenome' => ['O último sobrenome informado está incorreto.'],
            ]);
        }

        // Redefine a senha diretamente
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('login')->with('status', 'Sua senha foi redefinida com sucesso!');
    }

    /**
     * Normaliza e compara duas strings.
     */
    private function compareNormalizedStrings(string $str1, string $str2): bool
    {
        return $this->normalize($str1) === $this->normalize($str2);
    }

    /**
     * Converte para minúsculas, remove acentos e caracteres não-alfanuméricos.
     */
    private function normalize(string $str): string
    {
        $str = mb_strtolower(trim($str), 'UTF-8');
        
        $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
        if ($transliterator) {
            $str = $transliterator->transliterate($str);
        }

        $unwanted_array = [
            'á'=>'a', 'à'=>'a', 'â'=>'a', 'ä'=>'a', 'ã'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
            'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e', 'í'=>'i', 'ì'=>'i', 'î'=>'i', 'ï'=>'i',
            'ñ'=>'n', 'ó'=>'o', 'ò'=>'o', 'ô'=>'o', 'ö'=>'o', 'õ'=>'o', 'ø'=>'o', 'œ'=>'o',
            'ú'=>'u', 'ù'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'ÿ'=>'y',
            'Á'=>'a', 'À'=>'a', 'Â'=>'a', 'Ä'=>'a', 'Ã'=>'a', 'Å'=>'a', 'Æ'=>'a', 'Ç'=>'c',
            'É'=>'e', 'È'=>'e', 'Ê'=>'e', 'Ë'=>'e', 'Í'=>'i', 'Ì'=>'i', 'Î'=>'i', 'ï'=>'i',
            'Ñ'=>'n', 'Ó'=>'o', 'Ò'=>'o', 'Ô'=>'o', 'Ö'=>'o', 'Õ'=>'o', 'Ø'=>'o', 'Œ'=>'o',
            'Ú'=>'u', 'Ù'=>'u', 'Û'=>'u', 'Ü'=>'u', 'Ý'=>'y', 'Ÿ'=>'y'
        ];
        $str = strtr($str, $unwanted_array);

        return preg_replace('/[^a-z0-9]/', '', $str);
    }
}
