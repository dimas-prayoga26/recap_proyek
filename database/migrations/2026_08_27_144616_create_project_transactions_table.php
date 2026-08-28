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
        Schema::create('project_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->unsignedBigInteger('amount');
            $table->date('recorded_at');
            $table->unsignedSmallInteger('payment_number')->nullable();
            $table->unsignedSmallInteger('payment_total')->nullable();
            $table->unsignedBigInteger('receipt_total')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_transactions');
    }
};
