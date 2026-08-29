<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectArea;
use App\Models\ProjectOffer;
use App\Models\Vendor;
use App\Models\WorkItem;
use App\Models\WorkPackageItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VendorImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_import_creates_new_vendors_and_skips_existing_names(): void
    {
        Vendor::create(['name' => 'Vendor Sudah Ada']);

        $csv = "Nama Vendor,Nama Kontak,No. Telepon,Catatan\n"
            ."Vendor Import Baru,Pak Budi,0812111111,Vendor uji coba import\n"
            ."Vendor Sudah Ada,Kontak Lama,0812999999,Harus dilewati\n";

        $file = UploadedFile::fake()->createWithContent('vendors.csv', $csv);

        $response = $this->post(route('vendor.import'), ['file' => $file]);

        $response->assertRedirect(route('vendor.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('vendors', [
            'name' => 'Vendor Import Baru',
            'contact_name' => 'Pak Budi',
            'phone' => '0812111111',
        ]);
        $this->assertDatabaseHas('vendors', [
            'name' => 'Vendor Sudah Ada',
            'contact_name' => null,
        ]);
        $this->assertSame(1, Vendor::query()->where('name', 'Vendor Sudah Ada')->count());
    }

    public function test_vendor_can_be_updated_and_related_brand_snapshots_are_synced(): void
    {
        $project = Project::create([
            'name' => 'Project Kemang',
            'slug' => 'project-kemang-test',
            'status' => 'active',
        ]);
        $area = ProjectArea::create([
            'project_id' => $project->id,
            'code' => 'K8',
            'name' => 'Project Kemang - K8',
        ]);
        $vendor = Vendor::create(['name' => 'Vendor Lama Test']);
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'project_area_id' => $area->id,
            'vendor_id' => $vendor->id,
            'name' => 'Pembelian Pohon',
            'brand' => 'Vendor Lama Test',
            'offer_rupiah' => 8800000,
        ]);

        ProjectOffer::create([
            'project_id' => $project->id,
            'project_area_id' => $area->id,
            'vendor_id' => $vendor->id,
            'work_item_id' => $workItem->id,
            'project_name' => $project->name,
            'area' => $area->code,
            'pekerjaan' => $workItem->name,
            'brand' => 'Vendor Lama Test',
            'penawaran_rupiah' => 8800000,
        ]);

        WorkPackageItem::create([
            'work_item_id' => $workItem->id,
            'vendor_id' => $vendor->id,
            'name' => 'Pohon area depan',
            'brand' => 'Vendor Lama Test',
            'sort_order' => 1,
        ]);

        $response = $this->put(route('vendor.update', $vendor), [
            'form_context' => 'vendor_update',
            'editing_vendor_id' => $vendor->id,
            'name' => 'Akmal Revisi',
            'contact_name' => 'Pak Akmal',
            'phone' => '0812',
            'notes' => 'Vendor tanaman',
        ]);

        $response
            ->assertRedirect(route('vendor.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'name' => 'Akmal Revisi',
            'contact_name' => 'Pak Akmal',
            'phone' => '0812',
            'notes' => 'Vendor tanaman',
        ]);
        $this->assertDatabaseHas('work_items', [
            'id' => $workItem->id,
            'brand' => 'Akmal Revisi',
        ]);
        $this->assertDatabaseHas('project_offers', [
            'work_item_id' => $workItem->id,
            'brand' => 'Akmal Revisi',
        ]);
        $this->assertDatabaseHas('work_package_items', [
            'work_item_id' => $workItem->id,
            'brand' => 'Akmal Revisi',
        ]);
    }

    public function test_vendor_can_be_deleted_and_related_vendor_references_are_cleared(): void
    {
        $project = Project::create([
            'name' => 'Project Vendor Delete',
            'slug' => 'project-vendor-delete-'.uniqid(),
            'status' => 'active',
        ]);
        $area = ProjectArea::create([
            'project_id' => $project->id,
            'code' => 'K9',
            'name' => 'Project Vendor Delete - K9',
        ]);
        $vendor = Vendor::create(['name' => 'Vendor Hapus Test']);
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'project_area_id' => $area->id,
            'vendor_id' => $vendor->id,
            'name' => 'Pekerjaan Vendor Hapus',
            'brand' => 'Vendor Hapus Test',
            'offer_rupiah' => 1000000,
        ]);
        ProjectOffer::create([
            'project_id' => $project->id,
            'project_area_id' => $area->id,
            'vendor_id' => $vendor->id,
            'work_item_id' => $workItem->id,
            'project_name' => $project->name,
            'area' => 'K9',
            'pekerjaan' => 'Pekerjaan Vendor Hapus',
            'brand' => 'Vendor Hapus Test',
            'penawaran_rupiah' => 1000000,
        ]);

        $response = $this->delete(route('vendor.destroy', $vendor));

        $response
            ->assertRedirect(route('vendor.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('vendors', ['id' => $vendor->id]);
        $this->assertDatabaseHas('work_items', [
            'id' => $workItem->id,
            'vendor_id' => null,
            'brand' => null,
        ]);
        $this->assertDatabaseHas('project_offers', [
            'work_item_id' => $workItem->id,
            'vendor_id' => null,
            'brand' => null,
        ]);
    }
}
