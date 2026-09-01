<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveProject;
use App\Models\Project;
use App\Models\ProjectOffer;
use App\Models\ProjectTransactionAllocation;
use App\Models\Vendor;
use App\Models\WorkItem;
use App\Models\WorkPackageItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectOfferController extends Controller
{
    use ResolvesActiveProject;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'brand' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', Rule::in(['usd', 'idr'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $projects = Project::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $activeProject = $this->activeProject();
        $project = filled($filters['project_id'] ?? null)
            ? $projects->firstWhere('id', (int) $filters['project_id'])
            : ($activeProject ?? $projects->first());

        $filters['project_id'] = $project?->id;

        $offers = ProjectOffer::query()
            ->with(['vendor', 'workItem.packageItems.vendor'])
            ->when($project, fn ($query) => $query->where('project_id', $project->id))
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
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('pages.offer-form', [
            'title' => 'Kategori Pekerjaan',
            'activeProject' => $project,
            'trueActiveProjectId' => $activeProject?->id,
            'projects' => $projects,
            'offers' => $offers,
            'vendors' => Vendor::query()->orderBy('name')->get(),
            'brands' => ProjectOffer::query()
                ->when($project, fn ($query) => $query->where('project_id', $project->id))
                ->whereNotNull('brand')
                ->where('brand', '!=', '')
                ->distinct()
                ->orderBy('brand')
                ->pluck('brand'),
            'filters' => $filters,
            'totalOffers' => ProjectOffer::query()
                ->when($project, fn ($query) => $query->where('project_id', $project->id))
                ->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeOfferAmounts($request);

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'pekerjaan' => ['required', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'penawaran_usd' => ['nullable', 'numeric', 'min:0', 'required_without:penawaran_rupiah'],
            'penawaran_rupiah' => ['nullable', 'integer', 'min:0', 'required_without:penawaran_usd'],
            'catatan' => ['nullable', 'string'],
            'is_package' => ['nullable', 'boolean'],
            'package_items' => ['nullable', 'array'],
            'package_items.*.name' => ['nullable', 'string', 'max:255'],
            'package_items.*.brand' => ['nullable', 'string', 'max:255'],
        ]);
        $packageItems = $this->validatedPackageItems($request);
        $isPackage = $request->boolean('is_package');

        if ($isPackage && $packageItems->count() < 2) {
            return back()
                ->withErrors(['package_items' => 'Isi minimal 2 daftar pekerjaan dalam paket.'])
                ->withInput();
        }

        $project = filled($validated['project_id'] ?? null)
            ? Project::query()->find((int) $validated['project_id'])
            : $this->activeProject();

        if (! $project) {
            return redirect()
                ->route('project.index')
                ->with('status', 'Belum ada project aktif. Silakan buat atau pilih project terlebih dahulu.');
        }

        $vendor = filled($validated['vendor_id'] ?? null)
            ? Vendor::query()->find($validated['vendor_id'])
            : null;
        $fallbackBrand = $packageItems->pluck('brand')->first(fn (?string $brand): bool => filled($brand));
        $vendor ??= filled($fallbackBrand) ? Vendor::firstOrCreate(['name' => $fallbackBrand]) : null;
        $primaryBrand = $vendor?->name;

        DB::transaction(function () use ($validated, $project, $vendor, $primaryBrand, $packageItems, $isPackage): void {
            $workItemAttributes = [
                'project_id' => $project->id,
                'vendor_id' => $vendor?->id,
                'name' => $validated['pekerjaan'],
                'brand' => $primaryBrand,
                'offer_usd' => $validated['penawaran_usd'] ?? null,
                'offer_rupiah' => $validated['penawaran_rupiah'] ?? null,
                'notes' => $validated['catatan'] ?? null,
            ];
            $workItem = $isPackage
                ? WorkItem::create($workItemAttributes)
                : WorkItem::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'name' => $validated['pekerjaan'],
                    ],
                    [
                        'vendor_id' => $vendor?->id,
                        'brand' => $primaryBrand,
                        'offer_usd' => $validated['penawaran_usd'] ?? null,
                        'offer_rupiah' => $validated['penawaran_rupiah'] ?? null,
                        'notes' => $validated['catatan'] ?? null,
                    ],
                );

            $this->syncPackageItems($workItem, $isPackage ? $packageItems : collect());

            ProjectOffer::create([
                'pekerjaan' => $validated['pekerjaan'],
                'brand' => $primaryBrand,
                'penawaran_usd' => $validated['penawaran_usd'] ?? null,
                'penawaran_rupiah' => $validated['penawaran_rupiah'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
                'project_id' => $project->id,
                'vendor_id' => $vendor?->id,
                'work_item_id' => $workItem->id,
                'project_name' => $project->name,
            ]);
        });

        return redirect()
            ->route('kategori-pekerjaan.index', ['project_id' => $project->id])
            ->with('status', 'Kategori pekerjaan berhasil disimpan.');
    }

    public function update(Request $request, ProjectOffer $projectOffer): RedirectResponse
    {
        $this->normalizeOfferAmounts($request);

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'pekerjaan' => ['required', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'penawaran_usd' => ['nullable', 'numeric', 'min:0', 'required_without:penawaran_rupiah'],
            'penawaran_rupiah' => ['nullable', 'integer', 'min:0', 'required_without:penawaran_usd'],
            'catatan' => ['nullable', 'string'],
            'is_package' => ['nullable', 'boolean'],
            'package_items' => ['nullable', 'array'],
            'package_items.*.name' => ['nullable', 'string', 'max:255'],
            'package_items.*.brand' => ['nullable', 'string', 'max:255'],
        ]);
        $packageItems = $this->validatedPackageItems($request);
        $isPackage = $request->boolean('is_package');

        if ($isPackage && $packageItems->count() < 2) {
            return back()
                ->withErrors(['package_items' => 'Isi minimal 2 daftar pekerjaan dalam paket.'])
                ->withInput();
        }

        $project = filled($validated['project_id'] ?? null)
            ? Project::query()->find((int) $validated['project_id'])
            : $this->activeProject();

        if (! $project) {
            return redirect()
                ->route('project.index')
                ->with('status', 'Belum ada project aktif. Silakan buat atau pilih project terlebih dahulu.');
        }

        $vendor = filled($validated['vendor_id'] ?? null)
            ? Vendor::query()->find($validated['vendor_id'])
            : null;
        $fallbackBrand = $packageItems->pluck('brand')->first(fn (?string $brand): bool => filled($brand));
        $vendor ??= filled($fallbackBrand) ? Vendor::firstOrCreate(['name' => $fallbackBrand]) : null;
        $primaryBrand = $vendor?->name;

        DB::transaction(function () use ($projectOffer, $validated, $project, $vendor, $primaryBrand, $packageItems, $isPackage): void {
            if ($projectOffer->workItem) {
                $projectOffer->workItem->update([
                    'project_id' => $project->id,
                    'vendor_id' => $vendor?->id,
                    'name' => $validated['pekerjaan'],
                    'brand' => $primaryBrand,
                    'offer_usd' => $validated['penawaran_usd'] ?? null,
                    'offer_rupiah' => $validated['penawaran_rupiah'] ?? null,
                    'notes' => $validated['catatan'] ?? null,
                ]);

                $this->syncPackageItems($projectOffer->workItem, $isPackage ? $packageItems : collect());
            }

            $projectOffer->update([
                'pekerjaan' => $validated['pekerjaan'],
                'brand' => $primaryBrand,
                'penawaran_usd' => $validated['penawaran_usd'] ?? null,
                'penawaran_rupiah' => $validated['penawaran_rupiah'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
                'project_id' => $project->id,
                'vendor_id' => $vendor?->id,
                'project_name' => $project->name,
            ]);
        });

        return redirect()
            ->route('kategori-pekerjaan.index', ['project_id' => $project->id])
            ->with('status', 'Kategori pekerjaan berhasil diperbarui.');
    }

    public function destroy(ProjectOffer $projectOffer): RedirectResponse
    {
        $projectId = $projectOffer->project_id;
        $workItem = $projectOffer->workItem;

        if ($workItem && $this->workItemHasPaymentHistory($workItem)) {
            return back()
                ->withErrors(['delete' => 'Kategori pekerjaan tidak bisa dihapus karena sudah punya transaksi atau rekap pembayaran.']);
        }

        DB::transaction(function () use ($projectOffer, $workItem): void {
            $projectOffer->delete();

            if (! $workItem) {
                return;
            }

            $hasOtherOffer = ProjectOffer::query()
                ->where('work_item_id', $workItem->id)
                ->exists();

            if (! $hasOtherOffer) {
                $workItem->paymentGroups()->delete();
                $workItem->packageItems()->delete();
                $workItem->delete();
            }
        });

        return redirect()
            ->route('kategori-pekerjaan.index', array_filter([
                'project_id' => $projectId,
            ]))
            ->with('status', 'Kategori pekerjaan berhasil dihapus.');
    }

    private function workItemHasPaymentHistory(WorkItem $workItem): bool
    {
        return $workItem->transactions()->exists()
            || $workItem->paymentGroups()->whereHas('terms')->exists()
            || ProjectTransactionAllocation::query()->where('work_item_id', $workItem->id)->exists();
    }

    private function normalizeOfferAmounts(Request $request): void
    {
        $request->merge([
            'penawaran_usd' => $this->normalizeDecimalInput($request->input('penawaran_usd')),
            'penawaran_rupiah' => $this->normalizeIntegerInput($request->input('penawaran_rupiah')),
        ]);
    }

    private function normalizeIntegerInput(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return $digits === '' ? null : $digits;
    }

    private function normalizeDecimalInput(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^\d,.]/', '', $value) ?? '';

        if ($value === '') {
            return null;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        $decimalSeparator = false;

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
        } elseif ($lastComma !== false) {
            $decimalSeparator = strlen($value) - $lastComma <= 3 ? ',' : false;
        } elseif ($lastDot !== false) {
            $decimalSeparator = strlen($value) - $lastDot <= 3 ? '.' : false;
        }

        if ($decimalSeparator === false) {
            $normalized = preg_replace('/\D/', '', $value);

            return $normalized === '' ? null : $normalized;
        }

        $parts = explode($decimalSeparator, $value);
        $decimal = array_pop($parts);
        $integer = preg_replace('/\D/', '', implode('', $parts));
        $decimal = preg_replace('/\D/', '', $decimal);

        if ($integer === '' && $decimal === '') {
            return null;
        }

        return ($integer === '' ? '0' : $integer).($decimal === '' ? '' : '.'.$decimal);
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
