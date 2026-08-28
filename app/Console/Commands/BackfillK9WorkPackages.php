<?php

namespace App\Console\Commands;

use App\Models\PaymentGroup;
use App\Models\PaymentTerm;
use App\Models\Project;
use App\Models\ProjectArea;
use App\Models\ProjectOffer;
use App\Models\Vendor;
use App\Models\WorkItem;
use App\Models\WorkPackageItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

#[Signature('kemang:backfill-k9-packages {path=template/Laporan Keuangan Proyek Kemang Update.xlsx}')]
#[Description('Backfill K9 merged-cell package blocks from the Kemang spreadsheet into work_package_items.')]
class BackfillK9WorkPackages extends Command
{
    /**
     * @var array<string, bool>
     */
    private array $sectionHeaders = [
        'Interior Produksi' => true,
        'Pekerjaan Lantai (ADP Marmer)' => true,
        'Pekerjaan Lantai (Infinity Stone)' => true,
        'Pekerjaan Lantai (Hamparan Stone)' => true,
        'Pekerjaan Lantai Marmer Sisa' => true,
        'Pekerjaan Lantai (Sehati Kramik)' => true,
        'Pekerjaan Meja Marmer' => true,
        'Pembelian Aksesoris Interior' => true,
        'Pembelian Tambahan' => true,
        'Oprasional Proyek' => true,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = base_path($this->argument('path'));

        if (! is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $sheet = $this->readSheet($path, 'K9');

        if ($sheet === null) {
            $this->error('Sheet K9 tidak ditemukan.');

            return self::FAILURE;
        }

        $project = Project::query()->where('slug', 'project-kemang')->firstOrFail();
        $area = ProjectArea::query()
            ->where('project_id', $project->id)
            ->where('code', 'K9')
            ->firstOrFail();
        $packages = $this->packagesFromMergedRanges($sheet['rows'], $sheet['merge_ranges']);
        $created = 0;
        $updated = 0;
        $packageItems = 0;
        $terms = 0;
        $removedLegacyPackages = 0;

        DB::transaction(function () use ($project, $area, $packages, &$created, &$updated, &$packageItems, &$terms, &$removedLegacyPackages): void {
            $removedLegacyPackages = $this->deleteLegacySectionPackages($project, $area);

            foreach ($packages as $package) {
                $workItem = $this->packageWorkItem($project, $area, $package);
                $wasRecentlyCreated = $workItem->wasRecentlyCreated;

                $this->syncOffer($project, $area, $workItem, $package);
                $this->syncPackageItems($workItem, $package['items']);
                $terms += $this->syncPaymentTerms($workItem, $package);
                $packageItems += count($package['items']);

                if ($wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        });

        $this->info("Selesai. Paket legacy dibersihkan: {$removedLegacyPackages}, paket baru: {$created}, paket update: {$updated}, item paket tersimpan: {$packageItems}, termin paket tersimpan/update: {$terms}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<int, string|null>>  $rows
     * @param  array<int, array{start_col: int, start_row: int, end_col: int, end_row: int}>  $mergeRanges
     * @return array<int, array{name: string, package_name: string|null, start_row: int, end_row: int, brand: string|null, rupiah: int|null, usd: float|null, payments: array<int, int>, overflow_item: array{name: string, brand: string|null, rupiah: int}|null, items: array<int, array{name: string, brand: string|null}>}>
     */
    private function packagesFromMergedRanges(array $rows, array $mergeRanges): array
    {
        $blocks = [];

        foreach ($mergeRanges as $range) {
            if ($range['start_row'] === $range['end_row'] || $range['start_col'] !== $range['end_col']) {
                continue;
            }

            if (! in_array($range['start_col'], range(2, 12), true)) {
                continue;
            }

            if ($range['start_col'] >= 5 && $this->hasPackageDefiningMerge($mergeRanges, $range['start_row'], $range['end_row'])) {
                continue;
            }

            $names = $this->namesInRange($rows, $range['start_row'], $range['end_row']);

            if (count($names) < 2) {
                continue;
            }

            $rupiah = $this->offerAmount($rows, $mergeRanges, $range['start_row'], $range['end_row'], 4);
            $usd = $this->offerAmount($rows, $mergeRanges, $range['start_row'], $range['end_row'], 3);
            $brand = $this->firstFilled($rows, $range['start_row'], $range['end_row'], 2);

            if ($rupiah === null && $usd === null && ! filled($brand)) {
                continue;
            }

            $key = "{$range['start_row']}:{$range['end_row']}";
            $blocks[$key] = [
                'name' => $names[0],
                'package_name' => $this->nearestSectionName($rows, $range['start_row']),
                'start_row' => $range['start_row'],
                'end_row' => $range['end_row'],
                'brand' => $brand,
                'rupiah' => $rupiah,
                'usd' => $usd,
                'payments' => $this->payments($rows[$range['start_row']] ?? []),
                'overflow_item' => $this->overflowItem($rows, $range['end_row'], $rupiah, $this->payments($rows[$range['start_row']] ?? [])),
                'items' => $this->packageItemsInRange($rows, $range['start_row'], $range['end_row'], $brand),
            ];
        }

        usort($blocks, fn (array $left, array $right): int => $left['start_row'] <=> $right['start_row']);

        return array_values($blocks);
    }

    private function hasPackageDefiningMerge(array $mergeRanges, int $startRow, int $paymentEndRow): bool
    {
        foreach ($mergeRanges as $range) {
            if (
                in_array($range['start_col'], [2, 3, 4], true)
                && $range['start_col'] === $range['end_col']
                && $range['start_row'] === $startRow
                && $range['end_row'] <= $paymentEndRow
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{name: string, brand: string|null, rupiah: int}|null
     */
    private function overflowItem(array $rows, int $endRow, ?int $rupiah, array $payments): ?array
    {
        $overflow = array_sum($payments) - (int) ($rupiah ?? 0);

        if ($overflow <= 0) {
            return null;
        }

        for ($rowNumber = $endRow + 1; $rowNumber <= $endRow + 3; $rowNumber++) {
            $name = trim((string) ($rows[$rowNumber][1] ?? ''));
            $amount = $this->numericInteger($rows[$rowNumber][4] ?? null);

            if ($name === '' || $amount === null) {
                continue;
            }

            if ($amount !== $overflow) {
                continue;
            }

            $brand = trim((string) ($rows[$rowNumber][2] ?? ''));

            return [
                'name' => $name,
                'brand' => $brand !== '' ? $brand : null,
                'rupiah' => $amount,
            ];
        }

        return null;
    }

    private function offerAmount(array $rows, array $mergeRanges, int $startRow, int $endRow, int $column): float|int|null
    {
        if ($this->hasVerticalMerge($mergeRanges, $column, $startRow, $endRow)) {
            return $column === 4
                ? $this->numericInteger($rows[$startRow][$column] ?? null)
                : $this->numeric($rows[$startRow][$column] ?? null);
        }

        $total = 0;

        for ($rowNumber = $startRow; $rowNumber <= $endRow; $rowNumber++) {
            $amount = $this->numeric($rows[$rowNumber][$column] ?? null);

            if ($amount !== null) {
                $total += $amount;
            }
        }

        if ($total <= 0) {
            return null;
        }

        return $column === 4 ? (int) round($total) : $total;
    }

    private function hasVerticalMerge(array $mergeRanges, int $column, int $startRow, int $endRow): bool
    {
        foreach ($mergeRanges as $range) {
            if (
                $range['start_col'] === $column
                && $range['end_col'] === $column
                && $range['start_row'] === $startRow
                && $range['end_row'] === $endRow
            ) {
                return true;
            }
        }

        return false;
    }

    private function nearestSectionName(array $rows, int $startRow): ?string
    {
        for ($rowNumber = $startRow - 1; $rowNumber >= 1; $rowNumber--) {
            $name = trim((string) ($rows[$rowNumber][1] ?? ''));

            if ($name === '') {
                continue;
            }

            if (isset($this->sectionHeaders[$name])) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function namesInRange(array $rows, int $startRow, int $endRow): array
    {
        $names = [];

        for ($rowNumber = $startRow; $rowNumber <= $endRow; $rowNumber++) {
            $name = trim((string) ($rows[$rowNumber][1] ?? ''));

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function firstFilled(array $rows, int $startRow, int $endRow, int $column): ?string
    {
        for ($rowNumber = $startRow; $rowNumber <= $endRow; $rowNumber++) {
            $value = trim((string) ($rows[$rowNumber][$column] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{name: string, brand: string|null}>
     */
    private function packageItemsInRange(array $rows, int $startRow, int $endRow, ?string $fallbackBrand): array
    {
        $items = [];

        for ($rowNumber = $startRow; $rowNumber <= $endRow; $rowNumber++) {
            $name = trim((string) ($rows[$rowNumber][1] ?? ''));

            if ($name === '') {
                continue;
            }

            $brand = trim((string) ($rows[$rowNumber][2] ?? ''));

            $items[] = [
                'name' => $name,
                'brand' => $brand !== '' ? $brand : $fallbackBrand,
            ];
        }

        return $items;
    }

    private function deleteLegacySectionPackages(Project $project, ProjectArea $area): int
    {
        $workItems = WorkItem::query()
            ->with('paymentGroups')
            ->where('project_id', $project->id)
            ->where('project_area_id', $area->id)
            ->where('notes', 'like', 'Import paket dari sheet K9 baris %')
            ->whereDoesntHave('transactions')
            ->get();

        foreach ($workItems as $workItem) {
            $paymentGroupIds = $workItem->paymentGroups->pluck('id');

            PaymentTerm::query()->whereIn('payment_group_id', $paymentGroupIds)->delete();
            PaymentGroup::query()->whereIn('id', $paymentGroupIds)->delete();
            ProjectOffer::query()->where('work_item_id', $workItem->id)->delete();
            WorkPackageItem::query()->where('work_item_id', $workItem->id)->delete();
            $workItem->delete();
        }

        return $workItems->count();
    }

    /**
     * @param  array{name: string, package_name: string|null, start_row: int, end_row: int, brand: string|null, rupiah: int|null, usd: float|null, payments: array<int, int>, items: array<int, array{name: string, brand: string|null}>}  $package
     */
    private function packageWorkItem(Project $project, ProjectArea $area, array $package): WorkItem
    {
        $vendor = filled($package['brand']) ? Vendor::firstOrCreate(['name' => $package['brand']]) : null;
        $workItem = WorkItem::query()
            ->where('project_id', $project->id)
            ->where('project_area_id', $area->id)
            ->where('notes', $this->signature($package))
            ->first();

        if (! $workItem) {
            $workItem = $this->findExistingPackageByItems($project, $area, $package);
        }

        if (! $workItem) {
            $workItem = WorkItem::query()
                ->where('project_id', $project->id)
                ->where('project_area_id', $area->id)
                ->where('name', $package['name'])
                ->whereDoesntHave('transactions')
                ->oldest('id')
                ->first();
        }

        if (! $workItem) {
            $workItem = new WorkItem([
                'project_id' => $project->id,
                'project_area_id' => $area->id,
            ]);
        }

        $workItem->fill([
            'vendor_id' => $vendor?->id,
            'name' => $package['name'],
            'package_name' => $package['package_name'],
            'brand' => $package['brand'],
            'offer_rupiah' => $package['rupiah'],
            'offer_usd' => $package['usd'],
            'notes' => $this->signature($package),
        ]);
        $workItem->save();

        return $workItem;
    }

    /**
     * @param  array{name: string, rupiah: int|null, items: array<int, array{name: string, brand: string|null}>}  $package
     */
    private function findExistingPackageByItems(Project $project, ProjectArea $area, array $package): ?WorkItem
    {
        $query = WorkItem::query()
            ->with('packageItems')
            ->where('project_id', $project->id)
            ->where('project_area_id', $area->id)
            ->whereHas('packageItems')
            ->whereDoesntHave('transactions');

        if ($package['rupiah'] !== null) {
            $query->where('offer_rupiah', $package['rupiah']);
        }

        $expectedNames = collect($package['items'])->pluck('name')->all();

        return $query->get()
            ->first(function (WorkItem $workItem) use ($expectedNames): bool {
                return $workItem->packageItems->pluck('name')->values()->all() === $expectedNames;
            });
    }

    /**
     * @param  array{name: string, package_name: string|null, brand: string|null, rupiah: int|null, usd: float|null}  $package
     */
    private function syncOffer(Project $project, ProjectArea $area, WorkItem $workItem, array $package): void
    {
        ProjectOffer::updateOrCreate(
            ['work_item_id' => $workItem->id],
            [
                'project_id' => $project->id,
                'project_area_id' => $area->id,
                'vendor_id' => $workItem->vendor_id,
                'project_name' => $project->name,
                'area' => $area->code,
                'pekerjaan' => $package['name'],
                'brand' => $package['brand'],
                'penawaran_usd' => $package['usd'],
                'penawaran_rupiah' => $package['rupiah'],
                'catatan' => filled($package['package_name'])
                    ? "Paket/Kategori: {$package['package_name']}. {$workItem->notes}"
                    : $workItem->notes,
            ],
        );
    }

    /**
     * @param  array<int, array{name: string, brand: string|null}>  $items
     */
    private function syncPackageItems(WorkItem $workItem, array $items): void
    {
        $workItem->packageItems()->delete();

        foreach ($items as $index => $item) {
            $vendor = filled($item['brand']) ? Vendor::firstOrCreate(['name' => $item['brand']]) : null;

            WorkPackageItem::create([
                'work_item_id' => $workItem->id,
                'vendor_id' => $vendor?->id,
                'name' => $item['name'],
                'brand' => $item['brand'],
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * @param  array{name: string, start_row: int, rupiah: int|null, usd: float|null, payments: array<int, int>, overflow_item: array{name: string, brand: string|null, rupiah: int}|null}  $package
     */
    private function syncPaymentTerms(WorkItem $workItem, array $package): int
    {
        if ($package['payments'] === []) {
            return 0;
        }

        $payments = $package['overflow_item'] !== null
            ? $this->splitPaymentsForOverflow($package['payments'], (int) ($package['rupiah'] ?? 0))['primary']
            : $package['payments'];
        $paymentGroup = PaymentGroup::query()
            ->where('project_id', $workItem->project_id)
            ->where(function ($query) use ($package, $workItem) {
                $query
                    ->where('work_item_id', $workItem->id)
                    ->orWhere('code', 'Paket-K9-'.$package['start_row']);
            })
            ->first();

        if (! $paymentGroup) {
            $paymentGroup = new PaymentGroup([
                'project_id' => $workItem->project_id,
                'work_item_id' => $workItem->id,
                'code' => 'Paket-K9-'.$package['start_row'],
                'status' => 'berjalan',
            ]);
        }

        $paymentGroup->fill([
            'work_item_id' => $workItem->id,
            'name' => $package['name'],
            'total_amount' => (int) ($package['rupiah'] ?? 0),
            'offer_rupiah_snapshot' => (int) ($package['rupiah'] ?? 0),
            'offer_usd_snapshot' => $package['usd'],
            'total_terms' => 1,
        ]);
        $paymentGroup->save();

        foreach ($payments as $number => $amount) {
            PaymentTerm::updateOrCreate(
                ['payment_group_id' => $paymentGroup->id, 'payment_number' => $number],
                [
                    'amount' => $amount,
                    'paid_at' => now()->toDateString(),
                    'notes' => 'Import termin dari blok paket merge sheet K9. Tanggal pembayaran asli tidak tersedia.',
                ],
            );
        }

        $paymentGroup->terms()
            ->whereNotIn('payment_number', array_keys($payments))
            ->delete();

        $paymentGroup->update([
            'paid_terms' => $paymentGroup->terms()->count(),
            'total_terms' => $this->automaticTotalTerms($paymentGroup),
        ]);

        return count($payments) + $this->syncOverflowPaymentTerms($workItem, $package);
    }

    /**
     * @param  array{name: string, start_row: int, rupiah: int|null, payments: array<int, int>, overflow_item: array{name: string, brand: string|null, rupiah: int}|null}  $package
     */
    private function syncOverflowPaymentTerms(WorkItem $workItem, array $package): int
    {
        if ($package['overflow_item'] === null) {
            return 0;
        }

        $overflowPayments = $this->splitPaymentsForOverflow($package['payments'], (int) ($package['rupiah'] ?? 0))['overflow'];

        if ($overflowPayments === []) {
            return 0;
        }

        $overflow = $package['overflow_item'];
        $vendor = filled($overflow['brand']) ? Vendor::firstOrCreate(['name' => $overflow['brand']]) : null;
        $targetWorkItem = WorkItem::query()
            ->where('project_id', $workItem->project_id)
            ->where('project_area_id', $workItem->project_area_id)
            ->where('name', $overflow['name'])
            ->oldest('id')
            ->first();

        if (! $targetWorkItem) {
            $targetWorkItem = WorkItem::create([
                'project_id' => $workItem->project_id,
                'project_area_id' => $workItem->project_area_id,
                'vendor_id' => $vendor?->id,
                'name' => $overflow['name'],
                'brand' => $overflow['brand'],
                'offer_rupiah' => $overflow['rupiah'],
                'notes' => 'Import item limpahan pembayaran dari sheet K9 baris '.$package['start_row'].'.',
            ]);
        }

        $targetGroup = PaymentGroup::query()
            ->where('project_id', $targetWorkItem->project_id)
            ->where('work_item_id', $targetWorkItem->id)
            ->first();

        if (! $targetGroup) {
            $targetGroup = new PaymentGroup([
                'project_id' => $targetWorkItem->project_id,
                'work_item_id' => $targetWorkItem->id,
                'code' => 'Termin-'.$targetWorkItem->id,
                'status' => 'berjalan',
            ]);
        }

        $targetGroup->fill([
            'work_item_id' => $targetWorkItem->id,
            'name' => $targetWorkItem->name,
            'total_amount' => (int) ($targetWorkItem->offer_rupiah ?? $overflow['rupiah']),
            'offer_rupiah_snapshot' => (int) ($targetWorkItem->offer_rupiah ?? $overflow['rupiah']),
            'offer_usd_snapshot' => $targetWorkItem->offer_usd,
            'total_terms' => 1,
        ]);
        $targetGroup->save();

        foreach ($overflowPayments as $number => $amount) {
            PaymentTerm::updateOrCreate(
                ['payment_group_id' => $targetGroup->id, 'payment_number' => $number],
                [
                    'amount' => $amount,
                    'paid_at' => now()->toDateString(),
                    'notes' => 'Limpahan selisih pembayaran dari '.$package['name'].'.',
                ],
            );
        }

        $targetGroup->update([
            'paid_terms' => $targetGroup->terms()->count(),
            'total_terms' => $this->automaticTotalTerms($targetGroup),
        ]);

        return count($overflowPayments);
    }

    private function automaticTotalTerms(PaymentGroup $paymentGroup): int
    {
        $offer = (int) ($paymentGroup->offer_rupiah_snapshot ?? $paymentGroup->total_amount ?? 0);
        $paid = (int) $paymentGroup->terms()->sum('amount');
        $highestPaymentNumber = (int) $paymentGroup->terms()->max('payment_number');

        if ($offer - $paid > 0) {
            return max($highestPaymentNumber + 1, 1);
        }

        return max($highestPaymentNumber, 1);
    }

    /**
     * @param  array<int, int>  $payments
     * @return array{primary: array<int, int>, overflow: array<int, int>}
     */
    private function splitPaymentsForOverflow(array $payments, int $offer): array
    {
        $primary = [];
        $overflow = [];
        $remaining = $offer;

        foreach ($payments as $number => $amount) {
            $primaryAmount = min($amount, max(0, $remaining));
            $overflowAmount = $amount - $primaryAmount;

            if ($primaryAmount > 0) {
                $primary[$number] = $primaryAmount;
            }

            if ($overflowAmount > 0) {
                $overflow[$number] = $overflowAmount;
            }

            $remaining -= $primaryAmount;
        }

        return compact('primary', 'overflow');
    }

    /**
     * @param  array{start_row: int, end_row: int}  $package
     */
    private function signature(array $package): string
    {
        return "Import paket merge dari sheet K9 baris {$package['start_row']}-{$package['end_row']}.";
    }

    /**
     * @param  array<int, string|null>  $row
     * @return array<int, int>
     */
    private function payments(array $row): array
    {
        $payments = [];

        for ($number = 1; $number <= 8; $number++) {
            $amount = $this->numericInteger($row[4 + $number] ?? null);

            if ($amount !== null) {
                $payments[$number] = $amount;
            }
        }

        return $payments;
    }

    /**
     * @return array{rows: array<int, array<int, string|null>>, merge_ranges: array<int, array{start_col: int, start_row: int, end_col: int, end_row: int}>}|null
     */
    private function readSheet(string $path, string $sheetName): ?array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return null;
        }

        $target = $this->sheetTarget($zip, $sheetName);

        if ($target === null) {
            $zip->close();

            return null;
        }

        $sharedStrings = $this->sharedStrings($zip);
        $sheetXml = simplexml_load_string($zip->getFromName($target));
        $zip->close();

        if ($sheetXml === false) {
            return null;
        }

        return [
            'rows' => $this->sheetRows($sheetXml, $sharedStrings),
            'merge_ranges' => $this->mergeRanges($sheetXml),
        ];
    }

    private function sheetTarget(ZipArchive $zip, string $sheetName): ?string
    {
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $targetRid = null;

        foreach ($workbook->sheets->sheet as $sheet) {
            if ((string) $sheet['name'] === $sheetName) {
                $attrs = $sheet->attributes('r', true);
                $targetRid = (string) $attrs['id'];
            }
        }

        if ($targetRid === null) {
            return null;
        }

        $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));

        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] !== $targetRid) {
                continue;
            }

            $target = ltrim((string) $rel['Target'], '/');

            return str_starts_with($target, 'xl/') ? $target : "xl/{$target}";
        }

        return null;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<int, string|null>>
     */
    private function sheetRows(\SimpleXMLElement $sheetXml, array $sharedStrings): array
    {
        $rows = [];

        foreach ($sheetXml->sheetData->row as $row) {
            $rowNumber = (int) $row['r'];
            $rowData = [];

            foreach ($row->c as $cell) {
                $colIndex = $this->columnIndex((string) $cell['r']);
                $type = (string) $cell['t'];
                $value = null;

                if (isset($cell->v)) {
                    $raw = (string) $cell->v;
                    $value = $type === 's' ? ($sharedStrings[(int) $raw] ?? '') : $raw;
                } elseif (isset($cell->is->t)) {
                    $value = (string) $cell->is->t;
                }

                $rowData[$colIndex] = $value;
            }

            $rows[$rowNumber] = $rowData;
        }

        return $rows;
    }

    /**
     * @return array<int, array{start_col: int, start_row: int, end_col: int, end_row: int}>
     */
    private function mergeRanges(\SimpleXMLElement $sheetXml): array
    {
        $ranges = [];

        foreach ($sheetXml->mergeCells->mergeCell ?? [] as $mergeCell) {
            $range = $this->rangeParts((string) $mergeCell['ref']);

            if ($range !== null) {
                $ranges[] = $range;
            }
        }

        return $ranges;
    }

    /**
     * @return array{start_col: int, start_row: int, end_col: int, end_row: int}|null
     */
    private function rangeParts(string $range): ?array
    {
        if (! str_contains($range, ':')) {
            return null;
        }

        [$start, $end] = explode(':', $range, 2);

        return [
            'start_col' => $this->columnIndex($start),
            'start_row' => $this->rowNumber($start),
            'end_col' => $this->columnIndex($end),
            'end_row' => $this->rowNumber($end),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedXml === false) {
            return [];
        }

        $sharedXmlEl = simplexml_load_string($sharedXml);
        $strings = [];

        foreach ($sharedXmlEl->si as $si) {
            $text = '';

            foreach ($si->xpath('.//*[local-name()="t"]') as $textNode) {
                $text .= (string) $textNode;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function columnIndex(string $cellReference): int
    {
        $letters = preg_replace('/[0-9]/', '', $cellReference);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }

    private function rowNumber(string $cellReference): int
    {
        return (int) preg_replace('/[^0-9]/', '', $cellReference);
    }

    private function numeric(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return $float > 0 ? $float : null;
    }

    private function numericInteger(mixed $value): ?int
    {
        $float = $this->numeric($value);

        return $float !== null ? (int) round($float) : null;
    }
}
