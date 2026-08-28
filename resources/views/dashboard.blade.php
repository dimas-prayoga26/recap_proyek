@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard Keuangan Proyek')

@section('page_actions')
  <div class="dropdown project-switcher">
    <input type="hidden" id="active-project" value="{{ $activeProject?->slug ?? '' }}" />
    <button class="btn project-switcher-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <span class="project-switcher-icon"><i class="ti ti-folders"></i></span>
      <span>
        <span class="project-switcher-label">Proyek Aktif</span>
        <span class="project-switcher-name" id="active-project-name">{{ $activeProject?->name ?? 'Belum ada project' }}</span>
      </span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" id="project-menu">
      @forelse ($projects as $project)
        <li>
          <form method="POST" action="{{ route('dashboard.active-project') }}">
            @csrf
            <input type="hidden" name="project_id" value="{{ $project->id }}" />
            <button class="dropdown-item project-option {{ $activeProject?->is($project) ? 'active' : '' }}" type="submit" data-project-value="{{ $project->slug }}" data-project-name="{{ $project->name }}">
              <span class="project-option-check"><i class="ti ti-folder"></i></span>
              <span class="project-option-copy">
                <span class="project-option-title">{{ $project->name }}</span>
                <span class="project-option-meta">Saldo {{ $projectBalances[$project->id] ?? 'Rp 0' }}</span>
              </span>
            </button>
          </form>
        </li>
      @empty
        <li>
          <span class="dropdown-item text-muted">Project belum tersedia</span>
        </li>
      @endforelse
      <li id="project-divider"><hr class="dropdown-divider" /></li>
      <li>
        <a class="dropdown-item project-add-option" href="{{ route('project.index') }}">
          <i class="ti ti-plus me-2"></i> Tambah Proyek Baru
        </a>
      </li>
    </ul>
  </div>
  <div class="dropdown dashboard-export">
    <button class="btn btn-primary dropdown-toggle dashboard-export-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="ti ti-download me-1"></i> Export
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
      <li>
        <a class="dropdown-item" href="{{ route('laporan.index', ['export' => 'excel']) }}" id="export-excel-link">
          <i class="ti ti-file-export me-2"></i> Excel
        </a>
      </li>
      <li>
        <a class="dropdown-item" href="{{ route('laporan.index', ['export' => 'pdf']) }}" id="export-pdf-link">
          <i class="ti ti-file-type-pdf me-2"></i> PDF
        </a>
      </li>
    </ul>
  </div>
@endsection

@push('styles')
  <style>
    .termin-position-card {
      transition: box-shadow 0.15s ease, transform 0.15s ease;
    }

    .termin-position-card:hover {
      box-shadow: 0 4px 12px rgba(16, 24, 40, 0.08);
      transform: translateY(-1px);
    }

    .recent-transaction-card .card-header {
      border-bottom: 1px solid #eef2f6;
      padding-bottom: 18px;
    }

    .recent-transaction-table thead th {
      background: #f8fafc;
      border-bottom: 0;
      color: #697586;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0;
      text-transform: uppercase;
    }

    .recent-transaction-table tbody td {
      vertical-align: middle;
    }

    .transaction-name-cell {
      align-items: center;
      display: flex;
      gap: 12px;
      min-width: 280px;
    }

    .transaction-name-cell .avtar {
      flex: 0 0 auto;
    }

    .transaction-name-cell strong {
      color: #202939;
      display: block;
      font-weight: 600;
      line-height: 1.25;
    }

    .transaction-name-cell span {
      color: #697586;
      display: block;
      font-size: 12px;
      margin-top: 3px;
    }

    .transaction-date strong {
      color: #202939;
      display: block;
      font-weight: 600;
    }

    .transaction-date span {
      color: #697586;
      display: block;
      font-size: 12px;
      margin-top: 2px;
    }

    .transaction-amount {
      font-size: 15px;
      font-weight: 700;
      white-space: nowrap;
    }

    .receipt-action {
      align-items: center;
      display: inline-flex;
      gap: 6px;
    }

    @media (max-width: 767.98px) {
      .recent-transaction-card .card-header .row {
        gap: 14px;
      }

      .recent-transaction-card .card-header .col-auto {
        width: 100%;
      }

      .recent-transaction-card .card-header .btn {
        width: 100%;
      }
    }
  </style>
@endpush

