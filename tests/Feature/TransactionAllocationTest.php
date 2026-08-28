<?php

namespace Tests\Feature;

use App\Models\PaymentGroup;
use App\Models\PaymentTerm;
use App\Models\Project;
use App\Models\ProjectArea;
use App\Models\ProjectTransaction;
use App\Models\ProjectTransactionAllocation;
use App\Models\TransactionCategory;
use App\Models\Vendor;
use App\Models\WorkItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TransactionAllocationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_expense_transaction_can_allocate_overpayment_to_another_work_item(): void
    {
        $project = Project::create([
            'name' => 'Project Allocation Test',
            'slug' => 'project-allocation-test-'.uniqid(),
            'status' => 'active',
        ]);
        $area = ProjectArea::create([
            'project_id' => $project->id,
            'code' => 'K9',
            'name' => 'Project Allocation Test - K9',
        ]);
        $vendor = Vendor::firstOrCreate(['name' => 'Arabescato Greco']);
        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Jasa Tukang', 'type' => 'keluar'],
            ['status' => 'active'],
        );
        $mainWorkItem = WorkItem::create([
            'project_id' => $project->id,
            'project_area_id' => $area->id,
            'vendor_id' => $vendor->id,
            'name' => 'Ruang Kerja',
            'offer_rupiah' => 428087040,
        ]);
        $additionalWorkItem = WorkItem::create([
            'project_id' => $project->id,
            'project_area_id' => $area->id,
            'name' => 'Tambahan Black Zebra',
            'offer_rupiah' => 4972800,
        ]);

        $response = $this->post(route('transactions.store'), [
            'type' => 'keluar',
            'project_area_id' => $area->id,
            'transaction_category_id' => $category->id,
            'work_item_id' => $mainWorkItem->id,
            'vendor_id' => $vendor->id,
            'amount' => 433059840,
            'recorded_at' => '2026-08-28',
            'payment_number' => 1,
            'payment_total' => 1,
            'receipt_total' => 433059840,
            'allocations' => [
                [
                    'work_item_id' => $additionalWorkItem->id,
                    'amount' => 4972800,
                    'payment_number' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('uang-keluar.index'));

        $transaction = ProjectTransaction::query()->latest('id')->firstOrFail();
        $mainGroup = PaymentGroup::query()->where('work_item_id', $mainWorkItem->id)->firstOrFail();
        $additionalGroup = PaymentGroup::query()->where('work_item_id', $additionalWorkItem->id)->firstOrFail();

        $this->assertSame(433059840, $transaction->amount);
        $this->assertSame(428087040, PaymentTerm::query()->where('payment_group_id', $mainGroup->id)->value('amount'));
        $this->assertSame(4972800, PaymentTerm::query()->where('payment_group_id', $additionalGroup->id)->value('amount'));
        $this->assertSame(8, $mainGroup->refresh()->fixed_total_terms);
        $this->assertSame(2, ProjectTransactionAllocation::query()->where('project_transaction_id', $transaction->id)->count());
        $this->assertDatabaseHas('project_transaction_allocations', [
            'project_transaction_id' => $transaction->id,
            'work_item_id' => $additionalWorkItem->id,
            'amount' => 4972800,
            'role' => 'additional',
        ]);
    }

    public function test_expense_transaction_keeps_fixed_total_terms_separate_from_paid_terms(): void
    {
        $project = Project::create([
            'name' => 'Project Fixed Termin Test',
            'slug' => 'project-fixed-termin-test-'.uniqid(),
            'status' => 'active',
        ]);
        $area = ProjectArea::create([
            'project_id' => $project->id,
            'code' => 'K9',
            'name' => 'Project Fixed Termin Test - K9',
        ]);
        $vendor = Vendor::firstOrCreate(['name' => 'Build Dec Interior']);
        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Jasa Tukang', 'type' => 'keluar'],
            ['status' => 'active'],
        );
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'project_area_id' => $area->id,
            'vendor_id' => $vendor->id,
            'name' => 'Pekerjaan Termin Delapan',
            'offer_rupiah' => 80000000,
        ]);

        $response = $this->post(route('transactions.store'), [
            'type' => 'keluar',
            'project_area_id' => $area->id,
            'transaction_category_id' => $category->id,
            'work_item_id' => $workItem->id,
            'vendor_id' => $vendor->id,
            'amount' => 10000000,
            'recorded_at' => '2026-08-28',
            'payment_number' => 2,
            'payment_total' => 8,
            'receipt_total' => 80000000,
        ]);

        $response->assertRedirect(route('uang-keluar.index'));

        $paymentGroup = PaymentGroup::query()->where('work_item_id', $workItem->id)->firstOrFail();

        $this->assertSame(8, $paymentGroup->fixed_total_terms);
        $this->assertSame(8, $paymentGroup->total_terms);
        $this->assertSame(1, $paymentGroup->paid_terms);
        $this->assertDatabaseHas('payment_terms', [
            'payment_group_id' => $paymentGroup->id,
            'payment_number' => 2,
            'amount' => 10000000,
        ]);
    }

    public function test_expense_transaction_rejects_payment_number_above_fixed_total_terms(): void
    {
        $project = Project::create([
            'name' => 'Project Invalid Termin Test',
            'slug' => 'project-invalid-termin-test-'.uniqid(),
            'status' => 'active',
        ]);
        $area = ProjectArea::create([
            'project_id' => $project->id,
            'code' => 'K9',
            'name' => 'Project Invalid Termin Test - K9',
        ]);
        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Jasa Tukang', 'type' => 'keluar'],
            ['status' => 'active'],
        );
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'project_area_id' => $area->id,
            'name' => 'Pekerjaan Termin Tidak Valid',
            'offer_rupiah' => 80000000,
        ]);

        $response = $this->from(route('uang-keluar.index'))->post(route('transactions.store'), [
            'type' => 'keluar',
            'project_area_id' => $area->id,
            'transaction_category_id' => $category->id,
            'work_item_id' => $workItem->id,
            'amount' => 10000000,
            'recorded_at' => '2026-08-28',
            'payment_number' => 9,
            'payment_total' => 8,
            'receipt_total' => 80000000,
        ]);

        $response
            ->assertRedirect(route('uang-keluar.index'))
            ->assertSessionHasErrors('payment_number');

        $this->assertDatabaseMissing('payment_groups', [
            'work_item_id' => $workItem->id,
        ]);
    }
}
