<?php

use App\Http\Controllers\MarcaController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\SegurancaController;
use Illuminate\Support\Facades\Route;

// Login, 2 fatores e sair: rotas do Fortify (config/fortify.php). Sem cadastro aberto.

Route::middleware(['auth', 'dois.fatores', 'marca.atual'])->group(function () {
    Route::get('/', [PainelController::class, 'inicio'])->name('inicio');
    Route::get('/aprovar', [PainelController::class, 'aprovar'])->name('aprovar');
    Route::get('/calendario', [PainelController::class, 'calendario'])->name('calendario');
    Route::get('/publicados', [PainelController::class, 'publicados'])->name('publicados');

    Route::get('/marcas', [MarcaController::class, 'index'])->name('marcas');
    Route::post('/marcas/{marca}/abrir', [MarcaController::class, 'escolher'])->name('marcas.escolher');
    Route::post('/marcas/{marca}/conferir', [MarcaController::class, 'conferir'])
        ->middleware('throttle:10,1')->name('marcas.conferir');

    Route::get('/seguranca', SegurancaController::class)->middleware('password.confirm')->name('seguranca');
});
