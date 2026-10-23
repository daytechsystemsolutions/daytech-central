<?php

use App\Http\Controllers\Api\FabricaController;
use Illuminate\Support\Facades\Route;

// API da fábrica de conteúdo (o kit no PC). Chave: php artisan central:token-fabrica.
Route::middleware(['fabrica', 'throttle:120,1'])->prefix('fabrica/marcas/{marca}')->group(function () {
    Route::put('/calendario', [FabricaController::class, 'enviarCalendario']);
    Route::get('/calendario/decisoes', [FabricaController::class, 'decisoesCalendario']);
    Route::post('/conteudos', [FabricaController::class, 'enviarConteudo']);
    Route::get('/conteudos/decisoes', [FabricaController::class, 'decisoesConteudos']);
    Route::get('/ideias', [FabricaController::class, 'ideias']);
    Route::get('/pedidos', [FabricaController::class, 'pedidos']);
    Route::post('/pedidos/{chave}/iniciar', [FabricaController::class, 'iniciarPedido']);
    Route::get('/calendario/da-central', [FabricaController::class, 'temasDaCentral']);
});
