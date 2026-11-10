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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignUlid('invoice_id')->constrained()->restrictOnDelete();

            $table->text('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);

            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
