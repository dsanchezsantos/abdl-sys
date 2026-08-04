<?php

namespace App\Http\Controllers;

use App\Models\Livro;
use App\Models\Feira;
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

        // Filtro por Categoria
        if ($request->filled('categoria')) {
            $query->whereIn('categoria', (array) $request->input('categoria'));
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
        $categorias = Livro::whereNotNull('categoria')
            ->where('categoria', '!=', '')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        $editoras = Livro::whereNotNull('editora')
            ->where('editora', '!=', '')
            ->distinct()
            ->orderBy('editora')
            ->pluck('editora');

        $representantes = Livro::whereNotNull('representante')
            ->where('representante', '!=', '')
            ->distinct()
            ->orderBy('representante')
            ->pluck('representante');

        $feiras = Feira::orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Catalogo/Index', [
            'livros' => $livros,
            'filters' => [
                'search' => $request->input('search', ''),
                'min_value' => $request->input('min_value', ''),
                'max_value' => $request->input('max_value', ''),
                'categoria' => (array) $request->input('categoria', []),
                'editora' => (array) $request->input('editora', []),
                'representante' => (array) $request->input('representante', []),
                'feira_id' => (array) $request->input('feira_id', []),
            ],
            'categorias' => $categorias,
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

        return Inertia::render('Catalogo/Show', [
            'livro' => $livro,
            'estatisticas_feiras' => $estatisticasFeiras,
            'vendas' => $vendas,
            'feiras' => $feirasFiltro,
            'filters' => $request->only(['feira_id']),
        ]);
    }
}
