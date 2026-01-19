<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('auditable'); // Model type and ID (creates index automatically)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event'); // created, updated, deleted, enabled, disabled, etc.
            $table->string('action_type'); // 'setting_change', 'credential_change', 'price_change', 'module_toggle', etc.
            $table->text('description')->nullable();
            $table->json('old_values')->nullable(); // Previous state
            $table->json('new_values')->nullable(); // New state
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('module')->nullable(); // Which module triggered this
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['action_type', 'created_at']);
            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

