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
        Schema::create('items', function (Blueprint $table) {
            $table->id(); // bigint, primary key, auto-increment
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('price_cents', 8, 2); // 8 total digits, 2 after decimal
            $table->string('image_url', 255)->nullable();
            $table->tinyInteger('is_available')->default(1)->comment('1 = available, 0 = unavailable');
            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
