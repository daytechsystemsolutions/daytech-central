<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conteudo extends Model
{
    protected $table = 'conteudos';

    protected $fillable = [
        'marca_id', 'nome', 'titulo', 'calendario_chave', 'agendado_para', 'publico', 'legenda_instagram',
        'texto_linkedin', 'conferencia', 'fontes', 'versao', 'estado', 'decisao', 'nota', 'versao_decidida',
        'decidido_em', 'ajuste_feito', 'pdf_caminho', 'hash_laminas', 'link', 'publicado_em',
    ];

    protected function casts(): array
    {
        return [
            'agendado_para' => 'datetime',
            'publico' => 'array',
            'conferencia' => 'array',
            'fontes' => 'array',
            'decidido_em' => 'datetime',
            'publicado_em' => 'datetime',
        ];
    }

    public const PUBLICOS = ['consultoria' => 'consultorias', 'clinica' => 'clínicas', 'autonomo' => 'autônomos',
        'sesmt' => 'SESMT', 'psicologo' => 'psicólogos'];

    public static function publicoEmTexto(?array $publico): string
    {
        return collect($publico ?? [])->map(fn ($p) => self::PUBLICOS[$p] ?? $p)->implode(', ');
    }

    public function publicoTexto(): string
    {
        return self::publicoEmTexto($this->publico);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function laminas(): HasMany
    {
        return $this->hasMany(ConteudoLamina::class)->orderBy('ordem');
    }

    /** Mesma conta do kit (carrossel/central.py): sha256 das linhas "sha256 de cada lâmina", na ordem. */
    public static function hashDasLaminas(array $imagens): string
    {
        return hash('sha256', implode("\n", array_map(fn ($b) => hash('sha256', $b), $imagens)));
    }

    /** Só as lâminas da versão atual. */
    public function laminasAtuais()
    {
        return $this->laminas()->where('versao', $this->versao)->get();
    }

    public function situacao(): array
    {
        return match ($this->estado) {
            'aguardando' => ['espera você', 'espera'],
            'agendado' => ['agendado', 'agendado'],
            'ajuste' => ['ajuste pedido', 'espera'],
            'tirado' => ['tirado', 'erro'],
            'publicado' => ['publicado', 'ok'],
            'falhou' => ['falhou', 'erro'],
            default => [$this->estado, 'neutro'],
        };
    }

    /** Dá para desfazer a aprovação até a hora do post (e se ainda não saiu). */
    public function podeDesfazer(): bool
    {
        return in_array($this->estado, ['agendado', 'tirado', 'ajuste'], true)
            && ($this->agendado_para === null || $this->agendado_para->isFuture());
    }
}
