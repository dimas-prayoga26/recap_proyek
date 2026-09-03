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
        Schema::table('project_transactions', function (Blueprint $table): void {
            $table->foreignId('service_detail_work_item_id')
                ->nullable()
                ->after('work_item_id')
                ->constrained('work_items')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_transactions', function (Blueprint $table): void {
            $table->dropForeign(['service_detail_work_item_id']);
            $table->dropColumn('service_detail_work_item_id');
        });
    }
};
