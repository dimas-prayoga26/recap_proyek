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
        Schema::table('payment_groups', function (Blueprint $table) {
            $table->foreignId('work_item_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('offer_rupiah_snapshot')->nullable()->after('total_amount');
            $table->decimal('offer_usd_snapshot', 15, 2)->nullable()->after('offer_rupiah_snapshot');
            $table->index(['project_id', 'work_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_groups', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'work_item_id']);
            $table->dropConstrainedForeignId('work_item_id');
            $table->dropColumn(['offer_rupiah_snapshot', 'offer_usd_snapshot']);
        });
    }
};
