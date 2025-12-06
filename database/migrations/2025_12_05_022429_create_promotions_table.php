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
            Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopid')->constrained('shops')->onDelete('cascade');

            $table->string('code', 30)->unique();
            $table->enum('type', ['percent', 'fixedamount']);
            $table->integer('value'); // percent or cents

            $table->dateTime('startsat');
            $table->dateTime('endsat');

            $table->boolean('isactive')->default(1);

            $table->integer('usagelimit')->nullable(); // user can use X times

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
