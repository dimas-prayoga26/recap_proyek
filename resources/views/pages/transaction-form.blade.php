@php
  $isIncome = ($mode ?? 'masuk') === 'masuk';
  $transactionType = $isIncome ? 'masuk' : 'keluar';
  $requestedWorkItem = old('work_item_id')
    ? ($workItems ?? collect())->firstWhere('id', (int) old('work_item_id'))
    : (request()->query('work_item_id')
      ? ($workItems ?? collect())->firstWhere('id', (int) request()->query('work_item_id'))
      : null);
  $selectedProject = old('project_id')
    ? ($projects ?? collect())->firstWhere('id', (int) old('project_id'))
    : ($requestedWorkItem?->project ?? $activeProject ?? ($projects ?? collect())->first());
  $defaultWorkItem = ($workItems ?? collect())->first(fn ($item) => (int) $item->project_id === (int) $selectedProject?->id)
    ?? ($workItems ?? collect())->first();
  $selectedWorkItem = $requestedWorkItem ?? $defaultWorkItem;
  $selectedVendor = old('vendor_id')
    ? ($vendors ?? collect())->firstWhere('id', (int) old('vendor_id'))
    : $selectedWorkItem?->vendor;
@endphp

@extends('layouts.app')

@section('title', $title)
@section('page_title', $title)

@section('page_actions')
  <a href="{{ route('dashboard') }}" class="btn btn-light-secondary">
    <i class="ti ti-arrow-left me-1"></i> Ringkasan
  </a>
  <a href="{{ $isIncome ? route('uang-keluar.index') : route('uang-masuk.index') }}" class="btn btn-primary">
    <i class="ti {{ $isIncome ? 'ti-receipt-2' : 'ti-wallet' }} me-1"></i>
    {{ $isIncome ? 'Input Debit' : 'Input Credit' }}
  </a>
@endsection

@push('styles')
  <style>
    .transaction-form-shell {
      align-items: flex-start;
    }

    .transaction-kind {
      background: #f8fafc;
      border: 1px solid #e3e8ef;
      border-radius: 8px;
      display: inline-flex;
      gap: 6px;
      padding: 5px;
    }

    .transaction-kind .btn {
      border-radius: 6px;
      min-width: 136px;
    }

    .form-section-title {
      align-items: center;
      border-bottom: 1px solid #eef2f6;
      display: flex;
      gap: 10px;
      margin-bottom: 22px;
      padding-bottom: 16px;
    }

    .form-section-title .avtar {
      flex: 0 0 auto;
    }

    .form-label {
      color: #364152;
      font-weight: 600;
      margin-bottom: 8px;
    }

    .form-helper {
      color: #697586;
      display: block;
      font-size: 12px;
      margin-top: 6px;
    }

    .termin-panel {
      background: #fffbeb;
      border: 1px solid #fde68a;
      border-radius: 8px;
      padding: 18px;
    }

    .work-termin-info {
      background: #f8fbff;
      border: 1px solid #d7e9fb;
      border-radius: 8px;
      margin-bottom: 18px;
      padding: 16px;
    }

    .work-termin-metrics {
      display: grid;
      gap: 12px;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      margin-bottom: 14px;
    }

    .work-termin-metric {
      background: #fff;
      border: 1px solid #eef2f6;
      border-radius: 8px;
      padding: 12px;
    }

    .work-termin-metric span {
      color: #697586;
      display: block;
      font-size: 12px;
    }

    .work-termin-metric strong {
      color: #202939;
      display: block;
      font-size: 18px;
      line-height: 1.25;
      margin-top: 4px;
    }

    .work-termin-history {
      flex: 0 0 auto;
      width: auto;
    }

    .work-termin-toolbar {
      align-items: center;
      display: flex;
      flex: 0 0 auto;
      gap: 8px;
    }

    .termin-currency-switch {
      background: #fff;
      border: 1px solid #d7e9fb;
      border-radius: 8px;
      display: inline-flex;
      gap: 4px;
      padding: 4px;
    }

    .termin-currency-switch button {
      background: transparent;
      border: 0;
      border-radius: 6px;
      color: #697586;
      font-size: 12px;
      font-weight: 700;
      min-width: 42px;
      padding: 6px 10px;
    }

    .termin-currency-switch button.is-active {
      background: #2196f3;
      color: #fff;
    }

    .amount-currency-switch {
      background: #f8fafc;
      border-right: 0;
      gap: 3px;
      padding: 4px;
    }

    .amount-currency-switch button {
      min-width: 38px;
      padding: 5px 8px;
    }

    .amount-helper {
      align-items: center;
      display: flex;
      gap: 8px;
      min-height: 18px;
    }

    .work-termin-package {
      border-top: 1px solid #e3e8ef;
      margin-top: 14px;
      padding-top: 14px;
    }

    .allocation-row {
      background: #f8fafc;
      border: 1px solid #e3e8ef;
      border-radius: 8px;
      margin-bottom: 12px;
      padding: 14px 14px 4px;
    }

    .allocation-history {
      background: #f8fafc;
      border: 1px dashed #cdd5df;
      border-radius: 8px;
      margin-bottom: 12px;
      padding: 12px 14px;
    }

    .allocation-history-list {
      margin: 0;
      padding: 0;
    }

    .allocation-history-list li {
      align-items: center;
      color: #697586;
      display: flex;
      font-size: 13px;
      justify-content: space-between;
      list-style: none;
      padding: 4px 0;
    }

    .allocation-history-list li strong {
      color: #364152;
      font-weight: 600;
    }

    .work-termin-package-list {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin: 0;
      padding: 0;
    }

    .work-termin-package-list li {
      background: #e3f2fd;
      border-radius: 6px;
      color: #202939;
      display: inline-flex;
      font-size: 12px;
      list-style: none;
      padding: 6px 9px;
    }

    .searchable-select {
      position: relative;
    }

    .searchable-select-menu {
      background: #fff;
      border: 1px solid #e3e8ef;
      border-radius: 8px;
      box-shadow: 0 12px 24px rgba(16, 24, 40, 0.12);
      display: none;
      margin-top: 4px;
      max-height: 240px;
      overflow-y: auto;
      padding: 6px;
      position: absolute;
      left: 0;
      right: 0;
      top: 100%;
      z-index: 20;
    }

    .searchable-select-menu.is-open {
      display: block;
    }

    .searchable-select-item {
      background: transparent;
      border: 0;
      border-radius: 6px;
      color: #202939;
      display: block;
      font-size: 14px;
      padding: 8px 10px;
      text-align: left;
      width: 100%;
    }

    .searchable-select-item small {
      color: #697586;
      display: block;
      font-size: 11px;
      margin-top: 2px;
    }

    .searchable-select-item:hover,
    .searchable-select-item.is-active {
      background: #eef6ff;
      color: #2196f3;
    }

    .searchable-select-badge {
      background: #e3f9e5;
      border-radius: 999px;
      color: #1a9c47;
      display: inline-block;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.02em;
      margin-top: 4px;
      padding: 2px 8px;
      text-transform: uppercase;
    }

    .searchable-select-empty {
      color: #697586;
      font-size: 13px;
      padding: 8px 10px;
    }

    .receipt-upload {
      border: 1px dashed #9cc8f5;
      border-radius: 8px;
      background: #f8fbff;
      display: block;
      padding: 20px;
      text-align: center;
    }

    .receipt-upload input {
      display: none;
    }

    .receipt-upload .avtar {
      margin: 0 auto 10px;
    }

    .receipt-preview {
      align-items: center;
      border: 1px solid #e3e8ef;
      border-radius: 8px;
      display: none;
      gap: 12px;
      margin-top: 14px;
      padding: 10px;
    }

    .receipt-preview.is-visible {
      display: flex;
    }

    .receipt-preview img {
      border-radius: 6px;
      height: 62px;
      object-fit: cover;
      width: 82px;
    }

    .receipt-preview-file {
      align-items: center;
      border-radius: 6px;
      display: none;
      height: 62px;
      justify-content: center;
      width: 82px;
    }

    .receipt-preview-file.is-visible {
      display: flex;
    }

    .draft-summary {
      position: sticky;
      top: 92px;
    }

    .summary-line {
      align-items: flex-start;
      border-bottom: 1px solid #eef2f6;
      display: flex;
      justify-content: space-between;
      gap: 16px;
      padding: 13px 0;
    }

    .summary-line:last-child {
      border-bottom: 0;
      padding-bottom: 0;
    }

    .summary-line span {
      color: #697586;
      font-size: 12px;
    }

    .summary-line strong {
      color: #202939;
      font-size: 14px;
      text-align: right;
    }

    @media (max-width: 991.98px) {
      .draft-summary {
        position: static;
      }
    }

    @media (max-width: 575.98px) {
      .transaction-kind {
        width: 100%;
      }

      .transaction-kind .btn {
        min-width: 0;
        width: 100%;
      }

      .work-termin-metrics {
        grid-template-columns: 1fr;
      }
    }
  </style>
