<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTherapistStatusToAppointmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('therapist_status');
            $table->string('patient_status');
            $table->boolean('created_by_therapist')->default(true);
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('therapist_status');
            $table->dropColumn('patient_status');
            $table->dropColumn('created_by_therapist');
            $table->string('status');
        });
    }
}
