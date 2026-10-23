<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarioItem extends Model
{
    protected $table = 'calendario_itens';

    protected $fillable = ['marca_id', 'chave', 'data', 'hora', 'titulo', 'ideia', 'publico', 'tipo', 'estado', 'carrossel', 'decisao', 'nota', 'decidido_em'];

    /** Estados em que o tema ainda pode ser trocado ou tirado (antes da produção). */
    public const DECIDIVEIS = ['proposto', 'tema_aprovado', 'trocar', 'tirado'];

    protected function casts(): array
    {
        return ['data' => 'date', 'publico' => 'array', 'decidido_em' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function podeMexer(): bool
    {
        return in_array($this->estado, self::DECIDIVEIS, true);
    }

    /** Estado como a tela mostra: [texto, classe da pílula]. Usa o conteúdo, quando já foi produzido. */
    public function situacao(?Conteudo $conteudo): array
    {
        if ($conteudo) {
            return $conteudo->situacao();
        }

        return match (true) {
            $this->decisao === 'tirar' || $this->estado === 'tirado' => ['tirado', 'erro'],
            $this->decisao === 'trocar' || $this->estado === 'trocar' => ['troca pedida', 'agendado'],
            $this->estado === 'publicado' => ['publicado', 'ok'],
            default => ['tema', 'neutro'],
        };
    }
}
