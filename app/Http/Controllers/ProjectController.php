<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveProject;
use App\Models\ActiveProjectSelection;
use App\Models\Project;
use App\Models\ProjectArea;
use App\Models\WorkItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    use ResolvesActiveProject;

    public function index(): View
    {
        return view('pages.project-form', [
            'title' => 'Project Holding',
            'activeProject' => $this->activeProject(),
            'projects' => Project::query()
                ->withCount(['workItems'])
                ->addSelect(['vendors_count' => WorkItem::query()
                    ->selectRaw('count(distinct vendor_id)')
                    ->whereColumn('project_id', 'projects.id')
                    ->whereNotNull('vendor_id'),
                ])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $project = Project::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'status' => 'active',
            'description' => $validated['description'] ?? null,
        ]);

        ProjectArea::create([
            'project_id' => $project->id,
            'code' => 'Lainnya',
            'name' => $project->name.' - Lainnya',
        ]);

        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $project->id],
        );

        return redirect()
            ->route('project.index')
            ->with('status', "Project Holding \"{$project->name}\" berhasil dibuat dan dijadikan project aktif.");
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('projects', 'name')->ignore($project)],
            'description' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($project, $validated): void {
            $oldName = $project->name;

            $project->update([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name'], $project->id),
                'description' => $validated['description'] ?? null,
            ]);

            $project->offers()->update(['project_name' => $project->name]);

            $project->areas()
                ->where('name', 'like', $oldName.' - %')
                ->get()
                ->each(function (ProjectArea $area) use ($project): void {
                    $area->update(['name' => $project->name.' - '.$area->code]);
                });
        });

        return redirect()
            ->route('project.index')
            ->with('status', "Project Holding \"{$project->name}\" berhasil diperbarui.");
    }

    private function uniqueSlug(string $name, ?int $ignoreProjectId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 1;

        while (Project::query()
            ->when($ignoreProjectId, fn ($query) => $query->whereKeyNot($ignoreProjectId))
            ->where('slug', $slug)
            ->exists()
        ) {
            $suffix++;
            $slug = $baseSlug.'-'.$suffix;
        }

        return $slug;
    }
}
