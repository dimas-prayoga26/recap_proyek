<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ActiveProjectSelection;
use App\Models\Project;

trait ResolvesActiveProject
{
    private function activeProject(): ?Project
    {
        $fallbackProject = Project::query()->where('status', 'active')->orderBy('name')->first();

        if (! $fallbackProject) {
            return null;
        }

        $selection = ActiveProjectSelection::query()->where('key', 'dashboard')->first();

        if ($selection) {
            $selectedProject = Project::query()
                ->where('id', $selection->project_id)
                ->where('status', 'active')
                ->first();

            if ($selectedProject) {
                return $selectedProject;
            }
        }

        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $fallbackProject->id],
        );

        return $fallbackProject;
    }
}
