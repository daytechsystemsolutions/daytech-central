<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Chave de acesso da fábrica de conteúdo (o kit no PC). Só o hash SHA-256 fica no banco. */
class TokenFabrica extends Model
{
    protected $table = 'tokens_fabrica';

    protected $fillable = ['nome', 'hash', 'ultimo_uso_em', 'escopo'];

    protected function casts(): array
    {
        return ['ultimo_uso_em' => 'datetime'];
    }

    public static function hashDe(string $token): string
    {
        return hash('sha256', $token);
    }
}
