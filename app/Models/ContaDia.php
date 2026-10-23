<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContaDia extends Model
{
    protected $table = 'conta_dias';

    protected $fillable = ['marca_id', 'dia', 'seguidores', 'posts', 'alcance', 'visitas_perfil', 'contas_engajadas', 'interacoes', 'toques_link'];

    protected function casts(): array
    {
        return ['dia' => 'date'];
    }
}
