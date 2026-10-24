<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_ref', 10)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name', 100);
            $table->string('customer_email');
            $table->string('customer_phone', 20);
            $table->unsignedTinyInteger('quantity');
            $table->decimal('total_price', 10, 2);
            $table->string('status', 12)->default('confirmed');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
