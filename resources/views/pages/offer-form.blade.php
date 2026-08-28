@php
  $selectedProject = old('project_id')
    ? ($projects ?? collect())->firstWhere('id', (int) old('project_id'))
    : $activeProject;
  $selectedArea = old('area', $filters['area'] ?? ($areas ?? collect())->first()?->code ?? 'K9');
@endphp

@extends('layouts.app')

@section('title', $title)
@section('page_title', $title)

@section('page_actions')
  <a href="{{ route('project.index') }}" class="btn btn-light-secondary">
    <i class="ti ti-folders me-1"></i> Project Holding
  </a>
  <a href="{{ route('uang-keluar.index') }}" class="btn btn-primary">
    <i class="ti ti-plus me-1"></i> Input Transaksi
  </a>
@endsection

@push('styles')
  <style>
    .form-helper {
      color: #697586;
      display: block;
      font-size: 12px;
      margin-top: 6px;
    }

    .offer-form-title {
      align-items: center;
      border-bottom: 1px solid #eef2f6;
      display: flex;
      gap: 12px;
      margin-bottom: 22px;
      padding-bottom: 16px;
    }

    .offer-currency-card {
      background: #f8fafc;
      border: 1px solid #e3e8ef;
      border-radius: 8px;
      padding: 16px;
    }

    .offer-currency-card.is-usd {
      background: #f7f3ff;
      border-color: #d6bcfa;
    }

    .offer-currency-card.is-idr {
      background: #fff8e1;
      border-color: #ffe082;
    }

    .offer-summary-card {
      position: sticky;
      top: 92px;
    }

    .offer-summary-line {
      align-items: flex-start;
      border-bottom: 1px solid #eef2f6;
      display: flex;
      gap: 16px;
      justify-content: space-between;
      padding: 13px 0;
    }

    .offer-summary-line:last-child {
      border-bottom: 0;
      padding-bottom: 0;
    }

    .offer-summary-line span {
      color: #697586;
      font-size: 12px;
    }

    .offer-summary-line strong {
      color: #202939;
      font-size: 14px;
      text-align: right;
    }

    .offer-table td,
    .offer-table th {
      vertical-align: middle;
    }

    .offer-table thead th {
      background: #f8fafc;
      border-bottom: 0;
      color: #697586;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0;
      text-transform: uppercase;
    }

    .offer-work-title {
      color: #202939;
      display: block;
      font-weight: 600;
      min-width: 260px;
    }

    .offer-work-area {
      color: #697586;
      display: block;
      font-size: 12px;
      margin-top: 3px;
    }

    .offer-filter-grid {
      align-items: end;
      display: grid;
      gap: 12px;
      grid-template-columns: minmax(220px, 1.4fr) minmax(140px, 0.8fr) minmax(160px, 1fr) minmax(140px, 0.8fr) auto;
    }

    .offer-filter-action {
      display: flex;
      gap: 8px;
    }

    .offer-group-row td {
      background: #eef7ff;
      color: #1e88e5;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0;
      text-transform: uppercase;
    }

    .package-row {
      align-items: center;
      display: flex;
      gap: 8px;
      margin-bottom: 8px;
    }

    .package-row-area,
    .package-row-material {
      flex: 1 1 0;
    }

    .offer-table-footer {
      align-items: center;
      border-top: 1px solid #eef2f6;
      display: flex;
      gap: 12px;
      justify-content: space-between;
      padding-top: 16px;
    }

    @media (max-width: 991.98px) {
      .offer-filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .offer-summary-card {
        position: static;
      }
    }

    @media (max-width: 575.98px) {
      .offer-filter-grid {
        grid-template-columns: 1fr;
      }

      .offer-filter-action,
      .offer-filter-action .btn {
        width: 100%;
      }

      .offer-table-footer {
        align-items: stretch;
        flex-direction: column;
      }
    }
  </style>
@endpush

