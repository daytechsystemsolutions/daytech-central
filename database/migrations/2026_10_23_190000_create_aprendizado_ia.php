<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('leitura')->nullable(); // escrita pelo Claude no 7º dia: o que deu certo e o que testar
            $table->timestamp('leitura_em')->nullable();
        });

        // Regras que a produção segue (escritas pelo Claude toda quinta; o usuário pode desligar).
        Schema::create('regras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marca_id')->constrained('marcas')->cascadeOnDelete();
            $table->string('chave', 80); // identificador estável dado pelo Claude (mantém o "desligada" entre semanas)
            $table->string('tipo', 10)->default('faca'); // faca | evite | teste
            $table->string('texto', 300);
            $table->string('evidencia', 200)->nullable(); // "3 posts, nota média 78"
            $table->boolean('ativa')->default(true);
            $table->boolean('vigente')->default(true); // false = saiu da lista da última quinta
            $table->timestamps();
            $table->unique(['marca_id', 'chave']);
        });

        // Tema sugerido pela IA no calendário (e se é um teste dos 30%).
        Schema::table('calendario_itens', function (Blueprint $table) {
            $table->boolean('sugerido_ia')->default(false);
            $table->boolean('teste')->default(false);
            $table->string('motivo', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('calendario_itens', fn (Blueprint $t) => $t->dropColumn(['sugerido_ia', 'teste', 'motivo']));
        Schema::dropIfExists('regras');
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['leitura', 'leitura_em']));
    }
};
