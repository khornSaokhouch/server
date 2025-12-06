<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('userid')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('orderid')->nullable()->constrained('orders')->nullOnDelete();

            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_session_id')->nullable()->index();
            $table->string('status')->nullable()->index();

            $table->integer('amount_cents')->nullable();
            $table->string('currency', 10)->default('usd');

            $table->json('raw_response')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
