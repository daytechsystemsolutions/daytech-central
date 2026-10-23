<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostComentario extends Model
{
    protected $table = 'post_comentarios';

    protected $fillable = ['post_id', 'ig_id', 'usuario', 'texto', 'curtidas', 'comentado_em', 'visto_em'];

    protected function casts(): array
    {
        return ['comentado_em' => 'datetime', 'visto_em' => 'datetime'];
    }
}
