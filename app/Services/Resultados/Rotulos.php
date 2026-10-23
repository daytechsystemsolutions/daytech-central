<?php

namespace App\Services\Resultados;

/** Nomes que aparecem na tela para os valores da classificação dos posts. */
class Rotulos
{
    public const TODOS = [
        'acelerar' => 'Acelerar', 'facilitar' => 'Facilitar', 'organizar' => 'Organizar', 'ensinar' => 'Ensinar', 'institucional' => 'Institucional',
        'consultoria' => 'Consultorias', 'clinica' => 'Clínicas', 'sesmt' => 'SESMT', 'autonomo' => 'Autônomos', 'psicologo' => 'Psicólogos', 'geral' => 'Geral',
        'passo_a_passo' => 'passo a passo', 'lista' => 'lista', 'antes_depois' => 'antes e depois', 'demonstracao' => 'demonstração do sistema',
        'dado' => 'dado ou número', 'pergunta' => 'pergunta', 'depoimento' => 'depoimento', 'outro' => 'outro',
        'comente' => 'Comente a palavra', 'salve' => 'Salve para usar depois', 'compartilhe' => 'Mande para alguém',
        'link_bio' => 'Link da bio', 'whatsapp' => 'WhatsApp', 'nenhuma' => 'Sem chamada', 'outra' => 'Outra chamada',
    ];

    public static function de(?string $valor): string
    {
        return $valor === null ? '' : (self::TODOS[$valor] ?? $valor);
    }
}