@endpush

@section('content')
  <div class="row transaction-form-shell">
    <div class="col-xl-8">
      <div class="card">
        <div class="card-body">
          <div class="form-section-title">
            <div class="avtar avtar-lg {{ $isIncome ? 'bg-light-success' : 'bg-light-primary' }}">
              <i class="ti {{ $isIncome ? 'ti-wallet' : 'ti-receipt-2' }} {{ $isIncome ? 'text-success' : 'text-primary' }}"></i>
            </div>
            <div>
              <h4 class="mb-1">Form Transaksi</h4>
              <p class="text-muted mb-0">{{ $activeProject?->name ?? 'Belum ada project aktif' }}</p>
            </div>
          </div>

          @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
          @endif

          @if ($errors->any())
            <div class="alert alert-danger">
              Data belum lengkap. Cek lagi field yang wajib diisi.
            </div>
          @endif

          <form id="transaction-form" method="POST" action="{{ route('transactions.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="type" id="transaction-type" value="{{ $transactionType }}" />
            <input type="hidden" name="kelompok_pembayaran" value="termin" />

            <div class="mb-4">
              <label class="form-label d-block">Jenis Transaksi</label>
              <div class="transaction-kind">
                <a href="{{ route('uang-masuk.index') }}" class="btn {{ $isIncome ? 'btn-success' : 'btn-light-secondary' }}">
                  <i class="ti ti-arrow-down-left me-1"></i> Credit
                </a>
                <a href="{{ route('uang-keluar.index') }}" class="btn {{ $isIncome ? 'btn-light-secondary' : 'btn-primary' }}">
                  <i class="ti ti-arrow-up-right me-1"></i> Debit
                </a>
              </div>
            </div>

            <div class="row">
              <div class="col-md-12">
                <div class="mb-3">
                  <label for="project-holding-search" class="form-label">Project Holding</label>
                  <div class="searchable-select js-searchable-select">
                    <input type="text" class="form-control searchable-select-input @error('project_id') is-invalid @enderror" id="project-holding-search" data-role="search-input" placeholder="Cari project holding..." autocomplete="off" />
                    <div class="searchable-select-menu" data-role="menu"></div>
                    <select class="form-select d-none" id="project-holding" name="project_id" data-role="source" required>
                      @forelse (($projects ?? collect()) as $project)
                        <option value="{{ $project->id }}" data-active="{{ $activeProject?->id === $project->id ? '1' : '0' }}" @selected((int) old('project_id', $selectedProject?->id) === $project->id)>
                          {{ $project->name }}
                        </option>
                      @empty
                        <option value="">Project holding belum tersedia</option>
                      @endforelse
                    </select>
                  </div>
                  @error('project_id')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="activity-name" class="form-label">Nama Barang / Nama Kegiatan</label>
                  <div class="searchable-select js-searchable-select" data-filter-select="#project-holding" data-filter-attr="projectId">
                    <input type="text" class="form-control searchable-select-input @error('work_item_id') is-invalid @enderror" data-role="search-input" placeholder="Cari nama barang / kegiatan..." autocomplete="off" />
                    <div class="searchable-select-menu" data-role="menu"></div>
                    <select class="form-select d-none" id="activity-name" name="work_item_id" data-role="source">
                      @forelse (($workItems ?? collect()) as $item)
                        <option
                          value="{{ $item->id }}"
                          data-vendor-id="{{ $item->vendor_id }}"
                          data-project-id="{{ $item->project_id }}"
                          data-package-name="{{ $item->package_name }}"
                          data-package-label="{{ trim(($item->package_name ? 'Kategori: '.$item->package_name.' | ' : '').$item->packageItems->pluck('name')->join(', ')) }}"
                          data-search="{{ $item->name.' '.$item->package_name.' '.$item->brand.' '.$item->packageItems->pluck('name')->join(' ') }}"
                          @selected((int) old('work_item_id', $selectedWorkItem?->id) === $item->id)
                        >
                          {{ $item->package_name ? $item->package_name.' - '.$item->name : $item->name }}
                        </option>
                      @empty
                        <option value="">Nama barang/kegiatan belum tersedia</option>
                      @endforelse
                    </select>
                  </div>
                  @error('work_item_id')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="vendor-name" class="form-label">Nama Vendor</label>
                  <div class="searchable-select js-searchable-select">
                    <input type="text" class="form-control searchable-select-input @error('vendor_id') is-invalid @enderror" data-role="search-input" placeholder="Cari nama vendor..." autocomplete="off" />
                    <div class="searchable-select-menu" data-role="menu"></div>
                    <select class="form-select d-none" id="vendor-name" name="vendor_id" data-role="source">
                      <option value="">-</option>
                      @foreach (($vendors ?? collect()) as $vendor)
                        <option value="{{ $vendor->id }}" @selected((int) old('vendor_id', $selectedVendor?->id) === $vendor->id)>
                          {{ $vendor->name }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  @error('vendor_id')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            </div>

            @unless ($isIncome)
              <div class="work-termin-info" id="work-termin-info">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                  <div>
                    <small class="text-muted">Info Termin</small>
                    <h5 class="mb-0" id="termin-info-title">-</h5>
                  </div>
                  <div class="work-termin-toolbar">
                    <div class="termin-currency-switch" id="termin-currency-switch">
                      <button type="button" class="is-active" data-currency="IDR">IDR</button>
                      <button type="button" data-currency="USD">USD</button>
                    </div>
                    <select class="form-select form-select-sm work-termin-history" id="termin-info-history"></select>
                  </div>
                </div>
                <div class="work-termin-metrics">
                  <div class="work-termin-metric">
                    <span>Total Penawaran</span>
                    <strong id="termin-info-offer">Rp 0</strong>
                  </div>
                  <div class="work-termin-metric">
                    <span>Total Sudah Dibayar</span>
                    <strong class="text-success" id="termin-info-paid">Rp 0</strong>
                  </div>
                  <div class="work-termin-metric">
                    <span>Sisa</span>
                    <strong class="text-primary" id="termin-info-remaining">Rp 0</strong>
                  </div>
                </div>
                <div class="work-termin-package d-none" id="termin-info-package">
                  <small class="text-muted d-block mb-1">Paket ini mencakup:</small>
                  <ul class="work-termin-package-list" id="termin-info-package-list"></ul>
                </div>
              </div>
            @endunless

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="amount-display" class="form-label">Nominal</label>
                  <input type="hidden" id="amount" name="amount" value="{{ old('amount') }}" />
                  <input type="hidden" id="amount-currency" name="amount_currency" value="{{ old('amount_currency', 'IDR') }}" />
                  <input type="hidden" id="amount-exchange-rate" name="amount_exchange_rate" value="{{ old('amount_exchange_rate') }}" />
                  <div class="input-group">
                    <span class="input-group-text amount-currency-switch termin-currency-switch" id="amount-currency-switch">
                      <button type="button" data-currency="IDR" @class(['is-active' => old('amount_currency', 'IDR') !== 'USD'])>Rp</button>
                      <button type="button" data-currency="USD" @class(['is-active' => old('amount_currency') === 'USD'])>USD</button>
                    </span>
                    <input type="text" class="form-control @error('amount') is-invalid @enderror" id="amount-display" name="amount_display" inputmode="decimal" autocomplete="off" placeholder="0" value="{{ old('amount_display', old('amount')) }}" required />
                    @error('amount')
                      <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                  </div>
                  <span class="form-helper amount-helper" id="amount-helper"></span>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="record-date" class="form-label">Tanggal Pencatatan</label>
                  <input type="date" class="form-control @error('recorded_at') is-invalid @enderror" id="record-date" name="recorded_at" value="{{ old('recorded_at', now()->toDateString()) }}" required />
                  @error('recorded_at')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            </div>

            <div class="mb-4">
              <label for="notes" class="form-label">Catatan</label>
              <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Tambahan detail transaksi bila diperlukan">{{ old('notes') }}</textarea>
            </div>

            <div class="mb-4 d-none">
              <label class="form-label d-block">Termin Pembayaran</label>
              @unless ($isIncome)
                <span class="form-helper mb-2">Jika disimpan dari Debit, nominal ini otomatis masuk ke rekap Termin Pembayaran.</span>
              @endunless
              <div class="termin-panel" id="termin-panel">
                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="receipt-code" class="form-label">Nomor / Nama Kuitansi</label>
                      <input type="text" class="form-control" id="receipt-code" name="payment_group_code" value="{{ old('payment_group_code') }}" placeholder="Contoh: Kuitansi #001" />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="receipt-total" class="form-label">Total Nilai Kuitansi</label>
                      <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" class="form-control" id="receipt-total" name="receipt_total" min="0" step="1" value="{{ old('receipt_total') }}" placeholder="0" />
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-0">
                      <label for="payment-number" class="form-label">Pembayaran ke</label>
                      <select class="form-select" id="payment-number" name="payment_number">
                        @for ($i = 1; $i <= 24; $i++)
                          <option value="{{ $i }}" @selected((int) old('payment_number', 1) === $i)>Pembayaran ke-{{ $i }}</option>
                        @endfor
                      </select>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-0">
                      <label for="payment-total" class="form-label">Jumlah Termin Otomatis</label>
                      <input type="number" class="form-control" id="payment-total" name="payment_total" min="1" value="{{ old('payment_total', $selectedWorkItem?->paymentGroups->first()?->total_terms ?? 1) }}" readonly />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            @unless ($isIncome)
              <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <label class="form-label d-block mb-0">Alokasi Tambahan (opsional)</label>
                  <small class="text-muted" id="allocation-summary">Total alokasi: Rp 0</small>
                </div>
                <span class="form-helper mb-2">
                  Kalau nominal transaksi ini sebenarnya melunasi lebih dari satu pekerjaan sekaligus (misal ada tambahan/paket lain yang dibayar bareng), pecah sisanya ke pekerjaan lain di sini.
                </span>

                <div class="allocation-history d-none" id="allocation-history">
                  <small class="text-muted d-block mb-2">
                    <i class="ti ti-history me-1"></i>
                    Riwayat alokasi pekerjaan ini (sudah tercatat, tidak ikut tersimpan lagi):
                  </small>
                  <ul class="allocation-history-list" id="allocation-history-list"></ul>
                </div>

                <div id="allocation-rows"></div>
                <button type="button" class="btn btn-sm btn-light-secondary" id="allocation-add-row">
                  <i class="ti ti-plus me-1"></i> Tambah Alokasi
                </button>
                @error('allocations')
                  <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
              </div>
            @endunless

            <div class="mb-4">
              <label class="form-label d-block">Bukti Transfer (Opsional)</label>
              <label class="receipt-upload" for="receipt-file">
                <span class="avtar avtar-lg bg-light-primary">
                  <i class="ti ti-photo-plus text-primary"></i>
                </span>
                <strong class="d-block">Upload Bukti</strong>
                <input type="file" id="receipt-file" name="receipt" accept="image/jpeg,image/jpg,image/png,image/webp,application/pdf,.pdf" />
              </label>
              <div class="receipt-preview" id="receipt-preview">
                <img alt="Preview bukti transaksi" id="receipt-image" />
                <span class="receipt-preview-file bg-light-danger text-danger fw-semibold" id="receipt-file-badge">PDF</span>
                <div>
                  <strong id="receipt-name">Belum ada file</strong>
                  <span class="form-helper mb-0" id="receipt-size">-</span>
                </div>
              </div>
            </div>

            <div class="alert alert-primary d-none" id="draft-status"></div>

            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="ti ti-device-floppy me-1"></i> Simpan Transaksi
              </button>
              <button type="reset" class="btn btn-light-secondary">
                <i class="ti ti-refresh me-1"></i> Reset
              </button>
            </div>
          </form>

          <div class="modal fade" id="project-activate-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <form method="POST" action="{{ route('dashboard.active-project') }}">
                  @csrf
                  <input type="hidden" name="project_id" id="project-activate-id" value="" />
                  <input type="hidden" name="redirect_to" value="{{ $isIncome ? route('uang-masuk.index', [], false) : route('uang-keluar.index', [], false) }}" />
                  <div class="modal-header">
                    <h5 class="modal-title">Jadikan Project Aktif?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <p class="mb-0">"<strong id="project-activate-name"></strong>" bukan project holding yang sedang aktif. Aktifkan dan pindah ke project ini?</p>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                      <i class="ti ti-check me-1"></i> Ya, Aktifkan
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card draft-summary">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <small class="text-muted">Preview</small>
              <h4 class="mb-0">Ringkasan Input</h4>
            </div>
            <span class="badge {{ $isIncome ? 'bg-light-success text-success' : 'bg-light-primary text-primary' }}" id="summary-type">
              {{ $isIncome ? 'Credit' : 'Debit' }}
            </span>
          </div>

          <div class="summary-line">
            <span>Project Holding</span>
            <strong id="summary-project-holding">{{ $selectedProject?->name ?? 'Belum tersedia' }}</strong>
          </div>
          <div class="summary-line">
            <span>Nama</span>
            <strong id="summary-name">{{ $selectedWorkItem?->name ?? 'Belum tersedia' }}</strong>
          </div>
          <div class="summary-line">
            <span>Vendor</span>
            <strong id="summary-vendor">{{ $selectedVendor?->name ?? '-' }}</strong>
          </div>
          <div class="summary-line">
            <span>Nominal</span>
            <strong id="summary-amount">Rp 0</strong>
          </div>
          <div class="summary-line">
            <span>Tanggal</span>
            <strong id="summary-date">-</strong>
          </div>
          <div class="summary-line">
            <span>Kelompok</span>
            <strong id="summary-payment">Kuitansi - 1/3</strong>
          </div>
          <div class="summary-line">
            <span>Bukti</span>
            <strong id="summary-receipt">Belum ada</strong>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const form = document.querySelector('#transaction-form');
      const dateInput = document.querySelector('#record-date');
      const projectHoldingInput = document.querySelector('#project-holding');
      const activityInput = document.querySelector('#activity-name');
      const vendorInput = document.querySelector('#vendor-name');
      const amountInput = document.querySelector('#amount');
      const amountDisplayInput = document.querySelector('#amount-display');
      const amountCurrencyInput = document.querySelector('#amount-currency');
      const amountExchangeRateInput = document.querySelector('#amount-exchange-rate');
      const amountCurrencySwitch = document.querySelector('#amount-currency-switch');
      const amountHelper = document.querySelector('#amount-helper');
      const receiptCodeInput = document.querySelector('#receipt-code');
      const receiptTotalInput = document.querySelector('#receipt-total');
      const paymentNumberInput = document.querySelector('#payment-number');
      const paymentTotalInput = document.querySelector('#payment-total');
      const receiptFileInput = document.querySelector('#receipt-file');
      const receiptPreview = document.querySelector('#receipt-preview');
      const receiptImage = document.querySelector('#receipt-image');
      const receiptFileBadge = document.querySelector('#receipt-file-badge');
      const receiptName = document.querySelector('#receipt-name');
      const receiptSize = document.querySelector('#receipt-size');
      const draftStatus = document.querySelector('#draft-status');

      function enhanceSearchableSelect(wrapper) {
        const select = wrapper.querySelector('[data-role="source"]');
        const input = wrapper.querySelector('[data-role="search-input"]');
        const menu = wrapper.querySelector('[data-role="menu"]');
        const filterAttr = wrapper.dataset.filterAttr;
        const filterSelect = wrapper.dataset.filterSelect ? document.querySelector(wrapper.dataset.filterSelect) : null;

        function options() {
          const list = Array.from(select.options).filter(function (option) {
            return option.value !== '';
          });

          if (!filterSelect || !filterSelect.value) {
            return list;
          }

          return list.filter(function (option) {
            return !option.dataset[filterAttr] || option.dataset[filterAttr] === filterSelect.value;
          });
        }

        function selectedLabel() {
          const option = select.options[select.selectedIndex];
          return option && option.value !== '' ? option.textContent.trim() : '';
        }

        function syncInputFromSelect() {
          input.value = selectedLabel();
          input.classList.remove('is-invalid');
        }

        function closeMenu() {
          menu.classList.remove('is-open');
        }

        function renderMenu(term) {
          const query = (term || '').toLowerCase();
          const matches = options().filter(function (option) {
            return (option.dataset.search || option.textContent).toLowerCase().includes(query);
          });

          menu.innerHTML = '';

          if (matches.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'searchable-select-empty';
            empty.textContent = 'Tidak ditemukan';
            menu.appendChild(empty);
            return;
          }

          matches.forEach(function (option) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'searchable-select-item' + (option.value === select.value ? ' is-active' : '');

            const label = document.createElement('span');
            label.textContent = option.textContent.trim();
            item.appendChild(label);

            if (option.dataset.active === '1') {
              const badge = document.createElement('span');
              badge.className = 'searchable-select-badge';
              badge.textContent = 'Aktif';
              item.appendChild(badge);
            }

            if (option.dataset.packageLabel) {
              const helper = document.createElement('small');
              helper.textContent = option.dataset.packageLabel;
              item.appendChild(helper);
            }

            item.addEventListener('mousedown', function (event) {
              event.preventDefault();
              select.value = option.value;
              syncInputFromSelect();
              closeMenu();
              select.dispatchEvent(new Event('change', { bubbles: true }));
            });

            menu.appendChild(item);
          });
        }

        function openMenu() {
          renderMenu('');
          menu.classList.add('is-open');
        }

        input.addEventListener('focus', function () {
          input.value = '';
          openMenu();
        });

        input.addEventListener('input', function () {
          renderMenu(input.value);
          menu.classList.add('is-open');
        });

        input.addEventListener('blur', function () {
          setTimeout(function () {
            syncInputFromSelect();
            closeMenu();
          }, 120);
        });

        function refreshForFilter() {
          const allowedValues = options().map(function (option) {
            return option.value;
          });

          if (select.value && allowedValues.indexOf(select.value) === -1) {
            select.value = allowedValues[0] || '';
            syncInputFromSelect();
            select.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }

        select.addEventListener('change', syncInputFromSelect);

        if (filterSelect) {
          filterSelect.addEventListener('change', refreshForFilter);
        }

        syncInputFromSelect();

        return { wrapper: wrapper, sync: syncInputFromSelect, refreshForFilter: refreshForFilter };
      }

      const searchableSelects = Array.from(document.querySelectorAll('.js-searchable-select')).map(enhanceSearchableSelect);
      const searchableSelectSyncs = searchableSelects.map(function (entry) {
        return entry.sync;
      });

      const projectActivateModalEl = document.querySelector('#project-activate-modal');

      if (projectActivateModalEl) {
        const projectActivateModal = new bootstrap.Modal(projectActivateModalEl);
        const projectActivateIdInput = document.querySelector('#project-activate-id');
        const projectActivateNameEl = document.querySelector('#project-activate-name');
        const projectHoldingEntry = searchableSelects.find(function (entry) {
          return entry.wrapper.contains(projectHoldingInput);
        });
        const activeProjectId = '{{ $activeProject?->id }}';
        let lastConfirmedProjectId = projectHoldingInput.value;
        let projectActivateConfirmed = false;

        projectHoldingInput.addEventListener('change', function () {
          if (!activeProjectId || projectHoldingInput.value === activeProjectId) {
            lastConfirmedProjectId = projectHoldingInput.value;
            return;
          }

          const option = projectHoldingInput.selectedOptions[0];
          projectActivateIdInput.value = projectHoldingInput.value;
          projectActivateNameEl.textContent = option ? option.textContent.trim() : '';
          projectActivateConfirmed = false;
          projectActivateModal.show();
        });

        projectActivateModalEl.querySelector('form').addEventListener('submit', function () {
          projectActivateConfirmed = true;
        });

        projectActivateModalEl.addEventListener('hidden.bs.modal', function () {
          if (!projectActivateConfirmed) {
            projectHoldingInput.value = lastConfirmedProjectId;

            if (projectHoldingEntry) {
              projectHoldingEntry.sync();
            }
          }
        });
      }

      const allocationRows = document.querySelector('#allocation-rows');
      const allocationAddRow = document.querySelector('#allocation-add-row');
      const allocationSummary = document.querySelector('#allocation-summary');
      const allocationHistory = document.querySelector('#allocation-history');
      const allocationHistoryList = document.querySelector('#allocation-history-list');

      function renderAllocationHistory(info) {
        if (!allocationHistory) {
          return;
        }

        const shared = (info && info.shared_allocations) || [];

        allocationHistoryList.innerHTML = '';

        if (shared.length === 0) {
          allocationHistory.classList.add('d-none');
          return;
        }

        shared.forEach(function (allocation) {
          const item = document.createElement('li');
          item.innerHTML = '<span>' + allocation.name + '</span><strong>' + formatCurrency(allocation.amount) + '</strong>';
          allocationHistoryList.appendChild(item);
        });

        allocationHistory.classList.remove('d-none');
      }
      let allocationRowIndex = 0;

      function updateAllocationSummary() {
        if (!allocationSummary) {
          return;
        }

        const total = Array.from(document.querySelectorAll('.allocation-amount')).reduce(function (sum, input) {
          return sum + (Number(input.value) || 0);
        }, 0);

        allocationSummary.textContent = 'Total alokasi: ' + formatCurrency(total);
        allocationSummary.classList.toggle('text-danger', total > Number(amountInput.value || 0));
      }

      function createAllocationRow() {
        const index = allocationRowIndex++;
        const row = document.createElement('div');
        row.className = 'allocation-row row g-2 align-items-end';
        row.innerHTML =
          '<div class="col-md-5">'
          + '<label class="form-label">Pekerjaan</label>'
          + '<div class="searchable-select js-searchable-select" data-filter-select="#project-holding" data-filter-attr="projectId">'
          + '<input type="text" class="form-control searchable-select-input" data-role="search-input" placeholder="Cari pekerjaan..." autocomplete="off" />'
          + '<div class="searchable-select-menu" data-role="menu"></div>'
          + '<select class="form-select d-none" name="allocations[' + index + '][work_item_id]" data-role="source"></select>'
          + '</div>'
          + '</div>'
          + '<div class="col-md-3">'
          + '<label class="form-label">Nominal</label>'
          + '<div class="input-group">'
          + '<span class="input-group-text">Rp</span>'
          + '<input type="number" class="form-control allocation-amount" name="allocations[' + index + '][amount]" min="0" step="1" placeholder="0" />'
          + '</div>'
          + '</div>'
          + '<div class="col-md-2">'
          + '<label class="form-label">Pembayaran ke</label>'
          + '<input type="number" class="form-control" name="allocations[' + index + '][payment_number]" min="1" placeholder="Otomatis" />'
          + '</div>'
          + '<div class="col-md-2">'
          + '<button type="button" class="btn btn-light-secondary w-100 allocation-remove"><i class="ti ti-trash"></i></button>'
          + '</div>'
          + '<div class="col-12 mb-3">'
          + '<input type="text" class="form-control form-control-sm mt-1" name="allocations[' + index + '][notes]" placeholder="Catatan alokasi (opsional)" />'
          + '</div>';

        const select = row.querySelector('[data-role="source"]');
        select.innerHTML = activityInput.innerHTML;
        Array.from(select.options).forEach(function (option) {
          option.selected = false;
          option.removeAttribute('selected');
        });
        select.insertAdjacentHTML('afterbegin', '<option value="">-</option>');
        select.value = '';

        allocationRows.appendChild(row);
        enhanceSearchableSelect(row.querySelector('.js-searchable-select'));

        row.querySelector('.allocation-remove').addEventListener('click', function () {
          row.remove();
          updateAllocationSummary();
        });

        row.querySelector('.allocation-amount').addEventListener('input', updateAllocationSummary);
      }

      if (allocationAddRow) {
        allocationAddRow.addEventListener('click', createAllocationRow);
        amountDisplayInput.addEventListener('input', updateAllocationSummary);
      }

      const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
      const rupiahFormatter = new Intl.NumberFormat('id-ID');
      const terminInfo = @json($workItemTerminInfo ?? []);
      const terminTitle = document.querySelector('#termin-info-title');
      const terminHistory = document.querySelector('#termin-info-history');
      const terminPackage = document.querySelector('#termin-info-package');
      const terminPackageList = document.querySelector('#termin-info-package-list');
      const terminOffer = document.querySelector('#termin-info-offer');
      const terminPaid = document.querySelector('#termin-info-paid');
      const terminRemaining = document.querySelector('#termin-info-remaining');
      const terminCurrencySwitch = document.querySelector('#termin-currency-switch');
      const dollarFormatter = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2, minimumFractionDigits: 0 });
      let terminCurrency = 'IDR';
      let usdToIdrRate = Number(amountExchangeRateInput.value) || null;

      function numericValue(value) {
        const amount = Number(value);

        return Number.isFinite(amount) ? amount : 0;
      }

      function normalizeIdrInputValue(value) {
        return String(value || '').replace(/\D/g, '');
      }

      function normalizeUsdInputValue(value) {
        const cleaned = String(value || '').replace(/[^\d,.]/g, '');

        if (cleaned === '') {
          return '';
        }

        const lastComma = cleaned.lastIndexOf(',');
        const lastDot = cleaned.lastIndexOf('.');
        let decimalSeparator = '';

        if (lastComma !== -1 && lastDot !== -1) {
          decimalSeparator = lastComma > lastDot ? ',' : '.';
        } else if (lastComma !== -1) {
          decimalSeparator = cleaned.length - lastComma <= 3 ? ',' : '';
        } else if (lastDot !== -1) {
          decimalSeparator = cleaned.length - lastDot <= 3 ? '.' : '';
        }

        if (decimalSeparator === '') {
          return cleaned.replace(/\D/g, '');
        }

        const parts = cleaned.split(decimalSeparator);
        const decimal = parts.pop().replace(/\D/g, '').slice(0, 2);
        const integer = parts.join('').replace(/\D/g, '') || '0';

        return integer + (cleaned.endsWith(decimalSeparator) ? '.' : (decimal !== '' ? '.' + decimal : ''));
      }

      function formatIdrInputValue(value) {
        const digits = normalizeIdrInputValue(value);

        return digits === '' ? '' : rupiahFormatter.format(Number(digits));
      }

      function formatUsdInputValue(value) {
        const normalized = normalizeUsdInputValue(value);

        if (normalized === '') {
          return '';
        }

        const hasTrailingDecimal = normalized.endsWith('.');
        const parts = normalized.split('.');
        const integer = parts[0] === '' ? '0' : dollarFormatter.format(Number(parts[0]));
        const decimal = parts[1] || '';

        return integer + (hasTrailingDecimal ? '.' : (decimal !== '' ? '.' + decimal : ''));
      }

      function numericCurrencyValue(value, currency) {
        if (typeof value !== 'string') {
          return numericValue(value);
        }

        if (currency === 'USD') {
          return Number(normalizeUsdInputValue(value) || 0);
        }

        return Number(normalizeIdrInputValue(value) || 0);
      }

      function hasPositiveAmount(value) {
        return numericValue(value) > 0;
      }

      function formatCurrency(value) {
        return 'Rp ' + rupiahFormatter.format(numericCurrencyValue(value, 'IDR'));
      }

      function formatUsd(value) {
        if (value === null || value === undefined) {
          return 'USD -';
        }

        return 'USD ' + dollarFormatter.format(numericCurrencyValue(value, 'USD'));
      }

      function amountCurrency() {
        return amountCurrencyInput.value || 'IDR';
      }

      function amountAsIdr() {
        if (amountCurrency() !== 'USD') {
          return Math.round(numericCurrencyValue(amountDisplayInput.value, 'IDR'));
        }

        const displayAmount = numericCurrencyValue(amountDisplayInput.value, 'USD');

        if (displayAmount === 0) {
          return 0;
        }

        if (!usdToIdrRate) {
          return null;
        }

        return Math.round(displayAmount * usdToIdrRate);
      }

      function syncAmountInput() {
        const idrAmount = amountAsIdr();

        amountInput.value = idrAmount === null ? '' : idrAmount;
        amountExchangeRateInput.value = amountCurrency() === 'USD' && usdToIdrRate ? usdToIdrRate : '';
        amountDisplayInput.value = amountCurrency() === 'USD'
          ? formatUsdInputValue(amountDisplayInput.value)
          : formatIdrInputValue(amountDisplayInput.value);
        amountDisplayInput.placeholder = amountCurrency() === 'USD' ? '0.00' : '0';
        amountHelper.classList.remove('text-danger');

        amountCurrencySwitch.querySelectorAll('button').forEach(function (button) {
          button.classList.toggle('is-active', button.dataset.currency === amountCurrency());
        });

        if (amountCurrency() === 'USD') {
          amountHelper.textContent = idrAmount === null
            ? 'Menunggu kurs USD...'
            : 'Setara ' + formatCurrency(idrAmount);
        } else {
          amountHelper.textContent = '';
        }
      }

      function setAmountCurrency(currency) {
        const previousCurrency = amountCurrency();
        const currentIdrAmount = amountAsIdr();

        amountCurrencyInput.value = currency;

        if (previousCurrency !== currency && currentIdrAmount !== null) {
          if (currency === 'USD') {
            amountDisplayInput.value = usdToIdrRate ? Number(currentIdrAmount / usdToIdrRate).toFixed(2) : '';
          } else {
            amountDisplayInput.value = currentIdrAmount;
          }
        }

        syncAmountInput();
        updateAllocationSummary();
        updateSummary();
      }

      function convertedUsd(value) {
        if (numericValue(value) === 0) {
          return 0;
        }

        if (!usdToIdrRate) {
          return null;
        }

        return numericValue(value) / usdToIdrRate;
      }

      function idrOfferForDisplay(info) {
        if (hasPositiveAmount(info.offer)) {
          return numericValue(info.offer);
        }

        if (hasPositiveAmount(info.offer_usd) && usdToIdrRate) {
          return numericValue(info.offer_usd) * usdToIdrRate;
        }

        return 0;
      }

      function usdOfferForDisplay(info) {
        if (hasPositiveAmount(info.offer_usd)) {
          return numericValue(info.offer_usd);
        }

        return convertedUsd(info.offer);
      }

      function paidForDisplay(info) {
        if (terminCurrency !== 'USD') {
          return numericValue(info.paid);
        }

        return convertedUsd(info.paid);
      }

      function remainingForDisplay(info) {
        if (terminCurrency !== 'USD') {
          return numericValue(info.remaining);
        }

        if (hasPositiveAmount(info.offer_usd)) {
          const paidUsd = convertedUsd(info.paid);

          return paidUsd === null ? numericValue(info.offer_usd) : numericValue(info.offer_usd) - paidUsd;
        }

        return convertedUsd(info.remaining);
      }

      function formatTerminCurrency(value) {
        return terminCurrency === 'USD' ? formatUsd(value) : formatCurrency(value);
      }

      function setTerminCurrency(currency) {
        terminCurrency = currency;

        if (!terminCurrencySwitch) {
          return;
        }

        terminCurrencySwitch.querySelectorAll('button').forEach(function (item) {
          item.classList.toggle('is-active', item.dataset.currency === currency);
        });
      }

      function syncTerminCurrencyForInfo(info) {
        if (!hasPositiveAmount(info.offer) && hasPositiveAmount(info.offer_usd)) {
          setTerminCurrency('USD');
        }
      }

      async function fetchUsdRate() {
        try {
          const response = await fetch('https://open.er-api.com/v6/latest/USD', { cache: 'no-store' });
          const data = await response.json();
          const rate = Number(data && data.rates ? data.rates.IDR : 0);

          if (rate > 0) {
            usdToIdrRate = rate;
            syncAmountInput();
            updateTerminInfo();
          }
        } catch (error) {
          usdToIdrRate = null;
        }
      }

      function currentTerminInfo() {
        return terminInfo[activityInput.value] || {
          offer: 0,
          paid: 0,
          remaining: 0,
          next_payment_number: 1,
          total_terms: 1,
          terms: [],
        };
      }

      function refreshPaymentNumberOptions(info, forceDefault) {
        const paidByNumber = {};

        (info.terms || []).forEach(function (term) {
          paidByNumber[term.number] = term.amount;
        });

        const nextPaymentNumber = Number(info.next_payment_number || 1);
        const totalTerms = Math.max(1, parseInt(paymentTotalInput.value, 10) || Number(info.total_terms || 1) || nextPaymentNumber);
        const currentValue = Number(paymentNumberInput.value);
        const desiredValue = !forceDefault && currentValue && currentValue <= totalTerms
          ? currentValue
          : Math.min(nextPaymentNumber, totalTerms);

        paymentNumberInput.innerHTML = '';

        for (let number = 1; number <= totalTerms; number++) {
          const option = document.createElement('option');
          option.value = number;
          option.textContent = 'Pembayaran ke-'
            + number
            + ' - '
            + (paidByNumber[number] !== undefined ? formatCurrency(paidByNumber[number]) : 'Belum dibayar');
          paymentNumberInput.appendChild(option);
        }

        paymentNumberInput.value = desiredValue;
      }

      function parsePackageItems(notes) {
        const match = /^Paket gabungan \d+ (?:area|pekerjaan) \(harga satu paket, bukan per-pekerjaan\):\s*(.+?)\.\s*(?:Kontraktor:.*?\.\s*)?Total penawaran/.exec(notes || '');

        if (!match) {
          return null;
        }

        return match[1].split(', ').map(function (segment) {
          return segment.trim();
        }).filter(function (segment) {
          return segment !== '';
        });
      }

      function updateTerminInfo() {
        const info = currentTerminInfo();
        const nextPaymentNumber = Number(info.next_payment_number || 1);

        if (terminTitle) {
          const terms = info.terms || [];

          syncTerminCurrencyForInfo(info);

          const offerDisplay = terminCurrency === 'USD' ? usdOfferForDisplay(info) : idrOfferForDisplay(info);
          const paidDisplay = paidForDisplay(info);
          const remainingDisplay = remainingForDisplay(info);

          terminTitle.textContent = selectedText(activityInput) || '-';
          terminOffer.textContent = formatTerminCurrency(offerDisplay);
          terminPaid.textContent = formatTerminCurrency(paidDisplay);
          terminRemaining.textContent = formatTerminCurrency(remainingDisplay);
          terminRemaining.classList.toggle('text-danger', remainingDisplay !== null && numericValue(remainingDisplay) < 0);
          terminRemaining.classList.toggle('text-primary', remainingDisplay === null || numericValue(remainingDisplay) >= 0);

          terminHistory.innerHTML = '';
          terms.forEach(function (term) {
            const option = document.createElement('option');
            option.value = term.number;
            option.textContent = 'Pembayaran ke-' + term.number + ' - ' + formatTerminCurrency(terminCurrency === 'USD' ? convertedUsd(term.amount) : term.amount);
            terminHistory.appendChild(option);
          });

          if (Number(info.remaining || 0) > 0 || terms.length === 0) {
            const upcomingOption = document.createElement('option');
            upcomingOption.value = nextPaymentNumber;
            upcomingOption.textContent = 'Pembayaran ke-' + nextPaymentNumber + ' - Belum dibayar';
            terminHistory.appendChild(upcomingOption);
            terminHistory.value = nextPaymentNumber;
          } else {
            terminHistory.value = terms.length > 0 ? terms[terms.length - 1].number : nextPaymentNumber;
          }

          receiptTotalInput.value = info.offer || '';
          paymentTotalInput.value = Number(info.total_terms || 1);

          const packageItems = (info.package_items || []).length > 0
            ? info.package_items.map(function (item) {
                return item.brand ? item.name + ' - ' + item.brand : item.name;
              })
            : parsePackageItems(info.notes);

          if (packageItems) {
            terminPackageList.innerHTML = '';
            packageItems.forEach(function (packageItem) {
              const item = document.createElement('li');
              item.textContent = packageItem;
              terminPackageList.appendChild(item);
            });
            terminPackage.classList.remove('d-none');
          } else {
            terminPackage.classList.add('d-none');
          }
        }

        renderAllocationHistory(info);
        refreshPaymentNumberOptions(info, true);
        updateSummary();
      }

      if (terminCurrencySwitch) {
        terminCurrencySwitch.querySelectorAll('button').forEach(function (button) {
          button.addEventListener('click', function () {
            setTerminCurrency(button.dataset.currency || 'IDR');

            if (terminCurrency === 'USD' && !usdToIdrRate) {
              fetchUsdRate();
            }

            updateTerminInfo();
          });
        });

        fetchUsdRate();
      }

      function selectedDayName() {
        if (!dateInput.value) {
          return '';
        }

        const selectedDate = new Date(dateInput.value + 'T00:00:00');
        return dayNames[selectedDate.getDay()];
      }

      function selectedText(input) {
        if (!input || !input.selectedOptions || input.selectedOptions.length === 0) {
          return '';
        }

        return input.selectedOptions[0].textContent.trim();
      }

      function updateSummary() {
        const receiptLabel = receiptCodeInput.value.trim() || 'Kuitansi';
        const paymentLabel = receiptLabel + ' - ' + (paymentNumberInput.value || 1) + '/' + (paymentTotalInput.value || 1);
        const summaryAmount = amountCurrency() === 'USD'
          ? formatUsd(amountDisplayInput.value) + ' (' + formatCurrency(amountInput.value) + ')'
          : formatCurrency(amountInput.value);

        document.querySelector('#summary-project-holding').textContent = selectedText(projectHoldingInput) || 'Belum tersedia';
        document.querySelector('#summary-name').textContent = selectedText(activityInput) || 'Belum tersedia';
        document.querySelector('#summary-vendor').textContent = selectedText(vendorInput) || '-';
        document.querySelector('#summary-amount').textContent = summaryAmount;
        document.querySelector('#summary-date').textContent = dateInput.value ? selectedDayName() + ', ' + dateInput.value : '-';
        document.querySelector('#summary-payment').textContent = paymentLabel;
      }

      function syncVendorFromWorkItem() {
        const selectedOption = activityInput.selectedOptions[0];
        const vendorId = selectedOption ? selectedOption.dataset.vendorId : '';

        if (vendorId) {
          vendorInput.value = vendorId;
          vendorInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        updateTerminInfo();
      }

      function sizeLabel(bytes) {
        return Math.round(bytes / 1024) + ' KB';
      }

      function canvasToBlob(canvas, quality) {
        return new Promise(function (resolve) {
          canvas.toBlob(resolve, 'image/jpeg', quality);
        });
      }

      async function compressImage(file) {
        const image = new Image();
        image.src = URL.createObjectURL(file);

        await new Promise(function (resolve, reject) {
          image.onload = resolve;
          image.onerror = reject;
        });

        let maxSide = 1280;
        let quality = 0.82;
        let blob = null;

        for (let attempt = 0; attempt < 10; attempt++) {
          const ratio = Math.min(1, maxSide / Math.max(image.width, image.height));
          const canvas = document.createElement('canvas');
          canvas.width = Math.round(image.width * ratio);
          canvas.height = Math.round(image.height * ratio);
          canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);

          blob = await canvasToBlob(canvas, quality);

          if (blob.size <= 110 * 1024 || quality <= 0.48) {
            break;
          }

          quality -= 0.08;
          maxSide -= 120;
        }

        URL.revokeObjectURL(image.src);

        return blob;
      }

      async function handleReceiptFile() {
        const file = receiptFileInput.files[0];

        if (!file) {
          receiptPreview.classList.remove('is-visible');
          receiptImage.removeAttribute('src');
          receiptImage.style.display = '';
          receiptFileBadge.classList.remove('is-visible');
          document.querySelector('#summary-receipt').textContent = 'Belum ada';
          return;
        }

        const fileName = file.name.toLowerCase();
        const isPdf = file.type === 'application/pdf' || fileName.endsWith('.pdf');
        const isAllowedImage = file.type.startsWith('image/') && /\.(jpe?g|png|webp)$/i.test(file.name);

        if (!isPdf && !isAllowedImage) {
          receiptName.textContent = 'Format harus gambar atau PDF';
          receiptSize.textContent = 'Pilih JPG, PNG, WEBP, atau PDF';
          receiptPreview.classList.add('is-visible');
          receiptImage.removeAttribute('src');
          receiptImage.style.display = 'none';
          receiptFileBadge.classList.remove('is-visible');
          document.querySelector('#summary-receipt').textContent = 'Format tidak sesuai';
          return;
        }

        receiptName.textContent = file.name;
        receiptSize.textContent = isPdf ? sizeLabel(file.size) : 'Memproses resize...';
        receiptPreview.classList.add('is-visible');

        if (isPdf) {
          receiptImage.removeAttribute('src');
          receiptImage.style.display = 'none';
          receiptFileBadge.classList.add('is-visible');
          document.querySelector('#summary-receipt').textContent = 'PDF ' + sizeLabel(file.size);
          return;
        }

        const compressedBlob = await compressImage(file);
        const compressedFile = new File(
          [compressedBlob],
          file.name.replace(/\.(jpg|jpeg|png|webp)$/i, '') + '.jpg',
          { type: 'image/jpeg', lastModified: Date.now() },
        );

        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(compressedFile);
        receiptFileInput.files = dataTransfer.files;

        const previewUrl = URL.createObjectURL(compressedFile);

        receiptImage.src = previewUrl;
        receiptImage.style.display = '';
        receiptFileBadge.classList.remove('is-visible');
        receiptName.textContent = compressedFile.name;
        receiptSize.textContent = 'Asli ' + sizeLabel(file.size) + ' | Resize ' + sizeLabel(compressedFile.size);
        document.querySelector('#summary-receipt').textContent = 'JPEG ' + sizeLabel(compressedFile.size);
      }

      [dateInput, projectHoldingInput, vendorInput, amountDisplayInput, receiptCodeInput, paymentNumberInput, paymentTotalInput].forEach(function (input) {
        input.addEventListener('input', function () {
          if (input === amountDisplayInput) {
            syncAmountInput();
            updateAllocationSummary();
          }

          updateSummary();
        });

        input.addEventListener('change', function () {
          if (input === amountDisplayInput) {
            syncAmountInput();
            updateAllocationSummary();
          }

          updateSummary();
        });
      });

      amountCurrencySwitch.querySelectorAll('button').forEach(function (button) {
        button.addEventListener('click', function () {
          setAmountCurrency(button.dataset.currency || 'IDR');

          if (amountCurrency() === 'USD' && !usdToIdrRate) {
            fetchUsdRate();
          }
        });
      });

      activityInput.addEventListener('change', syncVendorFromWorkItem);
      projectHoldingInput.addEventListener('change', function () {
        searchableSelects.forEach(function (entry) {
          if (entry.wrapper.dataset.filterSelect) {
            entry.refreshForFilter();
          }
        });

        updateSummary();
      });
      receiptFileInput.addEventListener('change', handleReceiptFile);

      paymentTotalInput.addEventListener('input', function () {
        refreshPaymentNumberOptions(currentTerminInfo());
      });

      searchableSelects.forEach(function (entry) {
        if (entry.wrapper.dataset.filterSelect) {
          entry.refreshForFilter();
        }
      });

      form.addEventListener('submit', function (event) {
        syncAmountInput();

        if (amountCurrency() === 'USD' && !amountInput.value) {
          event.preventDefault();
          amountHelper.textContent = 'Kurs USD belum tersedia. Coba lagi sebentar.';
          amountHelper.classList.add('text-danger');
          amountDisplayInput.focus();
          return;
        }

        if (!activityInput.value) {
          event.preventDefault();
          const searchInput = activityInput.closest('.searchable-select').querySelector('[data-role="search-input"]');
          searchInput.classList.add('is-invalid');
          searchInput.focus();
          return;
        }

        const totalAllocations = Array.from(document.querySelectorAll('.allocation-amount')).reduce(function (sum, input) {
          return sum + (Number(input.value) || 0);
        }, 0);

        if (totalAllocations > Number(amountInput.value || 0)) {
          event.preventDefault();
          updateAllocationSummary();
          allocationSummary.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });

      form.addEventListener('reset', function () {
        setTimeout(function () {
          receiptPreview.classList.remove('is-visible');
          draftStatus.classList.add('d-none');
          syncAmountInput();
          updateTerminInfo();
          searchableSelectSyncs.forEach(function (sync) {
            sync();
          });

          if (allocationRows) {
            allocationRows.innerHTML = '';
            updateAllocationSummary();
          }
        });
      });

      syncAmountInput();

      if (amountCurrency() === 'USD' && !usdToIdrRate) {
        fetchUsdRate();
      }

      updateTerminInfo();
    });
  </script>
@endpush
