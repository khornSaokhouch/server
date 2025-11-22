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
        Schema::create('item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_option_group_id')->constrained('item_option_groups')->onDelete('cascade');
            $table->string('name', length: 100); // e.g. "Normal", "Less", "Extra"
            $table->decimal('price_adjust_cents', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_options');
    }
};
