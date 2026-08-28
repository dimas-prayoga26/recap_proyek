<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['work_item_id', 'sort_order']);
            $table->index(['work_item_id', 'name']);
        });

        $this->backfillPackageItemsFromExistingNotes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_package_items');
    }

    private function backfillPackageItemsFromExistingNotes(): void
    {
        DB::table('work_items')
            ->where('notes', 'like', 'Paket gabungan %')
            ->orderBy('id')
            ->get()
            ->each(function (object $workItem): void {
                $items = $this->packageItemsFromNote((string) $workItem->notes, $workItem->brand);

                if ($items === []) {
                    return;
                }

                foreach ($items as $index => $item) {
                    $vendorId = null;

                    if (filled($item['brand'])) {
                        $vendorId = DB::table('vendors')->where('name', $item['brand'])->value('id');

                        if (! $vendorId) {
                            $vendorId = DB::table('vendors')->insertGetId([
                                'name' => $item['brand'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    DB::table('work_package_items')->insert([
                        'work_item_id' => $workItem->id,
                        'vendor_id' => $vendorId,
                        'name' => $item['name'],
                        'brand' => $item['brand'],
                        'sort_order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $packageName = $this->packageName((string) $workItem->name, $items);

                if ($packageName !== $workItem->name) {
                    $nameExists = DB::table('work_items')
                        ->where('project_id', $workItem->project_id)
                        ->where('project_area_id', $workItem->project_area_id)
                        ->where('name', $packageName)
                        ->where('id', '!=', $workItem->id)
                        ->exists();

                    if (! $nameExists) {
                        DB::table('work_items')->where('id', $workItem->id)->update([
                            'name' => $packageName,
                            'updated_at' => now(),
                        ]);

                        DB::table('project_offers')->where('work_item_id', $workItem->id)->update([
                            'pekerjaan' => $packageName,
                            'updated_at' => now(),
                        ]);

                        DB::table('payment_groups')->where('work_item_id', $workItem->id)->update([
                            'name' => $packageName,
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    /**
     * @return array<int, array{name: string, brand: string|null}>
     */
    private function packageItemsFromNote(string $note, ?string $fallbackBrand): array
    {
        preg_match('/^Paket gabungan \d+ area \(harga satu paket, bukan per-area\):\s*(.+?)\.\s*(?:Kontraktor:\s*(.*?)\.\s*)?Total penawaran/u', $note, $match);

        if (! isset($match[1])) {
            return [];
        }

        $sharedBrand = trim($match[2] ?? '') ?: $fallbackBrand;

        return collect(explode(', ', $match[1]))
            ->map(function (string $segment) use ($sharedBrand): array {
                $segment = trim($segment);
                preg_match('/^(.*)\s\(([^)]+)\)$/u', $segment, $itemMatch);

                return [
                    'name' => trim($itemMatch[1] ?? $segment),
                    'brand' => trim($itemMatch[2] ?? '') ?: $sharedBrand,
                ];
            })
            ->filter(fn (array $item): bool => $item['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{name: string, brand: string|null}>  $items
     */
    private function packageName(string $currentName, array $items): string
    {
        $suffix = ' - '.collect($items)->pluck('name')->join(', ');

        if (str_ends_with($currentName, $suffix)) {
            return trim(substr($currentName, 0, -strlen($suffix)));
        }

        return $currentName;
    }
};
