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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('orderid')->constrained('orders')->onDelete('cascade');
            $table->foreignId('itemid')->constrained('items')->onDelete('cascade');

            $table->string('namesnapshot', 150); // item name at order time
            $table->integer('unitprice_cents');
            $table->integer('quantity');
            $table->string('notes', 255)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
