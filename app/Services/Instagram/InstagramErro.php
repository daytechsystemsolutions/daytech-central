<?php

namespace App\Services\Instagram;

use RuntimeException;

/** Falha ao falar com a API do Instagram. A mensagem é segura para mostrar na tela (sem token). */
class InstagramErro extends RuntimeException
{
    /** Código de erro do Graph (100 = parâmetro ou métrica que este item não aceita; 190 = token; 4/17/32/613 = limite). */
    public function __construct(string $mensagem, public readonly ?int $codigoGraph = null)
    {
        parent::__construct($mensagem);
    }

    /** O pedido em si não serve para este item (ex.: métrica que o post não tem). Token e limite não entram aqui. */
    public function naoSuportado(): bool
    {
        return $this->codigoGraph === 100;
    }
}
