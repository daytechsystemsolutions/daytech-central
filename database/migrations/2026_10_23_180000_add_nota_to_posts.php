<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedTinyInteger('nota')->nullable(); // 0 a 100 (App\Services\Resultados\NotaDoPost)
            $table->json('nota_partes')->nullable(); // pontos de cada parte, valores, médias e pesos usados
            $table->timestamp('nota_fechada_em')->nullable(); // 7º dia: depois disso não muda mais
        });
        Schema::table('marcas', function (Blueprint $table) {
            // Fase da conta para os pesos da nota: null = automática (500 seguidores), "conhecido" ou "clientes".
            $table->string('fase', 12)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['nota', 'nota_partes', 'nota_fechada_em']));
        Schema::table('marcas', fn (Blueprint $t) => $t->dropColumn('fase'));
    }
};
