<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conteudos', function (Blueprint $table) {
            $table->string('erro_postagem', 300)->nullable(); // por que a postagem falhou (sem token)
        });
        Schema::table('marcas', function (Blueprint $table) {
            $table->timestamp('postagem_ligada_em')->nullable(); // quando o usuário ligou "Postar sozinho no horário"
        });
    }

    public function down(): void
    {
        Schema::table('conteudos', fn (Blueprint $t) => $t->dropColumn('erro_postagem'));
        Schema::table('marcas', fn (Blueprint $t) => $t->dropColumn('postagem_ligada_em'));
    }
};
