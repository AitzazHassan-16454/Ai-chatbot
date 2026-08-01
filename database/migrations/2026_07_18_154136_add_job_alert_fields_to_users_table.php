<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cv_path')->nullable()->after('tokens_limit');
            $table->string('cv_name')->nullable()->after('cv_path');
            $table->string('job_keywords')->nullable()->after('cv_name');
            $table->string('job_location')->nullable()->after('job_keywords');
            $table->string('job_type')->nullable()->after('job_location')->default('full_time');
            $table->string('job_remote')->nullable()->after('job_type')->default('any');
            $table->string('job_experience')->nullable()->after('job_remote')->default('mid');
            $table->unsignedInteger('job_salary_min')->nullable()->after('job_experience');
            $table->unsignedInteger('job_salary_max')->nullable()->after('job_salary_min');
            $table->boolean('job_alerts_enabled')->default(false)->after('job_salary_max');
            $table->string('job_alert_frequency')->default('daily')->after('job_alerts_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'cv_path', 'cv_name', 'job_keywords', 'job_location',
                'job_type', 'job_remote', 'job_experience',
                'job_salary_min', 'job_salary_max',
                'job_alerts_enabled', 'job_alert_frequency',
            ]);
        });
    }
};
