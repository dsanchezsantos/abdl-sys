<?php

namespace App\Http\Controllers;

use App\Models\Cartao;
use App\Models\Feira;
use App\Models\VendaHeader;
use App\Enums\CartaoClassificacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CartoesController extends Controller
{
    /**
     * Exibe a lista de cartões cadastrados com paginação (limitada a 50) e filtros.
     */
    public function index(Request $request)
    {
        $query = Cartao::query()
            ->where('grupo', '!=', 'PAGAMENTO SEM GRUPO')
            ->where('classificacao', '!=', CartaoClassificacao::TESTE->value);

        // Filtro por termo (Tag ou Identificação do Aluno)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('tag_code', 'ilike', '%' . $search . '%')
                  ->orWhere('identificacao_aluno', 'ilike', '%' . $search . '%');
            });
        }

        // Filtro por classificação do cartão
        if ($request->filled('classificacao')) {
            $query->whereIn('classificacao', (array) $request->input('classificacao'));
        }

        // Filtro por feira
        if ($request->filled('feira_id')) {
            $query->whereIn('id_feira', (array) $request->input('feira_id'));
        }

        // Filtro por grupo/escola
        if ($request->filled('grupo')) {
            $query->whereIn('grupo', (array) $request->input('grupo'));
        }

        // Paginação de 50 cartões por página
        $cartoes = $query->with('feira')->orderBy('tag_code')->paginate(50)->withQueryString();

        // Listas dinâmicas para filtros
        $grupos = Cartao::whereNotNull('grupo')
            ->where('grupo', '!=', '')
            ->where('grupo', '!=', 'PAGAMENTO SEM GRUPO')
            ->where('classificacao', '!=', CartaoClassificacao::TESTE->value)
            ->distinct()
            ->orderBy('grupo')
            ->pluck('grupo');

        $classificacoes = collect(CartaoClassificacao::cases())
            ->filter(fn($enum) => $enum !== CartaoClassificacao::TESTE)
            ->map(fn($enum) => [
                'value' => $enum->value,
                'label' => $enum->label()
            ])
            ->values()
            ->toArray();

        $feiras = Feira::orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Cartoes/Index', [
            'cartoes' => $cartoes,
            'filters' => [
                'search' => $request->input('search', ''),
                'classificacao' => (array) $request->input('classificacao', []),
                'feira_id' => (array) $request->input('feira_id', []),
                'grupo' => (array) $request->input('grupo', []),
            ],
            'grupos' => $grupos,
            'classificacoes' => $classificacoes,
            'feiras' => $feiras,
        ]);
    }

    /**
     * Exibe os detalhes de um cartão, faturamento consumido e histórico de vendas.
     */
    public function show(Request $request, Cartao $cartao)
    {
        // Bloquear acesso a cartões do grupo PAGAMENTO SEM GRUPO ou classificação TESTE
        if ($cartao->grupo === 'PAGAMENTO SEM GRUPO' || $cartao->classificacao === CartaoClassificacao::TESTE) {
            abort(404);
        }

        $cartao->load('feira');

        // Estatísticas do cartão específico
        $faturamentoTotal = DB::table('pagamentos')
            ->where('tag_code', $cartao->tag_code)
            ->where('id_feira', $cartao->id_feira)
            ->sum('value');

        $totalTransacoes = DB::table('pagamentos')
            ->where('tag_code', $cartao->tag_code)
            ->where('id_feira', $cartao->id_feira)
            ->count();

        // Lista de vendas das quais este cartão fez parte
        $vendasQuery = VendaHeader::whereExists(function ($query) use ($cartao) {
            $query->select(DB::raw(1))
                ->from('pagamentos')
                ->whereColumn('pagamentos.sell_number', 'venda_headers.sell_number')
                ->whereColumn('pagamentos.id_feira', 'venda_headers.id_feira')
                ->where('pagamentos.tag_code', $cartao->tag_code)
                ->where('pagamentos.id_feira', $cartao->id_feira);
        });

        // Eager load relations de itens e pagamentos
        $vendas = $vendasQuery->with(['itensVenda', 'pagamentos'])
            ->latest('date_hour')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Cartoes/Show', [
            'cartao' => $cartao,
            'estatisticas' => [
                'faturamento_total' => $faturamentoTotal ?: 0,
                'total_transacoes' => $totalTransacoes ?: 0,
            ],
            'vendas' => $vendas,
        ]);
    }
}
