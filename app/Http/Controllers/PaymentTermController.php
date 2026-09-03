<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveProject;
use App\Models\PaymentGroup;
use App\Models\PaymentTerm;
use App\Models\ProjectTransaction;
use App\Models\ProjectTransactionAllocation;
use App\Models\ProjectTransactionAttachment;
use App\Models\Vendor;
use App\Models\WorkItem;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PaymentTermController extends Controller
{
    use ResolvesActiveProject;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'terms' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $activeProject = $this->activeProject();
        $vendors = Vendor::query()
            ->when($activeProject, fn ($query) => $query->whereHas('workItems', fn ($query) => $query->whereBelongsTo($activeProject)))
            ->orderBy('name')
            ->get();

        $workItems = WorkItem::query()
            ->with([
                'paymentGroups.terms.allocations.transaction.attachments',
                'paymentGroups.terms.allocations.transaction.serviceDetailWorkItem',
                'paymentGroups.terms.allocations.transaction.workItem',
                'vendor',
            ])
            ->when($activeProject, fn ($query) => $query->whereBelongsTo($activeProject))
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
        $serviceDetailOptions = WorkItem::query()
            ->with('vendor')
            ->when($activeProject, fn ($query) => $query->whereBelongsTo($activeProject))
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

        $filteredRows = filled($filters['terms'] ?? null)
            ? $allRows->filter(fn (array $row) => $row['summary']['total_terms'] === (int) $filters['terms'])->values()
            : $allRows;
        $paymentTotals = [
            'offer' => (int) $filteredRows->sum(fn (array $row): int => (int) $row['summary']['offer']),
            'paid' => (int) $filteredRows->sum(fn (array $row): int => (int) $row['summary']['paid']),
            'remaining' => (int) $filteredRows->sum(fn (array $row): int => max(0, (int) $row['summary']['remaining'])),
            'row_count' => $filteredRows->count(),
            'payment_count' => (int) $filteredRows->sum(fn (array $row): int => $row['payments']->count()),
        ];

        $maxTermsColumn = filled($filters['terms'] ?? null)
            ? (int) $filters['terms']
            : max(1, (int) ($filteredRows->max('summary.total_terms') ?: 1));

        $rows = $this->paginateRows($filteredRows, $request);

        return view('pages.payment-terms', [
            'title' => 'Rekap Pembayaran',
            'activeProject' => $activeProject,
            'filters' => $filters,
            'rows' => $rows,
            'vendors' => $vendors,
            'availableTermsOptions' => $availableTermsOptions,
            'maxTermsColumn' => $maxTermsColumn,
            'paymentTotals' => $paymentTotals,
            'serviceDetailOptions' => $serviceDetailOptions,
        ]);
    }

    public function updateServiceDetail(Request $request, PaymentTerm $paymentTerm): RedirectResponse
    {
        $validated = $request->validate([
            'service_detail_work_item_id' => ['nullable', 'exists:work_items,id'],
        ]);
        $activeProject = $this->activeProject();

        $paymentTerm->load([
            'paymentGroup',
            'allocations.transaction',
        ]);

        abort_if(! $activeProject || $paymentTerm->paymentGroup?->project_id !== $activeProject->id, 404);

        $transaction = $this->transactionForTerm($paymentTerm);

        abort_if(! $transaction, 404);

        $serviceDetailWorkItem = null;

        if (filled($validated['service_detail_work_item_id'] ?? null)) {
            $serviceDetailWorkItem = WorkItem::query()
                ->whereBelongsTo($activeProject)
                ->findOrFail($validated['service_detail_work_item_id']);
        }

        $transaction->forceFill([
            'service_detail_work_item_id' => $serviceDetailWorkItem && (int) $serviceDetailWorkItem->id !== (int) $transaction->work_item_id
                ? $serviceDetailWorkItem->id
                : null,
        ])->save();

        return back()->with('status', 'Rincian jasa berhasil diperbarui.');
    }

    public function destroy(PaymentTerm $paymentTerm): RedirectResponse
    {
        $activeProject = $this->activeProject();
        $paymentTerm->loadMissing('paymentGroup');

        abort_if(! $activeProject || $paymentTerm->paymentGroup?->project_id !== $activeProject->id, 404);

        $attachmentsToDelete = collect();

        DB::transaction(function () use ($paymentTerm, &$attachmentsToDelete): void {
            $paymentTerm->load([
                'paymentGroup',
                'allocations.transaction.attachments',
                'allocations.transaction.allocations.workItem.vendor',
            ]);

            $paymentGroup = $paymentTerm->paymentGroup;
            $allocations = $paymentTerm->allocations;
            $transactions = $allocations
                ->pluck('transaction')
                ->filter()
                ->unique('id');

            foreach ($transactions as $transaction) {
                $allocationsForTerm = $allocations
                    ->where('project_transaction_id', $transaction->id)
                    ->values();
                $remainingAllocations = $transaction->allocations
                    ->whereNotIn('id', $allocationsForTerm->pluck('id')->all())
                    ->values();

                ProjectTransactionAllocation::query()
                    ->whereIn('id', $allocationsForTerm->pluck('id')->all())
                    ->delete();

                if ($remainingAllocations->isEmpty()) {
                    $attachmentsToDelete = $attachmentsToDelete->merge($this->attachmentFilesFor($transaction));
                    $transaction->delete();

                    continue;
                }

                $replacementAllocation = $remainingAllocations->first();

                $transaction->forceFill([
                    'work_item_id' => $replacementAllocation->work_item_id,
                    'vendor_id' => $replacementAllocation->workItem?->vendor_id,
                    'payment_group_id' => $replacementAllocation->payment_group_id,
                    'amount' => (int) $remainingAllocations->sum('amount'),
                    'payment_number' => $replacementAllocation->payment_number,
                ])->save();
            }

            $paymentTerm->delete();

            if ($paymentGroup) {
                $this->refreshPaymentGroupCounters($paymentGroup);
            }
        });

        $attachmentsToDelete->each(
            fn (array $file): bool => Storage::disk($file['disk'])->delete($file['path']),
        );

        return back()->with('status', 'Pembayaran berhasil dihapus. Sisa pembayaran sudah dihitung ulang.');
    }

    private function paymentRows(Collection $workItems): Collection
    {
        return $workItems->map(function (WorkItem $workItem) {
            $paymentGroup = $workItem->paymentGroups->first();
            $summary = $this->paymentSummary($workItem, $paymentGroup);
            $vendorName = $workItem->vendor?->name ?? '-';

            return [
                'work_item' => $workItem,
                'vendor_name' => $vendorName,
                'can_update_service_detail' => $this->canUpdateServiceDetailFor($vendorName),
                'payment_group' => $paymentGroup,
                'summary' => $summary,
                'payments' => $paymentGroup
                    ? $paymentGroup->terms
                        ->sortBy('payment_number')
                        ->mapWithKeys(fn (PaymentTerm $term): array => [
                            $term->payment_number => [
                                'amount' => (int) $term->amount,
                                'detail' => $this->paymentDetail($term, $workItem, $paymentGroup),
                            ],
                        ])
                    : collect(),
            ];
        });
    }

    private function canUpdateServiceDetailFor(string $vendorName): bool
    {
        return str_starts_with(mb_strtolower(trim($vendorName)), 'jasa pasang');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateRows(Collection $rows, Request $request): LengthAwarePaginator
    {
        $perPage = 10;
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, (int) $request->integer('page', 1)), $lastPage);

        return new LengthAwarePaginator(
            $rows->forPage($currentPage, $perPage)->values(),
            $total,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => collect($request->query())->except('page')->all(),
            ],
        );
    }

    private function paymentDetail(PaymentTerm $term, WorkItem $workItem, PaymentGroup $paymentGroup): array
    {
        $allocation = $this->allocationForTerm($term);
        $transaction = $allocation?->transaction;
        $attachment = $transaction?->attachments->first();
        $workItemName = $transaction?->workItem?->name ?? $workItem->name;

        return [
            'payment_number' => $term->payment_number,
            'payment_term_id' => $term->id,
            'amount' => (int) $term->amount,
            'recorded_at' => $this->formatRecordedDate($transaction?->recorded_at ?? $term->paid_at),
            'work_item_id' => $transaction?->work_item_id ?? $paymentGroup->work_item_id,
            'work_item_name' => $workItemName,
            'service_detail_id' => $transaction?->service_detail_work_item_id,
            'service_detail' => $this->serviceDetailLabel($transaction?->serviceDetailWorkItem),
            'search_keyword' => $this->serviceKeyword($workItemName),
            'notes' => $allocation?->notes ?? $transaction?->notes ?? $term->notes ?? '-',
            'receipt_url' => $attachment ? $this->attachmentUrl($attachment) : '',
            'receipt_mime' => $attachment?->mime_type ?? '',
            'receipt_name' => $attachment?->original_name ?? '',
        ];
    }

    private function serviceKeyword(string $name): string
    {
        $stopWords = ['belanja', 'pasang', 'pekerjaan', 'jasa', 'non', 'parent', 'dan', 'di', 'ke', 'untuk', 'dengan', 'utama'];

        $keyword = collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter(fn (string $word): bool => $word !== '' && ! is_numeric($word) && ! in_array(mb_strtolower($word), $stopWords, true))
            ->sortByDesc(fn (string $word): int => mb_strlen($word))
            ->first();

        return $keyword ? mb_strtolower($keyword) : '';
    }

    private function attachmentUrl(ProjectTransactionAttachment $attachment): string
    {
        if ($attachment->disk === 'public') {
            return '/storage/'.ltrim($attachment->path, '/');
        }

        return Storage::disk($attachment->disk)->url($attachment->path);
    }

    private function allocationForTerm(PaymentTerm $term): ?ProjectTransactionAllocation
    {
        return $term->allocations
            ->sortByDesc(fn (ProjectTransactionAllocation $allocation): int => $allocation->transaction?->recorded_at?->timestamp ?? 0)
            ->first();
    }

    private function transactionForTerm(PaymentTerm $term): ?ProjectTransaction
    {
        return $this->allocationForTerm($term)?->transaction;
    }

    private function serviceDetailLabel(?WorkItem $workItem): string
    {
        if (! $workItem) {
            return '';
        }

        return trim(preg_replace('/^\s*Belanja\s+/i', '', $workItem->name) ?? $workItem->name);
    }

    private function attachmentFilesFor(ProjectTransaction $transaction): Collection
    {
        return $transaction->attachments
            ->map(fn (ProjectTransactionAttachment $attachment): array => [
                'disk' => $attachment->disk,
                'path' => $attachment->path,
            ])
            ->filter(fn (array $file): bool => filled($file['disk']) && filled($file['path']))
            ->values();
    }

    private function refreshPaymentGroupCounters(PaymentGroup $paymentGroup): void
    {
        $highestPaymentNumber = (int) $paymentGroup->terms()->max('payment_number');
        $paidAmount = (int) $paymentGroup->terms()->sum('amount');
        $offer = (int) ($paymentGroup->offer_rupiah_snapshot ?? $paymentGroup->total_amount ?? 0);
        $remaining = $offer - $paidAmount;

        $paymentGroup->update([
            'paid_terms' => $paymentGroup->terms()->count(),
            'total_terms' => $this->automaticTotalTerms($remaining, $highestPaymentNumber),
        ]);
    }

    private function formatRecordedDate(?CarbonInterface $date): string
    {
        if (! $date) {
            return '-';
        }

        return $date->format('d F Y');
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
