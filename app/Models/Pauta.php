<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pauta extends Model
{
    protected $table = 'pautas';

    protected $fillable = ['marca_id', 'chave', 'titulo', 'ideia', 'pilar', 'publico', 'formato', 'potencial', 'partes', 'fontes', 'prazo', 'estado', 'calendario_chave'];

    /** Pesos do potencial (plano aprovado em 23/10/2026). */
    public const PESOS = ['mercado' => 30, 'historico' => 30, 'prazo' => 20, 'duvidas' => 20];

    public const NOMES = ['mercado' => 'O que o mercado procura', 'historico' => 'Histórico da conta', 'prazo' => 'Prazos e obrigações', 'duvidas' => 'Dúvidas reais'];

    protected function casts(): array
    {
        return ['partes' => 'array', 'fontes' => 'array', 'prazo' => 'date'];
    }

    /** Potencial a partir das partes (0 a 100 cada), com os pesos. */
    public static function calcular(array $partes): int
    {
        $soma = 0;
        foreach (self::PESOS as $parte => $peso) {
            $soma += max(0, min(100, (int) ($partes[$parte]['pontos'] ?? 0))) * $peso;
        }

        return (int) round($soma / 100);
    }
}
