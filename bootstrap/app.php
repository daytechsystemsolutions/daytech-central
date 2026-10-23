<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Só o porteiro (Caddy, IP fixo na rede docker "porteiro") pode dizer o IP real e o https.
        $middleware->trustProxies(at: ['10.201.0.2']);
        $middleware->append(\App\Http\Middleware\CabecalhosSeguranca::class);
        $middleware->alias([
            'dois.fatores' => \App\Http\Middleware\ExigirDoisFatores::class,
            'marca.atual' => \App\Http\Middleware\MarcaAtual::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
