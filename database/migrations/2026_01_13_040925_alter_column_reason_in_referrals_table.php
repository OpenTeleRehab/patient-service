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
        Schema::table('referrals', function (Blueprint $table) {
            $table->renameColumn('reason', 'request_reason');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->text('request_reason')->nullable()->change();
            $table->text('reject_reason')->nullable()->after('request_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            Schema::table('referrals', function (Blueprint $table) {
                $table->string('request_reason')->change();
                $table->dropColumn('reject_reason');
            });

            Schema::table('referrals', function (Blueprint $table) {
                $table->renameColumn('request_reason', 'reason');
            });
        });
    }
};
