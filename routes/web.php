<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FeiraController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CartoesController;
use App\Http\Controllers\RelatoriosController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ConviteController;
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
    Route::patch("/catalogo/livros/bulk", [CatalogoController::class, "bulkUpdate"])->name("catalogo.livros.bulk");
    Route::get("/catalogo/livros/{livro}", [CatalogoController::class, "show"])->name("catalogo.show");
    Route::patch("/catalogo/livros/{livro}", [CatalogoController::class, "update"])->name("catalogo.livros.update");

    Route::post("/feiras/{feira}/editoras-representantes", [FeiraController::class, "storeEditoraRepresentante"])->name("feiras.editoras.store");
    Route::post("/feiras/{feira}/editoras-representantes/import", [FeiraController::class, "importEditorasRepresentantes"])->name("feiras.editoras.import");
    Route::delete("/feiras/{feira}/editoras-representantes/{id}", [FeiraController::class, "destroyEditoraRepresentante"])->name("feiras.editoras.destroy");
 
    Route::get("/cartoes", [CartoesController::class, "index"])->name("cartoes.index");
    Route::get("/cartoes/{cartao}", [CartoesController::class, "show"])->name("cartoes.show");
    Route::get("/relatorios", [RelatoriosController::class, "index"])->name("relatorios.index");
    Route::post("/relatorios", [RelatoriosController::class, "store"])->name("relatorios.store");
    Route::get("/relatorios/{relatorio}/download", [RelatoriosController::class, "download"])->name("relatorios.download");
 
    Route::get("/feiras/{feira}/export/livros", [\App\Http\Controllers\ExportController::class, "exportLivros"])->name("feiras.export.livros");
    Route::get("/feiras/{feira}/export/cartoes", [\App\Http\Controllers\ExportController::class, "exportCartoes"])->name("feiras.export.cartoes");
    Route::get("/feiras/{feira}/export/vendas", [\App\Http\Controllers\ExportController::class, "exportVendasTransacoes"])->name("feiras.export.vendas");
 
    Route::get("/profile", [ProfileController::class, "edit"])->name("profile.edit");
    Route::patch("/profile", [ProfileController::class, "update"])->name("profile.update");
    Route::delete("/profile", [ProfileController::class, "destroy"])->name("profile.destroy");
 
    // Rotas de Gestão de Usuários e Convites
    Route::get("/usuarios", [UserController::class, "index"])->name("usuarios.index");
    Route::delete("/usuarios/{user}", [UserController::class, "destroy"])->name("usuarios.destroy");
    Route::post("/usuarios/convites", [ConviteController::class, "store"])->name("usuarios.convites.store");
});
 
// Rotas públicas para cadastro por convite
Route::middleware("guest")->group(function () {
    Route::get("/convite/{token}", [ConviteController::class, "showRegistrationForm"])->name("convite.show");
    Route::post("/convite/{token}", [ConviteController::class, "register"])->name("convite.register");
});
 
require __DIR__."/auth.php";
