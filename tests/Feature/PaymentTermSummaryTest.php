<?php

namespace Tests\Feature;

use App\Models\ActiveProjectSelection;
use App\Models\PaymentGroup;
use App\Models\PaymentTerm;
use App\Models\Project;
use App\Models\ProjectTransaction;
use App\Models\ProjectTransactionAllocation;
use App\Models\TransactionCategory;
use App\Models\Vendor;
use App\Models\WorkItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentTermSummaryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unpaid_work_item_ignores_stale_total_terms_and_shows_first_payment_column(): void
    {
        [$project, $workItem] = $this->workItemForActiveProject('Pekerjaan Belum Dibayar');
        $this->paymentGroupFor($workItem, 8);

        $response = $this->get(route('termin-pembayaran.index', [
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
        [$project, $unpaidWorkItem] = $this->workItemForActiveProject('Pekerjaan Termin 1');
        $partialWorkItem = $this->workItemInProject($project, 'Pekerjaan Termin 2');
        $paidOffWorkItem = $this->workItemInProject($project, 'Pekerjaan Termin 3');

        $this->paymentGroupFor($unpaidWorkItem, 8);
        $this->paymentGroupFor($partialWorkItem, 8, payments: [1 => 30000000]);
        $this->paymentGroupFor($paidOffWorkItem, 8, payments: [1 => 30000000, 2 => 30000000, 3 => 20000000]);

        $response = $this->get(route('termin-pembayaran.index'));

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
        [$project, $oneTermWorkItem] = $this->workItemForActiveProject('Pekerjaan Hanya Termin 1');
        $twoTermWorkItem = $this->workItemInProject($project, 'Pekerjaan Hanya Termin 2');

        $this->paymentGroupFor($oneTermWorkItem, 8);
        $this->paymentGroupFor($twoTermWorkItem, 8, payments: [1 => 30000000]);

        $response = $this->get(route('termin-pembayaran.index', [
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
        [$project, $workItem] = $this->workItemForActiveProject('Pekerjaan Lunas Termin 3');
        $this->paymentGroupFor($workItem, 8, payments: [1 => 30000000, 2 => 30000000, 3 => 20000000]);

        $response = $this->get(route('termin-pembayaran.index', [
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
        [$project, $firstWorkItem] = $this->workItemForActiveProject('Pasang Kanopi');
        $firstVendor = Vendor::create(['name' => 'Vendor Kanopi']);
        $secondVendor = Vendor::create(['name' => 'Vendor Lantai']);
        $secondWorkItem = $this->workItemInProject($project, 'Pasang Lantai');

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

    public function test_paid_payment_cell_shows_nominal_with_eye_button_and_limited_modal_details(): void
    {
        [$project, $workItem] = $this->workItemForActiveProject('Pekerjaan Dengan Bukti');
        $vendor = Vendor::create(['name' => 'Vendor Bukti']);
        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Jasa Tukang', 'type' => 'keluar'],
            ['status' => 'active'],
        );
        $workItem->update(['vendor_id' => $vendor->id]);
        $paymentGroup = $this->paymentGroupFor($workItem, 1, payments: [1 => 2500000]);
        $paymentTerm = $paymentGroup->terms()->firstOrFail();
        $transaction = ProjectTransaction::create([
            'project_id' => $project->id,
            'transaction_category_id' => $category->id,
            'work_item_id' => $workItem->id,
            'vendor_id' => $vendor->id,
            'payment_group_id' => $paymentGroup->id,
            'type' => 'keluar',
            'amount' => 2500000,
            'recorded_at' => '2026-08-29',
            'payment_number' => 1,
            'payment_total' => 1,
            'receipt_total' => 80000000,
            'notes' => 'Catatan bukti pembayaran',
        ]);
        $transaction->attachments()->create([
            'disk' => 'public',
            'path' => 'transaction-receipts/kwitansi-test.pdf',
            'original_name' => 'kwitansi-test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 12000,
        ]);
        ProjectTransactionAllocation::create([
            'project_transaction_id' => $transaction->id,
            'work_item_id' => $workItem->id,
            'payment_group_id' => $paymentGroup->id,
            'payment_term_id' => $paymentTerm->id,
            'amount' => 2500000,
            'payment_number' => 1,
            'role' => 'primary',
            'notes' => 'Allocation note',
        ]);

        $response = $this->get(route('termin-pembayaran.index'));

        $response
            ->assertOk()
            ->assertSee('term-payment-action', false)
            ->assertSee('<span>Rp 2.500.000</span>', false)
            ->assertSee('term-payment-button', false)
            ->assertSee('<i class="ti ti-eye"></i>', false)
            ->assertSee('data-amount="Rp 2.500.000"', false)
            ->assertSee('data-notes="Allocation note"', false)
            ->assertSee('kwitansi-test.pdf')
            ->assertDontSee('data-work-name=', false)
            ->assertDontSee('data-vendor-name=', false)
            ->assertDontSee('id="payment-detail-work"', false)
            ->assertDontSee('id="payment-detail-vendor"', false)
            ->assertDontSee('id="payment-detail-type"', false)
            ->assertDontSee('id="payment-detail-date"', false);
    }

    /**
     * @return array{Project, WorkItem}
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
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'name' => $workItemName,
            'offer_rupiah' => 80000000,
        ]);

        return [$project, $workItem];
    }

    private function workItemInProject(Project $project, string $workItemName): WorkItem
    {
        return WorkItem::create([
            'project_id' => $project->id,
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
