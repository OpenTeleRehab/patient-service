<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('call_histories')
            ->whereNotIn('patient_id', function ($query) {
                $query->select('id')->from('users');
            })->delete();

        Schema::table('call_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->change();
            $table->foreign('patient_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_histories', function (Blueprint $table) {
            Schema::table('call_histories', function (Blueprint $table) {
                $table->dropForeign(['patient_id']);
                $table->integer('patient_id')->change();
            });
        });
    }
};
