<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddColumnsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('first_name');
            $table->string('last_name');
            $table->integer('clinic_id');
            $table->integer('country_id');
            $table->string('gender');
            $table->dateTime('date_of_birth')->nullable();
            $table->string('note')->nullable();
            $table->string('identity')->unique()->nullable();
            $table->string('phone')->unique();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name');
            $table->dropColumn('first_name');
            $table->dropColumn('last_name');
            $table->dropColumn('clinic_id');
            $table->dropColumn('country_id');
            $table->dropColumn('gender');
            $table->dropColumn('date_of_birth');
            $table->dropColumn('note');
            $table->dropColumn('identity');
            $table->dropColumn('phone');
        });
    }
}
