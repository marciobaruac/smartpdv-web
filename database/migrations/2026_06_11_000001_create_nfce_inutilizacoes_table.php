<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNfceInutilizacoesTable extends Migration
{
    public function up()
    {
        Schema::create('nfce_inutilizacoes', function (Blueprint $table) {
            $table->id();
            $table->integer('serie');
            $table->integer('numero_inicio');
            $table->integer('numero_final');
            $table->integer('ano');
            $table->string('justificativa');
            $table->string('protocolo')->nullable();
            $table->string('status')->default('inutilizado');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('nfce_inutilizacoes');
    }
}
