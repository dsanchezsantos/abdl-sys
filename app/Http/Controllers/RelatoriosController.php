<?php

namespace App\Http\Controllers;

use App\Models\Relatorio;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RelatoriosController extends Controller
{
    public function index()
    {
        // Dummy data to match prototype if table is empty
        $relatorios = Relatorio::all();
        
        if ($relatorios->isEmpty()) {
            $relatorios = [
                [
                    'id' => 1,
                    'nome' => 'Fechamento Mensal - Saquarema Jan/25',
                    'id_label' => '#REP-2025-098',
                    'created_at' => '2025-01-24',
                    'status' => 'concluido',
                ],
                [
                    'id' => 2,
                    'nome' => 'Auditoria Detalhada: Editora Sextante',
                    'id_label' => '#REP-2025-102',
                    'created_at' => '2025-01-24',
                    'status' => 'processando',
                ],
                [
                    'id' => 3,
                    'nome' => 'Vendas por Categoria: Literatura Infantil',
                    'id_label' => '#REP-2025-085',
                    'created_at' => '2025-01-22',
                    'status' => 'concluido',
                ],
            ];
        }

        return Inertia::render('Relatorios/Index', [
            'relatorios' => $relatorios,
        ]);
    }
}
