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
        Schema::table('item_options', function (Blueprint $table) {
            // Add is_active column
            $table->boolean('is_active')->default(1)->after('icon');
            // Change price_adjust_cents to decimal(8,2) for fractional values
            $table->decimal('price_adjust_cents', 8, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_options', function (Blueprint $table) {
            $table->dropColumn('is_active');
            // revert back to integer if needed
            $table->integer('price_adjust_cents')->default(0)->change();
        });
    }
};
