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
        Schema::create('payment_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name')->nullable();
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->unsignedSmallInteger('total_terms')->default(1);
            $table->unsignedSmallInteger('paid_terms')->default(0);
            $table->string('status')->default('berjalan');
            $table->timestamps();

            $table->unique(['project_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_groups');
    }
};
