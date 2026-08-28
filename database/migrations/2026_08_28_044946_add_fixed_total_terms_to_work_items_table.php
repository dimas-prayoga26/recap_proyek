<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('fixed_total_terms')->default(8)->after('offer_usd');
        });

        DB::table('payment_groups')
            ->select('work_item_id', DB::raw('MAX(COALESCE(fixed_total_terms, total_terms, 8)) as fixed_total_terms'))
            ->whereNotNull('work_item_id')
            ->groupBy('work_item_id')
            ->orderBy('work_item_id')
            ->each(function (object $paymentGroup): void {
                DB::table('work_items')
                    ->where('id', $paymentGroup->work_item_id)
                    ->update([
                        'fixed_total_terms' => max(1, (int) $paymentGroup->fixed_total_terms),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropColumn('fixed_total_terms');
        });
    }
};
