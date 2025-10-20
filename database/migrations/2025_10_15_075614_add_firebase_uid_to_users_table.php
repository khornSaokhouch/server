<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('firebase_uid')->nullable()->unique()->after('phone');
            $table->string('profile_image')->nullable()->after('firebase_uid'); // ✅ optional: Firebase/Google photo
            $table->enum('role', ['customer', 'owner', 'admin'])->default('customer')->after('profile_image');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['firebase_uid', 'profile_image', 'role']);
        });
    }
};
