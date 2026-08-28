<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesActiveProject;
use App\Models\ActiveProjectSelection;
use App\Models\PaymentGroup;
use App\Models\Project;
use App\Models\ProjectTransaction;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesActiveProject;

    public function index(): View
    {
        $projects = Project::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $activeProject = $this->activeProject();

        $income = $this->projectTotal($activeProject, 'masuk');
        $expense = $this->projectTotal($activeProject, 'keluar');
        $balance = $income - $expense;

        return view('dashboard', [
            'projects' => $projects,
            'activeProject' => $activeProject,
            'projectBalances' => $this->projectBalances($projects),
            'summary' => [
                'income' => $this->formatRupiahShort($income),
                'expense' => $this->formatRupiahShort($expense),
                'balance' => $this->formatRupiahShort($balance),
                'balance_usd' => number_format(max(0, $balance) / 16300, 0, ',', '.'),
            ],
            'chartSeries' => $this->chartSeries($activeProject),
            'paymentGroup' => $this->paymentGroupSummary($activeProject),
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
        ]);

        ActiveProjectSelection::updateOrCreate(
            ['key' => 'dashboard'],
            ['project_id' => $validated['project_id']],
        );

        return redirect()->route('dashboard');
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

    private function paymentGroupSummary(?Project $project): ?array
    {
        if (! $project) {
            return null;
        }

        $paymentGroup = PaymentGroup::query()
            ->with(['terms', 'workItem'])
            ->whereBelongsTo($project)
            ->latest()
            ->first();

        if (! $paymentGroup) {
            return null;
        }

        $totalAmount = (int) ($paymentGroup->offer_rupiah_snapshot ?? $paymentGroup->total_amount ?? 0);
        $paidTerms = $paymentGroup->terms->count();
        $paidAmount = (int) $paymentGroup->terms->sum('amount');
        $remainingAmount = $totalAmount - $paidAmount;
        $highestPaymentNumber = (int) $paymentGroup->terms->max('payment_number');
        $totalTerms = $remainingAmount > 0 ? $highestPaymentNumber + 1 : max($highestPaymentNumber, 1);

        return [
            'code' => $paymentGroup->code,
            'work_item_id' => $paymentGroup->work_item_id,
            'paid_terms' => $paidTerms,
            'total_terms' => $totalTerms,
            'progress' => (int) round(($paidTerms / $totalTerms) * 100),
            'paid_amount' => $this->formatRupiahShort($paidAmount),
            'remaining_amount' => $this->formatRupiahShort($remainingAmount),
        ];
    }

    private function recentTransactions(?Project $project): Collection
    {
        if (! $project) {
            return collect();
        }

        return ProjectTransaction::query()
            ->with(['attachments', 'category', 'paymentGroup', 'projectArea', 'workItem'])
            ->whereBelongsTo($project)
            ->latest('recorded_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (ProjectTransaction $transaction) => [
                'date' => $transaction->recorded_at?->format('d/m/Y') ?? '-',
                'day' => $this->dayName($transaction->recorded_at),
                'name' => $transaction->workItem?->name ?? '-',
                'area' => $transaction->projectArea?->name ?? $project->name,
                'category' => $transaction->category?->name ?? '-',
                'group' => $transaction->paymentGroup
                    ? $transaction->paymentGroup->code.' - '.($transaction->payment_number ?? 1).'/'.($transaction->payment_total ?? $transaction->paymentGroup->total_terms ?? 1)
                    : '-',
                'type' => $transaction->type,
                'amount' => $this->formatRupiah($transaction->amount),
                'has_receipt' => $transaction->attachments->isNotEmpty(),
            ]);
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
        return 'Rp '.number_format($amount, 0, ',', '.');
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

    private function formatDecimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
    }
}
