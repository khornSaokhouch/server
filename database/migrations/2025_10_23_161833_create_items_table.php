<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id(); // bigint, primary key, auto-increment
            $table->foreignId('shop_id')->constrained('shops')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->integer('price_cents'); // store price as integer cents
            $table->string('image_url', 255)->nullable();
            $table->boolean('is_available')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps(); // created_at and updated_at

            $table->index(['shop_id', 'category_id', 'is_available', 'display_order'], 'idx_items_browse');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
