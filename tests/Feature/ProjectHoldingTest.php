<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectArea;
use App\Models\ProjectOffer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProjectHoldingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_project_holding_can_be_updated(): void
    {
        $project = Project::create([
            'name' => 'Project Holding Lama Test',
            'slug' => 'project-holding-lama-test',
            'status' => 'active',
            'description' => 'Alamat lama',
        ]);
        $area = ProjectArea::create([
            'project_id' => $project->id,
            'code' => 'K9',
            'name' => 'Project Holding Lama Test - K9',
        ]);
        ProjectOffer::create([
            'project_id' => $project->id,
            'project_area_id' => $area->id,
            'project_name' => 'Project Holding Lama Test',
            'area' => 'K9',
            'pekerjaan' => 'Pekerjaan Test',
            'penawaran_rupiah' => 1000000,
        ]);

        $response = $this->put(route('project.update', $project), [
            'form_context' => 'project_update',
            'editing_project_id' => $project->id,
            'name' => 'Project Holding Baru Test',
            'description' => 'Alamat baru',
        ]);

        $response
            ->assertRedirect(route('project.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Project Holding Baru Test',
            'slug' => 'project-holding-baru-test',
            'description' => 'Alamat baru',
        ]);
        $this->assertDatabaseHas('project_areas', [
            'id' => $area->id,
            'name' => 'Project Holding Baru Test - K9',
        ]);
        $this->assertDatabaseHas('project_offers', [
            'project_id' => $project->id,
            'project_name' => 'Project Holding Baru Test',
        ]);
    }
}
