<?php

namespace Tests\Feature;

use App\Models\ActiveProjectSelection;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ActiveProjectSwitchTest extends TestCase
{
    use DatabaseTransactions;

    public function test_switching_active_project_redirects_back_to_given_page(): void
    {
        $firstProject = Project::create([
            'name' => 'Project Switch Test A',
            'slug' => 'project-switch-test-a-'.uniqid(),
            'status' => 'active',
        ]);
        $secondProject = Project::create([
            'name' => 'Project Switch Test B',
            'slug' => 'project-switch-test-b-'.uniqid(),
            'status' => 'active',
        ]);
        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $firstProject->id],
        );

        $response = $this->post(route('dashboard.active-project'), [
            'project_id' => $secondProject->id,
            'redirect_to' => '/vendor',
        ]);

        $response->assertRedirect('/vendor');
        $this->assertSame($secondProject->id, ActiveProjectSelection::where('key', 'dashboard')->value('project_id'));
    }

    public function test_switching_active_project_without_redirect_to_goes_to_dashboard(): void
    {
        $project = Project::create([
            'name' => 'Project Switch Test C',
            'slug' => 'project-switch-test-c-'.uniqid(),
            'status' => 'active',
        ]);

        $response = $this->post(route('dashboard.active-project'), [
            'project_id' => $project->id,
        ]);

        $response->assertRedirect(route('dashboard'));
    }
}
