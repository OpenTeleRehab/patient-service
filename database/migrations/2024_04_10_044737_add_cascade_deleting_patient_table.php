<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCascadeDeletingPatientTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('treatment_plan_id')->change();
            $table->foreign('treatment_plan_id')->references('id')->on('treatment_plans')->onDelete('cascade');
        });

        Schema::table('questionnaire_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('activity_id')->change();
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->unsignedBigInteger('treatment_plan_id')->change();
            $table->foreign('treatment_plan_id')->references('id')->on('treatment_plans')->onDelete('cascade');
        });

        Schema::table('assistive_technologies', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->change();
            $table->foreign('patient_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->change();
            $table->foreign('patient_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->change();
            $table->foreign('patient_id')->references('id')->on('users')->onDelete('cascade');
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
            $table->dropForeign(['treatment_plan_id']);
        });

        Schema::table('questionnaire_answers', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->dropForeign(['treatment_plan_id']);
        });

        Schema::table('assistive_technologies', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
        });

        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
        });
    }
}
