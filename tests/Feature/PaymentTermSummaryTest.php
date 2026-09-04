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
use Illuminate\Support\Facades\Storage;
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

    public function test_payment_recap_shows_paid_and_remaining_summary_cards(): void
    {
        [$project, $partialWorkItem] = $this->workItemForActiveProject('Pekerjaan Summary Sisa');
        $paidOffWorkItem = $this->workItemInProject($project, 'Pekerjaan Summary Lunas');

        $this->paymentGroupFor($partialWorkItem, 2, payments: [1 => 30000000, 2 => 20000000]);
        $this->paymentGroupFor($paidOffWorkItem, 1, payments: [1 => 80000000]);

        $response = $this->get(route('termin-pembayaran.index'));

        $response
            ->assertSee('data-summary-card="total"', false)
            ->assertSee('data-summary-card="paid"', false)
            ->assertSee('data-summary-card="remaining"', false)
            ->assertSee('Total Penawaran')
            ->assertSee('Total Sudah Dibayar')
            ->assertSee('Total Sisa Pembayaran')
            ->assertSee('Rp 160.000.000')
            ->assertSee('Rp 130.000.000')
            ->assertSee('Rp 30.000.000')
            ->assertSee('Semua Vendor - 2 pekerjaan')
            ->assertSee('Semua Vendor - 3 pembayaran')
            ->assertSee('bg-light-warning text-warning', false)
            ->assertSee('Belum Lunas')
            ->assertSee('class="col-12 col-md-4"', false);
    }

    public function test_payment_recap_shows_lunas_badge_when_remaining_is_zero(): void
    {
        [$project, $paidOffWorkItem] = $this->workItemForActiveProject('Pekerjaan Summary Lunas Total');
        $vendor = Vendor::create(['name' => 'Vendor Lunas Total']);
        $paidOffWorkItem->update(['vendor_id' => $vendor->id]);
        $this->paymentGroupFor($paidOffWorkItem, 1, payments: [1 => 80000000]);

        $response = $this->get(route('termin-pembayaran.index', [
            'vendor_id' => $vendor->id,
        ]));

        $response
            ->assertSee('Rp 0')
            ->assertSee('bg-light-success text-success', false)
            ->assertSee('Lunas')
            ->assertDontSee('Belum Lunas');
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
            ->assertDontSee('<span class="term-work-title">Pekerjaan Hanya Termin 1</span>', false)
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
            ->assertSee('data-summary-vendor="Vendor Kanopi"', false)
            ->assertSee('Vendor Kanopi - 1 pekerjaan')
            ->assertSee('bg-light-warning text-warning', false)
            ->assertSee('Belum Lunas')
            ->assertDontSee('<span class="term-work-title">Pasang Lantai</span>', false);
    }

    public function test_payment_recap_table_shows_ten_rows_per_page(): void
    {
        [$project] = $this->workItemForActiveProject('Pekerjaan Paginate 00');

        for ($index = 1; $index <= 12; $index++) {
            $workItem = $this->workItemInProject($project, 'Pekerjaan Paginate '.str_pad((string) $index, 2, '0', STR_PAD_LEFT));

            $this->paymentGroupFor($workItem, 1);
        }

        $firstPage = $this->get(route('termin-pembayaran.index'));

        $firstPage
            ->assertSee('term-table-full', false)
            ->assertSee('id="payment-terms-panel"', false)
            ->assertSee('id="term-filter-form"', false)
            ->assertSee('$.ajax', false)
            ->assertSee("$(document).on('click', '#payment-terms-panel .term-pagination a'", false)
            ->assertSee('Menampilkan 1-10 dari 13 pekerjaan')
            ->assertSee('<span class="term-work-title">Pekerjaan Paginate 00</span>', false)
            ->assertSee('<span class="term-work-title">Pekerjaan Paginate 09</span>', false)
            ->assertDontSee('<span class="term-work-title">Pekerjaan Paginate 10</span>', false)
            ->assertSee('page=2', false);
        $this->assertSame(10, substr_count($firstPage->getContent(), 'class="term-work-title"'));

        $secondPage = $this->get(route('termin-pembayaran.index', ['page' => 2]));

        $secondPage
            ->assertSee('Menampilkan 11-13 dari 13 pekerjaan')
            ->assertSee('<span class="term-work-title">Pekerjaan Paginate 10</span>', false)
            ->assertSee('<span class="term-work-title">Pekerjaan Paginate 12</span>', false)
            ->assertDontSee('<span class="term-work-title">Pekerjaan Paginate 09</span>', false);
        $this->assertSame(3, substr_count($secondPage->getContent(), 'class="term-work-title"'));
    }

    public function test_paid_payment_cell_shows_nominal_with_action_menu_and_limited_modal_details(): void
    {
        config(['filesystems.disks.public.url' => 'http://127.0.0.1:8001/storage']);

        [$project, $workItem] = $this->workItemForActiveProject('Pekerjaan Dengan Bukti');
        $vendor = Vendor::create(['name' => 'Jasa Pasang Bukti']);
        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Jasa Tukang', 'type' => 'keluar'],
            ['status' => 'active'],
        );
        $workItem->update(['vendor_id' => $vendor->id]);
        $serviceDetailWorkItem = $this->workItemInProject($project, 'Belanja Marmer Family Room lantai 2');
        $paymentGroup = $this->paymentGroupFor($workItem, 1, payments: [1 => 2500000]);
        $paymentTerm = $paymentGroup->terms()->firstOrFail();
        $transaction = ProjectTransaction::create([
            'project_id' => $project->id,
            'transaction_category_id' => $category->id,
            'work_item_id' => $workItem->id,
            'service_detail_work_item_id' => $serviceDetailWorkItem->id,
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
            'mime_type' => null,
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
            ->assertSee('term-payment-menu-button', false)
            ->assertSee('<i class="ti ti-dots-vertical"></i>', false)
            ->assertSee('term-payment-detail-action', false)
            ->assertSee('<i class="ti ti-eye"></i>', false)
            ->assertSee('term-payment-update-action', false)
            ->assertSee('Update Rincian')
            ->assertSee('data-update-action="'.route('termin-pembayaran.rincian.update', $paymentTerm).'"', false)
            ->assertSee('data-update-work-item-id="'.$workItem->id.'"', false)
            ->assertSee('data-current-service-detail-id="'.$serviceDetailWorkItem->id.'"', false)
            ->assertSee('id="payment-update-detail-modal"', false)
            ->assertSee('id="payment-update-detail-form"', false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('Simpan Rincian')
            ->assertSee('term-payment-delete-action', false)
            ->assertSee('<i class="ti ti-trash"></i>', false)
            ->assertSee('data-bs-target="#payment-delete-modal"', false)
            ->assertSee('data-delete-action="'.route('termin-pembayaran.destroy', $paymentTerm).'"', false)
            ->assertSee('data-delete-payment-number="1"', false)
            ->assertSee('data-delete-amount="Rp 2.500.000"', false)
            ->assertSee('id="payment-delete-modal"', false)
            ->assertSee('id="payment-delete-form"', false)
            ->assertSee('id="payment-delete-summary"', false)
            ->assertSee('method="POST"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('Sisa pembayaran akan dihitung ulang setelah data ini dihapus.')
            ->assertSee('paymentDeleteForm.action', false)
            ->assertSee('paymentDeleteSummary.textContent', false)
            ->assertDontSee('onsubmit=', false)
            ->assertDontSee('confirm(', false)
            ->assertSee('payment-detail-preview', false)
            ->assertSee('max-height: min(58vh, 460px)', false)
            ->assertSee('Tanggal Pencatatan')
            ->assertSee('setPaymentDetailZoom')
            ->assertSee('payment-detail-preview.is-zoomed', false)
            ->assertSee('Math.min(5', false)
            ->assertSee("addEventListener('wheel'", false)
            ->assertSee('is-dragging')
            ->assertDontSee('id="payment-detail-zoom-controls"', false)
            ->assertDontSee('id="payment-detail-zoom-in"', false)
            ->assertDontSee('id="payment-detail-zoom-out"', false)
            ->assertSee('data-amount="Rp 2.500.000"', false)
            ->assertSee('data-recorded-at="Sabtu, 29 August 2026"', false)
            ->assertSee('data-service-detail="Marmer Family Room lantai 2"', false)
            ->assertSee('id="payment-detail-service-row"', false)
            ->assertSee('id="payment-detail-service"', false)
            ->assertSee('Rincian Jasa')
            ->assertSee('button.dataset.serviceDetail', false)
            ->assertSee('filterPaymentUpdateOptions', false)
            ->assertSee('data-notes="Allocation note"', false)
            ->assertSee('data-receipt-mime=""', false)
            ->assertSee('data-receipt-url="/storage/transaction-receipts/kwitansi-test.pdf"', false)
            ->assertSee('kwitansi-test.pdf')
            ->assertSee('receiptPath.endsWith(\'.pdf\')', false)
            ->assertSee('Preview gambar gagal dimuat. Buka file bukti pembayaran.')
            ->assertDontSee('127.0.0.1:8001/storage')
            ->assertDontSee('data-work-name=', false)
            ->assertDontSee('data-vendor-name=', false)
            ->assertDontSee('id="payment-detail-work"', false)
            ->assertDontSee('id="payment-detail-vendor"', false)
            ->assertDontSee('id="payment-detail-type"', false);
    }

    public function test_payment_update_detail_action_only_shows_for_jasa_pasang_vendor(): void
    {
        [, $regularWorkItem] = $this->workItemForActiveProject('Pekerjaan Vendor Biasa');
        $regularVendor = Vendor::create(['name' => 'Vendor Biasa Test '.uniqid()]);
        $regularWorkItem->update(['vendor_id' => $regularVendor->id]);
        $regularPaymentGroup = $this->paymentGroupFor($regularWorkItem, 1, payments: [1 => 1000000]);
        $regularPaymentTerm = $regularPaymentGroup->terms()->firstOrFail();

        $response = $this->get(route('termin-pembayaran.index'));

        $response
            ->assertOk()
            ->assertSee('<span class="term-work-title">Pekerjaan Vendor Biasa</span>', false)
            ->assertDontSee('data-update-action="'.route('termin-pembayaran.rincian.update', $regularPaymentTerm).'"', false)
            ->assertSee('term-payment-detail-action', false)
            ->assertSee('term-payment-delete-action', false);

        [, $jasaPasangWorkItem] = $this->workItemForActiveProject('Pekerjaan Vendor Jasa Pasang');
        $jasaPasangVendor = Vendor::create(['name' => 'Jasa Pasang Detail Test '.uniqid()]);
        $jasaPasangWorkItem->update(['vendor_id' => $jasaPasangVendor->id]);
        $jasaPasangPaymentGroup = $this->paymentGroupFor($jasaPasangWorkItem, 1, payments: [1 => 2000000]);
        $jasaPasangPaymentTerm = $jasaPasangPaymentGroup->terms()->firstOrFail();

        $response = $this->get(route('termin-pembayaran.index'));

        $response
            ->assertOk()
            ->assertSee('<span class="term-work-title">Pekerjaan Vendor Jasa Pasang</span>', false)
            ->assertSee('data-update-action="'.route('termin-pembayaran.rincian.update', $jasaPasangPaymentTerm).'"', false);
    }

    public function test_payment_term_service_detail_can_be_updated(): void
    {
        [$project, $workItem] = $this->workItemForActiveProject('Pekerjaan Update Rincian Jasa');
        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Jasa Tukang', 'type' => 'keluar'],
            ['status' => 'active'],
        );
        $paymentGroup = $this->paymentGroupFor($workItem, 1, payments: [1 => 2500000]);
        $paymentTerm = $paymentGroup->terms()->firstOrFail();
        $serviceDetailWorkItem = $this->workItemInProject($project, 'Belanja Marmer Kamar Mandi Utama');
        $transaction = ProjectTransaction::create([
            'project_id' => $project->id,
            'transaction_category_id' => $category->id,
            'work_item_id' => $workItem->id,
            'payment_group_id' => $paymentGroup->id,
            'type' => 'keluar',
            'amount' => 2500000,
            'recorded_at' => '2026-09-03',
            'payment_number' => 1,
            'payment_total' => 1,
            'receipt_total' => 80000000,
        ]);
        ProjectTransactionAllocation::create([
            'project_transaction_id' => $transaction->id,
            'work_item_id' => $workItem->id,
            'payment_group_id' => $paymentGroup->id,
            'payment_term_id' => $paymentTerm->id,
            'amount' => 2500000,
            'payment_number' => 1,
            'role' => 'primary',
        ]);

        $response = $this->from(route('termin-pembayaran.index'))->patch(route('termin-pembayaran.rincian.update', $paymentTerm), [
            'service_detail_work_item_id' => $serviceDetailWorkItem->id,
        ]);

        $response
            ->assertRedirect(route('termin-pembayaran.index'))
            ->assertSessionHas('status', 'Rincian jasa berhasil diperbarui.');

        $this->assertDatabaseHas('project_transactions', [
            'id' => $transaction->id,
            'work_item_id' => $workItem->id,
            'service_detail_work_item_id' => $serviceDetailWorkItem->id,
        ]);
    }

    public function test_payment_term_service_detail_update_rejects_other_project_work_item(): void
    {
        [$project, $workItem] = $this->workItemForActiveProject('Pekerjaan Rincian Project Aktif');
        $otherProject = Project::create([
            'name' => 'Project Rincian Lain '.uniqid(),
            'slug' => 'project-rincian-lain-'.uniqid(),
            'status' => 'active',
        ]);
        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Jasa Tukang', 'type' => 'keluar'],
            ['status' => 'active'],
        );
        $paymentGroup = $this->paymentGroupFor($workItem, 1, payments: [1 => 2500000]);
        $paymentTerm = $paymentGroup->terms()->firstOrFail();
        $otherWorkItem = $this->workItemInProject($otherProject, 'Belanja Marmer Project Lain');
        $transaction = ProjectTransaction::create([
            'project_id' => $project->id,
            'transaction_category_id' => $category->id,
            'work_item_id' => $workItem->id,
            'payment_group_id' => $paymentGroup->id,
            'type' => 'keluar',
            'amount' => 2500000,
            'recorded_at' => '2026-09-03',
            'payment_number' => 1,
            'payment_total' => 1,
            'receipt_total' => 80000000,
        ]);
        ProjectTransactionAllocation::create([
            'project_transaction_id' => $transaction->id,
            'work_item_id' => $workItem->id,
            'payment_group_id' => $paymentGroup->id,
            'payment_term_id' => $paymentTerm->id,
            'amount' => 2500000,
            'payment_number' => 1,
            'role' => 'primary',
        ]);

        $response = $this->from(route('termin-pembayaran.index'))->patch(route('termin-pembayaran.rincian.update', $paymentTerm), [
            'service_detail_work_item_id' => $otherWorkItem->id,
        ]);

        $response->assertNotFound();

        $this->assertDatabaseHas('project_transactions', [
            'id' => $transaction->id,
            'service_detail_work_item_id' => null,
        ]);
    }

    public function test_payment_term_destroy_removes_last_transaction_and_recalculates_remaining(): void
    {
        Storage::fake('public');

        [$project, $workItem] = $this->workItemForActiveProject('Pekerjaan Hapus Pembayaran');
        $vendor = Vendor::create(['name' => 'Vendor Hapus Pembayaran']);
        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Jasa Tukang', 'type' => 'keluar'],
            ['status' => 'active'],
        );
        $workItem->update(['vendor_id' => $vendor->id]);
        $paymentGroup = $this->paymentGroupFor($workItem, 2, payments: [1 => 25000000]);
        $paymentTerm = $paymentGroup->terms()->firstOrFail();
        $transaction = ProjectTransaction::create([
            'project_id' => $project->id,
            'transaction_category_id' => $category->id,
            'work_item_id' => $workItem->id,
            'vendor_id' => $vendor->id,
            'payment_group_id' => $paymentGroup->id,
            'type' => 'keluar',
            'amount' => 25000000,
            'recorded_at' => '2026-08-29',
            'payment_number' => 1,
            'payment_total' => 2,
            'receipt_total' => 80000000,
            'notes' => 'Salah input pembayaran',
        ]);
        Storage::disk('public')->put('transaction-receipts/hapus-pembayaran.jpg', 'receipt');
        $attachment = $transaction->attachments()->create([
            'disk' => 'public',
            'path' => 'transaction-receipts/hapus-pembayaran.jpg',
            'original_name' => 'hapus-pembayaran.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 12000,
        ]);
        $allocation = ProjectTransactionAllocation::create([
            'project_transaction_id' => $transaction->id,
            'work_item_id' => $workItem->id,
            'payment_group_id' => $paymentGroup->id,
            'payment_term_id' => $paymentTerm->id,
            'amount' => 25000000,
            'payment_number' => 1,
            'role' => 'primary',
        ]);

        $response = $this
            ->from(route('termin-pembayaran.index'))
            ->delete(route('termin-pembayaran.destroy', $paymentTerm));

        $response
            ->assertRedirect(route('termin-pembayaran.index'))
            ->assertSessionHas('status', 'Pembayaran berhasil dihapus. Sisa pembayaran sudah dihitung ulang.');

        $this->assertDatabaseMissing('payment_terms', ['id' => $paymentTerm->id]);
        $this->assertDatabaseMissing('project_transaction_allocations', ['id' => $allocation->id]);
        $this->assertDatabaseMissing('project_transaction_attachments', ['id' => $attachment->id]);
        $this->assertDatabaseMissing('project_transactions', ['id' => $transaction->id]);
        $this->assertSame(0, $paymentGroup->refresh()->paid_terms);
        $this->assertSame(1, $paymentGroup->total_terms);
        Storage::disk('public')->assertMissing('transaction-receipts/hapus-pembayaran.jpg');
    }

    public function test_payment_term_destroy_reduces_transaction_when_other_allocations_remain(): void
    {
        [$project, $mainWorkItem] = $this->workItemForActiveProject('Pekerjaan Utama Hapus Alokasi');
        $additionalWorkItem = $this->workItemInProject($project, 'Pekerjaan Tambahan Hapus Alokasi');
        $vendor = Vendor::create(['name' => 'Vendor Multi Alokasi']);
        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Jasa Tukang', 'type' => 'keluar'],
            ['status' => 'active'],
        );
        $mainWorkItem->update(['vendor_id' => $vendor->id]);
        $additionalWorkItem->update(['vendor_id' => $vendor->id]);
        $mainPaymentGroup = $this->paymentGroupFor($mainWorkItem, 2, payments: [1 => 20000000]);
        $additionalPaymentGroup = $this->paymentGroupFor($additionalWorkItem, 2, payments: [1 => 10000000]);
        $mainPaymentTerm = $mainPaymentGroup->terms()->firstOrFail();
        $additionalPaymentTerm = $additionalPaymentGroup->terms()->firstOrFail();
        $transaction = ProjectTransaction::create([
            'project_id' => $project->id,
            'transaction_category_id' => $category->id,
            'work_item_id' => $mainWorkItem->id,
            'vendor_id' => $vendor->id,
            'payment_group_id' => $mainPaymentGroup->id,
            'type' => 'keluar',
            'amount' => 30000000,
            'recorded_at' => '2026-08-29',
            'payment_number' => 1,
            'payment_total' => 2,
            'receipt_total' => 80000000,
        ]);
        $mainAllocation = ProjectTransactionAllocation::create([
            'project_transaction_id' => $transaction->id,
            'work_item_id' => $mainWorkItem->id,
            'payment_group_id' => $mainPaymentGroup->id,
            'payment_term_id' => $mainPaymentTerm->id,
            'amount' => 20000000,
            'payment_number' => 1,
            'role' => 'primary',
        ]);
        $additionalAllocation = ProjectTransactionAllocation::create([
            'project_transaction_id' => $transaction->id,
            'work_item_id' => $additionalWorkItem->id,
            'payment_group_id' => $additionalPaymentGroup->id,
            'payment_term_id' => $additionalPaymentTerm->id,
            'amount' => 10000000,
            'payment_number' => 1,
            'role' => 'additional',
        ]);

        $response = $this
            ->from(route('termin-pembayaran.index'))
            ->delete(route('termin-pembayaran.destroy', $additionalPaymentTerm));

        $response->assertRedirect(route('termin-pembayaran.index'));

        $this->assertDatabaseHas('project_transactions', [
            'id' => $transaction->id,
            'amount' => 20000000,
            'work_item_id' => $mainWorkItem->id,
            'payment_group_id' => $mainPaymentGroup->id,
        ]);
        $this->assertDatabaseHas('payment_terms', ['id' => $mainPaymentTerm->id]);
        $this->assertDatabaseHas('project_transaction_allocations', ['id' => $mainAllocation->id]);
        $this->assertDatabaseMissing('payment_terms', ['id' => $additionalPaymentTerm->id]);
        $this->assertDatabaseMissing('project_transaction_allocations', ['id' => $additionalAllocation->id]);
        $this->assertSame(0, $additionalPaymentGroup->refresh()->paid_terms);
        $this->assertSame(1, $additionalPaymentGroup->total_terms);
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
