<?php

namespace App\Http\Controllers;

use App\Models\Feira;
use App\Models\EditoraRepresentante;
use App\Http\Requests\StoreFeiraRequest;
use App\Enums\FeiraStatus;
use App\Jobs\SincronizarFeiraMaestroJob;
use App\Jobs\ProcessarPaginaVendaJob;
use App\Jobs\CalcularEstatisticasFeiraJob;
use Illuminate\Bus\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        if (count($rows) <= 1) {
            throw ValidationException::withMessages([
                'file' => ['O arquivo não possui registros de dados além do cabeçalho.'],
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
                'file' => ['As colunas do arquivo devem conter obrigatoriamente os cabeçalhos: "editora" e "representante".'],
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

        if ($count === 0) {
            throw ValidationException::withMessages([
                'file' => ['Nenhum registro válido foi encontrado para importação. Verifique se o arquivo segue o modelo esperado.'],
            ]);
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
        // 1. Buscar páginas com falha registrada
        $paginasFalhadas = DB::table('erros_integracao')
            ->where('id_feira', $feira->id)
            ->where('status', 'PENDENTE')
            ->distinct()
            ->pluck('pagina')
            ->filter(fn ($p) => $p > 0) // Ignora erro 0 (Maestro)
            ->values();

        // 2. Detectar páginas "órfãs" — jobs que nunca executaram (ficaram pendentes no Redis)
        $paginasOrfas = collect();
        $perPage = 100;

        try {
            $nowigo = new \App\Services\NowigoService($feira);
            $probe = $nowigo->buscarPagina(1, $perPage);
            $totalPages = (int) ($probe['pagination']['totalPages'] ?? 0);

            if ($totalPages > 0) {
                $limit = config('services.nowigo.limit_pages');
                if ($limit && $totalPages > $limit) {
                    $totalPages = $limit;
                }

                $todasPaginas = collect(range(1, $totalPages));

                // Páginas que têm QUALQUER registro em erros_integracao (já sabemos que executaram)
                $paginasComErro = DB::table('erros_integracao')
                    ->where('id_feira', $feira->id)
                    ->where('pagina', '>', 0)
                    ->distinct()
                    ->pluck('pagina');

                // Candidatas: páginas sem erro registrado (podem ter rodado com sucesso, ou nunca rodaram)
                $paginasCandidatas = $todasPaginas->diff($paginasComErro);

                // Heurística: comparar total de vendas processadas vs. o esperado pelas páginas "limpas"
                // Se há déficit significativo (>10%), reprocessar todas as candidatas.
                // É seguro porque o ProcessarPaginaVendaJob usa upsert/updateOrCreate (idempotente).
                $totalVendasProcessadas = \App\Models\VendaHeader::where('id_feira', $feira->id)
                    ->where('processado', true)
                    ->count();

                $vendasEsperadas = $paginasCandidatas->count() * $perPage;

                if ($paginasCandidatas->isNotEmpty() && $totalVendasProcessadas < $vendasEsperadas * 0.9) {
                    $paginasOrfas = $paginasCandidatas;
                    Log::info("Repescagem Feira #{$feira->id}: {$paginasOrfas->count()} páginas órfãs detectadas (vendas: {$totalVendasProcessadas}, esperado: ~{$vendasEsperadas})");
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Repescagem Feira #{$feira->id}: Probe da API falhou, usando apenas erros registrados. Erro: " . $e->getMessage());
        }

        // 3. Unir páginas falhadas + órfãs, sem duplicatas
        $paginasParaReprocessar = $paginasFalhadas
            ->merge($paginasOrfas)
            ->unique()
            ->sort()
            ->values();

        if ($paginasParaReprocessar->isEmpty()) {
            return back()->with('error', 'Não há páginas pendentes para repescagem.');
        }

        // 4. Bloqueio Pessimista
        $feira = Feira::where('id', $feira->id)->lockForUpdate()->first();

        if ($feira->is_sincronizando) {
            return back()->with('error', 'Uma sincronização já está em andamento.');
        }

        $feira->update(['is_sincronizando' => true]);

        // 5. Marcar os erros antigos como em retentativa
        DB::table('erros_integracao')
            ->where('id_feira', $feira->id)
            ->where('status', 'PENDENTE')
            ->update(['status' => 'RETENTANDO', 'updated_at' => now()]);

        $usuarioId = auth()->id();

        // 6. Criar novo batch com callbacks corretos
        $jobs = $paginasParaReprocessar->map(
            fn ($page, $index) => (new ProcessarPaginaVendaJob($feira->id, $page, $perPage))
                ->delay(now()->addSeconds($index * 5))
        )->toArray();

        $totalFalhadas = $paginasFalhadas->count();
        $totalOrfas = $paginasOrfas->unique()->diff($paginasFalhadas)->count();

        $batch = Bus::batch($jobs)
            ->onQueue('sync-nowigo')
            ->name("Repescagem Feira #{$feira->id}: {$feira->nome}")
            ->allowFailures()
            ->then(function (Batch $batch) use ($feira, $usuarioId) {
                // Sucesso: Recalcular estatísticas
                CalcularEstatisticasFeiraJob::dispatch($feira->id);

                // Limpar erros resolvidos
                DB::table('erros_integracao')
                    ->where('id_feira', $feira->id)
                    ->where('status', 'RETENTANDO')
                    ->update(['status' => 'RESOLVIDO', 'updated_at' => now()]);

                if ($usuarioId) {
                    $usuario = \App\Models\User::find($usuarioId);
                    $usuario?->notify(new \App\Notifications\SincronizacaoFeiraNotification($feira, 'sucesso'));
                }
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($feira) {
                Log::error("ERRO na repescagem da Feira #{$feira->id}: " . $e->getMessage());
            })
            ->finally(function (Batch $batch) use ($feira) {
                $statusIntegridade = $batch->hasFailures() ? 'FALHA_PARCIAL' : 'INTEGRO';

                $feira->update([
                    'is_sincronizando' => false,
                    'status_integridade' => $statusIntegridade,
                ]);
            })
            ->dispatch();

        $feira->update(['ultimo_batch_id' => $batch->id]);

        $msg = "Repescagem iniciada: {$paginasParaReprocessar->count()} páginas";
        if ($totalFalhadas > 0) $msg .= " ({$totalFalhadas} falhadas";
        if ($totalOrfas > 0) $msg .= ($totalFalhadas > 0 ? " + " : "(") . "{$totalOrfas} órfãs";
        $msg .= ").";

        return back()->with('success', $msg);
    }
}
