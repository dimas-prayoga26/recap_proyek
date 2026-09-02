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
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TransactionAllocationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_expense_form_exposes_usd_offer_for_usd_only_work_item(): void
    {
        Cache::put('exchange-rate.usd-idr', 17000, 300);

        $project = Project::create([
            'name' => 'Project USD Termin Test',
            'slug' => 'project-usd-termin-test-'.uniqid(),
            'status' => 'active',
        ]);
        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $project->id],
        );
        $vendor = Vendor::firstOrCreate(['name' => 'Akmal']);
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'vendor_id' => $vendor->id,
            'name' => 'Pengerjaan Pagar Rumah USD',
            'offer_usd' => 100,
        ]);

        $response = $this->get(route('uang-keluar.index', ['work_item_id' => $workItem->id]));

        $response
            ->assertSee('id="amount-currency-switch"', false)
            ->assertSee('name="amount_display"', false)
            ->assertSee('data-currency="USD"', false)
            ->assertSee('Kurs sekarang USD Rp 17.000')
            ->assertViewHas('workItemTerminInfo', function (array $workItemTerminInfo) use ($workItem): bool {
                return isset($workItemTerminInfo[$workItem->id])
                    && $workItemTerminInfo[$workItem->id]['offer'] === 0
                    && $workItemTerminInfo[$workItem->id]['offer_rupiah'] === 0
                    && $workItemTerminInfo[$workItem->id]['offer_usd'] === 100.0;
            });
    }

    public function test_expense_transaction_accepts_formatted_usd_amount_input(): void
    {
        $project = Project::create([
            'name' => 'Project USD Amount Test',
            'slug' => 'project-usd-amount-test-'.uniqid(),
            'status' => 'active',
        ]);
        $vendor = Vendor::firstOrCreate(['name' => 'Vendor USD Amount']);
        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Jasa Tukang', 'type' => 'keluar'],
            ['status' => 'active'],
        );
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'vendor_id' => $vendor->id,
            'name' => 'Pekerjaan Bayar USD',
            'offer_rupiah' => 50000000,
            'offer_usd' => 3000,
        ]);

        $response = $this->post(route('transactions.store'), [
            'type' => 'keluar',
            'project_id' => $project->id,
            'transaction_category_id' => $category->id,
            'work_item_id' => $workItem->id,
            'vendor_id' => $vendor->id,
            'amount_display' => '1,250.50',
            'amount_currency' => 'USD',
            'amount_exchange_rate' => '16.000',
            'recorded_at' => '2026-09-01',
            'payment_number' => 1,
        ]);

        $response->assertRedirect(route('uang-keluar.index'));

        $this->assertSame(20008000, ProjectTransaction::query()->latest('id')->value('amount'));
    }

    public function test_expense_transaction_creates_default_category_when_category_is_not_submitted(): void
    {
        TransactionCategory::query()
            ->where('type', 'keluar')
            ->update(['status' => 'inactive']);
        $project = Project::create([
            'name' => 'Project Default Category Test',
            'slug' => 'project-default-category-test-'.uniqid(),
            'status' => 'active',
        ]);
        $vendor = Vendor::firstOrCreate(['name' => 'Vendor Default Category']);
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'vendor_id' => $vendor->id,
            'name' => 'Pekerjaan Kategori Default',
            'offer_rupiah' => 50000000,
        ]);

        $response = $this->post(route('transactions.store'), [
            'type' => 'keluar',
            'project_id' => $project->id,
            'work_item_id' => $workItem->id,
            'vendor_id' => $vendor->id,
            'amount' => 10000000,
            'recorded_at' => '2026-09-02',
            'payment_number' => 1,
            'receipt_total' => 50000000,
        ]);

        $response->assertRedirect(route('uang-keluar.index'));

        $transaction = ProjectTransaction::query()->latest('id')->firstOrFail();

        $this->assertSame('Operasional', $transaction->category->name);
        $this->assertSame('active', $transaction->category->status);
        $this->assertDatabaseHas('project_transactions', [
            'id' => $transaction->id,
            'transaction_category_id' => $transaction->category->id,
        ]);
    }

    public function test_expense_transaction_rejects_zero_amount(): void
    {
        $project = Project::create([
            'name' => 'Project Debit Zero Amount Test',
            'slug' => 'project-debit-zero-amount-test-'.uniqid(),
            'status' => 'active',
        ]);
        $vendor = Vendor::firstOrCreate(['name' => 'Vendor Debit Zero Amount']);
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'vendor_id' => $vendor->id,
            'name' => 'Pekerjaan Debit Nominal Nol',
            'offer_rupiah' => 50000000,
        ]);

        $response = $this->from(route('uang-keluar.index'))->post(route('transactions.store'), [
            'type' => 'keluar',
            'project_id' => $project->id,
            'work_item_id' => $workItem->id,
            'vendor_id' => $vendor->id,
            'amount' => 0,
            'recorded_at' => '2026-09-02',
            'payment_number' => 1,
            'receipt_total' => 50000000,
        ]);

        $response
            ->assertRedirect(route('uang-keluar.index'))
            ->assertSessionHasErrors([
                'amount' => 'Nominal transaksi harus lebih dari 0.',
            ]);

        $this->assertDatabaseMissing('project_transactions', [
            'project_id' => $project->id,
            'work_item_id' => $workItem->id,
            'amount' => 0,
        ]);
    }

    public function test_income_transaction_rejects_zero_amount(): void
    {
        $project = Project::create([
            'name' => 'Project Credit Zero Amount Test',
            'slug' => 'project-credit-zero-amount-test-'.uniqid(),
            'status' => 'active',
        ]);
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'name' => 'Pekerjaan Credit Nominal Nol',
            'offer_rupiah' => 50000000,
        ]);

        $response = $this->from(route('uang-masuk.index'))->post(route('transactions.store'), [
            'type' => 'masuk',
            'project_id' => $project->id,
            'work_item_id' => $workItem->id,
            'amount' => 0,
            'recorded_at' => '2026-09-02',
        ]);

        $response
            ->assertRedirect(route('uang-masuk.index'))
            ->assertSessionHasErrors([
                'amount' => 'Nominal transaksi harus lebih dari 0.',
            ]);

        $this->assertDatabaseMissing('project_transactions', [
            'project_id' => $project->id,
            'work_item_id' => $workItem->id,
            'amount' => 0,
        ]);
    }

    public function test_expense_transaction_can_allocate_overpayment_to_another_work_item(): void
    {
        $project = Project::create([
            'name' => 'Project Allocation Test',
            'slug' => 'project-allocation-test-'.uniqid(),
            'status' => 'active',
        ]);
        $vendor = Vendor::firstOrCreate(['name' => 'Arabescato Greco']);
        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Jasa Tukang', 'type' => 'keluar'],
            ['status' => 'active'],
        );
        $mainWorkItem = WorkItem::create([
            'project_id' => $project->id,
            'vendor_id' => $vendor->id,
            'name' => 'Ruang Kerja',
            'offer_rupiah' => 428087040,
        ]);
        $additionalWorkItem = WorkItem::create([
            'project_id' => $project->id,
            'name' => 'Tambahan Black Zebra',
            'offer_rupiah' => 4972800,
        ]);

        $response = $this->post(route('transactions.store'), [
            'type' => 'keluar',
            'project_id' => $project->id,
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
        $this->assertSame(1, $mainGroup->refresh()->total_terms);
        $this->assertSame(2, ProjectTransactionAllocation::query()->where('project_transaction_id', $transaction->id)->count());
        $this->assertDatabaseHas('project_transaction_allocations', [
            'project_transaction_id' => $transaction->id,
            'work_item_id' => $additionalWorkItem->id,
            'amount' => 4972800,
            'role' => 'additional',
        ]);
    }

    public function test_expense_transaction_ignores_submitted_total_terms_and_opens_next_unpaid_payment(): void
    {
        $project = Project::create([
            'name' => 'Project Automatic Termin Test',
            'slug' => 'project-automatic-termin-test-'.uniqid(),
            'status' => 'active',
        ]);
        $vendor = Vendor::firstOrCreate(['name' => 'Build Dec Interior']);
        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Jasa Tukang', 'type' => 'keluar'],
            ['status' => 'active'],
        );
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'vendor_id' => $vendor->id,
            'name' => 'Pekerjaan Termin Delapan',
            'offer_rupiah' => 80000000,
        ]);

        $response = $this->post(route('transactions.store'), [
            'type' => 'keluar',
            'project_id' => $project->id,
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

        $this->assertSame(3, $paymentGroup->total_terms);
        $this->assertSame(1, $paymentGroup->paid_terms);
        $this->assertSame(3, ProjectTransaction::query()->latest('id')->value('payment_total'));
        $this->assertDatabaseHas('payment_terms', [
            'payment_group_id' => $paymentGroup->id,
            'payment_number' => 2,
            'amount' => 10000000,
        ]);
    }
}
