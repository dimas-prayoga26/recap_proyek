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
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('payment_number');
            $table->unsignedBigInteger('amount');
            $table->date('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payment_group_id', 'payment_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_terms');
    }
};
