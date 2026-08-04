<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FeiraController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CartoesController;
use App\Http\Controllers\RelatoriosController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get("/", function () {
    return redirect()->route("login");
});

Route::middleware(["auth", "verified"])->group(function () {
    Route::get("/dashboard", [FeiraController::class, "index"])->name("dashboard");
    
    Route::post("/feiras", [FeiraController::class, "store"])->name("feiras.store");
    Route::get("/feiras/{feira}/auditoria", [FeiraController::class, "show"])->name("feiras.auditoria");
    Route::get("/feiras/{feira}/vendas", [FeiraController::class, "vendas"])->name("feiras.vendas");
    Route::post("/feiras/{feira}/sync", [FeiraController::class, "sync"])->name("feiras.sync");
    Route::post('/feiras/{feira}/retry-sync', [FeiraController::class, 'retrySync'])->name('feiras.retry-sync');

    Route::get("/catalogo", [CatalogoController::class, "index"])->name("catalogo.index");
    Route::get("/catalogo/livros/{livro}", [CatalogoController::class, "show"])->name("catalogo.show");

    Route::get("/cartoes", [CartoesController::class, "index"])->name("cartoes.index");
    Route::get("/cartoes/{cartao}", [CartoesController::class, "show"])->name("cartoes.show");
    Route::get("/relatorios", [RelatoriosController::class, "index"])->name("relatorios.index");
    Route::post("/relatorios", [RelatoriosController::class, "store"])->name("relatorios.store");
    Route::get("/relatorios/{relatorio}/download", [RelatoriosController::class, "download"])->name("relatorios.download");

    Route::get("/profile", [ProfileController::class, "edit"])->name("profile.edit");
    Route::patch("/profile", [ProfileController::class, "update"])->name("profile.update");
    Route::delete("/profile", [ProfileController::class, "destroy"])->name("profile.destroy");
});

require __DIR__."/auth.php";
