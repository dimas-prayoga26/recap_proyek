@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard Keuangan Proyek')

@section('page_actions')
  <div class="dropdown project-switcher">
    <input type="hidden" id="active-project" value="project-kemang" />
    <button class="btn project-switcher-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
      <span class="project-switcher-icon"><i class="ti ti-folders"></i></span>
      <span>
        <span class="project-switcher-label">Proyek Aktif</span>
        <span class="project-switcher-name" id="active-project-name">Project Kemang</span>
      </span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" id="project-menu">
      <li>
        <button class="dropdown-item project-option active" type="button" data-project-value="project-kemang" data-project-name="Project Kemang">
          <span class="project-option-check"><i class="ti ti-folder"></i></span>
          <span class="project-option-copy">
            <span class="project-option-title">Project Kemang</span>
            <span class="project-option-meta">Saldo Rp 10,6 jt</span>
          </span>
        </button>
      </li>
      <li id="project-divider"><hr class="dropdown-divider" /></li>
      <li>
        <button class="dropdown-item project-add-option" type="button" id="open-project-modal">
          <i class="ti ti-plus me-2"></i> Tambah Proyek Baru
        </button>
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
            Rp 25,4 jt
            <i class="ti ti-arrow-up-right-circle opacity-50"></i>
          </span>
          <p class="mb-0 opacity-75">Total Uang Masuk</p>
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
            Rp 14,8 jt
            <i class="ti ti-arrow-down-right-circle opacity-50"></i>
          </span>
          <p class="mb-0 opacity-75">Total Uang Keluar</p>
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
                <strong>650</strong>
              </div>
            </div>
          </div>
          <span class="text-white d-block f-34 f-w-500 my-2">Rp 10,6 jt</span>
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
              <h3>Uang Masuk vs Uang Keluar</h3>
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
              <small class="text-muted">Project Kemang</small>
              <h4 class="mb-0">Posisi Termin</h4>
            </div>
            <div class="col-auto">
              <a href="{{ route('kelompok-pembayaran.index') }}" class="btn btn-sm btn-light-primary">Detail</a>
            </div>
          </div>

          <div class="rounded bg-light-warning p-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <div class="d-flex align-items-center">
                <div class="avtar avtar-lg bg-warning text-white">
                  <i class="ti ti-clock-dollar"></i>
                </div>
                <div class="ms-2">
                  <h5 class="mb-0">Termin Berjalan</h5>
                  <small class="text-muted">Kuitansi #001</small>
                </div>
              </div>
              <span class="badge bg-warning">2/3</span>
            </div>
            <div class="progress mb-2" style="height: 8px">
              <div class="progress-bar bg-warning" role="progressbar" style="width: 67%" aria-valuenow="67" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="d-flex justify-content-between">
              <small class="text-muted">Sudah dibayar Rp 2,0 jt</small>
              <small class="text-muted">Sisa Rp 1,0 jt</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card recent-transaction-card">
        <div class="card-header">
          <div class="row align-items-center">
            <div class="col">
              <small class="text-muted">Project Kemang</small>
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
                <tr>
                  <td>
                    <div class="transaction-date">
                      <strong>27/08/2026</strong>
                      <span>Kamis</span>
                    </div>
                  </td>
                  <td>
                    <div class="transaction-name-cell">
                      <div class="avtar avtar-s bg-light-primary">
                        <i class="ti ti-receipt-2 text-primary"></i>
                      </div>
                      <div>
                        <strong>Beli material awal</strong>
                        <span>Project Kemang - K9</span>
                      </div>
                    </div>
                  </td>
                  <td><span class="badge bg-light-primary text-primary">Material</span></td>
                  <td>Kuitansi #001 - 1/3</td>
                  <td><span class="badge bg-light-danger text-danger">Keluar</span></td>
                  <td class="text-end"><span class="transaction-amount text-danger">- Rp 1.000.000</span></td>
                  <td>
                    <button type="button" class="btn btn-sm btn-light-success receipt-action">
                      <i class="ti ti-photo"></i> JPEG
                    </button>
                  </td>
                </tr>
                <tr>
                  <td>
                    <div class="transaction-date">
                      <strong>27/08/2026</strong>
                      <span>Kamis</span>
                    </div>
                  </td>
                  <td>
                    <div class="transaction-name-cell">
                      <div class="avtar avtar-s bg-light-success">
                        <i class="ti ti-wallet text-success"></i>
                      </div>
                      <div>
                        <strong>DP project</strong>
                        <span>Project Kemang - K9</span>
                      </div>
                    </div>
                  </td>
                  <td><span class="badge bg-light-success text-success">Dana Client</span></td>
                  <td>-</td>
                  <td><span class="badge bg-light-success text-success">Masuk</span></td>
                  <td class="text-end"><span class="transaction-amount text-success">+ Rp 5.000.000</span></td>
                  <td><span class="badge bg-light-secondary text-secondary">Belum Ada</span></td>
                </tr>
                <tr>
                  <td>
                    <div class="transaction-date">
                      <strong>26/08/2026</strong>
                      <span>Rabu</span>
                    </div>
                  </td>
                  <td>
                    <div class="transaction-name-cell">
                      <div class="avtar avtar-s bg-light-warning">
                        <i class="ti ti-receipt text-warning"></i>
                      </div>
                      <div>
                        <strong>Transport survey lokasi</strong>
                        <span>Project Kemang - K8</span>
                      </div>
                    </div>
                  </td>
                  <td><span class="badge bg-light-warning text-warning">Transportasi</span></td>
                  <td>-</td>
                  <td><span class="badge bg-light-danger text-danger">Keluar</span></td>
                  <td class="text-end"><span class="transaction-amount text-danger">- Rp 250.000</span></td>
                  <td>
                    <button type="button" class="btn btn-sm btn-light-success receipt-action">
                      <i class="ti ti-photo"></i> JPEG
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="projectModal" tabindex="-1" aria-labelledby="projectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="projectModalLabel">Tambah Proyek Baru</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="project-name" class="form-label">Nama Proyek</label>
            <input type="text" class="form-control" id="project-name" placeholder="Contoh: Project BSD" />
          </div>
          <div class="mb-0">
            <label for="project-team" class="form-label">Tim</label>
            <input type="text" class="form-control" id="project-team" placeholder="Contoh: Tim Finance" />
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-primary" id="save-project-button">
            <i class="ti ti-plus me-1"></i> Tambah Proyek
          </button>
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
      const activeProjectName = document.querySelector('#active-project-name');
      const projectMenu = document.querySelector('#project-menu');
      const openProjectModalButton = document.querySelector('#open-project-modal');
      const projectDivider = document.querySelector('#project-divider');
      const projectModalElement = document.querySelector('#projectModal');
      const projectModal = new bootstrap.Modal(projectModalElement);
      const projectNameInput = document.querySelector('#project-name');
      const saveProjectButton = document.querySelector('#save-project-button');
      const excelLink = document.querySelector('#export-excel-link');
      const pdfLink = document.querySelector('#export-pdf-link');

      function updateExportLinks() {
        const selectedProject = activeProjectInput.value;
        excelLink.href = '{{ route('laporan.index') }}?export=excel&project=' + encodeURIComponent(selectedProject);
        pdfLink.href = '{{ route('laporan.index') }}?export=pdf&project=' + encodeURIComponent(selectedProject);
      }

      function setActiveProject(value, name, selectedButton) {
        activeProjectInput.value = value;
        activeProjectName.textContent = name;

        document.querySelectorAll('.project-option').forEach(function (button) {
          button.classList.remove('active');
        });

        if (selectedButton) {
          selectedButton.classList.add('active');
        }

        updateExportLinks();
      }

      function bindProjectOption(button) {
        button.addEventListener('click', function () {
          setActiveProject(button.dataset.projectValue, button.dataset.projectName, button);
        });
      }

      document.querySelectorAll('.project-option').forEach(bindProjectOption);

      openProjectModalButton.addEventListener('click', function () {
        projectNameInput.value = '';
        projectModal.show();
      });

      saveProjectButton.addEventListener('click', function () {
        const projectName = projectNameInput.value.trim();

        if (!projectName) {
          projectNameInput.focus();
          return;
        }

        const projectValue = projectName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        const projectItem = document.createElement('li');
        const projectButton = document.createElement('button');

        projectButton.className = 'dropdown-item project-option';
        projectButton.type = 'button';
        projectButton.dataset.projectValue = projectValue;
        projectButton.dataset.projectName = projectName;
        projectButton.innerHTML =
          '<span class="project-option-check"><i class="ti ti-folder"></i></span><span class="project-option-copy"><span class="project-option-title"></span><span class="project-option-meta">Proyek baru</span></span>';
        projectButton.querySelector('.project-option-title').textContent = projectName;
        projectItem.appendChild(projectButton);
        projectMenu.insertBefore(projectItem, projectDivider);
        bindProjectOption(projectButton);
        setActiveProject(projectValue, projectName, projectButton);
        projectModal.hide();
      });

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
          { name: 'Uang Masuk', data: [7, 4, 6, 8, 5, 12, 9, 6, 10, 7, 11, 14] },
          { name: 'Uang Keluar', data: [3, 5, 4, 6, 4, 7, 6, 3, 8, 5, 7, 9] },
          { name: 'Saldo', data: [4, 3, 5, 7, 6, 8, 9, 7, 9, 10, 12, 13] }
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
