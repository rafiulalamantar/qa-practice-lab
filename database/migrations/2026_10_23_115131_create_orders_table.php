<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Public, non-guessable identifier shown to learners (24 hex chars).
            $table->char('order_number', 24)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Product data is snapshotted so orders survive catalogue changes.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 120);
            $table->string('product_image');
            $table->string('product_description', 500);
            $table->decimal('price', 10, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->string('country', 80);
            $table->string('ordered_by');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
