<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssistiveTechnologiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assistive_technologies', function (Blueprint $table) {
            $table->id();
            $table->integer('assistive_technology_id');
            $table->integer('patient_id');
            $table->integer('therapist_id');
            $table->integer('appointment_id')->nullable();
            $table->date('provision_date');
            $table->date('follow_up_date');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assistive_technologies');
    }
}
