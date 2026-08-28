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
        Schema::table('project_offers', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('project_area_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->after('project_area_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
            $table->dropConstrainedForeignId('project_area_id');
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
