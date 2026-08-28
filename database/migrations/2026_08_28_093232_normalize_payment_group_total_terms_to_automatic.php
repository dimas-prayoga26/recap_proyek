<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payment_groups')
            ->orderBy('id')
            ->each(function (object $paymentGroup): void {
                $summary = DB::table('payment_terms')
                    ->where('payment_group_id', $paymentGroup->id)
                    ->selectRaw('COALESCE(SUM(amount), 0) as paid_amount, COALESCE(MAX(payment_number), 0) as highest_payment_number, COUNT(*) as paid_terms')
                    ->first();

                $offer = (int) ($paymentGroup->offer_rupiah_snapshot ?? $paymentGroup->total_amount ?? 0);
                $paidAmount = (int) ($summary?->paid_amount ?? 0);
                $highestPaymentNumber = (int) ($summary?->highest_payment_number ?? 0);
                $remaining = $offer - $paidAmount;
                $totalTerms = $remaining > 0 ? $highestPaymentNumber + 1 : max($highestPaymentNumber, 1);

                DB::table('payment_groups')
                    ->where('id', $paymentGroup->id)
                    ->update([
                        'paid_terms' => (int) ($summary?->paid_terms ?? 0),
                        'total_terms' => max($totalTerms, 1),
                    ]);
            });
    }

    public function down(): void
    {
        //
    }
};
