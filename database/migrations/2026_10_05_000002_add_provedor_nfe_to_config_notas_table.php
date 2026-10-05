<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddProvedorNfeToConfigNotasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('config_notas', function (Blueprint $table) {
            if (!Schema::hasColumn('config_notas', 'provedor_nfe')) {
                // integranotas | sefaz  (padrao: integranotas)
                $table->string('provedor_nfe', 20)->default('integranotas')->after('token_nfe');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('config_notas', function (Blueprint $table) {
            if (Schema::hasColumn('config_notas', 'provedor_nfe')) {
                $table->dropColumn('provedor_nfe');
            }
        });
    }
}
