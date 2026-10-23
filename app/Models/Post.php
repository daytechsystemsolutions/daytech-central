<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Um post do perfil no Instagram (feito pela Central ou importado). */
class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = ['marca_id', 'ig_id', 'conteudo_id', 'tipo_midia', 'legenda', 'permalink', 'publicado_em',
        'capa_caminho', 'como_foi_feito', 'como_foi_feito_estimado', 'metricas_em', 'sem_numeros', 'nota', 'nota_partes', 'nota_fechada_em', 'leitura', 'leitura_em'];

    protected function casts(): array
    {
        return ['publicado_em' => 'datetime', 'metricas_em' => 'datetime', 'como_foi_feito' => 'array', 'como_foi_feito_estimado' => 'boolean', 'sem_numeros' => 'boolean', 'nota_partes' => 'array', 'nota_fechada_em' => 'datetime', 'leitura_em' => 'datetime'];
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function conteudo(): BelongsTo
    {
        return $this->belongsTo(Conteudo::class);
    }

    public function metricas(): HasMany
    {
        return $this->hasMany(PostMetrica::class)->orderBy('dia');
    }

    public function ultimaMetrica(): HasOne
    {
        return $this->hasOne(PostMetrica::class)->latestOfMany('dia');
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(PostComentario::class)->orderByDesc('comentado_em');
    }

    /** [texto, classe da pílula] da faixa da nota. */
    public function faixa(): array
    {
        return \App\Services\Resultados\NotaDoPost::faixa($this->nota);
    }

    /** Título curto: o do carrossel da Central ou a primeira linha da legenda. */
    public function titulo(): string
    {
        if ($this->conteudo) {
            return $this->conteudo->titulo;
        }
        $linha = trim(strtok((string) $this->legenda, "\n") ?: '');

        return $linha !== '' ? \Illuminate\Support\Str::limit($linha, 60) : 'Post de '.$this->publicado_em->format('d/m/Y');
    }
}
