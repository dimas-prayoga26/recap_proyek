<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveProject;
use App\Models\PaymentGroup;
use App\Models\ProjectArea;
use App\Models\WorkItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'title' => 'Termin Pembayaran',
            'activeProject' => $activeProject,
            'areas' => $areas,
            'filters' => $filters,
            'rows' => $rows,
            'availableTermsOptions' => $availableTermsOptions,
            'maxTermsColumn' => $maxTermsColumn,
        ]);
    }

    public function update(Request $request, WorkItem $workItem): RedirectResponse
    {
        $validated = $request->validate([
            'fixed_total_terms' => ['required', 'integer', 'min:1', 'max:24'],
            'area' => ['nullable', Rule::in(['K9', 'K8', 'C21', 'Lainnya'])],
            'terms' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $activeProject = $this->activeProject();

        if ($activeProject && ! $workItem->project()->whereKey($activeProject->id)->exists()) {
            abort(404);
        }

        $paymentGroup = $this->paymentGroupFor($workItem);
        $highestPaymentNumber = (int) $paymentGroup->terms()->max('payment_number');
        $fixedTotalTerms = (int) $validated['fixed_total_terms'];

        if ($fixedTotalTerms < $highestPaymentNumber) {
            throw ValidationException::withMessages([
                'fixed_total_terms' => 'Total Termin Rencana tidak boleh lebih kecil dari pembayaran terakhir yang sudah tercatat.',
            ]);
        }

        $workItem->update([
            'fixed_total_terms' => $fixedTotalTerms,
        ]);

        $paymentGroup->update([
            'fixed_total_terms' => $fixedTotalTerms,
            'total_terms' => max($fixedTotalTerms, $paymentGroup->total_terms ?: 1, $highestPaymentNumber),
        ]);

        $redirectTerms = filled($validated['terms'] ?? null) ? $fixedTotalTerms : null;

        return redirect()
            ->route('termin-pembayaran.index', [
                'area' => $validated['area'] ?? $workItem->projectArea?->code ?? 'K9',
                'terms' => $redirectTerms,
            ])
            ->with('status', 'Total Termin Rencana berhasil diperbarui.');
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
        $fixedTotalTerms = (int) ($workItem?->fixed_total_terms ?? $paymentGroup?->fixed_total_terms ?? $paymentGroup?->total_terms ?? 8);

        return [
            'offer' => $offer,
            'paid' => $paid,
            'remaining' => $offer - $paid,
            'next_payment_number' => $highestPaymentNumber + 1,
            'total_terms' => max($fixedTotalTerms, $highestPaymentNumber, 1),
        ];
    }

    private function paymentGroupFor(WorkItem $workItem): PaymentGroup
    {
        $paymentGroup = $workItem->paymentGroups->first();

        if ($paymentGroup) {
            return $paymentGroup;
        }

        $offerRupiah = (int) ($workItem->offer_rupiah ?? 0);

        return PaymentGroup::create([
            'project_id' => $workItem->project_id,
            'work_item_id' => $workItem->id,
            'code' => 'Termin-'.$workItem->id,
            'name' => $workItem->name,
            'total_amount' => $offerRupiah,
            'offer_rupiah_snapshot' => $offerRupiah,
            'offer_usd_snapshot' => $workItem->offer_usd,
            'total_terms' => max(1, (int) ($workItem->fixed_total_terms ?? 8)),
            'fixed_total_terms' => max(1, (int) ($workItem->fixed_total_terms ?? 8)),
            'paid_terms' => 0,
            'status' => 'berjalan',
        ]);
    }
}
