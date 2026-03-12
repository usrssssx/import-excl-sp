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
        Schema::create('portals', function (Blueprint $table) {
            $table->id();
            $table->string('member_id')->unique();
            $table->string('domain');
            $table->string('protocol', 10)->default('https');
            $table->string('access_token', 2048)->nullable();
            $table->string('refresh_token', 2048)->nullable();
            $table->unsignedBigInteger('access_expires_at')->nullable();
            $table->string('application_token', 1024)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['domain', 'protocol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portals');
    }
};
