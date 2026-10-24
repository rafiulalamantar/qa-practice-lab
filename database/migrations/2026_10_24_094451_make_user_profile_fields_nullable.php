<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TicketTrail registers with email + password only, so the ShopSphere
 * profile fields become optional on the shared users table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 60)->nullable()->change();
            $table->string('last_name', 60)->nullable()->change();
            $table->string('mobile', 15)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 60)->nullable(false)->change();
            $table->string('last_name', 60)->nullable(false)->change();
            $table->string('mobile', 15)->nullable(false)->change();
        });
    }
};
