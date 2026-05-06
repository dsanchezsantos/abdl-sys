<?php

namespace App\Http\Controllers;

use App\Models\Livro;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CatalogoController extends Controller
{
    public function index()
    {
        $livros = Livro::all();

        return Inertia::render('Catalogo/Index', [
            'livros' => $livros,
        ]);
    }
}
