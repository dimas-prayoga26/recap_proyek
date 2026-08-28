<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveProject;
use App\Models\PaymentGroup;
use App\Models\ProjectArea;
use App\Models\ProjectOffer;
use App\Models\Vendor;
use App\Models\WorkItem;
use App\Models\WorkPackageItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectOfferController extends Controller
{
    use ResolvesActiveProject;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'area' => ['nullable', 'string', 'max:20'],
            'brand' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', Rule::in(['usd', 'idr'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $filters['area'] = $filters['area'] ?? 'K9';

        $project = $this->activeProject();

        $areas = $project
            ? ProjectArea::query()
                ->where('project_id', $project->id)
                ->orderByRaw("FIELD(code, 'K9', 'K8', 'C21', 'Lainnya') = 0")
                ->orderByRaw("FIELD(code, 'K9', 'K8', 'C21', 'Lainnya')")
                ->orderBy('code')
                ->pluck('code')
            : collect();

        $offers = ProjectOffer::query()
            ->with(['workItem.packageItems.vendor'])
            ->when($project, fn ($query) => $query->where('project_id', $project->id))
            ->where('area', $filters['area'])
            ->when($filters['brand'] ?? null, fn ($query, string $brand) => $query->where('brand', $brand))
            ->when($filters['currency'] ?? null, function ($query, string $currency) {
                $column = $currency === 'usd' ? 'penawaran_usd' : 'penawaran_rupiah';

                $query->whereNotNull($column)->where($column, '>', 0);
            })
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('pekerjaan', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('catatan', 'like', "%{$search}%")
                        ->orWhereHas('workItem', function ($query) use ($search): void {
                            $query->where('package_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('workItem.packageItems', function ($query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('brand', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByRaw("FIELD(area, 'K9', 'K8', 'C21', 'Lainnya')")
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('pages.offer-form', [
            'title' => 'Kategori Pekerjaan',
            'activeProject' => $project,
            'offers' => $offers,
            'areas' => $areas,
            'brands' => ProjectOffer::query()
                ->when($project, fn ($query) => $query->where('project_id', $project->id))
                ->where('area', $filters['area'])
                ->whereNotNull('brand')
                ->where('brand', '!=', '')
                ->distinct()
                ->orderBy('brand')
                ->pluck('brand'),
            'filters' => $filters,
            'totalOffers' => ProjectOffer::query()
                ->when($project, fn ($query) => $query->where('project_id', $project->id))
                ->where('area', $filters['area'])
                ->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'area' => ['required', 'string', 'max:20', Rule::notIn(['__new__'])],
            'pekerjaan' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'penawaran_usd' => ['nullable', 'numeric', 'min:0', 'required_without:penawaran_rupiah'],
            'penawaran_rupiah' => ['nullable', 'integer', 'min:0', 'required_without:penawaran_usd'],
            'fixed_total_terms' => ['nullable', 'integer', 'min:1', 'max:24'],
            'catatan' => ['nullable', 'string'],
            'is_package' => ['nullable', 'boolean'],
            'package_items' => ['nullable', 'array'],
            'package_items.*.name' => ['nullable', 'string', 'max:255'],
            'package_items.*.brand' => ['nullable', 'string', 'max:255'],
        ]);
        $validated['fixed_total_terms'] = (int) ($validated['fixed_total_terms'] ?? 8);
        $packageItems = $this->validatedPackageItems($request);
        $isPackage = $request->boolean('is_package');

        if ($isPackage && $packageItems->count() < 2) {
            return back()
                ->withErrors(['package_items' => 'Isi minimal 2 daftar pekerjaan dalam paket.'])
                ->withInput();
        }

        $project = $this->activeProject();

        if (! $project) {
            return redirect()
                ->route('project.index')
                ->with('status', 'Belum ada project aktif. Silakan buat atau pilih project terlebih dahulu.');
        }

        $projectArea = ProjectArea::firstOrCreate(
            ['project_id' => $project->id, 'code' => $validated['area']],
            ['name' => $project->name.' - '.$validated['area']],
        );
        $primaryBrand = filled($validated['brand'] ?? null)
            ? $validated['brand']
            : $packageItems->pluck('brand')->first(fn (?string $brand): bool => filled($brand));
        $vendor = filled($primaryBrand)
            ? Vendor::firstOrCreate(['name' => $primaryBrand])
            : null;

        DB::transaction(function () use ($validated, $project, $projectArea, $vendor, $primaryBrand, $packageItems, $isPackage): void {
            $workItemAttributes = [
                'project_id' => $project->id,
                'project_area_id' => $projectArea->id,
                'vendor_id' => $vendor?->id,
                'name' => $validated['pekerjaan'],
                'brand' => $primaryBrand,
                'offer_usd' => $validated['penawaran_usd'] ?? null,
                'offer_rupiah' => $validated['penawaran_rupiah'] ?? null,
                'fixed_total_terms' => $validated['fixed_total_terms'],
                'notes' => $validated['catatan'] ?? null,
            ];
            $workItem = $isPackage
                ? WorkItem::create($workItemAttributes)
                : WorkItem::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'project_area_id' => $projectArea->id,
                        'name' => $validated['pekerjaan'],
                    ],
                    [
                        'vendor_id' => $vendor?->id,
                        'brand' => $primaryBrand,
                        'offer_usd' => $validated['penawaran_usd'] ?? null,
                        'offer_rupiah' => $validated['penawaran_rupiah'] ?? null,
                        'fixed_total_terms' => $validated['fixed_total_terms'],
                        'notes' => $validated['catatan'] ?? null,
                    ],
                );

            $this->syncPackageItems($workItem, $isPackage ? $packageItems : collect());
            $this->syncPaymentGroupPlan($workItem, $validated['fixed_total_terms']);

            ProjectOffer::create([
                'area' => $validated['area'],
                'pekerjaan' => $validated['pekerjaan'],
                'brand' => $primaryBrand,
                'penawaran_usd' => $validated['penawaran_usd'] ?? null,
                'penawaran_rupiah' => $validated['penawaran_rupiah'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
                'project_id' => $project->id,
                'project_area_id' => $projectArea->id,
                'vendor_id' => $vendor?->id,
                'work_item_id' => $workItem->id,
                'project_name' => $project->name,
            ]);
        });

        return redirect()
            ->route('kategori-pekerjaan.index')
            ->with('status', 'Kategori pekerjaan berhasil disimpan.');
    }

    public function update(Request $request, ProjectOffer $projectOffer): RedirectResponse
    {
        $validated = $request->validate([
            'area' => ['required', 'string', 'max:20', Rule::notIn(['__new__'])],
            'pekerjaan' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'penawaran_usd' => ['nullable', 'numeric', 'min:0', 'required_without:penawaran_rupiah'],
            'penawaran_rupiah' => ['nullable', 'integer', 'min:0', 'required_without:penawaran_usd'],
            'fixed_total_terms' => ['nullable', 'integer', 'min:1', 'max:24'],
            'catatan' => ['nullable', 'string'],
            'is_package' => ['nullable', 'boolean'],
            'package_items' => ['nullable', 'array'],
            'package_items.*.name' => ['nullable', 'string', 'max:255'],
            'package_items.*.brand' => ['nullable', 'string', 'max:255'],
        ]);
        $validated['fixed_total_terms'] = (int) ($validated['fixed_total_terms'] ?? $projectOffer->workItem?->fixed_total_terms ?? 8);
        $packageItems = $this->validatedPackageItems($request);
        $isPackage = $request->boolean('is_package');

        if ($isPackage && $packageItems->count() < 2) {
            return back()
                ->withErrors(['package_items' => 'Isi minimal 2 daftar pekerjaan dalam paket.'])
                ->withInput();
        }

        $project = $this->activeProject();

        if (! $project) {
            return redirect()
                ->route('project.index')
                ->with('status', 'Belum ada project aktif. Silakan buat atau pilih project terlebih dahulu.');
        }

        $projectArea = ProjectArea::firstOrCreate(
            ['project_id' => $project->id, 'code' => $validated['area']],
            ['name' => $project->name.' - '.$validated['area']],
        );
        $primaryBrand = filled($validated['brand'] ?? null)
            ? $validated['brand']
            : $packageItems->pluck('brand')->first(fn (?string $brand): bool => filled($brand));
        $vendor = filled($primaryBrand)
            ? Vendor::firstOrCreate(['name' => $primaryBrand])
            : null;

        DB::transaction(function () use ($projectOffer, $validated, $projectArea, $vendor, $primaryBrand, $packageItems, $isPackage): void {
            if ($projectOffer->workItem) {
                $highestPaymentNumber = (int) $projectOffer->workItem
                    ->paymentGroups()
                    ->whereHas('terms')
                    ->withMax('terms', 'payment_number')
                    ->get()
                    ->max('terms_max_payment_number');

                if ((int) $validated['fixed_total_terms'] < $highestPaymentNumber) {
                    throw ValidationException::withMessages([
                        'fixed_total_terms' => 'Total Fix Termin tidak boleh lebih kecil dari pembayaran terakhir yang sudah tercatat.',
                    ]);
                }

                $projectOffer->workItem->update([
                    'project_area_id' => $projectArea->id,
                    'vendor_id' => $vendor?->id,
                    'name' => $validated['pekerjaan'],
                    'brand' => $primaryBrand,
                    'offer_usd' => $validated['penawaran_usd'] ?? null,
                    'offer_rupiah' => $validated['penawaran_rupiah'] ?? null,
                    'fixed_total_terms' => $validated['fixed_total_terms'],
                    'notes' => $validated['catatan'] ?? null,
                ]);

                $this->syncPackageItems($projectOffer->workItem, $isPackage ? $packageItems : collect());
                $this->syncPaymentGroupPlan($projectOffer->workItem, $validated['fixed_total_terms']);
            }

            $projectOffer->update([
                'area' => $validated['area'],
                'pekerjaan' => $validated['pekerjaan'],
                'brand' => $primaryBrand,
                'penawaran_usd' => $validated['penawaran_usd'] ?? null,
                'penawaran_rupiah' => $validated['penawaran_rupiah'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
                'project_area_id' => $projectArea->id,
                'vendor_id' => $vendor?->id,
            ]);
        });

        return redirect()
            ->route('kategori-pekerjaan.index', ['area' => $validated['area']])
            ->with('status', 'Kategori pekerjaan berhasil diperbarui.');
    }

    private function syncPaymentGroupPlan(WorkItem $workItem, int $fixedTotalTerms): void
    {
        $offerRupiah = (int) ($workItem->offer_rupiah ?? 0);
        $paymentGroup = PaymentGroup::query()
            ->where('project_id', $workItem->project_id)
            ->where('work_item_id', $workItem->id)
            ->first();
        $highestPaymentNumber = (int) ($paymentGroup?->terms()->max('payment_number') ?? 0);

        if (! $paymentGroup) {
            PaymentGroup::create([
                'project_id' => $workItem->project_id,
                'work_item_id' => $workItem->id,
                'code' => 'Termin-'.$workItem->id,
                'name' => $workItem->name,
                'total_amount' => $offerRupiah,
                'offer_rupiah_snapshot' => $offerRupiah,
                'offer_usd_snapshot' => $workItem->offer_usd,
                'total_terms' => $fixedTotalTerms,
                'fixed_total_terms' => $fixedTotalTerms,
                'paid_terms' => 0,
                'status' => 'berjalan',
            ]);

            return;
        }

        $paymentGroup->update([
            'name' => $workItem->name,
            'total_amount' => $offerRupiah,
            'offer_rupiah_snapshot' => $offerRupiah,
            'offer_usd_snapshot' => $workItem->offer_usd,
            'total_terms' => max($fixedTotalTerms, $paymentGroup->total_terms ?: 1, $highestPaymentNumber),
            'fixed_total_terms' => max(1, $fixedTotalTerms),
            'paid_terms' => $paymentGroup->terms()->count(),
        ]);
    }

    /**
     * @return Collection<int, array{name: string, brand: string|null}>
     */
    private function validatedPackageItems(Request $request): Collection
    {
        return collect($request->input('package_items', []))
            ->map(fn (array $item): array => [
                'name' => trim((string) ($item['name'] ?? '')),
                'brand' => filled($item['brand'] ?? null) ? trim((string) $item['brand']) : null,
            ])
            ->filter(fn (array $item): bool => $item['name'] !== '')
            ->values();
    }

    /**
     * @param  Collection<int, array{name: string, brand: string|null}>  $packageItems
     */
    private function syncPackageItems(WorkItem $workItem, Collection $packageItems): void
    {
        $workItem->packageItems()->delete();

        $packageItems->each(function (array $item, int $index) use ($workItem): void {
            $vendor = filled($item['brand'])
                ? Vendor::firstOrCreate(['name' => $item['brand']])
                : null;

            WorkPackageItem::create([
                'work_item_id' => $workItem->id,
                'vendor_id' => $vendor?->id,
                'name' => $item['name'],
                'brand' => $item['brand'],
                'sort_order' => $index + 1,
            ]);
        });
    }
}
