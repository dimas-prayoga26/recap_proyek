<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveProject;
use App\Models\PaymentGroup;
use App\Models\ProjectArea;
use App\Models\Vendor;
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
            'search' => ['nullable', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'terms' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $activeProject = $this->activeProject();
        $areas = $activeProject
            ? ProjectArea::query()->whereBelongsTo($activeProject)->orderBy('name')->get()
            : collect();
        $selectedArea = filled($filters['area'] ?? null) ? $areas->firstWhere('code', $filters['area']) : null;
        $vendors = Vendor::query()
            ->when($activeProject, fn ($query) => $query->whereHas('workItems', fn ($query) => $query->whereBelongsTo($activeProject)))
            ->orderBy('name')
            ->get();

        $workItems = WorkItem::query()
            ->with(['paymentGroups.terms', 'vendor'])
            ->when($activeProject, fn ($query) => $query->whereBelongsTo($activeProject))
            ->when($selectedArea, fn ($query) => $query->whereBelongsTo($selectedArea, 'projectArea'))
            ->when(filled($filters['vendor_id'] ?? null), fn ($query) => $query->where('vendor_id', $filters['vendor_id']))
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->get();

        $allRows = $this->paymentRows($workItems)
            ->sortBy(fn (array $row) => $row['vendor_name'].'|'.$row['work_item']->name)
            ->values();
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
            'filters' => $filters,
            'rows' => $rows,
            'vendors' => $vendors,
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
                'vendor_name' => $workItem->vendor?->name ?? '-',
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
