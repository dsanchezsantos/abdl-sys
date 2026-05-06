<?php

namespace App\Http\Controllers;

use App\Models\Relatorio;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RelatoriosController extends Controller
{
    public function index()
    {
        // Buscamos as feiras para o seletor do formulário
        $feiras = \App\Models\Feira::orderBy('created_at', 'desc')->get(['id', 'nome']);

        // Buscamos os últimos 50 relatórios gerados
        $relatorios = Relatorio::with('feira')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($rel) {
                return [
                    'id' => $rel->id,
                    'nome' => $rel->tipo . ' - ' . ($rel->feira->nome ?? 'N/A'),
                    'id_label' => '#REP-' . $rel->created_at->format('Y') . '-' . str_pad($rel->id, 3, '0', STR_PAD_LEFT),
                    'created_at' => $rel->created_at->toDateTimeString(),
                    'status' => strtolower($rel->status->value), // Sincroniza com as cores do frontend
                    'tipo' => $rel->tipo,
                    'feira_nome' => $rel->feira->nome ?? 'N/A',
                    'download_url' => $rel->urlDownloadSegura(),
                ];
            });

        return Inertia::render('Relatorios/Index', [
            'relatorios' => $relatorios,
            'feiras' => $feiras
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_feira' => 'required|exists:feiras,id',
            'tipo' => 'required|string|in:editoras,cartao,vendas',
        ]);

        $relatorio = Relatorio::create([
            'id_feira' => $validated['id_feira'],
            'usuario_id' => auth()->id(),
            'tipo' => $validated['tipo'],
            'status' => \App\Enums\RelatorioStatus::FILA,
        ]);

        // Despacha o Job orquestrador (Fase 5)
        \App\Jobs\GerarRelatorioJob::dispatch($relatorio);

        return back()->with('success', 'Solicitação de relatório enviada para a fila com sucesso!');
    }
    public function download(Request $request, Relatorio $relatorio)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Link de download expirado ou inválido.');
        }

        if (!$relatorio->caminho_arquivo || !\Illuminate\Support\Facades\Storage::disk('public')->exists($relatorio->caminho_arquivo)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->download($relatorio->caminho_arquivo);
    }
}
