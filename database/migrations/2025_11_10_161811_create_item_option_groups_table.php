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
        Schema::create('item_option_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // e.g. "Sugar Level"
            $table->enum('type', ['select', 'multiple'])->default('select');
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_option_groups');
    }
};
