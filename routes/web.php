<?php

use App\Http\Controllers\AprovarController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\ResultadosController;
use App\Http\Controllers\SegurancaController;
use Illuminate\Support\Facades\Route;

// Login, 2 fatores e sair: rotas do Fortify (config/fortify.php). Sem cadastro aberto.

// Lâmina para o Instagram baixar na hora de postar: link assinado que expira em 2 horas, sem login.
Route::get('/p/{conteudo}/{lamina}', [AprovarController::class, 'laminaPublica'])->middleware(['signed', 'throttle:120,1'])->name('publico.lamina');

Route::middleware(['auth', 'dois.fatores', 'marca.atual'])->group(function () {
    Route::get('/', [PainelController::class, 'inicio'])->name('inicio');
    Route::get('/resultados', [ResultadosController::class, 'index'])->name('resultados');
    Route::get('/resultados/aprendemos', [ResultadosController::class, 'aprendizados'])->name('resultados.aprendizados');
    Route::post('/regras/{regra}/alternar', [ResultadosController::class, 'alternarRegra'])->name('regras.alternar');
    Route::get('/resultados/{post}', [ResultadosController::class, 'post'])->name('resultados.post');
    Route::get('/resultados/{post}/capa', [ResultadosController::class, 'capa'])->name('resultados.capa');
    Route::redirect('/publicados', '/resultados');

    Route::get('/aprovar', [AprovarController::class, 'index'])->name('aprovar');
    Route::get('/aprovar/{conteudo}', [AprovarController::class, 'ver'])->name('aprovar.ver');
    Route::post('/aprovar/{conteudo}/aprovar', [AprovarController::class, 'aprovar'])->name('aprovar.aprovar');
    Route::post('/aprovar/{conteudo}/ajuste', [AprovarController::class, 'ajuste'])->name('aprovar.ajuste');
    Route::post('/aprovar/{conteudo}/tirar', [AprovarController::class, 'tirar'])->name('aprovar.tirar');
    Route::post('/aprovar/{conteudo}/desfazer', [AprovarController::class, 'desfazer'])->name('aprovar.desfazer');
    Route::post('/aprovar/{conteudo}/tentar-de-novo', [AprovarController::class, 'tentarDeNovo'])->name('aprovar.tentar');
    Route::get('/aprovar/{conteudo}/laminas/{lamina}', [AprovarController::class, 'lamina'])->name('aprovar.lamina');
    Route::get('/aprovar/{conteudo}/linkedin.pdf', [AprovarController::class, 'pdf'])->name('aprovar.pdf');

    Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario');
    Route::post('/calendario/temas', [CalendarioController::class, 'novoTema'])->name('calendario.novo');
    Route::post('/calendario/semana/{inicio}/produzir', [CalendarioController::class, 'produzirSemana'])->name('calendario.produzir-semana');
    Route::post('/calendario/{item}/produzir', [CalendarioController::class, 'produzir'])->name('calendario.produzir');
    Route::post('/calendario/{item}/cancelar-producao', [CalendarioController::class, 'cancelarProducao'])->name('calendario.cancelar-producao');
    Route::post('/calendario/{item}/trocar', [CalendarioController::class, 'trocar'])->name('calendario.trocar');
    Route::post('/calendario/{item}/tirar', [CalendarioController::class, 'tirar'])->name('calendario.tirar');
    Route::post('/calendario/{item}/manter', [CalendarioController::class, 'manter'])->name('calendario.manter');
    Route::post('/pautas/{pauta}/calendario', [CalendarioController::class, 'pautaNoCalendario'])->name('pautas.calendario');
    Route::post('/pautas/{pauta}/descartar', [CalendarioController::class, 'descartarPauta'])->name('pautas.descartar');
    Route::post('/ideias', [CalendarioController::class, 'novaIdeia'])->name('ideias.criar');
    Route::delete('/ideias/{ideia}', [CalendarioController::class, 'apagarIdeia'])->name('ideias.apagar');

    Route::get('/marcas', [MarcaController::class, 'index'])->name('marcas');
    Route::post('/marcas/{marca}/abrir', [MarcaController::class, 'escolher'])->name('marcas.escolher');
    Route::post('/marcas/{marca}/postagem', [MarcaController::class, 'postagem'])->name('marcas.postagem');
    Route::post('/marcas/{marca}/conferir', [MarcaController::class, 'conferir'])
        ->middleware('throttle:10,1')->name('marcas.conferir');

    Route::get('/seguranca', SegurancaController::class)->middleware('password.confirm')->name('seguranca');
});
