<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            // Null owner = featured event: shared by everyone and read-only.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('description', 1000)->nullable();
            $table->string('category', 20);
            $table->string('venue', 160);
            $table->string('city', 80);
            $table->dateTime('event_date');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('total_seats');
            $table->string('image_url', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