@section('content')
  <div class="row">
    <div class="col-xl-4 col-md-6">
      <div class="card bg-secondary-dark dashnum-card finance-stat text-white overflow-hidden">
        <span class="round small"></span>
        <span class="round big"></span>
        <div class="card-body">
          <div class="row">
            <div class="col">
              <div class="avtar avtar-lg">
                <i class="text-white ti ti-wallet"></i>
              </div>
            </div>
            <div class="col-auto">
              <a href="{{ route('uang-masuk.index') }}" class="avtar avtar-s bg-secondary text-white">
                <i class="ti ti-plus"></i>
              </a>
            </div>
          </div>
          <span class="text-white d-block f-34 f-w-500 my-2">
            {{ $summary['income'] }}
            <i class="ti ti-arrow-up-right-circle opacity-50"></i>
          </span>
          <p class="mb-0 opacity-75">Total Debet</p>
        </div>
      </div>
    </div>

    <div class="col-xl-4 col-md-6">
      <div class="card bg-primary-dark dashnum-card finance-stat text-white overflow-hidden">
        <span class="round small"></span>
        <span class="round big"></span>
        <div class="card-body">
          <div class="row">
            <div class="col">
              <div class="avtar avtar-lg">
                <i class="text-white ti ti-receipt-2"></i>
              </div>
            </div>
            <div class="col-auto">
              <a href="{{ route('uang-keluar.index') }}" class="avtar avtar-s bg-primary text-white">
                <i class="ti ti-plus"></i>
              </a>
            </div>
          </div>
          <span class="text-white d-block f-34 f-w-500 my-2">
            {{ $summary['expense'] }}
            <i class="ti ti-arrow-down-right-circle opacity-50"></i>
          </span>
          <p class="mb-0 opacity-75">Total Kredit</p>
        </div>
      </div>
    </div>

    <div class="col-xl-4 col-md-12">
      <div class="card bg-primary-dark dashnum-card finance-stat project-balance-card text-white overflow-hidden">
        <span class="round bg-primary small"></span>
        <span class="round bg-primary big"></span>
        <div class="card-body">
          <div class="row">
            <div class="col">
              <div class="avtar avtar-lg">
                <i class="text-white ti ti-report-money"></i>
              </div>
            </div>
            <div class="col-auto">
              <div class="balance-usd-chip">
                <span>USD</span>
                <strong>{{ $summary['balance_usd'] }}</strong>
              </div>
            </div>
          </div>
          <span class="text-white d-block f-34 f-w-500 my-2">{{ $summary['balance'] }}</span>
          <p class="mb-0 opacity-75">Saldo Proyek IDR</p>
        </div>
      </div>
    </div>

    <div class="col-xl-8 col-md-12 d-flex dashboard-section-gap">
      <div class="card dashboard-equal-card w-100">
        <div class="card-body">
          <div class="row mb-3 align-items-center">
            <div class="col">
              <small class="text-muted">Arus Kas</small>
              <h3>Debet vs Kredit</h3>
            </div>
            <div class="col-auto">
              <select class="form-select p-r-35">
                <option>Bulan Ini</option>
                <option selected>Tahun Ini</option>
                <option>Semester Ini</option>
              </select>
            </div>
          </div>
          <div id="cashflow-chart"></div>
        </div>
      </div>
    </div>

    <div class="col-xl-4 col-md-12 d-flex dashboard-section-gap">
      <div class="card dashboard-equal-card w-100">
        <div class="card-body d-flex flex-column">
          <div class="row mb-3 align-items-center">
            <div class="col">
              <small class="text-muted">{{ $activeProject?->name ?? 'Belum ada project' }}</small>
              <h4 class="mb-0">Posisi Termin</h4>
            </div>
            <div class="col-auto">
              <a
                href="{{ $paymentGroup && $paymentGroup['work_item_id'] ? route('uang-keluar.index', ['work_item_id' => $paymentGroup['work_item_id']]) : route('termin-pembayaran.index') }}"
                class="btn btn-sm btn-light-primary"
              >
                Detail
              </a>
            </div>
          </div>

          @if ($paymentGroup)
            <a
              href="{{ $paymentGroup['work_item_id'] ? route('uang-keluar.index', ['work_item_id' => $paymentGroup['work_item_id']]) : route('termin-pembayaran.index') }}"
              class="rounded bg-light-warning p-3 d-block text-decoration-none termin-position-card"
            >
              <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center">
                  <div class="avtar avtar-lg bg-warning text-white">
                    <i class="ti ti-clock-dollar"></i>
                  </div>
                  <div class="ms-2">
                    <h5 class="mb-0">Termin Berjalan</h5>
                    <small class="text-muted">{{ $paymentGroup['code'] }}</small>
                  </div>
                </div>
                <span class="badge bg-warning">{{ $paymentGroup['paid_terms'] }}/{{ $paymentGroup['total_terms'] }}</span>
              </div>
              <div class="progress mb-2" style="height: 8px">
                <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $paymentGroup['progress'] }}%" aria-valuenow="{{ $paymentGroup['progress'] }}" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
              <div class="d-flex justify-content-between">
                <small class="text-muted">Sudah dibayar {{ $paymentGroup['paid_amount'] }}</small>
                <small class="text-muted">Sisa {{ $paymentGroup['remaining_amount'] }}</small>
              </div>
            </a>
          @else
            <div class="rounded bg-light-secondary p-3">
              <h5 class="mb-1">Belum ada termin</h5>
              <small class="text-muted">Kelompok pembayaran untuk project ini belum dibuat.</small>
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card recent-transaction-card">
        <div class="card-header">
          <div class="row align-items-center">
            <div class="col">
              <small class="text-muted">{{ $activeProject?->name ?? 'Belum ada project' }}</small>
              <h4 class="mb-0">Transaksi Terbaru</h4>
            </div>
            <div class="col-auto d-flex flex-wrap gap-2">
              <a href="{{ route('uang-keluar.index') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Input Transaksi
              </a>
              <a href="{{ route('laporan.index') }}" class="btn btn-light-primary">
                <i class="ti ti-file-export me-1"></i> Export Laporan
              </a>
            </div>
          </div>
        </div>
        <div class="card-body pt-0">
          <div class="table-responsive">
            <table class="table table-hover table-nowrap recent-transaction-table mb-0">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Nama Barang / Kegiatan</th>
                  <th>Kategori</th>
                  <th>Kelompok</th>
                  <th>Jenis</th>
                  <th class="text-end">Nominal</th>
                  <th>Bukti</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($recentTransactions as $transaction)
                  @php
                    $isIncomeTransaction = $transaction['type'] === 'masuk';
                  @endphp
                  <tr>
                    <td>
                      <div class="transaction-date">
                        <strong>{{ $transaction['date'] }}</strong>
                        <span>{{ $transaction['day'] }}</span>
                      </div>
                    </td>
                    <td>
                      <div class="transaction-name-cell">
                        <div class="avtar avtar-s {{ $isIncomeTransaction ? 'bg-light-success' : 'bg-light-primary' }}">
                          <i class="ti {{ $isIncomeTransaction ? 'ti-wallet' : 'ti-receipt-2' }} {{ $isIncomeTransaction ? 'text-success' : 'text-primary' }}"></i>
                        </div>
                        <div>
                          <strong>{{ $transaction['name'] }}</strong>
                          <span>{{ $transaction['area'] }}</span>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="badge {{ $isIncomeTransaction ? 'bg-light-success text-success' : 'bg-light-primary text-primary' }}">
                        {{ $transaction['category'] }}
                      </span>
                    </td>
                    <td>{{ $transaction['group'] }}</td>
                    <td>
                      <span class="badge {{ $isIncomeTransaction ? 'bg-light-success text-success' : 'bg-light-danger text-danger' }}">
                        {{ $isIncomeTransaction ? 'Masuk' : 'Keluar' }}
                      </span>
                    </td>
                    <td class="text-end">
                      <span class="transaction-amount {{ $isIncomeTransaction ? 'text-success' : 'text-danger' }}">
                        {{ $isIncomeTransaction ? '+' : '-' }} {{ $transaction['amount'] }}
                      </span>
                    </td>
                    <td>
                      @if ($transaction['has_receipt'])
                        <span class="badge bg-light-success text-success">JPEG</span>
                      @else
                        <span class="badge bg-light-secondary text-secondary">Belum Ada</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                      Belum ada transaksi untuk project aktif ini.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
  <script src="{{ asset('assets/berry/js/plugins/apexcharts.min.js') }}"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const activeProjectInput = document.querySelector('#active-project');
      const excelLink = document.querySelector('#export-excel-link');
      const pdfLink = document.querySelector('#export-pdf-link');
      const chartSeries = @json($chartSeries);

      function updateExportLinks() {
        const selectedProject = activeProjectInput.value;
        excelLink.href = '{{ route('laporan.index') }}?export=excel&project=' + encodeURIComponent(selectedProject);
        pdfLink.href = '{{ route('laporan.index') }}?export=pdf&project=' + encodeURIComponent(selectedProject);
      }

      updateExportLinks();

      new ApexCharts(document.querySelector('#cashflow-chart'), {
        chart: {
          type: 'bar',
          height: 420,
          stacked: false,
          toolbar: { show: false }
        },
        plotOptions: {
          bar: { horizontal: false, columnWidth: '46%' }
        },
        dataLabels: { enabled: false },
        colors: ['#2196f3', '#673ab7', '#ff9800'],
        series: [
          { name: 'Debet', data: chartSeries.income },
          { name: 'Kredit', data: chartSeries.expense },
          { name: 'Saldo', data: chartSeries.balance }
        ],
        xaxis: {
          categories: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
        },
        yaxis: {
          labels: {
            formatter: function (value) {
              return 'Rp ' + value + ' jt';
            }
          }
        },
        grid: { strokeDashArray: 4 },
        tooltip: { theme: 'dark' }
      }).render();

    });
  </script>
@endpush
