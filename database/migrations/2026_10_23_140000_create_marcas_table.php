<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marcas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->string('iniciais', 4);
            $table->string('cor', 7)->default('#1F5FBF');
            $table->string('descricao')->nullable(); // públicos e tom, em uma linha
            $table->string('horario_padrao')->nullable(); // texto para a tela: "Seg, qua e sex às 11h40"
            $table->string('instagram_usuario')->nullable(); // @, conferido na API
            $table->string('instagram_conta_id')->nullable();
            $table->text('instagram_token')->nullable(); // criptografado (cast encrypted)
            $table->timestamp('instagram_verificado_em')->nullable();
            $table->string('instagram_erro')->nullable(); // última falha ao conferir, sem dados sensíveis
            $table->boolean('postagem_automatica')->default(false);
            $table->boolean('ativa')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marcas');
    }
};
