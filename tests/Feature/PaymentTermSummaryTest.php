<?php

namespace Tests\Feature;

use App\Models\ActiveProjectSelection;
use App\Models\PaymentGroup;
use App\Models\PaymentTerm;
use App\Models\Project;
use App\Models\ProjectArea;
use App\Models\Vendor;
use App\Models\WorkItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentTermSummaryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unpaid_work_item_ignores_stale_total_terms_and_shows_first_payment_column(): void
    {
        [$area, $workItem] = $this->workItemForActiveProject('Pekerjaan Belum Dibayar');
        $this->paymentGroupFor($workItem, 8);

        $response = $this->get(route('termin-pembayaran.index', [
            'area' => $area->code,
            'terms' => 1,
        ]));

        $response
            ->assertOk()
            ->assertSee('Rekap Pembayaran')
            ->assertDontSee('Total Fix Termin')
            ->assertSee('Pekerjaan Belum Dibayar')
            ->assertSee('Pembayaran 1')
            ->assertDontSee('Pembayaran 2')
            ->assertDontSee('Pembayaran 8');
    }

    public function test_default_view_uses_largest_automatic_payment_count_across_mixed_work_items(): void
    {
        [$area, $unpaidWorkItem] = $this->workItemForActiveProject('Pekerjaan Termin 1');
        $partialWorkItem = $this->workItemInArea($area, 'Pekerjaan Termin 2');
        $paidOffWorkItem = $this->workItemInArea($area, 'Pekerjaan Termin 3');

        $this->paymentGroupFor($unpaidWorkItem, 8);
        $this->paymentGroupFor($partialWorkItem, 8, payments: [1 => 30000000]);
        $this->paymentGroupFor($paidOffWorkItem, 8, payments: [1 => 30000000, 2 => 30000000, 3 => 20000000]);

        $response = $this->get(route('termin-pembayaran.index', ['area' => $area->code]));

        $response
            ->assertOk()
            ->assertSee('Rekap Pembayaran')
            ->assertDontSee('Total Fix Termin')
            ->assertSee('Pekerjaan Termin 1')
            ->assertSee('Pekerjaan Termin 2')
            ->assertSee('Pekerjaan Termin 3')
            ->assertSee('Pembayaran 3')
            ->assertDontSee('Pembayaran 4')
            ->assertDontSee('Pembayaran 8');
    }

    public function test_terms_filter_limits_rows_and_columns_to_selected_automatic_payment_count(): void
    {
        [$area, $oneTermWorkItem] = $this->workItemForActiveProject('Pekerjaan Hanya Termin 1');
        $twoTermWorkItem = $this->workItemInArea($area, 'Pekerjaan Hanya Termin 2');

        $this->paymentGroupFor($oneTermWorkItem, 8);
        $this->paymentGroupFor($twoTermWorkItem, 8, payments: [1 => 30000000]);

        $response = $this->get(route('termin-pembayaran.index', [
            'area' => $area->code,
            'terms' => 2,
        ]));

        $response
            ->assertOk()
            ->assertSee('Pekerjaan Hanya Termin 2')
            ->assertDontSee('Pekerjaan Hanya Termin 1')
            ->assertSee('Pembayaran 2')
            ->assertDontSee('Pembayaran 3');
    }

    public function test_paid_off_work_item_stops_at_highest_paid_payment_column(): void
    {
        [$area, $workItem] = $this->workItemForActiveProject('Pekerjaan Lunas Termin 3');
        $this->paymentGroupFor($workItem, 8, payments: [1 => 30000000, 2 => 30000000, 3 => 20000000]);

        $response = $this->get(route('termin-pembayaran.index', [
            'area' => $area->code,
            'terms' => 3,
        ]));

        $response
            ->assertOk()
            ->assertSee('Pekerjaan Lunas Termin 3')
            ->assertSee('Pembayaran 3')
            ->assertDontSee('Pembayaran 4')
            ->assertDontSee('Pembayaran 8')
            ->assertSee('Rp 0');
    }

    public function test_payment_recap_can_filter_by_search_and_vendor(): void
    {
        [$area, $firstWorkItem] = $this->workItemForActiveProject('Pasang Kanopi');
        $firstVendor = Vendor::create(['name' => 'Vendor Kanopi']);
        $secondVendor = Vendor::create(['name' => 'Vendor Lantai']);
        $secondWorkItem = $this->workItemInArea($area, 'Pasang Lantai');

        $firstWorkItem->update(['vendor_id' => $firstVendor->id]);
        $secondWorkItem->update(['vendor_id' => $secondVendor->id]);
        $this->paymentGroupFor($firstWorkItem, 8);
        $this->paymentGroupFor($secondWorkItem, 8);

        $response = $this->get(route('termin-pembayaran.index', [
            'search' => 'Kanopi',
            'vendor_id' => $firstVendor->id,
        ]));

        $response
            ->assertOk()
            ->assertSee('Vendor')
            ->assertSee('Pasang Kanopi')
            ->assertSee('Vendor Kanopi')
            ->assertDontSee('Pasang Lantai');
    }

    /**
     * @return array{ProjectArea, WorkItem}
     */
    private function workItemForActiveProject(string $workItemName): array
    {
        $project = Project::create([
            'name' => 'Project Termin Update '.uniqid(),
            'slug' => 'project-termin-update-'.uniqid(),
            'status' => 'active',
        ]);
        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $project->id],
        );
        $area = ProjectArea::create([
            'project_id' => $project->id,
            'code' => 'K9',
            'name' => $project->name.' - K9',
        ]);
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'project_area_id' => $area->id,
            'name' => $workItemName,
            'offer_rupiah' => 80000000,
        ]);

        return [$area, $workItem];
    }

    private function workItemInArea(ProjectArea $area, string $workItemName): WorkItem
    {
        return WorkItem::create([
            'project_id' => $area->project_id,
            'project_area_id' => $area->id,
            'name' => $workItemName,
            'offer_rupiah' => 80000000,
        ]);
    }

    /**
     * @param  array<int, int>  $payments
     */
    private function paymentGroupFor(WorkItem $workItem, int $totalTerms, int $paidTerms = 0, array $payments = []): PaymentGroup
    {
        $paymentGroup = PaymentGroup::create([
            'project_id' => $workItem->project_id,
            'work_item_id' => $workItem->id,
            'code' => 'Termin-'.$workItem->id,
            'name' => $workItem->name,
            'total_amount' => 80000000,
            'offer_rupiah_snapshot' => 80000000,
            'total_terms' => $totalTerms,
            'paid_terms' => $paidTerms,
            'status' => 'berjalan',
        ]);

        foreach ($payments as $number => $amount) {
            PaymentTerm::create([
                'payment_group_id' => $paymentGroup->id,
                'payment_number' => $number,
                'amount' => $amount,
                'paid_at' => '2026-08-28',
            ]);
        }

        return $paymentGroup;
    }
}
