<?php

use App\Http\Controllers\Api\AprendizadoController;
use App\Http\Controllers\Api\FabricaController;
use Illuminate\Support\Facades\Route;

// Site avisa lead vindo do Instagram (só contagem). Chave: php artisan central:token-fabrica site --site.
Route::middleware(['fabrica:site', 'throttle:60,1'])->post('/site/marcas/{marca}/leads', [\App\Http\Controllers\Api\LeadsController::class, 'receber']);
// Página de links da bio do site: posts recentes e o link curto de cada um (mesma chave do site).
Route::middleware(['fabrica:site', 'throttle:60,1'])->get('/site/marcas/{marca}/links', [\App\Http\Controllers\Api\LinksSiteController::class, 'index']);

// API da fábrica de conteúdo (o kit no PC). Chave: php artisan central:token-fabrica.
Route::middleware(['fabrica', 'throttle:120,1'])->prefix('fabrica/marcas/{marca}')->group(function () {
    Route::put('/calendario', [FabricaController::class, 'enviarCalendario']);
    Route::get('/calendario/decisoes', [FabricaController::class, 'decisoesCalendario']);
    Route::post('/conteudos', [FabricaController::class, 'enviarConteudo']);
    Route::get('/conteudos/decisoes', [FabricaController::class, 'decisoesConteudos']);
    Route::post('/conteudos/{nome}/video', [FabricaController::class, 'enviarVideo']);
    Route::get('/ideias', [FabricaController::class, 'ideias']);
    Route::get('/pedidos', [FabricaController::class, 'pedidos']);
    Route::post('/pedidos/{chave}/iniciar', [FabricaController::class, 'iniciarPedido']);
    Route::get('/calendario/da-central', [FabricaController::class, 'temasDaCentral']);

    // Rota automática (IA feita pelo Claude na fábrica).
    Route::get('/posts/para-classificar', [AprendizadoController::class, 'paraClassificar']);
    Route::put('/posts/{igId}/como-foi-feito', [AprendizadoController::class, 'classificar']);
    Route::get('/posts/para-ler', [AprendizadoController::class, 'paraLer']);
    Route::put('/posts/{igId}/leitura', [AprendizadoController::class, 'ler']);
    Route::get('/posts/{igId}/capa', [AprendizadoController::class, 'capa']);
    Route::get('/aprendizados', [AprendizadoController::class, 'aprendizados']);
    Route::put('/regras', [AprendizadoController::class, 'regras']);
    Route::get('/pesquisa/insumos', [AprendizadoController::class, 'insumosDaPesquisa']);
    Route::put('/pautas', [AprendizadoController::class, 'pautas']);
});
