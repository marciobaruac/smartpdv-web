<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTokenNfeToConfigNotasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('config_notas', function (Blueprint $table) {
            if (!Schema::hasColumn('config_notas', 'token_nfe')) {
                $table->string('token_nfe', 255)->nullable()->after('csc_id');
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
            if (Schema::hasColumn('config_notas', 'token_nfe')) {
                $table->dropColumn('token_nfe');
            }
        });
    }
}
