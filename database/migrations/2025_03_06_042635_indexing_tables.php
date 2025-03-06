<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->index('treatment_plan_id');
            $table->index('type');
            $table->index('activity_id');
        });

        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->index('patient_id');
            $table->index('disease_id');
        });

        Schema::table('assistive_technologies', function (Blueprint $table) {
            $table->index('patient_id');
            $table->index('assistive_technology_id');
            $table->index('deleted_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('country_id');
            $table->index('clinic_id');
            $table->index('therapist_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        function dropIndexIfExists($table, $index)
        {
            $indexExists = DB::table('information_schema.statistics')
                ->where('table_schema', env('DB_DATABASE'))
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();

            if ($indexExists) {
                Schema::table($table, function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            }
        }

        dropIndexIfExists('activities', 'activities_treatment_plan_id_index');
        dropIndexIfExists('activities', 'activities_type_index');
        dropIndexIfExists('activities', 'activities_activity_id_index');

        dropIndexIfExists('treatment_plans', 'treatment_plans_patient_id_index');
        dropIndexIfExists('treatment_plans', 'treatment_plans_disease_id_index');

        dropIndexIfExists('assistive_technologies', 'assistive_technologies_patient_id_index');
        dropIndexIfExists('assistive_technologies', 'assistive_technologies_assistive_technology_id_index');
        dropIndexIfExists('assistive_technologies', 'assistive_technologies_deleted_at_index');

        dropIndexIfExists('users', 'users_country_id_index');
        dropIndexIfExists('users', 'users_clinic_id_index');
        dropIndexIfExists('users', 'users_therapist_id_index');
    }
};
