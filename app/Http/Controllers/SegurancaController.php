<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/** Configurar os 2 fatores (obrigatório) e ver os códigos de reserva. As ações são as rotas do Fortify. */
class SegurancaController extends Controller
{
    public function __invoke(Request $request): View
    {
        $usuario = $request->user();
        $confirmado = $usuario->two_factor_confirmed_at !== null;
        $pendente = ! $confirmado && filled($usuario->two_factor_secret);
        $mostrarCodigos = $confirmado && in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true);

        return view('seguranca', [
            'confirmado' => $confirmado,
            'pendente' => $pendente,
            'qr' => $pendente ? $usuario->twoFactorQrCodeSvg() : null,
            'chave' => $pendente ? trim(chunk_split(decrypt($usuario->two_factor_secret), 4, ' ')) : null,
            'codigos' => $mostrarCodigos ? $usuario->recoveryCodes() : [],
        ]);
    }
}
