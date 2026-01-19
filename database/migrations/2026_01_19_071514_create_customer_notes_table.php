<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('note');
            $table->json('tags')->nullable(); // ['VIP', 'Fraud risk', 'High value']
            $table->boolean('is_important')->default(false);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index('is_important');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notes');
    }
};
