<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_item_option_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id'); 
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('item_option_group_id');
            $table->unsignedBigInteger('item_option_id');
            $table->tinyInteger('status')->default(1); // 1 = active, 0 = inactive
            $table->timestamps();

            // Foreign keys
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('item_option_group_id')->references('id')->on('item_option_groups')->onDelete('cascade');
            $table->foreign('item_option_id')->references('id')->on('item_options')->onDelete('cascade');

            // Unique constraint to avoid duplicates
            $table->unique(['shop_id', 'item_id', 'item_option_group_id', 'item_option_id'], 'unique_shop_option_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_item_option_status');
    }
};
