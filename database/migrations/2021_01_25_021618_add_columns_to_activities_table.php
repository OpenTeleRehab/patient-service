<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->boolean('completed')->default(0);
            $table->tinyInteger('pain_level')->nullable();
            $table->tinyInteger('sets')->nullable();
            $table->tinyInteger('reps')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('completed');
            $table->dropColumn('pain_level');
            $table->dropColumn('sets');
            $table->dropColumn('reps');
        });
    }
}
