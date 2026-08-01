<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('requests_today')->default(0);
            $table->unsignedInteger('requests_day_limit')->default(250);
            $table->timestamp('daily_reset_at')->nullable();
            $table->timestamps();
        });

        $midnightPT = Carbon::now('America/Los_Angeles')->startOfDay()->addDay()->setTimezone('UTC');
        DB::table('system_usage')->insert([
            'requests_today'     => 0,
            'requests_day_limit' => 250,
            'daily_reset_at'     => $midnightPT,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_usage');
    }
};
