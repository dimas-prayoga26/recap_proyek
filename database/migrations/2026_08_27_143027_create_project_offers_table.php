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
        Schema::create('project_offers', function (Blueprint $table) {
            $table->id();
            $table->string('project_name')->default('Project Kemang');
            $table->string('area', 20);
            $table->string('pekerjaan');
            $table->string('brand')->nullable();
            $table->decimal('penawaran_usd', 15, 2)->nullable();
            $table->unsignedBigInteger('penawaran_rupiah')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['project_name', 'area']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_offers');
    }
};
