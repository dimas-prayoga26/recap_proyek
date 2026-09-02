<?php

namespace Tests\Feature;

use App\Models\ActiveProjectSelection;
use App\Models\PaymentGroup;
use App\Models\PaymentTerm;
use App\Models\Project;
use App\Models\ProjectOffer;
use App\Models\ProjectTransaction;
use App\Models\TransactionCategory;
use App\Models\WorkItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_shows_total_offer_card_for_active_project_with_usd_converted_to_idr(): void
    {
        Cache::forget('exchange-rate.usd-idr');
        Http::preventStrayRequests();
        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                [
                    'date' => '2026-09-02',
                    'base' => 'USD',
                    'quote' => 'IDR',
                    'rate' => 17000,
                ],
            ]),
        ]);

        $activeProject = Project::create([
            'name' => 'Project Total Offer Dashboard',
            'slug' => 'project-total-offer-dashboard-'.uniqid(),
            'status' => 'active',
        ]);
        $otherProject = Project::create([
            'name' => 'Project Lain Dashboard',
            'slug' => 'project-lain-dashboard-'.uniqid(),
            'status' => 'active',
        ]);
        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $activeProject->id],
        );
        ProjectOffer::create([
            'project_id' => $activeProject->id,
            'project_name' => $activeProject->name,
            'pekerjaan' => 'Pekerjaan Rupiah Dashboard',
            'penawaran_rupiah' => 1500000,
        ]);
        ProjectOffer::create([
            'project_id' => $activeProject->id,
            'project_name' => $activeProject->name,
            'pekerjaan' => 'Pekerjaan USD Dashboard',
            'penawaran_usd' => 100,
        ]);
        ProjectOffer::create([
            'project_id' => $otherProject->id,
            'project_name' => $otherProject->name,
            'pekerjaan' => 'Pekerjaan Project Lain',
            'penawaran_rupiah' => 9000000,
        ]);

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('dashboard-summary-strip', false)
            ->assertSee('Total Penawaran')
            ->assertSee('data-idr-total="Rp 3,2 jt"', false)
            ->assertSee('data-usd-total="USD 188"', false)
            ->assertSee('Kurs sekarang USD Rp 17.000');

        Http::assertSentCount(1);
    }

    public function test_dashboard_uses_fallback_usd_rate_when_exchange_rate_api_fails(): void
    {
        Cache::forget('exchange-rate.usd-idr');
        Http::preventStrayRequests();
        Http::fake([
            'api.frankfurter.dev/*' => Http::failedConnection(),
        ]);

        $project = Project::create([
            'name' => 'Project Fallback Rate Dashboard',
            'slug' => 'project-fallback-rate-dashboard-'.uniqid(),
            'status' => 'active',
        ]);
        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $project->id],
        );
        ProjectOffer::create([
            'project_id' => $project->id,
            'project_name' => $project->name,
            'pekerjaan' => 'Pekerjaan USD Fallback',
            'penawaran_usd' => 1,
        ]);

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('data-idr-total="Rp 16,3 rb"', false)
            ->assertSee('Kurs sekarang USD Rp 16.300');
    }

    public function test_paid_off_payment_group_uses_success_card_and_work_item_alias(): void
    {
        Cache::forget('exchange-rate.usd-idr');
        Http::preventStrayRequests();
        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                [
                    'date' => '2026-09-02',
                    'base' => 'USD',
                    'quote' => 'IDR',
                    'rate' => 17000,
                ],
            ]),
        ]);

        $project = Project::create([
            'name' => 'Project Termin Lunas Dashboard',
            'slug' => 'project-termin-lunas-dashboard-'.uniqid(),
            'status' => 'active',
        ]);
        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $project->id],
        );
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'name' => 'Pagar Rumah Utama',
            'offer_rupiah' => 2500000,
        ]);
        $paymentGroup = PaymentGroup::create([
            'project_id' => $project->id,
            'work_item_id' => $workItem->id,
            'code' => 'Termin-'.$workItem->id,
            'name' => $workItem->name,
            'total_amount' => 2500000,
            'offer_rupiah_snapshot' => 2500000,
            'total_terms' => 1,
            'paid_terms' => 1,
            'status' => 'lunas',
        ]);
        PaymentTerm::create([
            'payment_group_id' => $paymentGroup->id,
            'payment_number' => 1,
            'amount' => 2500000,
            'paid_at' => '2026-09-02',
        ]);

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Aktivitas terbaru')
            ->assertSee('Termin Aktivitas')
            ->assertSee('termin-position-alias', false)
            ->assertSee('>PR<', false)
            ->assertSee('bg-light-success', false)
            ->assertSee('is-paid-off', false)
            ->assertSee('progress-bar bg-success', false)
            ->assertDontSee('Posisi Termin');
    }

    public function test_dashboard_shows_all_payment_groups_in_position_scroll(): void
    {
        Cache::put('exchange-rate.usd-idr', 17000, 300);

        $project = Project::create([
            'name' => 'Project Banyak Termin Dashboard',
            'slug' => 'project-banyak-termin-dashboard-'.uniqid(),
            'status' => 'active',
        ]);
        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $project->id],
        );
        $firstWorkItem = WorkItem::create([
            'project_id' => $project->id,
            'name' => 'Belanja Tambahan Marmer',
            'offer_rupiah' => 4972800,
        ]);
        $secondWorkItem = WorkItem::create([
            'project_id' => $project->id,
            'name' => 'Pintu Utama Aluminium',
            'offer_rupiah' => 325557023,
        ]);

        $firstPaymentGroup = PaymentGroup::create([
            'project_id' => $project->id,
            'work_item_id' => $firstWorkItem->id,
            'code' => 'Termin-'.$firstWorkItem->id,
            'name' => $firstWorkItem->name,
            'total_amount' => 4972800,
            'offer_rupiah_snapshot' => 4972800,
            'total_terms' => 1,
            'paid_terms' => 1,
            'status' => 'lunas',
        ]);
        PaymentTerm::create([
            'payment_group_id' => $firstPaymentGroup->id,
            'payment_number' => 1,
            'amount' => 4972800,
            'paid_at' => '2026-09-02',
        ]);
        PaymentGroup::create([
            'project_id' => $project->id,
            'work_item_id' => $secondWorkItem->id,
            'code' => 'Termin-'.$secondWorkItem->id,
            'name' => $secondWorkItem->name,
            'total_amount' => 325557023,
            'offer_rupiah_snapshot' => 325557023,
            'total_terms' => 2,
            'paid_terms' => 0,
            'status' => 'belum_lunas',
        ]);

        $response = $this->get(route('dashboard'));

        $response
            ->assertSee('termin-position-scroll', false)
            ->assertSee('termin-position-panel', false)
            ->assertSee('Belanja Tambahan Marmer')
            ->assertSee('Pintu Utama Aluminium')
            ->assertSee('overflow: hidden', false)
            ->assertSee('flex-direction: column', false)
            ->assertSee('flex-direction: row', false);
    }

    public function test_dashboard_receipt_preview_uses_relative_storage_url_for_modal(): void
    {
        config(['filesystems.disks.public.url' => 'http://127.0.0.1:8001/storage']);
        Cache::put('exchange-rate.usd-idr', 17000, 300);

        $project = Project::create([
            'name' => 'Project Receipt Dashboard',
            'slug' => 'project-receipt-dashboard-'.uniqid(),
            'status' => 'active',
        ]);
        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $project->id],
        );
        $workItem = WorkItem::create([
            'project_id' => $project->id,
            'name' => 'Pekerjaan Bukti Dashboard',
            'offer_rupiah' => 2500000,
        ]);
        $category = TransactionCategory::firstOrCreate(
            ['name' => 'Jasa Tukang', 'type' => 'keluar'],
            ['status' => 'active'],
        );
        $transaction = ProjectTransaction::create([
            'project_id' => $project->id,
            'transaction_category_id' => $category->id,
            'work_item_id' => $workItem->id,
            'type' => 'keluar',
            'amount' => 2500000,
            'recorded_at' => '2026-09-02',
        ]);
        $transaction->attachments()->create([
            'disk' => 'public',
            'path' => 'transaction-receipts/bukti-dashboard.jpg',
            'original_name' => 'bukti-dashboard.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 61000,
        ]);

        $response = $this->get(route('dashboard'));

        $response
            ->assertSee('data-bs-target="#receipt-preview-modal"', false)
            ->assertSee('receipt-modal-preview', false)
            ->assertSee('max-height: min(62vh, 520px)', false)
            ->assertSee('Tanggal Pencatatan')
            ->assertSee('setReceiptPreviewZoom')
            ->assertSee("addEventListener('wheel'", false)
            ->assertSee('is-dragging')
            ->assertDontSee('id="receipt-preview-zoom-controls"', false)
            ->assertDontSee('id="receipt-preview-zoom-in"', false)
            ->assertDontSee('id="receipt-preview-zoom-out"', false)
            ->assertSee('data-receipt-date="Rabu, 2026-09-02"', false)
            ->assertSee('data-receipt-url="/storage/transaction-receipts/bukti-dashboard.jpg"', false)
            ->assertDontSee('127.0.0.1:8001/storage');
    }
}