@section('content')
  <div class="row">
    <div class="col-xl-8">
      <div class="card">
        <div class="card-body">
          <div class="offer-form-title">
            <div class="avtar avtar-lg bg-light-primary">
              <i class="ti ti-businessplan text-primary"></i>
            </div>
            <div>
              <h4 class="mb-1" id="offer-form-title-text">Input Kategori Pekerjaan</h4>
              <p class="text-muted mb-0">{{ $activeProject?->name ?? 'Belum ada project holding aktif' }}</p>
            </div>
          </div>

          @if (session('status'))
            <div class="alert alert-success">
              {{ session('status') }}
            </div>
          @endif

          @if ($errors->any())
            <div class="alert alert-danger">
              Mohon cek lagi input kategori pekerjaan yang masih belum sesuai.
            </div>
          @endif

          <form
            id="offer-form"
            method="POST"
            action="{{ route('kategori-pekerjaan.store') }}"
            data-create-url="{{ route('kategori-pekerjaan.store') }}"
            data-update-url-template="{{ route('kategori-pekerjaan.update', ['projectOffer' => '__ID__']) }}"
          >
            @csrf
            <input type="hidden" name="_method" id="offer-form-method" value="" />
            <input type="hidden" name="is_package" id="is-package-input" value="0" />
            <div class="row">
              <div class="col-md-6">
                <div class="mb-4">
                  <label for="project-select" class="form-label d-block">Project Holding</label>
                  <select class="form-select @error('project_id') is-invalid @enderror" id="project-select" name="project_id" required>
                    @forelse (($projects ?? collect()) as $project)
                      <option value="{{ $project->id }}" @selected((int) old('project_id', $selectedProject?->id) === $project->id)>{{ $project->name }}</option>
                    @empty
                      <option value="">Project holding belum tersedia</option>
                    @endforelse
                  </select>
                  @error('project_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-4">
                  <label for="area-select" class="form-label d-block">Area / Kode</label>
                  <select class="form-select @error('area') is-invalid @enderror" id="area-select" name="area" required>
                    @forelse (($areas ?? collect()) as $areaOption)
                      <option value="{{ $areaOption->code }}" @selected($selectedArea === $areaOption->code)>{{ $areaOption->code }}</option>
                    @empty
                      <option value="K9" selected>K9</option>
                    @endforelse
                    <option value="__new__" @selected(old('area') === '__new__')>+ Tambah Area/Kode Baru</option>
                  </select>
                  @error('area')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                  <span class="form-helper">K9/K8/C21 adalah area di bawah Project Holding yang dipilih.</span>
                </div>
              </div>
            </div>

            <div class="mb-4 form-check">
              <input type="checkbox" class="form-check-input" id="package-toggle" />
              <label class="form-check-label" for="package-toggle">Ini paket gabungan beberapa area/pekerjaan (harga satu paket)</label>
            </div>

            <div class="row">
              <div class="col-md-7" id="work-name-col">
                <div class="mb-3">
                  <label for="work-name" class="form-label" id="work-name-label">Pekerjaan</label>
                  <input type="text" class="form-control @error('pekerjaan') is-invalid @enderror" id="work-name" name="pekerjaan" value="{{ old('pekerjaan') }}" placeholder="Contoh: Kanopi kaca koridor samping Lt 3" required />
                  @error('pekerjaan')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>
              <div class="col-md-5" id="brand-field-wrapper">
                <div class="mb-3">
                  <label for="brand-name" class="form-label">Brand</label>
                  <input type="text" class="form-control @error('brand') is-invalid @enderror" id="brand-name" name="brand" value="{{ old('brand') }}" placeholder="Contoh: Dedi Besi" />
                  @error('brand')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            </div>

            <div class="mb-4 d-none" id="package-builder">
              <label class="form-label d-block">Daftar Area dalam Paket</label>
              <div id="package-rows"></div>
              <button type="button" class="btn btn-sm btn-light-secondary mt-1" id="package-add-row">
                <i class="ti ti-plus me-1"></i> Tambah Area
              </button>
              <span class="form-helper mb-0 d-block">Daftar ini disimpan sebagai item paket terpisah. Minimal isi 2 area.</span>
              <span class="form-helper text-danger d-none" id="package-error">Isi minimal 2 nama area sebelum menyimpan paket.</span>
              @error('package_items')
                <span class="form-helper text-danger">{{ $message }}</span>
              @enderror
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="offer-currency-card is-usd mb-3">
                  <label for="offer-usd" class="form-label">Penawaran USD</label>
                  <div class="input-group">
                    <span class="input-group-text">USD</span>
                    <input type="number" class="form-control @error('penawaran_usd') is-invalid @enderror" id="offer-usd" name="penawaran_usd" value="{{ old('penawaran_usd') }}" min="0" step="0.01" placeholder="0" />
                    @error('penawaran_usd')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="offer-currency-card is-idr mb-3">
                  <label for="offer-idr" class="form-label">Penawaran Rupiah</label>
                  <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" class="form-control @error('penawaran_rupiah') is-invalid @enderror" id="offer-idr" name="penawaran_rupiah" value="{{ old('penawaran_rupiah') }}" min="0" step="1000" placeholder="0" />
                    @error('penawaran_rupiah')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>
                </div>
              </div>
            </div>

            <div class="mb-4">
              <label for="offer-notes" class="form-label">Catatan</label>
              <textarea class="form-control @error('catatan') is-invalid @enderror" id="offer-notes" name="catatan" rows="3" placeholder="Contoh: Ongkos pasang, tambahan material, revisi penawaran">{{ old('catatan') }}</textarea>
              @error('catatan')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary" id="offer-submit-btn">
                <i class="ti ti-device-floppy me-1"></i> Simpan Kategori
              </button>
              <button type="reset" class="btn btn-light-secondary">
                <i class="ti ti-refresh me-1"></i> Reset
              </button>
              <button type="button" class="btn btn-light-secondary d-none" id="offer-cancel-edit">
                <i class="ti ti-x me-1"></i> Batal Edit
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="modal fade" id="new-area-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Tambah Kategori Baru</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <label for="new-area-modal-input" class="form-label">Nama Kategori</label>
              <input type="text" class="form-control" id="new-area-modal-input" placeholder="Contoh: K10" maxlength="20" />
              <span class="form-helper text-danger d-none" id="new-area-modal-error">Nama kategori tidak boleh kosong.</span>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="button" class="btn btn-primary" id="new-area-modal-confirm">Tambah</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card offer-summary-card">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <small class="text-muted">Preview</small>
              <h4 class="mb-0">Ringkasan Kategori</h4>
            </div>
            <span class="badge bg-light-primary text-primary" id="summary-area">K9</span>
          </div>
          <div class="offer-summary-line">
            <span>Pekerjaan</span>
            <strong id="summary-work">Belum diisi</strong>
          </div>
          <div class="offer-summary-line">
            <span>Brand</span>
            <strong id="summary-brand">-</strong>
          </div>
          <div class="offer-summary-line">
            <span>USD</span>
            <strong id="summary-usd">USD 0</strong>
          </div>
          <div class="offer-summary-line">
            <span>Rupiah</span>
            <strong id="summary-idr">Rp 0</strong>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <div class="row align-items-center">
            <div class="col">
              <small class="text-muted">Data tersimpan</small>
              <h4 class="mb-0">Daftar Kategori Pekerjaan</h4>
            </div>
            <div class="col-auto">
              <span class="badge bg-light-primary text-primary">{{ $offers->total() }} data {{ $filters['area'] ?? 'K9' }}</span>
            </div>
          </div>
        </div>
        <div class="card-body">
          <form method="GET" action="{{ route('kategori-pekerjaan.index') }}" class="offer-filter-grid mb-4">
            <div>
              <label for="filter-search" class="form-label">Cari</label>
              <input
                type="search"
                class="form-control"
                id="filter-search"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Pekerjaan, brand, atau catatan"
              />
            </div>
            <div>
              <input type="hidden" name="project_id" value="{{ $filters['project_id'] }}" />
              <label for="filter-area" class="form-label">Area</label>
              <select class="form-select" id="filter-area" name="area">
                @foreach ($areas as $area)
                  <option value="{{ $area->code }}" @selected(($filters['area'] ?? 'K9') === $area->code)>{{ $area->code }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label for="filter-brand" class="form-label">Brand</label>
              <select class="form-select" id="filter-brand" name="brand">
                <option value="">Semua Brand</option>
                @foreach ($brands as $brand)
                  <option value="{{ $brand }}" @selected(($filters['brand'] ?? '') === $brand)>{{ $brand }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label for="filter-currency" class="form-label">Penawaran</label>
              <select class="form-select" id="filter-currency" name="currency">
                <option value="">Semua</option>
                <option value="idr" @selected(($filters['currency'] ?? '') === 'idr')>Rupiah</option>
                <option value="usd" @selected(($filters['currency'] ?? '') === 'usd')>USD</option>
              </select>
            </div>
            <div class="offer-filter-action">
              <button type="submit" class="btn btn-primary">
                <i class="ti ti-filter me-1"></i> Filter
              </button>
              <a href="{{ route('kategori-pekerjaan.index') }}" class="btn btn-light-secondary">
                Reset
              </a>
            </div>
          </form>

          <div class="table-responsive">
            <table class="table table-hover table-nowrap offer-table mb-0">
              <thead>
                <tr>
                  <th>Pekerjaan</th>
                  <th>Brand</th>
                  <th class="text-end">USD</th>
                  <th class="text-end">Rupiah</th>
                  <th>Catatan</th>
                  <th class="text-end">Aksi</th>
                </tr>
              </thead>
              <tbody id="offer-table-body">
                @forelse ($offers->getCollection()->groupBy('area') as $areaName => $areaOffers)
                  <tr class="offer-group-row">
                    <td colspan="6">{{ $areaName }} · {{ $areaOffers->count() }} data</td>
                  </tr>
                  @foreach ($areaOffers as $offer)
                    <tr>
                      <td>
                        <span class="offer-work-title">{{ $offer->pekerjaan }}</span>
                        <span class="offer-work-area">{{ $offer->area }}</span>
                        @if ($offer->workItem?->packageItems?->isNotEmpty())
                          <span class="offer-work-area">
                            Paket:
                            {{ $offer->workItem->packageItems->pluck('name')->join(', ') }}
                          </span>
                        @endif
                      </td>
                      <td>{{ $offer->brand ?: '-' }}</td>
                      <td class="text-end">{{ $offer->penawaran_usd ? 'USD '.number_format((float) $offer->penawaran_usd, 2, '.', ',') : '-' }}</td>
                      <td class="text-end">{{ $offer->penawaran_rupiah ? 'Rp '.number_format($offer->penawaran_rupiah, 0, ',', '.') : '-' }}</td>
                      <td>{{ $offer->catatan ?: '-' }}</td>
                      <td class="text-end">
                        <button
                          type="button"
                          class="btn btn-sm btn-light-secondary offer-edit-btn"
                          data-id="{{ $offer->id }}"
                          data-project-id="{{ $offer->project_id }}"
                          data-area="{{ $offer->area }}"
                          data-pekerjaan="{{ $offer->pekerjaan }}"
                          data-brand="{{ $offer->brand }}"
                          data-usd="{{ $offer->penawaran_usd }}"
                          data-rupiah="{{ $offer->penawaran_rupiah }}"
                          data-catatan="{{ $offer->catatan }}"
                          data-package-items="{{ $offer->workItem?->packageItems?->map(fn ($item) => ['name' => $item->name, 'brand' => $item->brand])->values()->toJson(JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?? '[]' }}"
                        >
                          <i class="ti ti-edit"></i> Edit
                        </button>
                      </td>
                    </tr>
                  @endforeach
                @empty
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">Belum ada kategori pekerjaan.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          @if ($offers->hasPages())
            <div class="offer-table-footer">
              <small class="text-muted">
                Menampilkan {{ $offers->firstItem() }}-{{ $offers->lastItem() }} dari {{ $offers->total() }} data terfilter.
              </small>
              {{ $offers->links() }}
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const form = document.querySelector('#offer-form');
      const projectSelect = document.querySelector('#project-select');
      const areaSelect = document.querySelector('#area-select');
      const newAreaModalEl = document.querySelector('#new-area-modal');
      const newAreaModal = new bootstrap.Modal(newAreaModalEl);
      const newAreaModalInput = document.querySelector('#new-area-modal-input');
      const newAreaModalError = document.querySelector('#new-area-modal-error');
      const newAreaModalConfirm = document.querySelector('#new-area-modal-confirm');
      const workInput = document.querySelector('#work-name');
      const workLabel = document.querySelector('#work-name-label');
      const workCol = document.querySelector('#work-name-col');
      const brandInput = document.querySelector('#brand-name');
      const brandFieldWrapper = document.querySelector('#brand-field-wrapper');
      const usdInput = document.querySelector('#offer-usd');
      const idrInput = document.querySelector('#offer-idr');
      const notesInput = document.querySelector('#offer-notes');
      const idrFormatter = new Intl.NumberFormat('id-ID');
      const usdFormatter = new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

      const packageToggle = document.querySelector('#package-toggle');
      const isPackageInput = document.querySelector('#is-package-input');
      const packageBuilder = document.querySelector('#package-builder');
      const packageRows = document.querySelector('#package-rows');
      const packageAddRow = document.querySelector('#package-add-row');
      const packageError = document.querySelector('#package-error');

      const formTitleText = document.querySelector('#offer-form-title-text');
      const methodField = document.querySelector('#offer-form-method');
      const submitBtn = document.querySelector('#offer-submit-btn');
      const cancelEditBtn = document.querySelector('#offer-cancel-edit');

      let lastValidAreaValue = areaSelect.value;

      function refreshPackageRowNames() {
        Array.from(packageRows.querySelectorAll('.package-row')).forEach(function (row, index) {
          row.querySelector('.package-row-area').name = 'package_items[' + index + '][name]';
          row.querySelector('.package-row-material').name = 'package_items[' + index + '][brand]';
        });
      }

      function addPackageRow(data) {
        const row = document.createElement('div');
        row.className = 'package-row';
        row.innerHTML =
          '<input type="text" class="form-control package-row-area" placeholder="Nama area, contoh: Ruang Kerja" />'
          + '<input type="text" class="form-control package-row-material" placeholder="Brand/vendor, contoh: Build Dec Interior" />'
          + '<button type="button" class="btn btn-light-secondary package-row-remove"><i class="ti ti-trash"></i></button>';

        row.querySelector('.package-row-remove').addEventListener('click', function () {
          row.remove();
          refreshPackageRowNames();
          updateSummary();
        });

        packageRows.appendChild(row);
        row.querySelector('.package-row-area').value = data && data.name ? data.name : '';
        row.querySelector('.package-row-material').value = data && data.brand ? data.brand : '';
        row.querySelectorAll('input').forEach(function (input) {
          input.addEventListener('input', updateSummary);
        });
        refreshPackageRowNames();
      }

      function togglePackageMode(isPackage) {
        isPackageInput.value = isPackage ? '1' : '0';
        packageBuilder.classList.toggle('d-none', !isPackage);
        brandFieldWrapper.classList.toggle('d-none', isPackage);
        workCol.classList.toggle('col-md-7', !isPackage);
        workCol.classList.toggle('col-md-12', isPackage);
        workLabel.textContent = isPackage ? 'Nama Paket / Kategori' : 'Pekerjaan';
        workInput.placeholder = isPackage ? 'Contoh: Lantai Hamparan Stone' : 'Contoh: Kanopi kaca koridor samping Lt 3';
        packageError.classList.add('d-none');

        if (isPackage && packageRows.children.length === 0) {
          addPackageRow();
          addPackageRow();
        }
      }

      function selectedArea() {
        return areaSelect.value;
      }

      function addNewAreaOption(value) {
        const existing = Array.from(areaSelect.options).find(function (option) {
          return option.value !== '__new__' && option.value.toLowerCase() === value.toLowerCase();
        });

        if (existing) {
          areaSelect.value = existing.value;
        } else {
          const option = document.createElement('option');
          option.value = value;
          option.textContent = value;
          areaSelect.insertBefore(option, areaSelect.querySelector('option[value="__new__"]'));
          areaSelect.value = value;
        }

        lastValidAreaValue = areaSelect.value;
        updateSummary();
      }

      function formatIdr(value) {
        return Number(value || 0) > 0 ? 'Rp ' + idrFormatter.format(Number(value)) : '-';
      }

      function formatUsd(value) {
        return Number(value || 0) > 0 ? 'USD ' + usdFormatter.format(Number(value)) : '-';
      }

      function updateSummary() {
        const firstPackageBrand = Array.from(packageRows.querySelectorAll('.package-row-material'))
          .map(function (input) { return input.value.trim(); })
          .find(function (value) { return value !== ''; });

        document.querySelector('#summary-area').textContent = selectedArea();
        document.querySelector('#summary-work').textContent = workInput.value.trim() || 'Belum diisi';
        document.querySelector('#summary-brand').textContent = packageToggle.checked
          ? (firstPackageBrand || brandInput.value.trim() || '-')
          : (brandInput.value.trim() || '-');
        document.querySelector('#summary-usd').textContent = formatUsd(usdInput.value);
        document.querySelector('#summary-idr').textContent = formatIdr(idrInput.value);
      }

      [workInput, brandInput, usdInput, idrInput].forEach(function (input) {
        input.addEventListener('input', updateSummary);
      });

      projectSelect.addEventListener('change', function () {
        window.location.href = '{{ route('kategori-pekerjaan.index') }}?project_id=' + encodeURIComponent(projectSelect.value);
      });

      areaSelect.addEventListener('change', function () {
        if (areaSelect.value === '__new__') {
          areaSelect.value = lastValidAreaValue;
          newAreaModalInput.value = '';
          newAreaModalError.classList.add('d-none');
          newAreaModalInput.classList.remove('is-invalid');
          newAreaModal.show();
          return;
        }

        lastValidAreaValue = areaSelect.value;
        updateSummary();
      });

      newAreaModalEl.addEventListener('shown.bs.modal', function () {
        newAreaModalInput.focus();
      });

      newAreaModalConfirm.addEventListener('click', function () {
        const value = newAreaModalInput.value.trim();

        if (!value) {
          newAreaModalError.classList.remove('d-none');
          newAreaModalInput.classList.add('is-invalid');
          return;
        }

        addNewAreaOption(value);
        newAreaModal.hide();
      });

      newAreaModalInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          newAreaModalConfirm.click();
        }
      });

      newAreaModalInput.addEventListener('input', function () {
        newAreaModalError.classList.add('d-none');
        newAreaModalInput.classList.remove('is-invalid');
      });

      packageToggle.addEventListener('change', function () {
        togglePackageMode(packageToggle.checked);
      });

      packageAddRow.addEventListener('click', function () {
        addPackageRow();
      });

      form.addEventListener('submit', function (event) {
        if (!packageToggle.checked) {
          return;
        }

        const entries = Array.from(packageRows.querySelectorAll('.package-row'))
          .map(function (row) {
            return {
              name: row.querySelector('.package-row-area').value.trim(),
              brand: row.querySelector('.package-row-material').value.trim(),
            };
          })
          .filter(function (entry) {
            return entry.name !== '';
          });

        if (entries.length < 2) {
          event.preventDefault();
          packageError.classList.remove('d-none');
          return;
        }
      });

      function parsePackageNote(catatan) {
        const match = /^Paket gabungan \d+ area \(harga satu paket, bukan per-area\):\s*(.+?)\.\s*(?:Kontraktor:\s*(.*?)\.\s*)?Total penawaran/.exec(catatan || '');

        if (!match) {
          return null;
        }

        const sharedContractor = (match[2] || '').trim();

        const entries = match[1]
          .split(', ')
          .map(function (segment) {
            const segmentMatch = /^(.*)\s\(([^)]+)\)$/.exec(segment.trim());

            return segmentMatch
              ? { area: segmentMatch[1].trim(), material: segmentMatch[2].trim() }
              : { area: segment.trim(), material: sharedContractor };
          })
          .filter(function (entry) {
            return entry.area !== '';
          });

        return entries.length >= 2 ? entries : null;
      }

      function enterEditMode(button) {
        const data = button.dataset;
        const storedPackageEntries = JSON.parse(data.packageItems || '[]');
        const packageEntries = storedPackageEntries.length > 0 ? storedPackageEntries : parsePackageNote(data.catatan);

        areaSelect.value = data.area;
        projectSelect.value = data.projectId || projectSelect.value;
        lastValidAreaValue = data.area;
        brandInput.value = data.brand || '';
        usdInput.value = data.usd || '';
        idrInput.value = data.rupiah || '';
        notesInput.value = data.catatan || '';

        if (packageEntries) {
          packageToggle.checked = true;
          togglePackageMode(true);
          packageRows.innerHTML = '';
          workInput.value = data.pekerjaan || '';

          packageEntries.forEach(function (entry) {
            addPackageRow({
              name: entry.name || entry.area,
              brand: entry.brand || entry.material,
            });
          });
        } else {
          packageToggle.checked = false;
          togglePackageMode(false);
          workInput.value = data.pekerjaan;
        }

        form.action = form.dataset.updateUrlTemplate.replace('__ID__', data.id);
        methodField.value = 'PUT';
        formTitleText.textContent = 'Edit Kategori Pekerjaan';
        submitBtn.innerHTML = '<i class="ti ti-device-floppy me-1"></i> Update Kategori';
        cancelEditBtn.classList.remove('d-none');

        updateSummary();
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      document.querySelectorAll('.offer-edit-btn').forEach(function (button) {
        button.addEventListener('click', function () {
          enterEditMode(button);
        });
      });

      cancelEditBtn.addEventListener('click', function () {
        form.reset();
      });

      form.addEventListener('reset', function () {
        setTimeout(function () {
          form.action = form.dataset.createUrl;
          methodField.value = '';
          formTitleText.textContent = 'Input Kategori Pekerjaan';
          submitBtn.innerHTML = '<i class="ti ti-device-floppy me-1"></i> Simpan Kategori';
          cancelEditBtn.classList.add('d-none');
          packageToggle.checked = false;
          packageRows.innerHTML = '';
          togglePackageMode(false);
          refreshPackageRowNames();
          updateSummary();
        });
      });

      updateSummary();
    });
  </script>
@endpush
