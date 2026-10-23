<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chave de acesso da fábrica (o kit no PC). Só o hash fica no banco.
        Schema::create('tokens_fabrica', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('hash', 64)->unique();
            $table->timestamp('ultimo_uso_em')->nullable();
            $table->timestamps();
        });

        // Calendário de cada marca (vem do carrosseis/calendario.yaml do kit; a decisão do tema é daqui).
        Schema::create('calendario_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->string('chave'); // id do item no kit, ex.: 2026-11-03
            $table->date('data');
            $table->string('hora', 5)->default('11:40');
            $table->string('titulo');
            $table->text('ideia')->nullable();
            $table->json('publico')->nullable();
            $table->string('tipo')->nullable(); // produto | educativo
            $table->string('estado')->default('proposto'); // estado no kit
            $table->string('carrossel')->nullable(); // nome do carrossel produzido
            $table->string('decisao')->nullable(); // manter | trocar | tirar (decidido aqui)
            $table->text('nota')->nullable();
            $table->timestamp('decidido_em')->nullable();
            $table->timestamps();
            $table->unique(['marca_id', 'chave']);
        });

        // Conteúdo produzido (carrossel), com a decisão do usuário.
        Schema::create('conteudos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->string('nome'); // pasta do carrossel no kit
            $table->string('titulo');
            $table->string('calendario_chave')->nullable();
            $table->timestamp('agendado_para')->nullable();
            $table->json('publico')->nullable();
            $table->text('legenda_instagram')->nullable();
            $table->text('texto_linkedin')->nullable();
            $table->json('conferencia')->nullable();
            $table->json('fontes')->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->string('estado')->default('aguardando'); // aguardando | agendado | ajuste | tirado | publicado | falhou
            $table->string('decisao')->nullable(); // aprovar | ajuste | tirar
            $table->text('nota')->nullable();
            $table->unsignedInteger('versao_decidida')->nullable();
            $table->timestamp('decidido_em')->nullable();
            $table->text('ajuste_feito')->nullable(); // o que mudou na versão nova (escrito pela fábrica)
            $table->string('pdf_caminho')->nullable();
            $table->string('hash_laminas', 64)->nullable(); // o kit confere antes de postar: posta só o que foi visto
            $table->string('link')->nullable();
            $table->timestamp('publicado_em')->nullable();
            $table->timestamps();
            $table->unique(['marca_id', 'nome']);
        });

        Schema::create('conteudo_laminas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conteudo_id')->constrained('conteudos')->cascadeOnDelete();
            $table->unsignedInteger('versao');
            $table->unsignedSmallInteger('ordem');
            $table->string('caminho');
            $table->timestamps();
        });

        Schema::create('ideias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->text('texto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ideias');
        Schema::dropIfExists('conteudo_laminas');
        Schema::dropIfExists('conteudos');
        Schema::dropIfExists('calendario_itens');
        Schema::dropIfExists('tokens_fabrica');
    }
};
