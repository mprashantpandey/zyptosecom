<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->string('rule_type'); // 'time_based', 'version_based', 'platform_based', 'condition_based'
            $table->string('rule_key'); // e.g., 'cod_enabled', 'max_amount'
            $table->text('rule_value'); // JSON or string value
            $table->json('conditions')->nullable(); // Additional conditions
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['module_id', 'rule_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_rules');
    }
};

