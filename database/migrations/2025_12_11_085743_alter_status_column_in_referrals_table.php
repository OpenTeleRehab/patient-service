<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            DB::statement("ALTER TABLE `referrals` MODIFY `status` ENUM('invited', 'accepted', 'declined') NOT NULL DEFAULT 'invited'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            DB::statement("ALTER TABLE `referrals` MODIFY `status` ENUM('invited', 'declined') NOT NULL DEFAULT 'invited'");
        });
    }
};
