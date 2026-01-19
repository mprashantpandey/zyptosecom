<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 10); // default currency code
            $table->string('quote_currency', 10); // target currency code
            $table->decimal('rate', 18, 8); // conversion rate
            $table->string('source')->nullable(); // e.g. manual, api
            $table->timestamp('updated_at');
            
            $table->unique(['base_currency', 'quote_currency']);
            $table->index('base_currency');
            $table->index('quote_currency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
