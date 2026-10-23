<?php

namespace App\Console\Commands;

use App\Models\Marca;
use Illuminate\Console\Command;

/** Cria as marcas da DayTech que ainda não existem. Não mexe nas que já existem. */
class CentralPreparar extends Command
{
    protected $signature = 'central:preparar';

    protected $description = 'Cria as marcas da DayTech (só as que faltam)';

    public const MARCAS = [
        ['slug' => 'mednext', 'nome' => 'MedNext SST', 'iniciais' => 'MN', 'cor' => '#1F5FBF', 'ordem' => 1,
            'descricao' => '5 públicos · tom técnico e direto', 'horario_padrao' => 'Seg, qua e sex às 11h40'],
        ['slug' => 'lupe', 'nome' => 'LuPe Educa', 'iniciais' => 'LE', 'cor' => '#46505D', 'ordem' => 2,
            'descricao' => 'Ainda sem Instagram', 'horario_padrao' => null],
    ];

    public function handle(): int
    {
        foreach (self::MARCAS as $dados) {
            $marca = Marca::firstOrCreate(['slug' => $dados['slug']], $dados);
            $this->line(($marca->wasRecentlyCreated ? '✓ criada: ' : '• já existia: ').$marca->nome);
        }

        return self::SUCCESS;
    }
}
