<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveProject;
use App\Models\ActiveProjectSelection;
use App\Models\Project;
use App\Models\ProjectArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
                ->withCount(['workItems', 'areas'])
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

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 1;

        while (Project::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $baseSlug.'-'.$suffix;
        }

        return $slug;
    }
}
