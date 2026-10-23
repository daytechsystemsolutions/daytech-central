<?php

namespace App\Services\Resultados;

use Carbon\CarbonImmutable;

/** Semana (segunda a domingo), mês ou ano, com o anterior e o seguinte para as setas. */
class Periodo
{
    public function __construct(
        public readonly string $tipo,
        public readonly CarbonImmutable $inicio,
        public readonly CarbonImmutable $fim,
    ) {}

    public static function de(?string $tipo, ?string $data): self
    {
        $tipo = in_array($tipo, ['semana', 'mes', 'ano'], true) ? $tipo : 'mes';
        $base = $data && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) ? CarbonImmutable::parse($data) : CarbonImmutable::today();

        return match ($tipo) {
            'semana' => new self($tipo, $base->startOfWeek(CarbonImmutable::MONDAY), $base->endOfWeek(CarbonImmutable::SUNDAY)),
            'ano' => new self($tipo, $base->startOfYear(), $base->endOfYear()),
            default => new self($tipo, $base->startOfMonth(), $base->endOfMonth()),
        };
    }

    public function anterior(): self
    {
        return self::de($this->tipo, $this->inicio->subDay()->toDateString());
    }

    public function seguinte(): self
    {
        return self::de($this->tipo, $this->fim->addDay()->toDateString());
    }

    public function temSeguinte(): bool
    {
        return $this->fim->lt(CarbonImmutable::today());
    }

    public function nome(): string
    {
        return match ($this->tipo) {
            'semana' => 'semana de '.$this->inicio->format('d/m').' a '.$this->fim->format('d/m'),
            'ano' => $this->inicio->format('Y'),
            default => $this->inicio->locale('pt_BR')->translatedFormat('F Y'),
        };
    }
}
