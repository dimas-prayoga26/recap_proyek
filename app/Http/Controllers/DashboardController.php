<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveProject;
use App\Models\ActiveProjectSelection;
use App\Models\PaymentGroup;
use App\Models\Project;
use App\Models\ProjectOffer;
use App\Models\ProjectTransaction;
use App\Models\ProjectTransactionAttachment;
use App\Support\ExchangeRateService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesActiveProject;

    public function index(ExchangeRateService $exchangeRateService): View
    {
        $projects = Project::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $activeProject = $this->activeProject();

        $income = $this->projectTotal($activeProject, 'masuk');
        $expense = $this->projectTotal($activeProject, 'keluar');
        $balance = $income - $expense;
        $usdToIdrRate = $exchangeRateService->usdToIdr();

        return view('dashboard', [
            'projects' => $projects,
            'activeProject' => $activeProject,
            'projectBalances' => $this->projectBalances($projects),
            'offerSummary' => $this->projectOfferSummary($activeProject, $usdToIdrRate),
            'summary' => [
                'income' => $this->formatRupiahShort($income),
                'expense' => $this->formatRupiahShort($expense),
                'balance' => $this->formatRupiahShort($balance),
                'balance_usd' => number_format(max(0, $balance) / $usdToIdrRate, 0, ',', '.'),
            ],
            'chartSeries' => $this->chartSeries($activeProject),
            'paymentGroups' => $this->paymentGroupSummaries($activeProject),
            'recentTransactions' => $this->recentTransactions($activeProject),
        ]);
    }

    public function updateActiveProject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => [
                'required',
                Rule::exists('projects', 'id')->where('status', 'active'),
            ],
            'redirect_to' => ['nullable', 'string', 'max:2048'],
        ]);

        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $validated['project_id']],
        );

        $redirectTo = $validated['redirect_to'] ?? null;

        if ($redirectTo && str_starts_with($redirectTo, '/')) {
            return redirect()->to($redirectTo);
        }

        return redirect()->route('dashboard');
    }

    private function projectOfferSummary(?Project $project, float $usdToIdrRate): array
    {
        if (! $project) {
            return [
                'idr' => $this->formatRupiahShort(0),
                'usd' => $this->formatUsdShort(0),
                'rate' => $this->formatRupiah((int) round($usdToIdrRate)),
            ];
        }

        $totals = ProjectOffer::query()
            ->whereBelongsTo($project)
            ->toBase()
            ->selectRaw('COALESCE(SUM(penawaran_rupiah), 0) as rupiah_total')
            ->selectRaw('COALESCE(SUM(penawaran_usd), 0) as usd_total')
            ->first();

        $rupiahTotal = (int) ($totals->rupiah_total ?? 0);
        $usdTotal = (float) ($totals->usd_total ?? 0);
        $idrEquivalent = $rupiahTotal + (int) round($usdTotal * $usdToIdrRate);

        return [
            'idr' => $this->formatRupiahShort($idrEquivalent),
            'usd' => $this->formatUsdShort($idrEquivalent / $usdToIdrRate),
            'rate' => $this->formatRupiah((int) round($usdToIdrRate)),
        ];
    }

    private function projectTotal(?Project $project, string $type): int
    {
        if (! $project) {
            return 0;
        }

        return (int) ProjectTransaction::query()
            ->whereBelongsTo($project)
            ->where('type', $type)
            ->sum('amount');
    }

    private function projectBalances(Collection $projects): array
    {
        $balances = ProjectTransaction::query()
            ->select('project_id')
            ->selectRaw("SUM(CASE WHEN type = 'masuk' THEN amount ELSE -amount END) as balance")
            ->groupBy('project_id')
            ->pluck('balance', 'project_id');

        return $projects
            ->mapWithKeys(fn (Project $project) => [
                $project->id => $this->formatRupiahShort((int) ($balances[$project->id] ?? 0)),
            ])
            ->all();
    }

    private function chartSeries(?Project $project): array
    {
        $income = array_fill(0, 12, 0);
        $expense = array_fill(0, 12, 0);
        $balance = array_fill(0, 12, 0);

        if (! $project) {
            return compact('income', 'expense', 'balance');
        }

        $monthlyTotals = ProjectTransaction::query()
            ->whereBelongsTo($project)
            ->whereYear('recorded_at', now()->year)
            ->selectRaw('MONTH(recorded_at) as transaction_month, type, SUM(amount) as total')
            ->groupBy('transaction_month', 'type')
            ->get();

        foreach ($monthlyTotals as $total) {
            $index = (int) $total->transaction_month - 1;
            $value = round(((int) $total->total) / 1000000, 2);

            if ($total->type === 'masuk') {
                $income[$index] = $value;
            }

            if ($total->type === 'keluar') {
                $expense[$index] = $value;
            }
        }

        foreach (range(0, 11) as $index) {
            $balance[$index] = round($income[$index] - $expense[$index], 2);
        }

        return compact('income', 'expense', 'balance');
    }

    private function paymentGroupSummaries(?Project $project): Collection
    {
        if (! $project) {
            return collect();
        }

        return PaymentGroup::query()
            ->with(['terms', 'workItem.vendor'])
            ->whereBelongsTo($project)
            ->latest()
            ->get()
            ->map(fn (PaymentGroup $paymentGroup): array => $this->paymentGroupSummary($paymentGroup));
    }

    private function paymentGroupSummary(PaymentGroup $paymentGroup): array
    {
        $totalAmount = (int) ($paymentGroup->offer_rupiah_snapshot ?? $paymentGroup->total_amount ?? 0);
        $paidTerms = $paymentGroup->terms->count();
        $paidAmount = (int) $paymentGroup->terms->sum('amount');
        $remainingAmount = $totalAmount - $paidAmount;
        $highestPaymentNumber = (int) $paymentGroup->terms->max('payment_number');
        $totalTerms = $remainingAmount > 0 ? $highestPaymentNumber + 1 : max($highestPaymentNumber, 1);
        $progress = $totalAmount > 0
            ? min(100, max(0, (int) round(($paidAmount / $totalAmount) * 100)))
            : 0;

        return [
            'code' => $paymentGroup->code,
            'work_item_id' => $paymentGroup->work_item_id,
            'work_item_name' => $paymentGroup->workItem?->name ?? 'Belum ada pekerjaan',
            'work_item_alias' => $this->workItemAlias($paymentGroup->workItem?->name ?? 'Termin'),
            'vendor_name' => $paymentGroup->workItem?->vendor?->name ?? '-',
            'paid_terms' => $paidTerms,
            'total_terms' => $totalTerms,
            'progress' => $progress,
            'is_paid_off' => $remainingAmount <= 0,
            'total_amount' => $this->formatRupiah($totalAmount),
            'paid_amount' => $this->formatRupiah($paidAmount),
            'remaining_amount' => $this->formatRupiah($remainingAmount),
        ];
    }

    private function workItemAlias(string $name): string
    {
        $words = collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->values();

        if ($words->isEmpty()) {
            return 'TR';
        }

        if ($words->count() === 1) {
            return Str::upper(Str::substr((string) $words->first(), 0, 2));
        }

        return Str::upper($words
            ->map(fn (string $word): string => Str::substr($word, 0, 1))
            ->join(''));
    }

    private function recentTransactions(?Project $project): Collection
    {
        if (! $project) {
            return collect();
        }

        return ProjectTransaction::query()
            ->with(['attachments', 'vendor', 'workItem.vendor'])
            ->whereBelongsTo($project)
            ->latest('recorded_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(function (ProjectTransaction $transaction) use ($project) {
                $attachment = $transaction->attachments->first();

                return [
                    'date' => $transaction->recorded_at?->format('d/m/Y') ?? '-',
                    'day' => $this->dayName($transaction->recorded_at),
                    'recorded_at' => $this->formatRecordedDate($transaction->recorded_at),
                    'name' => $transaction->workItem?->name ?? '-',
                    'project_name' => $project->name,
                    'vendor' => $transaction->vendor?->name ?? $transaction->workItem?->vendor?->name ?? '-',
                    'type' => $transaction->type,
                    'amount' => $this->formatRupiah($transaction->amount),
                    'receipt_url' => $attachment ? $this->attachmentUrl($attachment) : null,
                    'receipt_mime' => $attachment?->mime_type,
                ];
            });
    }

    private function attachmentUrl(ProjectTransactionAttachment $attachment): string
    {
        if ($attachment->disk === 'public') {
            return '/storage/'.ltrim($attachment->path, '/');
        }

        return Storage::disk($attachment->disk)->url($attachment->path);
    }

    private function formatRecordedDate(?CarbonInterface $date): string
    {
        if (! $date) {
            return '-';
        }

        return $this->dayName($date).', '.$date->format('Y-m-d');
    }

    private function dayName(?CarbonInterface $date): string
    {
        if (! $date) {
            return '-';
        }

        return [
            'Minggu',
            'Senin',
            'Selasa',
            'Rabu',
            'Kamis',
            'Jumat',
            'Sabtu',
        ][$date->dayOfWeek];
    }

    private function formatRupiah(int $amount): string
    {
        $prefix = $amount < 0 ? '- ' : '';

        return $prefix.'Rp '.number_format(abs($amount), 0, ',', '.');
    }

    private function formatRupiahShort(int $amount): string
    {
        $prefix = $amount < 0 ? '- ' : '';
        $amount = abs($amount);

        if ($amount >= 1000000000) {
            return $prefix.'Rp '.$this->formatDecimal($amount / 1000000000).' M';
        }

        if ($amount >= 1000000) {
            return $prefix.'Rp '.$this->formatDecimal($amount / 1000000).' jt';
        }

        if ($amount >= 1000) {
            return $prefix.'Rp '.$this->formatDecimal($amount / 1000).' rb';
        }

        return $prefix.$this->formatRupiah($amount);
    }

    private function formatUsdShort(float $amount): string
    {
        if ($amount <= 0) {
            return 'USD 0';
        }

        if ($amount >= 1000000) {
            return 'USD '.$this->formatDecimal($amount / 1000000).' M';
        }

        if ($amount >= 1000) {
            return 'USD '.$this->formatDecimal($amount / 1000).' K';
        }

        return 'USD '.number_format($amount, $amount >= 100 ? 0 : 2, '.', ',');
    }

    private function formatDecimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
    }
}
