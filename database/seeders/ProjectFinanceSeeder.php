<?php

namespace Database\Seeders;

use App\Models\ActiveProjectSelection;
use App\Models\PaymentGroup;
use App\Models\Project;
use App\Models\ProjectArea;
use App\Models\TransactionCategory;
use App\Models\Vendor;
use App\Models\WorkItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProjectFinanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::firstOrCreate(
            ['slug' => 'project-kemang'],
            [
                'name' => 'Project Kemang',
                'status' => 'active',
                'description' => 'Project utama untuk pencatatan keuangan Kemang.',
            ],
        );

        $areas = collect([
            ['code' => 'K9', 'name' => 'Project Kemang - K9'],
            ['code' => 'K8', 'name' => 'Project Kemang - K8'],
            ['code' => 'C21', 'name' => 'Project Kemang - C21'],
            ['code' => 'Lainnya', 'name' => 'Project Kemang - Lainnya'],
        ])->mapWithKeys(function (array $area) use ($project) {
            $model = ProjectArea::firstOrCreate(
                ['project_id' => $project->id, 'code' => $area['code']],
                ['name' => $area['name']],
            );

            return [$area['code'] => $model];
        });

        foreach (['Dana Client', 'DP Project', 'Pelunasan', 'Reimbursement', 'Modal Tambahan'] as $name) {
            TransactionCategory::firstOrCreate(['name' => $name, 'type' => 'masuk']);
        }

        foreach (['Material', 'Jasa Tukang', 'Transportasi', 'Konsumsi', 'Operasional'] as $name) {
            TransactionCategory::firstOrCreate(['name' => $name, 'type' => 'keluar']);
        }

        $vendors = collect([
            'Client / Owner Project',
            'Goel',
            'Dedi Besi',
            'Satria Maju Bersama',
            'Wika WH',
            'Mugni',
            'Marmer Blanco Carrara',
            'Satvarious',
        ])->mapWithKeys(fn (string $name) => [
            Str::slug($name) => Vendor::firstOrCreate(['name' => $name]),
        ]);

        $items = [
            ['area' => 'K9', 'name' => 'DP pekerjaan interior', 'vendor' => 'Client / Owner Project'],
            ['area' => 'K9', 'name' => 'Pembelian marmer ruang tamu', 'vendor' => 'Marmer Blanco Carrara', 'brand' => 'Marmer Blanco Carrara'],
            ['area' => 'K9', 'name' => 'Transport survey lokasi', 'vendor' => 'Dedi Besi'],
            ['area' => 'K9', 'name' => 'Kanopi Kaca Koridor Samping Lt 3', 'vendor' => 'Goel'],
            ['area' => 'K8', 'name' => 'Kanopi Besi Koridor Samping Lt 2', 'vendor' => 'Dedi Besi', 'offer_rupiah' => 12750000],
            ['area' => 'K8', 'name' => 'Railing Tangga Depan', 'vendor' => 'Satria Maju Bersama', 'offer_rupiah' => 40710000],
            ['area' => 'K8', 'name' => 'Railing Tangga Belakang', 'vendor' => 'Satria Maju Bersama', 'offer_rupiah' => 39300000],
            ['area' => 'K8', 'name' => 'Water Heater', 'vendor' => 'Wika WH', 'offer_rupiah' => 136200000],
            ['area' => 'K8', 'name' => 'Pagar Depan', 'vendor' => 'Dedi Besi', 'offer_rupiah' => 29000000],
            ['area' => 'K8', 'name' => 'Pengerjaan Batu Aksesoris', 'vendor' => 'Mugni', 'offer_rupiah' => 4205000],
        ];

        foreach ($items as $item) {
            $vendor = $vendors[Str::slug($item['vendor'])] ?? null;

            WorkItem::firstOrCreate(
                [
                    'project_id' => $project->id,
                    'project_area_id' => $areas[$item['area']]->id,
                    'name' => $item['name'],
                ],
                [
                    'vendor_id' => $vendor?->id,
                    'brand' => $item['brand'] ?? null,
                    'offer_rupiah' => $item['offer_rupiah'] ?? null,
                    'offer_usd' => $item['offer_usd'] ?? null,
                ],
            );
        }

        PaymentGroup::firstOrCreate(
            ['project_id' => $project->id, 'code' => 'Kuitansi #001'],
            [
                'name' => 'Kuitansi #001',
                'total_amount' => 3000000,
                'total_terms' => 3,
                'fixed_total_terms' => 3,
                'paid_terms' => 2,
                'status' => 'berjalan',
            ],
        );

        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $project->id],
        );
    }
}
