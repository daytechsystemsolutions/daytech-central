<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Banco de pautas da pesquisa (escrito pelo Claude toda quinta), cada uma com a nota de potencial e as fontes.
        Schema::create('pautas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->string('chave', 80);
            $table->string('titulo', 200);
            $table->text('ideia')->nullable();
            $table->string('pilar', 20)->nullable();
            $table->string('publico', 20)->nullable();
            $table->string('formato', 10)->default('carrossel'); // carrossel | reels
            $table->unsignedTinyInteger('potencial');
            $table->json('partes'); // mercado, historico, prazo, duvidas: {pontos, resumo}
            $table->json('fontes')->nullable(); // [{titulo, url}]
            $table->date('prazo')->nullable(); // data que pesa no tema (ex.: obrigação), se houver
            $table->string('estado', 15)->default('nova'); // nova | no_calendario | descartada
            $table->string('calendario_chave', 40)->nullable();
            $table->timestamps();
            $table->unique(['marca_id', 'chave']);
        });

        Schema::table('calendario_itens', function (Blueprint $table) {
            $table->string('pauta_chave', 80)->nullable(); // de qual pauta veio (mostra "Por que este tema")
        });
    }

    public function down(): void
    {
        Schema::table('calendario_itens', fn (Blueprint $t) => $t->dropColumn('pauta_chave'));
        Schema::dropIfExists('pautas');
    }
};
