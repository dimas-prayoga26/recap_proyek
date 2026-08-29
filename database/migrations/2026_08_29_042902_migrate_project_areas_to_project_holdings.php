<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('project_areas') || ! Schema::hasTable('projects')) {
            return;
        }

        $activeProjectId = DB::table('active_project_selections')
            ->where('key', 'dashboard')
            ->value('project_id');

        $firstMovedProjectIdForActiveProject = null;

        DB::table('project_areas')
            ->orderBy('project_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $area) use ($activeProjectId, &$firstMovedProjectIdForActiveProject): void {
                $project = DB::table('projects')->where('id', $area->project_id)->first();

                if (! $project || ! $this->areaHasData($area)) {
                    return;
                }

                $targetProjectId = $this->targetProjectId($project, $area);

                $workItemIds = DB::table('work_items')
                    ->where('project_area_id', $area->id)
                    ->pluck('id');

                if ($workItemIds->isNotEmpty()) {
                    DB::table('work_items')
                        ->whereIn('id', $workItemIds)
                        ->update([
                            'project_id' => $targetProjectId,
                            'project_area_id' => null,
                            'updated_at' => now(),
                        ]);
                }

                DB::table('project_offers')
                    ->where('project_area_id', $area->id)
                    ->update([
                        'project_id' => $targetProjectId,
                        'project_area_id' => null,
                        'project_name' => DB::table('projects')->where('id', $targetProjectId)->value('name'),
                        'area' => '',
                        'updated_at' => now(),
                    ]);

                DB::table('project_transactions')
                    ->where('project_area_id', $area->id)
                    ->update([
                        'project_id' => $targetProjectId,
                        'project_area_id' => null,
                        'updated_at' => now(),
                    ]);

                if ($workItemIds->isNotEmpty()) {
                    DB::table('payment_groups')
                        ->whereIn('work_item_id', $workItemIds)
                        ->update([
                            'project_id' => $targetProjectId,
                            'updated_at' => now(),
                        ]);
                }

                if ((int) $activeProjectId === (int) $project->id && ! $firstMovedProjectIdForActiveProject) {
                    $firstMovedProjectIdForActiveProject = $targetProjectId;
                }
            });

        if ($firstMovedProjectIdForActiveProject) {
            DB::table('active_project_selections')
                ->where('key', 'dashboard')
                ->update([
                    'project_id' => $firstMovedProjectIdForActiveProject,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data split into project holdings cannot be safely merged back automatically.
    }

    private function areaHasData(object $area): bool
    {
        return DB::table('work_items')->where('project_area_id', $area->id)->exists()
            || DB::table('project_offers')->where('project_area_id', $area->id)->exists()
            || DB::table('project_transactions')->where('project_area_id', $area->id)->exists();
    }

    private function targetProjectId(object $project, object $area): int
    {
        if ($this->shouldKeepParentProject($project, $area)) {
            return (int) $project->id;
        }

        $targetName = $this->targetProjectName($project, $area);
        $existingProjectId = DB::table('projects')->where('name', $targetName)->value('id');

        if ($existingProjectId) {
            return (int) $existingProjectId;
        }

        return DB::table('projects')->insertGetId([
            'name' => $targetName,
            'slug' => $this->uniqueSlug($targetName),
            'status' => $project->status,
            'description' => $project->description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function shouldKeepParentProject(object $project, object $area): bool
    {
        if ($area->code !== 'Lainnya') {
            return false;
        }

        $areaCount = DB::table('project_areas')
            ->where('project_id', $project->id)
            ->count();

        return $areaCount === 1;
    }

    private function targetProjectName(object $project, object $area): string
    {
        $areaName = trim((string) $area->name);
        $normalizedAreaName = str_replace(' - ', ' ', $areaName);

        if ($normalizedAreaName !== '') {
            return $normalizedAreaName;
        }

        return trim($project->name.' '.$area->code);
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 1;

        while (DB::table('projects')->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $baseSlug.'-'.$suffix;
        }

        return $slug;
    }
};
