<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A conta, dia a dia (seguidores no fim do dia e o que aconteceu naquele dia).
        Schema::create('conta_dias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->date('dia');
            $table->unsignedInteger('seguidores')->nullable();
            $table->unsignedInteger('posts')->nullable();
            $table->unsignedInteger('alcance')->nullable();
            $table->unsignedInteger('visitas_perfil')->nullable();
            $table->unsignedInteger('contas_engajadas')->nullable();
            $table->unsignedInteger('interacoes')->nullable();
            $table->unsignedInteger('toques_link')->nullable();
            $table->timestamps();
            $table->unique(['marca_id', 'dia']);
        });

        // Cada post do perfil (os feitos pela Central e os antigos, importados).
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->string('ig_id')->unique();
            $table->foreignId('conteudo_id')->nullable()->constrained('conteudos')->nullOnDelete();
            $table->string('tipo_midia', 20); // CAROUSEL_ALBUM | IMAGE | VIDEO
            $table->text('legenda')->nullable();
            $table->string('permalink')->nullable();
            $table->timestamp('publicado_em');
            $table->string('capa_caminho')->nullable(); // capa guardada aqui (o link do Instagram expira)
            $table->json('como_foi_feito')->nullable(); // pilar, tipo, público, formato, chamada... (etapa da IA)
            $table->boolean('como_foi_feito_estimado')->default(false);
            $table->timestamp('metricas_em')->nullable();
            $table->boolean('sem_numeros')->default(false); // o Instagram não tem números deste post (antigo): não pedir de novo
            $table->timestamps();
            $table->index(['marca_id', 'publicado_em']);
        });

        // Números de cada post, uma leitura por dia (valores acumulados até aquele dia).
        Schema::create('post_metricas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->date('dia');
            foreach (['visualizacoes', 'alcance', 'curtidas', 'comentarios', 'salvos', 'compartilhamentos',
                'interacoes', 'novos_seguidores', 'visitas_perfil', 'acoes_perfil'] as $coluna) {
                $table->unsignedInteger($coluna)->nullable();
            }
            $table->timestamps();
            $table->unique(['post_id', 'dia']);
        });

        Schema::create('post_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('ig_id')->unique();
            $table->string('usuario')->nullable();
            $table->text('texto');
            $table->unsignedInteger('curtidas')->default(0);
            $table->timestamp('comentado_em');
            $table->timestamp('visto_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comentarios');
        Schema::dropIfExists('post_metricas');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('conta_dias');
    }
};
