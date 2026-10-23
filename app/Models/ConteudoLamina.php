<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConteudoLamina extends Model
{
    protected $table = 'conteudo_laminas';

    protected $fillable = ['conteudo_id', 'versao', 'ordem', 'caminho'];
}
