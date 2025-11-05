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
            $table->enum('status', ['active', 'inactive']);
            $table->unsignedBigInteger('owner_user_id');
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
