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
        Schema::table('work_items', function (Blueprint $table) {
            $table->index('project_id', 'work_items_project_id_index');
        });

        Schema::table('work_items', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'project_area_id', 'name']);
            $table->index(['project_id', 'project_area_id', 'name'], 'work_items_project_area_name_index');
        });

        DB::table('work_items')
            ->whereExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('work_package_items')
                    ->whereColumn('work_package_items.work_item_id', 'work_items.id');
            })
            ->orderBy('id')
            ->get()
            ->each(function (object $workItem): void {
                $itemNames = DB::table('work_package_items')
                    ->where('work_item_id', $workItem->id)
                    ->orderBy('sort_order')
                    ->pluck('name')
                    ->all();
                $packageName = $this->packageName((string) $workItem->name, $itemNames);

                if ($packageName === $workItem->name) {
                    return;
                }

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
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropIndex('work_items_project_area_name_index');
            $table->dropIndex('work_items_project_id_index');
        });
    }

    /**
     * @param  array<int, string>  $itemNames
     */
    private function packageName(string $currentName, array $itemNames): string
    {
        $suffix = ' - '.collect($itemNames)->join(', ');

        if (str_ends_with($currentName, $suffix)) {
            return trim(substr($currentName, 0, -strlen($suffix)));
        }

        return $currentName;
    }
};
