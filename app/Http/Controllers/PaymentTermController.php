<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveProject;
use App\Models\PaymentGroup;
use App\Models\ProjectArea;
use App\Models\WorkItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentTermController extends Controller
{
    use ResolvesActiveProject;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'area' => ['nullable', Rule::in(['K9', 'K8', 'C21', 'Lainnya'])],
            'terms' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);
        $filters['area'] = $filters['area'] ?? 'K9';

        $activeProject = $this->activeProject();
        $areas = $activeProject
            ? ProjectArea::query()->whereBelongsTo($activeProject)->orderBy('name')->get()
            : collect();
        $selectedArea = $areas->firstWhere('code', $filters['area']);

        $workItems = WorkItem::query()
            ->with(['paymentGroups.terms', 'projectArea', 'vendor'])
            ->when($activeProject, fn ($query) => $query->whereBelongsTo($activeProject))
            ->when($selectedArea, fn ($query) => $query->whereBelongsTo($selectedArea, 'projectArea'))
            ->orderBy('name')
            ->get();

        $allRows = $this->paymentRows($workItems);
        $availableTermsOptions = $allRows
            ->pluck('summary.total_terms')
            ->unique()
            ->sort()
            ->values();

        $rows = filled($filters['terms'] ?? null)
            ? $allRows->filter(fn (array $row) => $row['summary']['total_terms'] === (int) $filters['terms'])->values()
            : $allRows;

        $maxTermsColumn = filled($filters['terms'] ?? null)
            ? (int) $filters['terms']
            : max(1, (int) ($rows->max('summary.total_terms') ?: 1));

        return view('pages.payment-terms', [
            'title' => 'Rekap Pembayaran',
            'activeProject' => $activeProject,
            'areas' => $areas,
            'filters' => $filters,
            'rows' => $rows,
            'availableTermsOptions' => $availableTermsOptions,
            'maxTermsColumn' => $maxTermsColumn,
        ]);
    }

    private function paymentRows(Collection $workItems): Collection
    {
        return $workItems->map(function (WorkItem $workItem) {
            $paymentGroup = $workItem->paymentGroups->first();
            $summary = $this->paymentSummary($workItem, $paymentGroup);

            return [
                'work_item' => $workItem,
                'payment_group' => $paymentGroup,
                'summary' => $summary,
                'payments' => $paymentGroup
                    ? $paymentGroup->terms->keyBy('payment_number')
                    : collect(),
            ];
        });
    }

    private function paymentSummary(?WorkItem $workItem, ?PaymentGroup $paymentGroup): array
    {
        $offer = (int) ($paymentGroup?->offer_rupiah_snapshot ?? $paymentGroup?->total_amount ?? $workItem?->offer_rupiah ?? 0);
        $paid = (int) ($paymentGroup?->terms->sum('amount') ?? 0);
        $highestPaymentNumber = (int) ($paymentGroup?->terms->max('payment_number') ?? 0);
        $remaining = $offer - $paid;

        return [
            'offer' => $offer,
            'paid' => $paid,
            'remaining' => $remaining,
            'next_payment_number' => $remaining > 0 ? $highestPaymentNumber + 1 : max($highestPaymentNumber, 1),
            'total_terms' => $this->automaticTotalTerms($remaining, $highestPaymentNumber),
        ];
    }

    private function automaticTotalTerms(int $remaining, int $highestPaymentNumber): int
    {
        if ($remaining > 0) {
            return max($highestPaymentNumber + 1, 1);
        }

        return max($highestPaymentNumber, 1);
    }
}
