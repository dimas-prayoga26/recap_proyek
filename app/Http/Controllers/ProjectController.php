<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveProject;
use App\Models\ActiveProjectSelection;
use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $project->update([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name'], $project->id),
            'description' => $validated['description'] ?? null,
        ]);

        $project->offers()->update(['project_name' => $project->name]);

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
