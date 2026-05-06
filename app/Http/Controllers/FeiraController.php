<?php

namespace App\Http\Controllers;

use App\Models\Feira;
use App\Http\Requests\StoreFeiraRequest;
use App\Enums\FeiraStatus;
use Illuminate\Http\Request;
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
            "feira" => $feira->load("estatistica"),
        ]);
    }
}
