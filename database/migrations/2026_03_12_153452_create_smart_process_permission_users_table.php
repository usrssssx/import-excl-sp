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
        Schema::create('smart_process_permission_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smart_process_permission_id', 'spp_users_permission_fk')
                ->constrained('smart_process_permissions')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('bitrix_user_id');
            $table->timestamps();

            $table->unique(['smart_process_permission_id', 'bitrix_user_id'], 'spp_users_permission_user_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_process_permission_users');
    }
};
