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
        Schema::create('smart_process_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('entity_type_id');
            $table->string('title')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('allow_all_users')->default(false);
            $table->timestamps();

            $table->unique(['portal_id', 'entity_type_id']);
            $table->index(['portal_id', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_process_permissions');
    }
};
