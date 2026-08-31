<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveProject;
use App\Models\Project;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorController extends Controller
{
    use ResolvesActiveProject;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $activeProject = $this->activeProject();

        $vendors = $this->filteredVendorsQuery($filters)
            ->paginate(15)
            ->withQueryString();

        return view('pages.vendor-form', [
            'title' => 'Vendor',
            'activeProject' => $activeProject,
            'projects' => Project::query()->where('status', 'active')->orderBy('name')->get(),
            'vendors' => $vendors,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:vendors,name'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        Vendor::create($validated);

        return redirect()
            ->route('vendor.index')
            ->with('status', "Vendor \"{$validated['name']}\" berhasil ditambahkan.");
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('vendors', 'name')->ignore($vendor)],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($vendor, $validated): void {
            $vendor->update($validated);

            $vendor->workItems()->update(['brand' => $vendor->name]);
            $vendor->offers()->update(['brand' => $vendor->name]);
            $vendor->packageItems()->update(['brand' => $vendor->name]);
        });

        return redirect()
            ->route('vendor.index')
            ->with('status', "Vendor \"{$vendor->name}\" berhasil diperbarui.");
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $vendorName = $vendor->name;

        DB::transaction(function () use ($vendor): void {
            $vendor->workItems()->update([
                'vendor_id' => null,
                'brand' => null,
            ]);
            $vendor->offers()->update([
                'vendor_id' => null,
                'brand' => null,
            ]);
            $vendor->packageItems()->update([
                'vendor_id' => null,
                'brand' => null,
            ]);
            $vendor->transactions()->update(['vendor_id' => null]);

            $vendor->delete();
        });

        return redirect()
            ->route('vendor.index')
            ->with('status', "Vendor \"{$vendorName}\" berhasil dihapus.");
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($validated['file']->getRealPath(), 'r');
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return redirect()
                ->route('vendor.index')
                ->withErrors(['file' => 'File CSV kosong atau formatnya tidak sesuai.']);
        }

        $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]);
        $columnIndex = collect($header)
            ->map(fn ($column) => strtolower(trim((string) $column)))
            ->flip();

        $nameIndex = $columnIndex->get('nama vendor', 0);
        $contactIndex = $columnIndex->get('nama kontak');
        $phoneIndex = $columnIndex->get('no. telepon');
        $notesIndex = $columnIndex->get('catatan');

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $name = trim((string) ($row[$nameIndex] ?? ''));

            if ($name === '') {
                continue;
            }

            $vendor = Vendor::query()->firstOrCreate(
                ['name' => $name],
                [
                    'contact_name' => $contactIndex !== null ? (trim((string) ($row[$contactIndex] ?? '')) ?: null) : null,
                    'phone' => $phoneIndex !== null ? (trim((string) ($row[$phoneIndex] ?? '')) ?: null) : null,
                    'notes' => $notesIndex !== null ? (trim((string) ($row[$notesIndex] ?? '')) ?: null) : null,
                ],
            );

            $vendor->wasRecentlyCreated ? $imported++ : $skipped++;
        }

        fclose($handle);

        $message = "{$imported} vendor berhasil diimpor";
        $message .= $skipped > 0 ? ", {$skipped} dilewati karena nama sudah ada." : '.';

        return redirect()
            ->route('vendor.index')
            ->with('status', $message);
    }

    public function export(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $vendors = $this->filteredVendorsQuery($filters)->get();

        $rows = array_merge(
            [['Nama Vendor', 'Nama Kontak', 'No. Telepon', 'Catatan', 'Jumlah Pekerjaan', 'Jumlah Penawaran']],
            $vendors->map(fn (Vendor $vendor): array => [
                $vendor->name,
                $vendor->contact_name,
                $vendor->phone,
                $vendor->notes,
                $vendor->work_items_count,
                $vendor->offers_count,
            ])->all(),
        );

        $csv = collect($rows)
            ->map(function (array $row): string {
                $escaped = array_map(fn ($value) => '"'.str_replace('"', '""', (string) ($value ?? '')).'"', $row);

                return implode(',', $escaped);
            })
            ->implode("\r\n");

        $filename = 'vendor-semua-project-'.now()->format('Ymd-His').'.csv';

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * @param  array{search?: string|null}  $filters
     */
    private function filteredVendorsQuery(array $filters): Builder
    {
        return Vendor::query()
            ->withCount(['workItems', 'offers'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');
    }
}
