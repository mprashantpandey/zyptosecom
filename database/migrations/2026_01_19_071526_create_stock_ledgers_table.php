<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->integer('quantity_change'); // Positive for addition, negative for deduction
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('reason'); // purchase, sale, adjustment, return, damaged, expired
            $table->string('source_type')->nullable(); // order, adjustment, purchase, etc.
            $table->unsignedBigInteger('source_id')->nullable(); // Related order_id, adjustment_id, etc.
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['product_id', 'warehouse_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledgers');
    }
};
