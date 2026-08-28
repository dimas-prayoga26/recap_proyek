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
        Schema::create('project_transaction_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedSmallInteger('payment_number')->nullable();
            $table->string('role', 20)->default('primary');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['work_item_id', 'payment_group_id'], 'pta_work_item_payment_group_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_transaction_allocations');
    }
};
