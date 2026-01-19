<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->default('app'); // e.g. app, auth, checkout
            $table->string('key'); // e.g. checkout.place_order
            $table->string('locale', 10); // e.g. en, hi
            $table->text('value');
            $table->boolean('is_locked')->default(false); // protect system keys
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->unique(['group', 'key', 'locale']);
            $table->index('locale');
            $table->index('group');
            $table->index(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
