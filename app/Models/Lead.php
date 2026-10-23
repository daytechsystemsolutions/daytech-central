<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Contagem de lead vindo do Instagram (sem dado pessoal: o lead em si fica no CRM do MedNext). */
class Lead extends Model
{
    protected $table = 'leads';

    protected $fillable = ['marca_id', 'recebido_em', 'fonte', 'meio', 'campanha', 'conteudo'];

    protected function casts(): array
    {
        return ['recebido_em' => 'datetime'];
    }
}
