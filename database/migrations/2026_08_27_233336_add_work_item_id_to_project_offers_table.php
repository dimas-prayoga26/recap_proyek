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
            $table->foreignId('work_item_id')->nullable()->after('vendor_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_item_id');
        });
    }
};
