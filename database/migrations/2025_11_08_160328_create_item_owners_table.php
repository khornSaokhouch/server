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
        Schema::create('item_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->foreignId('shop_id')->constrained('shops')->onDelete('cascade'); // ✅ replaced owner_id with shop_id
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->boolean('inactive')->default(1)->comment('1 = available, 0 = unavailable');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_owners');
    }
};
