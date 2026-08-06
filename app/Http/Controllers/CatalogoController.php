<?php

namespace App\Http\Controllers;

use App\Models\Livro;
use App\Models\Feira;
use App\Models\EditoraRepresentante;
use App\Models\VendaHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CatalogoController extends Controller
{
    /**
     * Exibe a lista de livros com filtros e paginação no backend.
     */
    public function index(Request $request)
    {
        $query = Livro::query();

        // Filtro por termo (Nome ou ID)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('produto', 'ilike', '%' . $search . '%')
                  ->orWhere('produto_id_api', 'like', '%' . $search . '%');
            });
        }

        // Filtro por range de valor
        if ($request->filled('min_value')) {
            $query->where('valor', '>=', $request->input('min_value'));
        }

        if ($request->filled('max_value')) {
            $query->where('valor', '<=', $request->input('max_value'));
        }

        // Filtro por Editora
        if ($request->filled('editora')) {
            $query->whereIn('editora', (array) $request->input('editora'));
        }

        // Filtro por Representante
        if ($request->filled('representante')) {
            $query->whereIn('representante', (array) $request->input('representante'));
        }

        // Filtro por Feira
        if ($request->filled('feira_id')) {
            $query->whereIn('id_feira', (array) $request->input('feira_id'));
        }

        $livros = $query->orderBy('produto')->paginate(50)->withQueryString();

        // Listas dinâmicas para preencher os filtros
        $editoras = Livro::whereNotNull('editora')
            ->where('editora', '!=', '')
            ->pluck('editora')
            ->concat(EditoraRepresentante::pluck('editora'))
            ->unique()
            ->sort()
            ->values();

        $representantes = Livro::whereNotNull('representante')
            ->where('representante', '!=', '')
            ->pluck('representante')
            ->concat(EditoraRepresentante::pluck('representante'))
            ->unique()
            ->sort()
            ->values();

        $feiras = Feira::orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Catalogo/Index', [
            'livros' => $livros,
            'filters' => [
                'search' => $request->input('search', ''),
                'min_value' => $request->input('min_value', ''),
                'max_value' => $request->input('max_value', ''),
                'editora' => (array) $request->input('editora', []),
                'representante' => (array) $request->input('representante', []),
                'feira_id' => (array) $request->input('feira_id', []),
            ],
            'editoras' => $editoras,
            'representantes' => $representantes,
            'feiras' => $feiras,
        ]);
    }

    /**
     * Exibe a tela de detalhes de um livro específico com estatísticas de venda e histórico de vendas paginado.
     */
    public function show(Request $request, Livro $livro)
    {
        // Estatísticas de vendas agrupadas por feira
        $estatisticasFeiras = DB::table('itens_venda as iv')
            ->join('feiras as f', 'iv.id_feira', '=', 'f.id')
            ->where('iv.produto_id_api', $livro->produto_id_api)
            ->selectRaw('
                f.id as feira_id,
                f.nome as feira_nome,
                SUM(iv.amount)::int as total_vendido,
                SUM(iv.total_value)::numeric as faturamento
            ')
            ->groupBy('f.id', 'f.nome')
            ->get();

        // Consulta de vendas que contêm este livro
        $vendasQuery = VendaHeader::whereExists(function ($query) use ($livro) {
            $query->select(DB::raw(1))
                ->from('itens_venda')
                ->whereColumn('itens_venda.sell_number', 'venda_headers.sell_number')
                ->whereColumn('itens_venda.id_feira', 'venda_headers.id_feira')
                ->where('itens_venda.produto_id_api', $livro->produto_id_api);
        });

        // Filtrar por feira, se selecionado
        $selectedFeiraId = $request->input('feira_id');
        if ($request->filled('feira_id')) {
            $vendasQuery->where('id_feira', $selectedFeiraId);
        }

        // Filtro de range de valor (min_value, max_value)
        if ($request->filled('min_value')) {
            $vendasQuery->where('total_value', '>=', $request->input('min_value'));
        }
        if ($request->filled('max_value')) {
            $vendasQuery->where('total_value', '<=', $request->input('max_value'));
        }

        // Filtro de range de data (start_date, end_date)
        if ($request->filled('start_date')) {
            $vendasQuery->where('date_hour', '>=', $request->input('start_date') . ' 00:00:00');
        }
        if ($request->filled('end_date')) {
            $vendasQuery->where('date_hour', '<=', $request->input('end_date') . ' 23:59:59');
        }

        // Filtro de método de pagamento (sale_type)
        if ($request->filled('sale_type')) {
            $vendasQuery->where('sale_type', $request->input('sale_type'));
        }

        // Filtro de caixa (box)
        if ($request->filled('box')) {
            $vendasQuery->where('box', $request->input('box'));
        }

        // Filtro de range de quantidade de itens
        if ($request->filled('min_items')) {
            $vendasQuery->whereRaw('(select sum(amount) from itens_venda where itens_venda.sell_number = venda_headers.sell_number and itens_venda.id_feira = venda_headers.id_feira) >= ?', [$request->input('min_items')]);
        }
        if ($request->filled('max_items')) {
            $vendasQuery->whereRaw('(select sum(amount) from itens_venda where itens_venda.sell_number = venda_headers.sell_number and itens_venda.id_feira = venda_headers.id_feira) <= ?', [$request->input('max_items')]);
        }

        // Eager load relations de itens e pagamentos (limitados por feira para segurança)
        $vendas = $vendasQuery->with([
            'itensVenda' => fn($q) => $request->filled('feira_id') ? $q->where('id_feira', $selectedFeiraId) : $q,
            'pagamentos' => fn($q) => $request->filled('feira_id') ? $q->where('id_feira', $selectedFeiraId) : $q
        ])->latest('date_hour')->paginate(10)->withQueryString();

        // Listar feiras que possuem registro de venda deste livro (para o filtro select)
        $feiraIdsComVendas = DB::table('itens_venda')
            ->where('produto_id_api', $livro->produto_id_api)
            ->distinct()
            ->pluck('id_feira');

        $feirasFiltro = Feira::whereIn('id', $feiraIdsComVendas)->orderBy('nome')->get(['id', 'nome']);

        // Obter caixas/PDVs únicos que registraram vendas deste livro
        $boxesQuery = DB::table('venda_headers')
            ->whereExists(function ($query) use ($livro) {
                $query->select(DB::raw(1))
                    ->from('itens_venda')
                    ->whereColumn('itens_venda.sell_number', 'venda_headers.sell_number')
                    ->whereColumn('itens_venda.id_feira', 'venda_headers.id_feira')
                    ->where('itens_venda.produto_id_api', $livro->produto_id_api);
            });
        if ($request->filled('feira_id')) {
            $boxesQuery->where('id_feira', $selectedFeiraId);
        }
        $boxes = $boxesQuery->whereNotNull('box')
            ->where('box', '!=', '')
            ->distinct()
            ->orderBy('box')
            ->pluck('box');

        // Opções de editoras e representantes da feira do livro
        $editorasRepresentantes = EditoraRepresentante::where('id_feira', $livro->id_feira)
            ->orderBy('editora')
            ->get(['id', 'editora', 'representante']);

        return Inertia::render('Catalogo/Show', [
            'livro' => $livro,
            'estatisticas_feiras' => $estatisticasFeiras,
            'vendas' => $vendas,
            'feiras' => $feirasFiltro,
            'filters' => $request->only(['feira_id', 'min_value', 'max_value', 'start_date', 'end_date', 'sale_type', 'min_items', 'max_items', 'box']),
            'editoras_representantes' => $editorasRepresentantes,
            'boxes' => $boxes,
        ]);
    }

    /**
     * Atualiza as informações de Editora de um livro específico, resolvendo o representante correspondente.
     */
    public function update(Request $request, Livro $livro)
    {
        $validated = $request->validate([
            'editora' => 'required|string|max:255',
        ], [
            'editora.required' => 'A editora é obrigatória.',
        ]);

        $representante = EditoraRepresentante::where('id_feira', $livro->id_feira)
            ->where('editora', $validated['editora'])
            ->value('representante') ?? '';

        $livro->update([
            'editora' => $validated['editora'],
            'representante' => $representante,
        ]);

        return back()->with('success', 'Livro atualizado com sucesso!');
    }

    /**
     * Atualiza as informações de Editora de vários livros em lote, resolvendo o representante correspondente de cada feira.
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:livros,id',
            'editora' => 'required|string|max:255',
        ], [
            'ids.required' => 'Selecione pelo menos um livro.',
            'editora.required' => 'A editora é obrigatória.',
        ]);

        $livros = Livro::whereIn('id', $validated['ids'])->get();
        $feirasIds = $livros->pluck('id_feira')->unique();

        $representantesMap = EditoraRepresentante::whereIn('id_feira', $feirasIds)
            ->where('editora', $validated['editora'])
            ->pluck('representante', 'id_feira');

        foreach ($livros as $livro) {
            $rep = $representantesMap[$livro->id_feira] ?? '';
            $livro->update([
                'editora' => $validated['editora'],
                'representante' => $rep,
            ]);
        }

        return back()->with('success', count($validated['ids']) . ' livros atualizados com sucesso!');
    }
}
