<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('phc_service_id')->index()->nullable();
            $table->unsignedBigInteger('phc_worker_id')->index()->nullable();
            $table->json('supplementary_phc_workers')->nullable();
            $table->unsignedBigInteger('therapist_id')->nullable()->change();
            $table->unsignedBigInteger('clinic_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phc_service_id']);
            $table->dropIndex(['phc_worker_id']);
            $table->dropColumn(['phc_service_id', 'phc_worker_id', 'supplementary_phc_workers']);
            $table->integer('therapist_id')->change();
            $table->integer('clinic_id')->change();
        });
    }
};
