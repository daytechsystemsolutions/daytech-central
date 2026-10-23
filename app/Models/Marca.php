<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma marca da DayTech (MedNext SST, LuPe Educa...) com a conta de Instagram dela.
 * O token do Instagram fica criptografado no banco (APP_KEY) e nunca vai para a tela.
 */
class Marca extends Model
{
    protected $table = 'marcas';

    protected $fillable = [
        'nome', 'slug', 'iniciais', 'cor', 'descricao', 'horario_padrao',
        'instagram_usuario', 'instagram_conta_id', 'instagram_token',
        'instagram_verificado_em', 'instagram_erro', 'postagem_automatica', 'ativa', 'ordem', 'fase',
    ];

    protected $hidden = ['instagram_token'];

    protected function casts(): array
    {
        return [
            'instagram_token' => 'encrypted',
            'instagram_verificado_em' => 'datetime',
            'postagem_automatica' => 'boolean',
            'ativa' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativa', true)->orderBy('ordem')->orderBy('nome');
    }

    public function instagramConectado(): bool
    {
        return filled($this->instagram_conta_id) && filled($this->instagram_token) && $this->instagram_verificado_em !== null;
    }

    /** Seguidores na última leitura da conta. */
    public function seguidores(): ?int
    {
        return $this->contaDias()->whereNotNull('seguidores')->latest('dia')->value('seguidores');
    }

    /** "conhecido" (até 500 seguidores) ou "clientes"; escolhida à mão em Marcas, se houver. */
    public function faseAtual(): string
    {
        return $this->fase ?? (($this->seguidores() ?? 0) >= 500 ? 'clientes' : 'conhecido');
    }

    public function conteudos(): HasMany
    {
        return $this->hasMany(Conteudo::class);
    }

    public function calendario(): HasMany
    {
        return $this->hasMany(CalendarioItem::class);
    }

    public function ideias(): HasMany
    {
        return $this->hasMany(Ideia::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function regras(): HasMany
    {
        return $this->hasMany(Regra::class);
    }

    public function contaDias(): HasMany
    {
        return $this->hasMany(ContaDia::class);
    }
}
