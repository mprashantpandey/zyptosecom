<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('phone');
            $table->text('address_line_1');
            $table->text('address_line_2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('postal_code', 10);
            $table->string('country', 2)->default('IN');
            $table->string('landmark')->nullable();
            $table->enum('address_type', ['home', 'work', 'other'])->default('home');
            $table->boolean('is_default')->default(false);
            $table->boolean('cod_available')->nullable(); // Serviceability check
            $table->timestamps();
            
            $table->index(['user_id', 'is_default']);
            $table->index('postal_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_addresses');
    }
};

