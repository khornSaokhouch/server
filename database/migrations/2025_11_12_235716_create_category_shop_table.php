<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_shop', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->boolean('status')->default(1)->comment('1 = active, 0 = inactive');
            $table->timestamps();

            $table->unique(['shop_id', 'category_id']); // prevent duplicates
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_shop');
    }
};
