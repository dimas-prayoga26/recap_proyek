<?php

namespace Tests\Feature;

use App\Models\ActiveProjectSelection;
use App\Models\Project;
use App\Models\ProjectOffer;
use App\Models\WorkItem;
use App\Models\WorkPackageItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WorkPackageItemTest extends TestCase
{
    use DatabaseTransactions;

    public function test_regular_offer_creates_work_item_and_offer_record(): void
    {
        $project = Project::create([
            'name' => 'Project Termin Plan Test',
            'slug' => 'project-termin-plan-test-'.uniqid(),
            'status' => 'active',
        ]);
        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $project->id],
        );

        $response = $this->post(route('kategori-pekerjaan.store'), [
            'pekerjaan' => 'Pekerjaan Rencana 6 Termin',
            'penawaran_rupiah' => 120000000,
        ]);

        $response->assertRedirect(route('kategori-pekerjaan.index', ['project_id' => $project->id]));

        $workItem = WorkItem::query()
            ->where('project_id', $project->id)
            ->where('name', 'Pekerjaan Rencana 6 Termin')
            ->firstOrFail();

        $this->assertDatabaseHas('project_offers', [
            'project_id' => $project->id,
            'pekerjaan' => 'Pekerjaan Rencana 6 Termin',
            'penawaran_rupiah' => 120000000,
        ]);
    }

    public function test_package_offer_stores_child_items_in_their_own_table(): void
    {
        $project = Project::create([
            'name' => 'Project Paket Test',
            'slug' => 'project-paket-test-'.uniqid(),
            'status' => 'active',
        ]);
        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $project->id],
        );

        $response = $this->post(route('kategori-pekerjaan.store'), [
            'is_package' => '1',
            'pekerjaan' => 'Master Bedroom + Bathroom',
            'penawaran_rupiah' => 900000000,
            'package_items' => [
                ['name' => 'Master Bedroom + Bathroom', 'brand' => 'Build Dec Interior'],
                ['name' => 'Foyer Master Bedroom', 'brand' => 'Build Dec Interior'],
                ['name' => 'Gym Area', 'brand' => 'Build Dec Interior'],
            ],
        ]);

        $response->assertRedirect(route('kategori-pekerjaan.index', ['project_id' => $project->id]));

        $workItem = WorkItem::query()
            ->where('project_id', $project->id)
            ->where('name', 'Master Bedroom + Bathroom')
            ->firstOrFail();

        $this->assertDatabaseHas('project_offers', [
            'project_id' => $project->id,
            'work_item_id' => $workItem->id,
            'pekerjaan' => 'Master Bedroom + Bathroom',
        ]);
        $this->assertSame(3, WorkPackageItem::query()->where('work_item_id', $workItem->id)->count());
        $this->assertSame(
            ['Master Bedroom + Bathroom', 'Foyer Master Bedroom', 'Gym Area'],
            $workItem->packageItems()->pluck('name')->all(),
        );
        $this->assertSame(1, ProjectOffer::query()->where('work_item_id', $workItem->id)->count());
    }

    public function test_category_offer_can_be_deleted_when_it_has_no_payment_history(): void
    {
        $project = Project::create([
            'name' => 'Project Delete Kategori',
            'slug' => 'project-delete-kategori-'.uniqid(),
            'status' => 'active',
        ]);
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'name' => 'Kategori Bisa Dihapus',
            'offer_rupiah' => 5000000,
        ]);
        $offer = ProjectOffer::create([
            'project_id' => $project->id,
            'work_item_id' => $workItem->id,
            'project_name' => $project->name,
            'area' => '',
            'pekerjaan' => 'Kategori Bisa Dihapus',
            'penawaran_rupiah' => 5000000,
        ]);

        $response = $this->delete(route('kategori-pekerjaan.destroy', $offer));

        $response
            ->assertRedirect(route('kategori-pekerjaan.index', ['project_id' => $project->id]))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('project_offers', ['id' => $offer->id]);
        $this->assertDatabaseMissing('work_items', ['id' => $workItem->id]);
    }
}
