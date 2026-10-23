<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reels: duas aprovações (roteiro e depois o vídeo pronto). Plano da etapa 5, aprovado em 23/10/2026.
        Schema::table('conteudos', function (Blueprint $table) {
            $table->string('tipo', 10)->default('carrossel'); // carrossel | reels
            $table->string('fase', 10)->nullable(); // reels: roteiro | video
            $table->json('roteiro')->nullable(); // [{fala, mostra}] cena por cena
            $table->unsignedInteger('roteiro_aprovado_versao')->nullable();
            $table->string('video_caminho')->nullable();
            $table->string('video_hash', 64)->nullable();
            $table->string('capa_caminho')->nullable();
            $table->unsignedSmallInteger('duracao_segundos')->nullable();
        });
        Schema::table('calendario_itens', function (Blueprint $table) {
            $table->string('formato', 10)->default('carrossel');
        });
        Schema::table('post_metricas', function (Blueprint $table) {
            $table->unsignedInteger('tempo_medio_ms')->nullable(); // Reels: tempo médio assistido
        });
        Schema::table('marcas', function (Blueprint $table) {
            $table->unsignedTinyInteger('carrosseis_semana')->default(2);
            $table->unsignedTinyInteger('reels_semana')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('conteudos', fn (Blueprint $t) => $t->dropColumn(['tipo', 'fase', 'roteiro', 'roteiro_aprovado_versao', 'video_caminho', 'video_hash', 'capa_caminho', 'duracao_segundos']));
        Schema::table('calendario_itens', fn (Blueprint $t) => $t->dropColumn('formato'));
        Schema::table('post_metricas', fn (Blueprint $t) => $t->dropColumn('tempo_medio_ms'));
        Schema::table('marcas', fn (Blueprint $t) => $t->dropColumn(['carrosseis_semana', 'reels_semana']));
    }
};
