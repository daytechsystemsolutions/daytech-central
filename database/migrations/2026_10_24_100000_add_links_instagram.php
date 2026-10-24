<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rastro do Instagram até o lead (plano de crescimento, etapa 3, 24/10/2026): cada post leva a uma página do
        // site (destino) por um link curto que conta o clique e acrescenta a UTM do post.
        Schema::table('conteudos', function (Blueprint $table) {
            $table->string('destino', 200)->nullable(); // caminho no site, ex.: /modulos/afrp
            $table->string('codigo_link', 12)->nullable()->unique();
        });
        Schema::table('marcas', function (Blueprint $table) {
            $table->string('site_url', 200)->nullable(); // site da marca (MedNext: https://mednextsst.com)
        });
        Schema::create('link_cliques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conteudo_id')->constrained('conteudos')->cascadeOnDelete();
            $table->string('meio', 20); // bio, story, reels...
            $table->timestamp('clicado_em');
            $table->index(['conteudo_id', 'clicado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_cliques');
        Schema::table('marcas', fn (Blueprint $t) => $t->dropColumn('site_url'));
        Schema::table('conteudos', fn (Blueprint $t) => $t->dropColumn(['destino', 'codigo_link']));
    }
};
