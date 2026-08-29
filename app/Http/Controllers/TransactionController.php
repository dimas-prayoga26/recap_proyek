<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveProject;
use App\Models\PaymentGroup;
use App\Models\PaymentTerm;
use App\Models\Project;
use App\Models\ProjectTransaction;
use App\Models\ProjectTransactionAllocation;
use App\Models\TransactionCategory;
use App\Models\Vendor;
use App\Models\WorkItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransactionController extends Controller
{
    use ResolvesActiveProject;

    public function createIncome(): View
    {
        return $this->create('masuk', 'Input Credit');
    }

    public function createExpense(): View
    {
        return $this->create('keluar', 'Input Debit');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['masuk', 'keluar'])],
            'project_id' => ['required', 'exists:projects,id'],
            'transaction_category_id' => ['nullable', 'exists:transaction_categories,id'],
            'work_item_id' => ['required', 'exists:work_items,id'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'amount' => ['required', 'integer', 'min:0'],
            'recorded_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'payment_group_code' => ['nullable', 'string', 'max:255'],
            'receipt_total' => ['nullable', 'integer', 'min:0'],
            'payment_number' => ['nullable', 'integer', 'min:1'],
            'payment_total' => ['nullable', 'integer', 'min:1'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.work_item_id' => ['nullable', 'exists:work_items,id'],
            'allocations.*.amount' => ['nullable', 'integer', 'min:1'],
            'allocations.*.payment_number' => ['nullable', 'integer', 'min:1'],
            'allocations.*.notes' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $project = Project::query()->findOrFail($validated['project_id']);
        $workItem = WorkItem::query()->findOrFail($validated['work_item_id']);

        if ((int) $workItem->project_id !== (int) $project->id) {
            throw ValidationException::withMessages([
                'project_id' => 'Project Holding dan pekerjaan harus dari project yang sama.',
            ]);
        }

        $transactionCategoryId = $validated['transaction_category_id']
            ?? TransactionCategory::query()
                ->where('type', $validated['type'])
                ->where('status', 'active')
                ->orderBy('name')
                ->value('id');
        $additionalAllocations = $this->additionalAllocations($validated, $project);
        $additionalTotal = (int) $additionalAllocations->sum('amount');

        if ($additionalTotal > $validated['amount']) {
            return back()
                ->withErrors(['allocations' => 'Total alokasi tambahan tidak boleh lebih besar dari nominal transaksi.'])
                ->withInput();
        }

        $transaction = DB::transaction(function () use ($request, $validated, $project, $workItem, $transactionCategoryId, $additionalAllocations, $additionalTotal) {
            $paymentGroup = $this->paymentGroupFromTransaction($validated, $project, $workItem);
            $primaryAmount = $validated['type'] === 'keluar'
                ? $validated['amount'] - $additionalTotal
                : $validated['amount'];

            $transaction = ProjectTransaction::create([
                'project_id' => $project->id,
                'transaction_category_id' => $transactionCategoryId,
                'work_item_id' => $workItem->id,
                'vendor_id' => $validated['vendor_id'] ?? null,
                'payment_group_id' => $paymentGroup?->id,
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'recorded_at' => $validated['recorded_at'],
                'payment_number' => $validated['payment_number'] ?? null,
                'payment_total' => $validated['type'] === 'keluar' ? null : ($validated['payment_total'] ?? null),
                'receipt_total' => $validated['receipt_total'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($paymentGroup && $primaryAmount > 0) {
                $paymentTerm = $this->syncPaymentTerm(
                    $paymentGroup,
                    $validated['payment_number'] ?? 1,
                    $primaryAmount,
                    $validated['recorded_at'],
                    $validated['notes'] ?? null,
                );

                $this->recordAllocation($transaction, $workItem, $paymentGroup, $paymentTerm, $primaryAmount, 'primary', $validated['notes'] ?? null);
                $transaction->forceFill(['payment_total' => $paymentGroup->refresh()->total_terms])->save();
            }

            foreach ($additionalAllocations as $allocation) {
                $targetWorkItem = WorkItem::query()->findOrFail($allocation['work_item_id']);
                $targetGroup = $this->paymentGroupForWorkItem($project, $targetWorkItem);
                $targetTerm = $this->syncPaymentTerm(
                    $targetGroup,
                    $allocation['payment_number'] ?? $this->nextPaymentNumber($targetGroup),
                    $allocation['amount'],
                    $validated['recorded_at'],
                    $allocation['notes'] ?? 'Alokasi dari transaksi '.$transaction->id,
                );

                $this->recordAllocation($transaction, $targetWorkItem, $targetGroup, $targetTerm, $allocation['amount'], 'additional', $allocation['notes'] ?? null);
            }

            if ($request->hasFile('receipt')) {
                $file = $request->file('receipt');
                $path = $file->store('transaction-receipts', 'public');

                $transaction->attachments()->create([
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            return $transaction;
        });

        $route = $validated['type'] === 'masuk' ? 'uang-masuk.index' : 'uang-keluar.index';

        return redirect()
            ->route($route)
            ->with('status', 'Transaksi berhasil disimpan.');
    }

    private function create(string $type, string $title): View
    {
        $activeProject = $this->activeProject();
        $projects = Project::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $categories = TransactionCategory::query()
            ->where('type', $type)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $workItems = WorkItem::query()
            ->with(['packageItems.vendor', 'paymentGroups.terms', 'project', 'vendor'])
            ->whereHas('project', fn ($query) => $query->where('status', 'active'))
            ->orderBy('package_name')
            ->orderBy('name')
            ->get();

        return view('pages.transaction-form', [
            'mode' => $type,
            'title' => $title,
            'activeProject' => $activeProject,
            'projects' => $projects,
            'categories' => $categories,
            'workItems' => $workItems,
            'workItemTerminInfo' => $this->workItemTerminInfo($workItems),
            'vendors' => Vendor::query()
                ->orderBy('name')
                ->get(),
        ]);
    }

    private function additionalAllocations(array $validated, Project $project): Collection
    {
        if (($validated['type'] ?? null) !== 'keluar') {
            return collect();
        }

        $allocations = collect($validated['allocations'] ?? [])
            ->map(fn (array $allocation): array => [
                'work_item_id' => (int) ($allocation['work_item_id'] ?? 0),
                'amount' => (int) ($allocation['amount'] ?? 0),
                'payment_number' => filled($allocation['payment_number'] ?? null) ? (int) $allocation['payment_number'] : null,
                'notes' => filled($allocation['notes'] ?? null) ? trim((string) $allocation['notes']) : null,
            ])
            ->filter(fn (array $allocation): bool => $allocation['work_item_id'] > 0 && $allocation['amount'] > 0)
            ->values();

        if ($allocations->isEmpty()) {
            return $allocations;
        }

        $validWorkItemIds = WorkItem::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $allocations->pluck('work_item_id'))
            ->pluck('id')
            ->all();

        if ($allocations->pluck('work_item_id')->diff($validWorkItemIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'Alokasi tambahan harus memakai pekerjaan dari project yang sama.',
            ]);
        }

        return $allocations;
    }

    private function paymentGroupFromTransaction(array $validated, Project $project, WorkItem $workItem): ?PaymentGroup
    {
        if ($validated['type'] !== 'keluar') {
            return null;
        }

        $code = filled($validated['payment_group_code'] ?? null)
            ? $validated['payment_group_code']
            : 'Termin-'.$workItem->id;

        return $this->paymentGroupForWorkItem(
            $project,
            $workItem,
            $code,
            $validated['receipt_total'] ?? null,
        );
    }

    private function paymentGroupForWorkItem(Project $project, WorkItem $workItem, ?string $code = null, ?int $totalAmount = null): PaymentGroup
    {
        $code ??= 'Termin-'.$workItem->id;
        $offerRupiah = (int) ($workItem->offer_rupiah ?? 0);

        $paymentGroup = PaymentGroup::query()
            ->where('project_id', $project->id)
            ->where(function ($query) use ($code, $workItem) {
                $query
                    ->where('work_item_id', $workItem->id)
                    ->orWhere('code', $code);
            })
            ->first();

        if (! $paymentGroup) {
            $paymentGroup = new PaymentGroup([
                'project_id' => $project->id,
                'work_item_id' => $workItem->id,
                'code' => $code,
                'name' => $workItem->name,
                'status' => 'berjalan',
            ]);
        }

        $paymentGroup->fill([
            'work_item_id' => $workItem->id,
            'total_amount' => $totalAmount ?? $offerRupiah,
            'offer_rupiah_snapshot' => $offerRupiah,
            'offer_usd_snapshot' => $workItem->offer_usd,
            'total_terms' => $this->automaticTotalTermsForGroup($paymentGroup),
        ]);
        $paymentGroup->save();

        return $paymentGroup;
    }

    private function syncPaymentTerm(PaymentGroup $paymentGroup, int $paymentNumber, int $amount, string $paidAt, ?string $notes): PaymentTerm
    {
        $paymentTerm = PaymentTerm::updateOrCreate(
            [
                'payment_group_id' => $paymentGroup->id,
                'payment_number' => $paymentNumber,
            ],
            [
                'amount' => $amount,
                'paid_at' => $paidAt,
                'notes' => $notes,
            ],
        );

        $paymentGroup->update([
            'paid_terms' => $paymentGroup->terms()->count(),
            'total_terms' => $this->automaticTotalTermsForGroup($paymentGroup),
        ]);

        return $paymentTerm;
    }

    private function recordAllocation(ProjectTransaction $transaction, WorkItem $workItem, PaymentGroup $paymentGroup, PaymentTerm $paymentTerm, int $amount, string $role, ?string $notes): void
    {
        ProjectTransactionAllocation::create([
            'project_transaction_id' => $transaction->id,
            'work_item_id' => $workItem->id,
            'payment_group_id' => $paymentGroup->id,
            'payment_term_id' => $paymentTerm->id,
            'amount' => $amount,
            'payment_number' => $paymentTerm->payment_number,
            'role' => $role,
            'notes' => $notes,
        ]);
    }

    private function nextPaymentNumber(PaymentGroup $paymentGroup): int
    {
        $highestPaymentNumber = (int) $paymentGroup->terms()->max('payment_number');
        $remaining = $this->remainingAmountForGroup($paymentGroup);

        return $remaining > 0 ? $highestPaymentNumber + 1 : max($highestPaymentNumber, 1);
    }

    private function automaticTotalTermsForGroup(PaymentGroup $paymentGroup): int
    {
        $highestPaymentNumber = (int) $paymentGroup->terms()->max('payment_number');
        $remaining = $this->remainingAmountForGroup($paymentGroup);

        if ($remaining > 0) {
            return max($highestPaymentNumber + 1, 1);
        }

        return max($highestPaymentNumber, 1);
    }

    private function remainingAmountForGroup(PaymentGroup $paymentGroup): int
    {
        $offer = (int) ($paymentGroup->offer_rupiah_snapshot ?? $paymentGroup->total_amount ?? 0);
        $paid = (int) $paymentGroup->terms()->sum('amount');

        return $offer - $paid;
    }

    private function workItemTerminInfo(Collection $workItems): array
    {
        $sharedAllocations = $this->sharedAllocationsByWorkItem($workItems);

        return $workItems
            ->mapWithKeys(function (WorkItem $workItem) use ($sharedAllocations) {
                $paymentGroup = $workItem->paymentGroups->first();
                $terms = $paymentGroup?->terms
                    ->sortBy('payment_number')
                    ->map(fn (PaymentTerm $term) => [
                        'number' => $term->payment_number,
                        'amount' => $term->amount,
                        'paid_at' => $term->paid_at?->format('d/m/Y'),
                    ])
                    ->values() ?? collect();
                $offer = (int) ($paymentGroup?->offer_rupiah_snapshot ?? $paymentGroup?->total_amount ?? $workItem->offer_rupiah ?? 0);
                $paid = (int) $terms->sum('amount');
                $remaining = $offer - $paid;
                $highestPaymentNumber = (int) ($terms->max('number') ?? 0);
                $totalTerms = $remaining > 0 ? max($highestPaymentNumber + 1, 1) : max($highestPaymentNumber, 1);

                /** @var Collection<int, array{name: string, amount: int}> $allocations */
                $allocations = $sharedAllocations->get($workItem->id, collect());
                $allocatedToOthers = (int) $allocations->sum('amount');

                return [
                    $workItem->id => [
                        'offer' => $offer,
                        'paid' => $paid + $allocatedToOthers,
                        'remaining' => $remaining,
                        'allocated_to_others' => $allocatedToOthers,
                        'shared_allocations' => $allocations->values(),
                        'next_payment_number' => $remaining > 0 ? $highestPaymentNumber + 1 : max($highestPaymentNumber, 1),
                        'total_terms' => $totalTerms,
                        'terms' => $terms,
                        'package_name' => $workItem->package_name,
                        'package_items' => $workItem->packageItems
                            ->map(fn ($item) => [
                                'name' => $item->name,
                                'brand' => $item->brand,
                            ])
                            ->values(),
                        'notes' => $workItem->notes,
                    ],
                ];
            })
            ->all();
    }

    /**
     * Amounts already paid out to OTHER work items through transactions where each
     * given work item was the primary one, keyed by that primary work item id.
     *
     * @return Collection<int, Collection<int, array{name: string, amount: int}>>
     */
    private function sharedAllocationsByWorkItem(Collection $workItems): Collection
    {
        if ($workItems->isEmpty()) {
            return collect();
        }

        return ProjectTransactionAllocation::query()
            ->where('role', 'additional')
            ->whereHas('transaction', fn ($query) => $query->whereIn('work_item_id', $workItems->pluck('id')))
            ->with(['transaction:id,work_item_id', 'workItem:id,name'])
            ->get()
            ->groupBy(fn (ProjectTransactionAllocation $allocation) => $allocation->transaction->work_item_id)
            ->map(fn (Collection $allocations) => $allocations
                ->groupBy('work_item_id')
                ->map(fn (Collection $perWorkItem) => [
                    'name' => $perWorkItem->first()->workItem?->name ?? '-',
                    'amount' => (int) $perWorkItem->sum('amount'),
                ])
                ->values());
    }
}
