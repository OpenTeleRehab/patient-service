<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTotalOfWeeksToTreatmentPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->smallInteger('total_of_weeks')->default(1);
            $table->dropColumn('type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropColumn('total_of_weeks');
            $table->string('type');
        });
    }
}
