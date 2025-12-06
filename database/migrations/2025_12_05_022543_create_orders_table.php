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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('userid')->constrained('users')->onDelete('cascade');
            $table->foreignId('shopid')->constrained('shops')->onDelete('cascade');

            $table->foreignId('promoid')
                ->nullable()
                ->constrained('promotions')
                ->nullOnDelete();

            $table->enum('status', [
                'pending',
                'paid',
                'preparing',
                'ready',
                'completed',
                'cancelled'
            ])->index();

            $table->integer('subtotalcents');
            $table->integer('discountcents');
            $table->integer('totalcents');

            $table->dateTime('placedat');
            $table->dateTime('updatedat');

            // Use created_at or not?
            // For compatibility keep timestamps but you can disable if not needed.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
