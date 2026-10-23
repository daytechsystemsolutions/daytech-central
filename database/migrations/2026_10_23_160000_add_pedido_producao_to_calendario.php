<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendario_itens', function (Blueprint $table) {
            // "Produzir agora" pedido na Central; a sincronização do kit pega e marca o início.
            $table->timestamp('producao_pedida_em')->nullable();
            $table->timestamp('producao_iniciada_em')->nullable();
            // Tema criado na Central ("+ Novo tema"): o kit traz para o calendario.yaml na sincronização.
            $table->string('origem', 10)->default('kit');
        });
    }

    public function down(): void
    {
        Schema::table('calendario_itens', function (Blueprint $table) {
            $table->dropColumn(['producao_pedida_em', 'producao_iniciada_em', 'origem']);
        });
    }
};
