<?php

namespace App\Http\Controllers;

use App\Models\Feira;
use App\Models\EditoraRepresentante;
use App\Http\Requests\StoreFeiraRequest;
use App\Enums\FeiraStatus;
use App\Jobs\SincronizarFeiraMaestroJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Inertia\Inertia;

class FeiraController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render("Dashboard", [
            "feiras" => Feira::with("estatistica")->latest()->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFeiraRequest $request)
    {
        $validated = $request->validated();
        
        // Injeção de Regra de Negócio: Status Padrão PLANEJADA
        $validated["status"] = FeiraStatus::PLANEJADA;

        Feira::create($validated);

        return redirect()->route("dashboard")->with("success", "Feira cadastrada com sucesso!");
    }

    /**
     * Display the specified resource.
     */
    public function show(Feira $feira)
    {
        return Inertia::render("Feiras/Auditoria", [
            "feira" => $feira->only('id', 'nome', 'is_sincronizando', 'data_inicio', 'data_fim', 'status', 'status_integridade'),
            "estatisticas" => Inertia::lazy(fn () => $feira->estatistica),
            "ultimas_vendas" => Inertia::lazy(fn () => $feira->vendas()->with([
                'itensVenda' => fn($q) => $q->where('id_feira', $feira->id),
                'pagamentos' => fn($q) => $q->where('id_feira', $feira->id)
            ])->latest('date_hour')->limit(10)->get()),
            "editoras_representantes" => $feira->editorasRepresentantes()->orderBy('editora')->get(),
            "representantes_unicos" => $feira->editorasRepresentantes()->distinct()->orderBy('representante')->pluck('representante'),
        ]);
    }

    public function storeEditoraRepresentante(Request $request, Feira $feira)
    {
        $validated = $request->validate([
            'editora' => 'required|string|max:255',
            'representante' => 'required|string|max:255',
        ], [
            'editora.required' => 'A editora é obrigatória.',
            'representante.required' => 'O representante é obrigatório.',
        ]);

        $feira->editorasRepresentantes()->updateOrCreate(
            ['editora' => trim($validated['editora'])],
            ['representante' => trim($validated['representante'])]
        );

        return back()->with('success', 'Editora e Representante associados com sucesso!');
    }

    public function destroyEditoraRepresentante(Feira $feira, $id)
    {
        $feira->editorasRepresentantes()->findOrFail($id)->delete();
        return back()->with('success', 'Associação removida com sucesso!');
    }

    public function importEditorasRepresentantes(Request $request, Feira $feira)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ], [
            'file.required' => 'O arquivo é obrigatório.',
            'file.mimes' => 'O arquivo deve ser do tipo .xlsx, .xls ou .csv.',
        ]);

        $file = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'file' => ['Não foi possível ler o arquivo. Certifique-se de que é um formato válido (.csv ou .xlsx).'],
            ]);
        }

        if (empty($rows)) {
            throw ValidationException::withMessages([
                'file' => ['O arquivo está vazio.'],
            ]);
        }

        // Ler cabeçalho
        $header = array_map(function($h) {
            return trim(strtolower($h));
        }, $rows[0] ?? []);

        $editoraIdx = array_search('editoras', $header);
        if ($editoraIdx === false) {
            $editoraIdx = array_search('editora', $header);
        }

        $representanteIdx = array_search('representante', $header);
        if ($representanteIdx === false) {
            $representanteIdx = array_search('representantes', $header);
        }

        if ($editoraIdx === false || $representanteIdx === false) {
            throw ValidationException::withMessages([
                'file' => ['As colunas do arquivo devem conter obrigatoriamente os cabeçalhos: "editoras" e "representante".'],
            ]);
        }

        $count = 0;
        foreach (array_slice($rows, 1) as $row) {
            $editora = trim($row[$editoraIdx] ?? '');
            $representante = trim($row[$representanteIdx] ?? '');

            if ($editora !== '' && $representante !== '') {
                $feira->editorasRepresentantes()->updateOrCreate(
                    ['editora' => $editora],
                    ['representante' => $representante]
                );
                $count++;
            }
        }

        return back()->with('success', "$count registros de Editora e Representante importados com sucesso!");
    }

    /**
     * Exibe a lista de vendas da feira com paginação, filtros e relações (itens e pagamentos).
     */
    public function vendas(Request $request, Feira $feira)
    {
        $query = $feira->vendas()->with([
            'itensVenda' => fn($q) => $q->where('id_feira', $feira->id),
            'pagamentos' => fn($q) => $q->where('id_feira', $feira->id)
        ])->latest('date_hour');

        // Aplicar filtros
        if ($request->filled('search')) {
            $query->where('sell_number', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->filled('sale_type')) {
            $query->where('sale_type', $request->input('sale_type'));
        }

        if ($request->filled('box')) {
            $query->where('box', $request->input('box'));
        }

        if ($request->filled('min_value')) {
            $query->where('total_value', '>=', $request->input('min_value'));
        }

        if ($request->filled('max_value')) {
            $query->where('total_value', '<=', $request->input('max_value'));
        }

        if ($request->filled('start_date')) {
            $query->where('date_hour', '>=', $request->input('start_date') . ' 00:00:00');
        }

        if ($request->filled('end_date')) {
            $query->where('date_hour', '<=', $request->input('end_date') . ' 23:59:59');
        }

        $vendas = $query->paginate(100)->withQueryString();

        // Obter caixas únicos para o filtro select
        $boxes = $feira->vendas()
            ->whereNotNull('box')
            ->where('box', '!=', '')
            ->distinct()
            ->orderBy('box')
            ->pluck('box');

        return Inertia::render("Feiras/Vendas", [
            "feira" => $feira->only('id', 'nome', 'is_sincronizando', 'status', 'status_integridade'),
            "vendas" => $vendas,
            "filters" => $request->only(['search', 'sale_type', 'box', 'min_value', 'max_value', 'start_date', 'end_date']),
            "boxes" => $boxes,
        ]);
    }

    /**
     * Sincroniza os dados da feira com a API Nowigo.
     */
    public function sync(Feira $feira)
    {
        // Bloqueio Pessimista para evitar disparos duplos
        $feira = Feira::where('id', $feira->id)->lockForUpdate()->first();

        if ($feira->is_sincronizando) {
            return back()->with('error', 'Uma sincronização já está em andamento para esta feira.');
        }

        $feira->update(['is_sincronizando' => true, 'status_integridade' => 'INTEGRO']);

        SincronizarFeiraMaestroJob::dispatch($feira->id, auth()->id());

        return back()->with('success', 'Sincronização iniciada com sucesso.');
    }

    public function retrySync(Feira $feira)
    {
        if (!$feira->ultimo_batch_id) {
            return back()->with('error', 'Não há um lote anterior para repescar.');
        }

        $batch = Bus::findBatch($feira->ultimo_batch_id);

        if (!$batch || $batch->finished()) {
            // Se o lote já terminou e queremos repescar as falhas, o retry() do Laravel funciona
            // Mas se o lote sumiu do Redis (pruning), precisamos tratar.
            if (!$batch) {
                return back()->with('error', 'O lote original expirou. Por favor, inicie uma nova sincronização total.');
            }
        }

        $feira->update(['is_sincronizando' => true]);
        
        \Illuminate\Support\Facades\Artisan::call('queue:retry-batch', [
            'id' => [$feira->ultimo_batch_id]
        ]);

        return back()->with('success', 'Repescagem de dados iniciada.');
    }
}
