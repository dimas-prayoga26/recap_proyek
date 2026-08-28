<?php

namespace Tests\Feature;

use App\Models\ActiveProjectSelection;
use App\Models\PaymentGroup;
use App\Models\PaymentTerm;
use App\Models\Project;
use App\Models\ProjectArea;
use App\Models\WorkItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentTermUpdateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fixed_total_terms_can_be_updated_from_payment_terms_page(): void
    {
        [$area, $workItem] = $this->workItemForActiveProject('Pekerjaan Fix Termin');

        $response = $this->patch(route('termin-pembayaran.update', $workItem), [
            'fixed_total_terms' => 8,
            'area' => $area->code,
        ]);

        $response
            ->assertRedirect(route('termin-pembayaran.index', ['area' => $area->code]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('payment_groups', [
            'work_item_id' => $workItem->id,
            'fixed_total_terms' => 8,
            'total_terms' => 8,
        ]);
    }

    public function test_terms_filter_uses_fixed_total_terms_for_visible_payment_columns(): void
    {
        [$area, $workItem] = $this->workItemForActiveProject('Pekerjaan Filter Termin');
        $this->paymentGroupFor($workItem, 8);

        $response = $this->get(route('termin-pembayaran.index', [
            'area' => $area->code,
            'terms' => 8,
        ]));

        $response
            ->assertOk()
            ->assertSee('Pekerjaan Filter Termin')
            ->assertSee('Pembayaran 1')
            ->assertSee('Pembayaran 8');
    }

    public function test_default_view_uses_largest_fixed_terms_across_mixed_work_items(): void
    {
        [$area, $threeTermsWorkItem] = $this->workItemForActiveProject('Pekerjaan Termin 3');
        $sixTermsWorkItem = $this->workItemInArea($area, 'Pekerjaan Termin 6');
        $eightTermsWorkItem = $this->workItemInArea($area, 'Pekerjaan Termin 8');

        $this->paymentGroupFor($threeTermsWorkItem, 3);
        $this->paymentGroupFor($sixTermsWorkItem, 6);
        $this->paymentGroupFor($eightTermsWorkItem, 8);

        $response = $this->get(route('termin-pembayaran.index', ['area' => $area->code]));

        $response
            ->assertOk()
            ->assertSee('Pekerjaan Termin 3')
            ->assertSee('Pekerjaan Termin 6')
            ->assertSee('Pekerjaan Termin 8')
            ->assertSee('Pembayaran 8')
            ->assertSee('3x')
            ->assertSee('6x')
            ->assertSee('8x')
            ->assertDontSee('24x');
    }

    public function test_terms_filter_limits_rows_and_columns_to_selected_fixed_terms(): void
    {
        [$area, $threeTermsWorkItem] = $this->workItemForActiveProject('Pekerjaan Hanya Termin 3');
        $eightTermsWorkItem = $this->workItemInArea($area, 'Pekerjaan Hanya Termin 8');

        $this->paymentGroupFor($threeTermsWorkItem, 3);
        $this->paymentGroupFor($eightTermsWorkItem, 8);

        $response = $this->get(route('termin-pembayaran.index', [
            'area' => $area->code,
            'terms' => 3,
        ]));

        $response
            ->assertOk()
            ->assertSee('Pekerjaan Hanya Termin 3')
            ->assertDontSee('Pekerjaan Hanya Termin 8')
            ->assertSee('Pembayaran 3')
            ->assertDontSee('Pembayaran 4');
    }

    public function test_paid_off_work_item_can_still_keep_more_fixed_unpaid_term_slots(): void
    {
        [$area, $workItem] = $this->workItemForActiveProject('Pekerjaan Lunas Termin 3 Rencana 8');
        $paymentGroup = $this->paymentGroupFor($workItem, 8);

        foreach ([1 => 30000000, 2 => 30000000, 3 => 20000000] as $number => $amount) {
            PaymentTerm::create([
                'payment_group_id' => $paymentGroup->id,
                'payment_number' => $number,
                'amount' => $amount,
                'paid_at' => '2026-08-28',
            ]);
        }

        $response = $this->get(route('termin-pembayaran.index', [
            'area' => $area->code,
            'terms' => 8,
        ]));

        $response
            ->assertOk()
            ->assertSee('Pekerjaan Lunas Termin 3 Rencana 8')
            ->assertSee('Pembayaran 8')
            ->assertSee('Rp 0');
    }

    public function test_fixed_total_terms_cannot_be_lower_than_existing_highest_payment_number(): void
    {
        [$area, $workItem] = $this->workItemForActiveProject('Pekerjaan Termin Sudah Ada');
        $paymentGroup = $this->paymentGroupFor($workItem, 8, 1);
        PaymentTerm::create([
            'payment_group_id' => $paymentGroup->id,
            'payment_number' => 6,
            'amount' => 10000000,
            'paid_at' => '2026-08-28',
        ]);

        $response = $this->from(route('termin-pembayaran.index', ['area' => $area->code]))
            ->patch(route('termin-pembayaran.update', $workItem), [
                'fixed_total_terms' => 5,
                'area' => $area->code,
            ]);

        $response
            ->assertRedirect(route('termin-pembayaran.index', ['area' => $area->code]))
            ->assertSessionHasErrors('fixed_total_terms');

        $this->assertSame(8, $paymentGroup->refresh()->fixed_total_terms);
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

    private function paymentGroupFor(WorkItem $workItem, int $fixedTotalTerms, int $paidTerms = 0): PaymentGroup
    {
        $workItem->update(['fixed_total_terms' => $fixedTotalTerms]);

        return PaymentGroup::create([
            'project_id' => $workItem->project_id,
            'work_item_id' => $workItem->id,
            'code' => 'Termin-'.$workItem->id,
            'name' => $workItem->name,
            'total_amount' => 80000000,
            'offer_rupiah_snapshot' => 80000000,
            'total_terms' => $fixedTotalTerms,
            'fixed_total_terms' => $fixedTotalTerms,
            'paid_terms' => $paidTerms,
            'status' => 'berjalan',
        ]);
    }
}
