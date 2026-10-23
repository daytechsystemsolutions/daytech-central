<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Regra extends Model
{
    protected $table = 'regras';

    protected $fillable = ['marca_id', 'chave', 'tipo', 'texto', 'evidencia', 'ativa', 'vigente'];

    protected function casts(): array
    {
        return ['ativa' => 'boolean', 'vigente' => 'boolean'];
    }
}
