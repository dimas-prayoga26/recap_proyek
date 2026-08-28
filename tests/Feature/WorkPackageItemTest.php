<?php

namespace Tests\Feature;

use App\Models\ActiveProjectSelection;
use App\Models\Project;
use App\Models\ProjectArea;
use App\Models\ProjectOffer;
use App\Models\WorkItem;
use App\Models\WorkPackageItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WorkPackageItemTest extends TestCase
{
    use DatabaseTransactions;

    public function test_regular_offer_stores_custom_fixed_total_terms_on_work_item_and_payment_group(): void
    {
        $project = Project::create([
            'name' => 'Project Termin Plan Test',
            'slug' => 'project-termin-plan-test-'.uniqid(),
            'status' => 'active',
        ]);
        ProjectArea::create([
            'project_id' => $project->id,
            'code' => 'K9',
            'name' => 'Project Termin Plan Test - K9',
        ]);
        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $project->id],
        );

        $response = $this->post(route('kategori-pekerjaan.store'), [
            'area' => 'K9',
            'pekerjaan' => 'Pekerjaan Rencana 6 Termin',
            'brand' => 'Vendor Rencana',
            'penawaran_rupiah' => 120000000,
            'fixed_total_terms' => 6,
        ]);

        $response->assertRedirect(route('kategori-pekerjaan.index'));

        $workItem = WorkItem::query()
            ->where('project_id', $project->id)
            ->where('name', 'Pekerjaan Rencana 6 Termin')
            ->firstOrFail();

        $this->assertSame(6, $workItem->fixed_total_terms);
        $this->assertDatabaseHas('payment_groups', [
            'project_id' => $project->id,
            'work_item_id' => $workItem->id,
            'fixed_total_terms' => 6,
            'total_terms' => 6,
        ]);
    }

    public function test_package_offer_stores_child_items_in_their_own_table(): void
    {
        $project = Project::create([
            'name' => 'Project Paket Test',
            'slug' => 'project-paket-test-'.uniqid(),
            'status' => 'active',
        ]);
        ProjectArea::create([
            'project_id' => $project->id,
            'code' => 'K9',
            'name' => 'Project Paket Test - K9',
        ]);
        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $project->id],
        );

        $response = $this->post(route('kategori-pekerjaan.store'), [
            'area' => 'K9',
            'is_package' => '1',
            'pekerjaan' => 'Master Bedroom + Bathroom',
            'penawaran_rupiah' => 900000000,
            'package_items' => [
                ['name' => 'Master Bedroom + Bathroom', 'brand' => 'Build Dec Interior'],
                ['name' => 'Foyer Master Bedroom', 'brand' => 'Build Dec Interior'],
                ['name' => 'Gym Area', 'brand' => 'Build Dec Interior'],
            ],
        ]);

        $response->assertRedirect(route('kategori-pekerjaan.index'));

        $workItem = WorkItem::query()
            ->where('project_id', $project->id)
            ->where('name', 'Master Bedroom + Bathroom')
            ->firstOrFail();

        $this->assertDatabaseHas('project_offers', [
            'project_id' => $project->id,
            'work_item_id' => $workItem->id,
            'pekerjaan' => 'Master Bedroom + Bathroom',
        ]);
        $this->assertNull($workItem->package_name);
        $this->assertSame(8, $workItem->fixed_total_terms);
        $this->assertDatabaseHas('payment_groups', [
            'project_id' => $project->id,
            'work_item_id' => $workItem->id,
            'fixed_total_terms' => 8,
            'total_terms' => 8,
            'paid_terms' => 0,
        ]);
        $this->assertSame(3, WorkPackageItem::query()->where('work_item_id', $workItem->id)->count());
        $this->assertSame(
            ['Master Bedroom + Bathroom', 'Foyer Master Bedroom', 'Gym Area'],
            $workItem->packageItems()->pluck('name')->all(),
        );
        $this->assertSame(1, ProjectOffer::query()->where('work_item_id', $workItem->id)->count());
    }
}
