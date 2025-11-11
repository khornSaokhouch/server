<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('location', 255)->nullable();
            // ✅ status as 1 = active, 0 = inactive
            $table->tinyInteger('status')->default(1)->comment('1 = active, 0 = inactive');
            $table->unsignedBigInteger('owner_user_id');

            // Working hours
            $table->time('open_time')->nullable();   // e.g., "08:00:00"
            $table->time('close_time')->nullable();  // e.g., "18:00:00"
            // 👇 Shop image column
            $table->string('image')->nullable(); // store image path or URL

            $table->timestamps();

            // Index
            $table->index('owner_user_id', 'idx_shops_owner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
