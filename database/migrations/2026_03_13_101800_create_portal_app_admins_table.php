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
        Schema::create('portal_app_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('bitrix_user_id');
            $table->unsignedBigInteger('granted_by_bitrix_user_id')->nullable();
            $table->timestamps();

            $table->unique(['portal_id', 'bitrix_user_id'], 'portal_app_admins_portal_user_unique');
            $table->index(['portal_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portal_app_admins');
    }
};

