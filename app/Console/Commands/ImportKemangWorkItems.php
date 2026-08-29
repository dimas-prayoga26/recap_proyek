<?php

namespace App\Console\Commands;

use App\Models\PaymentGroup;
use App\Models\PaymentTerm;
use App\Models\Project;
use App\Models\ProjectOffer;
use App\Models\Vendor;
use App\Models\WorkItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

#[Signature('kemang:import-work-items {path=template/Laporan Keuangan Proyek Kemang Update.xlsx}')]
#[Description('Import Work Item, Kategori Pekerjaan (Project Offer), and Termin Pembayaran history for Project Kemang from the K9/K8/C21 sheets, without touching items that already exist.')]
class ImportKemangWorkItems extends Command
{
    /**
     * Job labels that repeat across a sheet (e.g. "Ongkos Pasang") and must be
     * qualified with the preceding real item name to stay distinct.
     *
     * @var array<string, bool>
     */
    private array $genericLabels = [
        'ongkos pasang' => true,
        'ongkos pasang tambahan' => true,
        'ongkos pasang hand rail' => true,
        'ongkos pola' => true,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = base_path($this->argument('path'));

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $projectDefinitions = [
            'K9' => 'Project Kemang K9',
            'K8' => 'Project Kemang K8',
            'C21' => 'Project Kemang C21',
        ];

        $created = 0;
        $skippedExisting = 0;
        $skippedHeaderRows = 0;
        $offersCreated = 0;
        $offersSkippedExisting = 0;
        $termsImported = 0;
        $termsSkippedUsdOnly = 0;
        $importedAt = now()->toDateString();

        DB::transaction(function () use ($path, $projectDefinitions, $importedAt, &$created, &$skippedExisting, &$skippedHeaderRows, &$offersCreated, &$offersSkippedExisting, &$termsImported, &$termsSkippedUsdOnly): void {
            foreach ($projectDefinitions as $code => $projectName) {
                $project = Project::firstOrCreate(
                    ['slug' => Str::slug($projectName)],
                    [
                        'name' => $projectName,
                        'status' => 'active',
                        'description' => 'Project holding hasil impor sheet '.$code.'.',
                    ],
                );

                $rows = $this->readSheetRows($path, $code);

                if ($rows === null) {
                    $this->warn("Sheet {$code} tidak ditemukan, dilewati.");

                    continue;
                }

                $lastRealName = null;
                $seenNames = [];

                foreach ($rows as $rowNumber => $row) {
                    if ($rowNumber <= 3) {
                        continue;
                    }

                    $originalName = trim((string) ($row[1] ?? ''));

                    if ($originalName === '') {
                        continue;
                    }

                    $hasOtherData = false;
                    foreach ($row as $colIndex => $value) {
                        if ($colIndex === 1) {
                            continue;
                        }

                        if (trim((string) $value) !== '') {
                            $hasOtherData = true;
                            break;
                        }
                    }

                    if (! $hasOtherData) {
                        $skippedHeaderRows++;

                        continue;
                    }

                    $brand = trim((string) ($row[2] ?? ''));
                    $usd = $this->numeric($row[3] ?? null);
                    $rupiah = $this->numeric($row[4] ?? null);

                    $normalized = mb_strtolower($originalName);
                    $isGeneric = isset($this->genericLabels[$normalized]);
                    $name = $originalName;

                    if ($isGeneric && $lastRealName !== null) {
                        $name = $originalName.' - '.$lastRealName;
                    } elseif (isset($seenNames[$normalized])) {
                        $seenNames[$normalized]++;
                        $name = $originalName.' ('.$seenNames[$normalized].')';
                    } else {
                        $seenNames[$normalized] = 1;
                    }

                    if (! $isGeneric) {
                        $lastRealName = $originalName;
                    }

                    $vendorId = null;

                    if ($brand !== '') {
                        $vendorId = Vendor::firstOrCreate(['name' => $brand])->id;
                    }

                    $workItemExists = WorkItem::query()
                        ->where('project_id', $project->id)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                        ->exists();

                    if ($workItemExists) {
                        $skippedExisting++;
                    } else {
                        WorkItem::create([
                            'project_id' => $project->id,
                            'vendor_id' => $vendorId,
                            'name' => $name,
                            'brand' => $brand !== '' ? $brand : null,
                            'offer_rupiah' => $rupiah !== null ? (int) round($rupiah) : null,
                            'offer_usd' => $usd,
                        ]);

                        $created++;
                    }

                    $workItemRecord = WorkItem::query()
                        ->where('project_id', $project->id)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                        ->first();

                    if ($workItemRecord && $rupiah !== null) {
                        $termsImported += $this->importPaymentTerms($workItemRecord, $row, $importedAt);
                    } elseif ($workItemRecord && $usd !== null) {
                        $termsSkippedUsdOnly++;
                    }

                    $offerExists = ProjectOffer::query()
                        ->where('project_id', $project->id)
                        ->where('pekerjaan', $originalName)
                        ->where(function ($query) use ($brand): void {
                            $brand !== '' ? $query->where('brand', $brand) : $query->whereNull('brand');
                        })
                        ->where(function ($query) use ($usd): void {
                            $usd !== null ? $query->where('penawaran_usd', $usd) : $query->whereNull('penawaran_usd');
                        })
                        ->where(function ($query) use ($rupiah): void {
                            $rupiah !== null ? $query->where('penawaran_rupiah', (int) round($rupiah)) : $query->whereNull('penawaran_rupiah');
                        })
                        ->exists();

                    if ($offerExists) {
                        $offersSkippedExisting++;

                        continue;
                    }

                    ProjectOffer::create([
                        'project_id' => $project->id,
                        'vendor_id' => $vendorId,
                        'project_name' => $project->name,
                        'pekerjaan' => $originalName,
                        'brand' => $brand !== '' ? $brand : null,
                        'penawaran_usd' => $usd,
                        'penawaran_rupiah' => $rupiah !== null ? (int) round($rupiah) : null,
                    ]);

                    $offersCreated++;
                }
            }
        });

        $this->info("Selesai. Work item baru: {$created} (dilewati karena sudah ada: {$skippedExisting}). Kategori pekerjaan baru: {$offersCreated} (dilewati karena sudah ada: {$offersSkippedExisting}). Termin pembayaran diimpor: {$termsImported} (item USD dilewati: {$termsSkippedUsdOnly}). Baris header/kosong dilewati: {$skippedHeaderRows}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function importPaymentTerms(WorkItem $workItem, array $row, string $importedAt): int
    {
        $payments = [];

        for ($number = 1; $number <= 8; $number++) {
            $amount = $this->numeric($row[4 + $number] ?? null);

            if ($amount !== null) {
                $payments[$number] = (int) round($amount);
            }
        }

        if ($payments === []) {
            return 0;
        }

        $paymentGroup = $this->paymentGroupFor($workItem);

        foreach ($payments as $number => $amount) {
            PaymentTerm::updateOrCreate(
                ['payment_group_id' => $paymentGroup->id, 'payment_number' => $number],
                [
                    'amount' => $amount,
                    'paid_at' => $importedAt,
                    'notes' => 'Diimpor dari spreadsheet. Tanggal pembayaran asli tidak tersedia.',
                ],
            );
        }

        $paymentGroup->update([
            'paid_terms' => $paymentGroup->terms()->count(),
            'total_terms' => $this->automaticTotalTerms($paymentGroup),
        ]);

        return count($payments);
    }

    private function paymentGroupFor(WorkItem $workItem): PaymentGroup
    {
        $code = 'Termin-'.$workItem->id;
        $offerRupiah = (int) ($workItem->offer_rupiah ?? 0);

        $paymentGroup = PaymentGroup::query()
            ->where('project_id', $workItem->project_id)
            ->where(function ($query) use ($code, $workItem): void {
                $query->where('work_item_id', $workItem->id)->orWhere('code', $code);
            })
            ->first();

        if (! $paymentGroup) {
            $paymentGroup = new PaymentGroup([
                'project_id' => $workItem->project_id,
                'work_item_id' => $workItem->id,
                'code' => $code,
                'name' => $workItem->name,
                'status' => 'berjalan',
            ]);
        }

        $paymentGroup->fill([
            'work_item_id' => $workItem->id,
            'total_amount' => $offerRupiah,
            'offer_rupiah_snapshot' => $offerRupiah,
            'offer_usd_snapshot' => $workItem->offer_usd,
            'total_terms' => 1,
        ]);
        $paymentGroup->save();

        return $paymentGroup;
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
     * @return array<int, array<int, string|null>>|null
     */
    private function readSheetRows(string $path, string $sheetName): ?array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return null;
        }

        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $targetRid = null;

        foreach ($workbook->sheets->sheet as $sheet) {
            if ((string) $sheet['name'] === $sheetName) {
                $attrs = $sheet->attributes('r', true);
                $targetRid = (string) $attrs['id'];
            }
        }

        if ($targetRid === null) {
            $zip->close();

            return null;
        }

        $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $target = null;

        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $targetRid) {
                $target = ltrim((string) $rel['Target'], '/');
            }
        }

        if ($target === null) {
            $zip->close();

            return null;
        }

        $target = str_starts_with($target, 'worksheets') ? "xl/{$target}" : $target;

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedXml !== false) {
            $sharedXmlEl = simplexml_load_string($sharedXml);

            foreach ($sharedXmlEl->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } else {
                    $text = '';

                    foreach ($si->r as $r) {
                        $text .= (string) $r->t;
                    }

                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetXml = simplexml_load_string($zip->getFromName($target));
        $zip->close();

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

    private function columnIndex(string $cellReference): int
    {
        $letters = preg_replace('/[0-9]/', '', $cellReference);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }

    private function numeric(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return $float > 0 ? $float : null;
    }
}
