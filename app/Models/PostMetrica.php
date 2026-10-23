<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostMetrica extends Model
{
    protected $table = 'post_metricas';

    /** Nome na API do Instagram => coluna. */
    public const DA_API = [
        'views' => 'visualizacoes', 'reach' => 'alcance', 'likes' => 'curtidas', 'comments' => 'comentarios',
        'saved' => 'salvos', 'shares' => 'compartilhamentos', 'total_interactions' => 'interacoes',
        'follows' => 'novos_seguidores', 'profile_visits' => 'visitas_perfil', 'profile_activity' => 'acoes_perfil',
    ];

    protected $fillable = ['post_id', 'dia', 'visualizacoes', 'alcance', 'curtidas', 'comentarios', 'salvos',
        'compartilhamentos', 'interacoes', 'novos_seguidores', 'visitas_perfil', 'acoes_perfil'];

    protected function casts(): array
    {
        return ['dia' => 'date'];
    }
}
