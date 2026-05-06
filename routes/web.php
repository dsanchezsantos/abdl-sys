<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FeiraController;
use App\Http\Controllers\CatalogoController;
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

    Route::get("/catalogo", [CatalogoController::class, "index"])->name("catalogo.index");
    Route::get("/relatorios", [RelatoriosController::class, "index"])->name("relatorios.index");

    Route::get("/profile", [ProfileController::class, "edit"])->name("profile.edit");
    Route::patch("/profile", [ProfileController::class, "update"])->name("profile.update");
    Route::delete("/profile", [ProfileController::class, "destroy"])->name("profile.destroy");
});

require __DIR__."/auth.php";
