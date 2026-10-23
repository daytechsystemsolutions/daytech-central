<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chave do site (só pode avisar lead) separada da chave da fábrica.
        Schema::table('tokens_fabrica', function (Blueprint $table) {
            $table->string('escopo', 10)->default('fabrica'); // fabrica | site
        });

        // Lead que chegou pelo site vindo de uma marca no Instagram. Só contagem e origem: nada de nome, e-mail ou telefone.
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->timestamp('recebido_em');
            $table->string('fonte', 40); // utm_source (instagram)
            $table->string('meio', 40)->nullable(); // utm_medium (bio, story...)
            $table->string('campanha', 100)->nullable(); // utm_campaign
            $table->string('conteudo', 100)->nullable(); // utm_content (post, quando houver)
            $table->timestamps();
            $table->index(['marca_id', 'recebido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::table('tokens_fabrica', fn (Blueprint $t) => $t->dropColumn('escopo'));
    }
};
