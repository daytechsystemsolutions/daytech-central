<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Um clique no link curto de um post (só a hora e o meio: nada de IP nem dado da pessoa). */
class LinkClique extends Model
{
    public $timestamps = false;

    protected $table = 'link_cliques';

    protected $fillable = ['conteudo_id', 'meio', 'clicado_em'];

    protected function casts(): array
    {
        return ['clicado_em' => 'datetime'];
    }
}
