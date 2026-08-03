<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->string('endpoint', 50)->default('generateContent');
            $table->string('model', 100)->nullable();
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('candidates_tokens')->default(0);
            $table->unsignedBigInteger('thoughts_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->unsignedSmallInteger('status')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('conversation_id')->references('id')->on('conversations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_usage_logs');
    }
};
