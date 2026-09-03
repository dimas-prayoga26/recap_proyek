@php
  $formatRupiah = fn (int $value) => 'Rp '.number_format($value, 0, ',', '.');
  $selectedVendor = $vendors->firstWhere('id', (int) ($filters['vendor_id'] ?? 0));
@endphp

@extends('layouts.app')

@section('title', $title)
@section('page_title', $title)

@section('page_actions')
  <a href="{{ route('kategori-pekerjaan.index') }}" class="btn btn-light-secondary">
    <i class="ti ti-businessplan me-1"></i> Kategori Pekerjaan
  </a>
  <a href="{{ route('uang-keluar.index') }}" class="btn btn-primary">
    <i class="ti ti-plus me-1"></i> Input Transaksi
  </a>
@endsection

@push('styles')
  <style>
    .term-filter-grid {
      align-items: end;
      display: grid;
      gap: 12px;
      grid-template-columns: minmax(220px, 1fr) minmax(190px, 0.75fr) minmax(150px, 0.55fr) auto;
    }

    .term-summary-card {
      align-items: center;
      border: 1px solid #eef2f6;
      border-radius: 8px;
      display: flex;
      gap: 12px;
      min-height: 78px;
      padding: 12px 14px;
    }

    .term-summary-card.is-total {
      background: #eef6ff;
      border-color: #b6dcff;
    }

    .term-summary-card.is-paid {
      background: #f0fdf4;
      border-color: #bbf7d0;
    }

    .term-summary-card.is-remaining {
      background: #fff7ed;
      border-color: #fed7aa;
    }

    .term-summary-icon {
      align-items: center;
      border-radius: 8px;
      color: #fff;
      display: inline-flex;
      flex: 0 0 36px;
      font-size: 18px;
      height: 36px;
      justify-content: center;
      width: 36px;
    }

    .term-summary-card.is-total .term-summary-icon {
      background: #2196f3;
    }

    .term-summary-card.is-paid .term-summary-icon {
      background: #00c853;
    }

    .term-summary-card.is-remaining .term-summary-icon {
      background: #f59e0b;
    }

    .term-summary-label {
      color: #697586;
      display: block;
      font-size: 11px;
      margin-bottom: 4px;
    }

    .term-summary-value {
      color: #202939;
      display: block;
      font-size: 16px;
      font-weight: 800;
      line-height: 1.15;
    }

    .term-summary-helper {
      color: #697586;
      display: block;
      font-size: 11px;
      margin-top: 4px;
    }

    .term-table th {
      background: #f8fafc;
      border-bottom: 0;
      color: #697586;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .term-table td {
      vertical-align: middle;
      white-space: nowrap;
    }

    .term-work-title {
      color: #202939;
      display: block;
      font-weight: 600;
      min-width: 260px;
      white-space: normal;
    }

    .term-amount-cell {
      font-weight: 700;
      text-align: right;
    }

    .term-payment-action {
      align-items: center;
      display: inline-flex;
      gap: 8px;
      justify-content: flex-end;
    }

    .term-payment-menu-button {
      align-items: center;
      background: #eef6ff;
      border: 1px solid #b6dcff;
      border-radius: 6px;
      color: #1e88e5;
      display: inline-flex;
      height: 28px;
      justify-content: center;
      font-weight: 700;
      padding: 0;
      text-decoration: none;
      width: 28px;
    }

    .term-payment-menu-button:hover {
      background: #dff0ff;
      color: #2196f3;
      text-decoration: none;
    }

    .term-payment-menu .dropdown-menu {
      border: 1px solid #eef2f6;
      border-radius: 8px;
      box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
      min-width: 180px;
      padding: 6px;
    }

    .term-payment-menu .dropdown-item {
      align-items: center;
      border-radius: 6px;
      display: inline-flex;
      gap: 8px;
      font-weight: 700;
      padding: 8px 10px;
    }

    .term-payment-menu .dropdown-item.text-danger:hover {
      background: #fff4f4;
      color: #ff3b30;
    }

    .payment-delete-visual {
      align-items: center;
      background: #fff4f4;
      border: 1px solid #ffdada;
      border-radius: 8px;
      display: flex;
      gap: 14px;
      padding: 16px;
    }

    .payment-delete-icon {
      align-items: center;
      background: #ffe1e1;
      border-radius: 8px;
      color: #ff3b30;
      display: inline-flex;
      flex: 0 0 44px;
      font-size: 22px;
      height: 44px;
      justify-content: center;
      width: 44px;
    }

    .payment-delete-summary {
      color: #202939;
      font-weight: 700;
      margin: 0;
    }

    .payment-delete-helper {
      color: #697586;
      font-size: 13px;
      margin: 4px 0 0;
    }

    .payment-detail-line {
      align-items: flex-start;
      border-bottom: 1px solid #eef2f6;
      display: flex;
      gap: 16px;
      justify-content: space-between;
      padding: 10px 0;
    }

    .payment-detail-line span {
      color: #697586;
      font-size: 12px;
    }

    .payment-detail-line strong {
      color: #202939;
      text-align: right;
    }

    .payment-detail-notes {
      color: #202939;
      margin: 0;
      white-space: pre-wrap;
    }

    .payment-detail-file {
      align-items: center;
      display: inline-flex;
      gap: 8px;
    }

    .payment-detail-preview {
      align-items: center;
      background: #f8fafc;
      border: 1px solid #eef2f6;
      border-radius: 8px;
      display: flex;
      justify-content: center;
      max-height: min(58vh, 460px);
      min-height: 180px;
      overflow: auto;
      padding: 12px;
    }

    .payment-detail-preview.is-zoomed {
      align-items: flex-start;
      justify-content: flex-start;
    }

    .payment-detail-preview img {
      display: block;
      height: auto;
      max-height: min(52vh, 400px);
      max-width: 100%;
      object-fit: contain;
      transition: width 0.15s ease;
      user-select: none;
      width: auto;
    }

    .payment-detail-preview img.is-zoomed {
      cursor: grab;
      max-height: none;
      max-width: none;
    }

    .payment-detail-preview img.is-zoomed.is-dragging {
      cursor: grabbing;
    }

    .payment-detail-preview iframe {
      height: min(52vh, 400px);
      max-height: 400px;
    }

    .vendor-search-dropdown {
      position: relative;
    }

    .vendor-search-dropdown .dropdown-menu {
      max-height: 320px;
      overflow: hidden;
    }

    .vendor-search-options {
      max-height: 250px;
      overflow-y: auto;
    }

    .vendor-search-options .dropdown-item {
      border-radius: 6px;
      padding: 8px 10px;
      white-space: normal;
    }

    .vendor-search-options .dropdown-item.active {
      background: #e3f2ff;
      color: #1e88e5;
      font-weight: 600;
    }

    .vendor-search-options .searchable-select-empty {
      color: #697586;
      font-size: 13px;
      padding: 8px 10px;
    }

    @media (max-width: 767.98px) {
      .term-filter-grid {
        grid-template-columns: 1fr;
      }

      .term-filter-grid .btn {
        width: 100%;
      }
    }
  </style>
@endpush

@section('content')
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <div class="row align-items-center">
            <div class="col">
              <small class="text-muted">{{ $activeProject?->name ?? 'Belum ada project' }}</small>
              <h4 class="mb-0">Rekap Pembayaran</h4>
            </div>
          </div>
        </div>
        <div class="card-body pt-0">
          @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
          @endif
          @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
          @endif

          <form method="GET" action="{{ route('termin-pembayaran.index') }}" class="term-filter-grid mb-4">
            <div>
              <label for="term-search" class="form-label">Search</label>
              <input type="search" class="form-control" id="term-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari pekerjaan atau vendor..." />
            </div>
            <div>
              <label for="term-vendor" class="form-label">Filter by Vendor</label>
              <input type="hidden" id="term-vendor" name="vendor_id" value="{{ $selectedVendor?->id }}" />
              <div class="dropdown vendor-search-dropdown">
                <button class="form-select text-start" type="button" id="term-vendor-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                  <span id="term-vendor-label">{{ $selectedVendor?->name ?? 'Semua Vendor' }}</span>
                </button>
                <div class="dropdown-menu w-100 p-2" aria-labelledby="term-vendor-toggle">
                  <input type="search" class="form-control form-control-sm mb-2" id="term-vendor-search" placeholder="Cari vendor..." autocomplete="off" />
                  <div class="vendor-search-options" id="term-vendor-options">
                    <button type="button" class="dropdown-item vendor-option {{ $selectedVendor ? '' : 'active' }}" data-value="" data-label="Semua Vendor" data-search="semua vendor">
                      Semua Vendor
                    </button>
                    @foreach ($vendors as $vendor)
                      <button type="button" class="dropdown-item vendor-option {{ $selectedVendor?->id === $vendor->id ? 'active' : '' }}" data-value="{{ $vendor->id }}" data-label="{{ $vendor->name }}" data-search="{{ strtolower($vendor->name) }}">
                        {{ $vendor->name }}
                      </button>
                    @endforeach
                    <div class="searchable-select-empty d-none" id="term-vendor-empty">Vendor tidak ditemukan.</div>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <label for="term-count" class="form-label">Jumlah Pembayaran</label>
              <select class="form-select" id="term-count" name="terms">
                <option value="">Semua</option>
                @foreach ($availableTermsOptions as $termsOption)
                  <option value="{{ $termsOption }}" @selected((int) ($filters['terms'] ?? 0) === $termsOption)>{{ $termsOption }}x</option>
                @endforeach
              </select>
            </div>
            <button type="submit" class="btn btn-primary">
              <i class="ti ti-filter me-1"></i> Tampilkan
            </button>
          </form>

          <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
              <div class="term-summary-card is-total" data-summary-card="total" data-summary-vendor="{{ $selectedVendor?->name ?? 'Semua Vendor' }}">
                <span class="term-summary-icon">
                  <i class="ti ti-file-invoice"></i>
                </span>
                <div>
                  <span class="term-summary-label">Total Penawaran</span>
                  <strong class="term-summary-value">{{ $formatRupiah($paymentTotals['offer'] ?? 0) }}</strong>
                  <span class="term-summary-helper">{{ $selectedVendor?->name ?? 'Semua Vendor' }} - {{ $paymentTotals['row_count'] ?? 0 }} pekerjaan</span>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="term-summary-card is-paid" data-summary-card="paid" data-summary-vendor="{{ $selectedVendor?->name ?? 'Semua Vendor' }}">
                <span class="term-summary-icon">
                  <i class="ti ti-cash-banknote"></i>
                </span>
                <div>
                  <span class="term-summary-label">Total Sudah Dibayar</span>
                  <strong class="term-summary-value">{{ $formatRupiah($paymentTotals['paid'] ?? 0) }}</strong>
                  <span class="term-summary-helper">{{ $selectedVendor?->name ?? 'Semua Vendor' }} - {{ $paymentTotals['payment_count'] ?? 0 }} pembayaran</span>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="term-summary-card is-remaining" data-summary-card="remaining" data-summary-vendor="{{ $selectedVendor?->name ?? 'Semua Vendor' }}">
                <span class="term-summary-icon">
                  <i class="ti ti-receipt-refund"></i>
                </span>
                <div>
                  <span class="term-summary-label">Total Sisa Pembayaran</span>
                  <strong class="term-summary-value">{{ $formatRupiah($paymentTotals['remaining'] ?? 0) }}</strong>
                  <span class="term-summary-helper">
                    {{ $selectedVendor?->name ?? 'Semua Vendor' }} -
                    @if (($paymentTotals['remaining'] ?? 0) > 0)
                      <span class="badge bg-light-warning text-warning">Belum Lunas</span>
                    @else
                      <span class="badge bg-light-success text-success">Lunas</span>
                    @endif
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-hover term-table mb-0">
              <thead>
                <tr>
                  <th>Pekerjaan</th>
                  <th>Vendor</th>
                  <th class="text-end">Penawaran</th>
                  @for ($number = 1; $number <= $maxTermsColumn; $number++)
                    <th class="text-end">Pembayaran {{ $number }}</th>
                  @endfor
                  <th class="text-end">Sisa</th>
                  <th class="text-end">Total Sudah Dibayar</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($rows as $row)
                  <tr>
                    <td>
                      <span class="term-work-title">{{ $row['work_item']->name }}</span>
                    </td>
                    <td>{{ $row['vendor_name'] }}</td>
                    <td class="term-amount-cell">{{ $formatRupiah($row['summary']['offer']) }}</td>
                    @for ($number = 1; $number <= $maxTermsColumn; $number++)
                    <td class="term-amount-cell">
                        @php
                          $payment = $row['payments']->get($number);
                        @endphp
                        @if ($payment)
                          <div class="term-payment-action">
                            <span>{{ $formatRupiah($payment['amount']) }}</span>
                            <div class="dropdown term-payment-menu">
                              <button
                                type="button"
                                class="term-payment-menu-button"
                                data-bs-toggle="dropdown"
                                data-bs-boundary="viewport"
                                aria-expanded="false"
                                aria-label="Aksi pembayaran ke-{{ $payment['detail']['payment_number'] }}"
                                title="Aksi pembayaran"
                              >
                                <i class="ti ti-dots-vertical"></i>
                              </button>
                              <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                  <button
                                    type="button"
                                    class="dropdown-item term-payment-detail-action"
                                    data-bs-toggle="modal"
                                    data-bs-target="#payment-detail-modal"
                                    data-payment-number="{{ $payment['detail']['payment_number'] }}"
                                    data-amount="{{ $formatRupiah($payment['detail']['amount']) }}"
                                    data-recorded-at="{{ $payment['detail']['recorded_at'] }}"
                                    data-service-detail="{{ $payment['detail']['service_detail'] }}"
                                    data-notes="{{ $payment['detail']['notes'] }}"
                                    data-receipt-url="{{ $payment['detail']['receipt_url'] }}"
                                    data-receipt-mime="{{ $payment['detail']['receipt_mime'] }}"
                                    data-receipt-name="{{ $payment['detail']['receipt_name'] }}"
                                  >
                                    <i class="ti ti-eye"></i>
                                    <span>Detail</span>
                                  </button>
                                </li>
                                <li>
                                  <button
                                    type="button"
                                    class="dropdown-item term-payment-update-action"
                                    data-bs-toggle="modal"
                                    data-bs-target="#payment-update-detail-modal"
                                    data-update-action="{{ route('termin-pembayaran.rincian.update', $payment['detail']['payment_term_id']) }}"
                                    data-update-payment-number="{{ $payment['detail']['payment_number'] }}"
                                    data-update-amount="{{ $formatRupiah($payment['detail']['amount']) }}"
                                    data-update-work-item-id="{{ $payment['detail']['work_item_id'] }}"
                                    data-update-work-item-name="{{ $payment['detail']['work_item_name'] }}"
                                    data-current-service-detail-id="{{ $payment['detail']['service_detail_id'] }}"
                                    data-update-search-keyword="{{ $payment['detail']['search_keyword'] }}"
                                  >
                                    <i class="ti ti-edit"></i>
                                    <span>Update Rincian</span>
                                  </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                  <button
                                    type="button"
                                    class="dropdown-item text-danger term-payment-delete-action"
                                    data-bs-toggle="modal"
                                    data-bs-target="#payment-delete-modal"
                                    data-delete-action="{{ route('termin-pembayaran.destroy', $payment['detail']['payment_term_id']) }}"
                                    data-delete-payment-number="{{ $payment['detail']['payment_number'] }}"
                                    data-delete-amount="{{ $formatRupiah($payment['detail']['amount']) }}"
                                  >
                                    <i class="ti ti-trash"></i>
                                    <span>Delete</span>
                                  </button>
                                </li>
                              </ul>
                            </div>
                          </div>
                        @else
                          -
                        @endif
                      </td>
                    @endfor
                    <td class="term-amount-cell {{ $row['summary']['remaining'] < 0 ? 'text-danger' : '' }}">
                      {{ $formatRupiah($row['summary']['remaining']) }}
                    </td>
                    <td class="term-amount-cell text-success">{{ $formatRupiah($row['summary']['paid']) }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="{{ 5 + $maxTermsColumn }}" class="text-center text-muted py-4">Belum ada pekerjaan yang sesuai.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="payment-detail-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="payment-detail-title">Detail Pembayaran</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="payment-detail-line">
            <span>Nominal</span>
            <strong id="payment-detail-amount">-</strong>
          </div>
          <div class="payment-detail-line">
            <span>Tanggal Pencatatan</span>
            <strong id="payment-detail-date">-</strong>
          </div>
          <div class="payment-detail-line d-none" id="payment-detail-service-row">
            <span>Rincian Jasa</span>
            <strong id="payment-detail-service">-</strong>
          </div>
          <div class="py-3">
            <span class="d-block text-muted fs-6 mb-2">Bukti Pembayaran</span>
            <div class="payment-detail-preview">
              <div id="payment-detail-empty" class="alert alert-light-secondary mb-0">Bukti belum ada.</div>
              <img src="" id="payment-detail-image" class="img-fluid rounded d-none" alt="Bukti pembayaran" />
              <iframe src="" id="payment-detail-pdf" class="w-100 border rounded d-none" title="Bukti pembayaran PDF"></iframe>
            </div>
            <a href="#" id="payment-detail-download" class="btn btn-light-primary payment-detail-file mt-3 d-none" target="_blank" rel="noopener">
              <i class="ti ti-external-link"></i>
              <span>Buka Bukti Pembayaran</span>
            </a>
          </div>
          <div>
            <span class="d-block text-muted fs-6 mb-2">Notes</span>
            <div class="border rounded p-3">
              <p class="payment-detail-notes" id="payment-detail-notes">-</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="payment-update-detail-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="POST" action="#" class="modal-content" id="payment-update-detail-form">
        @csrf
        @method('PATCH')
        <div class="modal-header">
          <h5 class="modal-title">Update Rincian Jasa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="payment-detail-line pt-0">
            <span>Pembayaran</span>
            <strong id="payment-update-detail-summary">-</strong>
          </div>
          <div class="payment-detail-line">
            <span>Pekerjaan Utama</span>
            <strong id="payment-update-work-item">-</strong>
          </div>
          <div class="mt-3">
            <label for="payment-update-detail-toggle" class="form-label">Rincian Jasa</label>
            <input type="hidden" id="payment-update-detail-value" name="service_detail_work_item_id" value="" />
            <div class="dropdown vendor-search-dropdown">
              <button class="form-select text-start" type="button" id="payment-update-detail-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <span id="payment-update-detail-label">Tanpa rincian</span>
              </button>
              <div class="dropdown-menu w-100 p-2" aria-labelledby="payment-update-detail-toggle">
                <input type="search" class="form-control form-control-sm mb-2" id="payment-update-detail-search" placeholder="Cari rincian pekerjaan..." autocomplete="off" />
                <div class="vendor-search-options" id="payment-update-detail-options">
                  <button type="button" class="dropdown-item rincian-detail-option active" data-value="" data-label="Tanpa rincian" data-search="tanpa rincian">
                    Tanpa rincian
                  </button>
                  @foreach (($serviceDetailOptions ?? collect()) as $item)
                    @php
                      $serviceDetailLabel = trim(preg_replace('/^\s*Belanja\s+/i', '', $item->name) ?? $item->name);
                      $serviceDetailVendorName = $item->vendor?->name;
                      $serviceDetailOptionLabel = $serviceDetailVendorName ? $serviceDetailLabel.' - '.$serviceDetailVendorName : $serviceDetailLabel;
                      $serviceDetailSearch = strtolower(trim($serviceDetailLabel.' '.$item->name.' '.$serviceDetailVendorName));
                    @endphp
                    <button
                      type="button"
                      class="dropdown-item rincian-detail-option"
                      data-value="{{ $item->id }}"
                      data-label="{{ $serviceDetailOptionLabel }}"
                      data-search="{{ $serviceDetailSearch }}"
                    >
                      {{ $serviceDetailOptionLabel }}
                    </button>
                  @endforeach
                  <div class="searchable-select-empty d-none" id="payment-update-detail-empty">Rincian tidak ditemukan.</div>
                </div>
              </div>
            </div>
            <small class="form-text text-muted">Pembayaran tetap masuk ke pekerjaan utama. Rincian ini hanya buat keterangan jasa di rekap.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">
            <i class="ti ti-device-floppy me-1"></i> Simpan Rincian
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="payment-delete-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="POST" action="#" class="modal-content" id="payment-delete-form">
        @csrf
        @method('DELETE')
        <div class="modal-header">
          <h5 class="modal-title">Hapus Pembayaran</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="payment-delete-visual">
            <span class="payment-delete-icon">
              <i class="ti ti-trash"></i>
            </span>
            <div>
              <p class="payment-delete-summary" id="payment-delete-summary">Hapus pembayaran ini?</p>
              <p class="payment-delete-helper">Sisa pembayaran akan dihitung ulang setelah data ini dihapus.</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">
            <i class="ti ti-trash me-1"></i> Hapus Pembayaran
          </button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const vendorInput = document.querySelector('#term-vendor');
      const vendorLabel = document.querySelector('#term-vendor-label');
      const vendorSearch = document.querySelector('#term-vendor-search');
      const vendorToggle = document.querySelector('#term-vendor-toggle');
      const vendorOptions = Array.from(document.querySelectorAll('.vendor-option'));
      const vendorEmpty = document.querySelector('#term-vendor-empty');

      function filterVendorOptions() {
        const keyword = vendorSearch.value.trim().toLowerCase();
        let visibleCount = 0;

        vendorOptions.forEach(function (option) {
          const isVisible = option.dataset.search.includes(keyword);
          option.classList.toggle('d-none', !isVisible);

          if (isVisible) {
            visibleCount++;
          }
        });

        vendorEmpty.classList.toggle('d-none', visibleCount > 0);
      }

      vendorOptions.forEach(function (option) {
        option.addEventListener('click', function () {
          vendorInput.value = option.dataset.value;
          vendorLabel.textContent = option.dataset.label;
          vendorOptions.forEach(function (item) {
            item.classList.toggle('active', item === option);
          });

          vendorSearch.value = '';
          filterVendorOptions();
          bootstrap.Dropdown.getOrCreateInstance(vendorToggle).hide();
        });
      });

      vendorSearch.addEventListener('input', filterVendorOptions);
      vendorToggle.addEventListener('shown.bs.dropdown', function () {
        vendorSearch.focus();
      });

      const paymentDetailTitle = document.querySelector('#payment-detail-title');
      const paymentDetailAmount = document.querySelector('#payment-detail-amount');
      const paymentDetailDate = document.querySelector('#payment-detail-date');
      const paymentDetailServiceRow = document.querySelector('#payment-detail-service-row');
      const paymentDetailService = document.querySelector('#payment-detail-service');
      const paymentDetailNotes = document.querySelector('#payment-detail-notes');
      const paymentDetailEmpty = document.querySelector('#payment-detail-empty');
      const paymentDetailPreview = document.querySelector('.payment-detail-preview');
      const paymentDetailImage = document.querySelector('#payment-detail-image');
      const paymentDetailPdf = document.querySelector('#payment-detail-pdf');
      const paymentDetailDownload = document.querySelector('#payment-detail-download');
      const paymentDetailDownloadText = paymentDetailDownload.querySelector('span');
      const paymentUpdateDetailForm = document.querySelector('#payment-update-detail-form');
      const paymentUpdateDetailSummary = document.querySelector('#payment-update-detail-summary');
      const paymentUpdateWorkItem = document.querySelector('#payment-update-work-item');
      const paymentUpdateDetailSearch = document.querySelector('#payment-update-detail-search');
      const paymentUpdateDetailToggle = document.querySelector('#payment-update-detail-toggle');
      const paymentUpdateDetailLabel = document.querySelector('#payment-update-detail-label');
      const paymentUpdateDetailValue = document.querySelector('#payment-update-detail-value');
      const paymentUpdateDetailEmpty = document.querySelector('#payment-update-detail-empty');
      const paymentUpdateDetailOptions = Array.from(document.querySelectorAll('.rincian-detail-option'));
      const paymentDeleteForm = document.querySelector('#payment-delete-form');
      const paymentDeleteSummary = document.querySelector('#payment-delete-summary');
      let paymentDetailZoom = 1;
      let isPaymentDetailDragging = false;
      let paymentDetailDragStartX = 0;
      let paymentDetailDragStartY = 0;
      let paymentDetailDragScrollLeft = 0;
      let paymentDetailDragScrollTop = 0;

      function setPaymentDetailZoom(value, pointerEvent = null) {
        const previousZoom = paymentDetailZoom;
        const previewRect = paymentDetailPreview.getBoundingClientRect();
        const pointerX = pointerEvent ? pointerEvent.clientX - previewRect.left : paymentDetailPreview.clientWidth / 2;
        const pointerY = pointerEvent ? pointerEvent.clientY - previewRect.top : paymentDetailPreview.clientHeight / 2;
        const scrollAnchorX = paymentDetailPreview.scrollLeft + pointerX;
        const scrollAnchorY = paymentDetailPreview.scrollTop + pointerY;

        paymentDetailZoom = Math.min(5, Math.max(1, value));
        paymentDetailPreview.classList.toggle('is-zoomed', paymentDetailZoom > 1);
        paymentDetailImage.classList.toggle('is-zoomed', paymentDetailZoom > 1);
        paymentDetailImage.style.width = paymentDetailZoom > 1 ? (paymentDetailZoom * 100) + '%' : '';

        if (paymentDetailZoom <= 1) {
          paymentDetailImage.classList.remove('is-dragging');
          isPaymentDetailDragging = false;
          paymentDetailPreview.scrollLeft = 0;
          paymentDetailPreview.scrollTop = 0;
        }

        if (pointerEvent && previousZoom !== paymentDetailZoom) {
          const zoomRatio = paymentDetailZoom / previousZoom;

          paymentDetailPreview.scrollLeft = (scrollAnchorX * zoomRatio) - pointerX;
          paymentDetailPreview.scrollTop = (scrollAnchorY * zoomRatio) - pointerY;
        }
      }

      function resetPaymentReceiptPreview() {
        paymentDetailEmpty.classList.add('d-none');
        paymentDetailImage.classList.add('d-none');
        paymentDetailPreview.classList.remove('is-zoomed');
        paymentDetailImage.classList.remove('is-zoomed', 'is-dragging');
        paymentDetailPdf.classList.add('d-none');
        paymentDetailDownload.classList.add('d-none');
        paymentDetailImage.removeAttribute('src');
        paymentDetailPdf.removeAttribute('src');
        paymentDetailImage.dataset.receiptName = '';
        paymentDetailImage.dataset.receiptUrl = '';
        paymentDetailImage.style.width = '';
        paymentDetailDownload.href = '#';
        setPaymentDetailZoom(1);
      }

      function showPaymentReceiptDownload(receiptName) {
        paymentDetailDownload.classList.remove('d-none');
        paymentDetailDownloadText.textContent = receiptName ? 'Buka ' + receiptName : 'Buka Bukti Pembayaran';
      }

      function showPaymentReceiptFallback(message, receiptName) {
        paymentDetailImage.classList.add('d-none');
        paymentDetailPdf.classList.add('d-none');
        showPaymentReceiptDownload(receiptName);

        if (message) {
          paymentDetailEmpty.textContent = message;
          paymentDetailEmpty.classList.remove('d-none');
        }
      }

      paymentDetailImage.addEventListener('error', function () {
        if (! paymentDetailImage.dataset.receiptUrl) {
          return;
        }

        showPaymentReceiptFallback('Preview gambar gagal dimuat. Buka file bukti pembayaran.', paymentDetailImage.dataset.receiptName || '');
      });

      function selectRincianOption(option) {
        if (!option) {
          return;
        }

        paymentUpdateDetailValue.value = option.dataset.value || '';
        paymentUpdateDetailLabel.textContent = option.dataset.label || 'Tanpa rincian';

        paymentUpdateDetailOptions.forEach(function (item) {
          item.classList.toggle('active', item === option);
        });
      }

      function filterPaymentUpdateOptions() {
        const keyword = paymentUpdateDetailSearch.value.trim().toLowerCase();
        let visibleCount = 0;

        paymentUpdateDetailOptions.forEach(function (option) {
          const isBlocked = option.dataset.blocked === '1';
          const matches = option.dataset.value === ''
            || (option.dataset.search || option.textContent).toLowerCase().includes(keyword);
          const isVisible = !isBlocked && matches;

          option.classList.toggle('d-none', !isVisible);

          if (isVisible) {
            visibleCount++;
          }
        });

        paymentUpdateDetailEmpty.classList.toggle('d-none', visibleCount > 0);
      }

      paymentUpdateDetailOptions.forEach(function (option) {
        option.addEventListener('click', function () {
          if (option.dataset.blocked === '1') {
            return;
          }

          selectRincianOption(option);
          bootstrap.Dropdown.getOrCreateInstance(paymentUpdateDetailToggle).hide();
        });
      });

      paymentUpdateDetailToggle.addEventListener('shown.bs.dropdown', function () {
        paymentUpdateDetailSearch.focus();
      });

      document.querySelectorAll('.term-payment-detail-action').forEach(function (button) {
        button.addEventListener('click', function () {
          const receiptUrl = button.dataset.receiptUrl || '';
          const receiptMime = (button.dataset.receiptMime || '').toLowerCase();
          const receiptName = button.dataset.receiptName || '';
          const receiptPath = (receiptUrl || receiptName).toLowerCase();
          const isPdf = receiptMime === 'application/pdf' || receiptPath.endsWith('.pdf');
          const isImage = receiptMime.startsWith('image/') || /\.(jpe?g|png)$/i.test(receiptPath);

          paymentDetailTitle.textContent = 'Pembayaran ke-' + (button.dataset.paymentNumber || '-');
          paymentDetailAmount.textContent = button.dataset.amount || '-';
          paymentDetailDate.textContent = button.dataset.recordedAt || '-';
          paymentDetailService.textContent = button.dataset.serviceDetail || '-';
          paymentDetailServiceRow.classList.toggle('d-none', !button.dataset.serviceDetail);
          paymentDetailNotes.textContent = button.dataset.notes || '-';
          resetPaymentReceiptPreview();

          if (!receiptUrl) {
            paymentDetailEmpty.textContent = 'Bukti belum ada.';
            paymentDetailEmpty.classList.remove('d-none');
            return;
          }

          paymentDetailDownload.href = receiptUrl;

          if (isPdf) {
            paymentDetailPdf.src = receiptUrl;
            paymentDetailPdf.classList.remove('d-none');
            showPaymentReceiptDownload(receiptName);
            return;
          }

          if (isImage) {
            paymentDetailImage.dataset.receiptName = receiptName;
            paymentDetailImage.dataset.receiptUrl = receiptUrl;
            paymentDetailImage.src = receiptUrl;
            paymentDetailImage.classList.remove('d-none');
            return;
          }

          showPaymentReceiptFallback('Preview tidak tersedia untuk tipe file ini.', receiptName);
        });
      });

      document.querySelectorAll('.term-payment-update-action').forEach(function (button) {
        button.addEventListener('click', function () {
          const blockedWorkItemId = button.dataset.updateWorkItemId || '';
          const currentServiceDetailId = button.dataset.currentServiceDetailId || '';

          paymentUpdateDetailForm.action = button.dataset.updateAction || '#';
          paymentUpdateDetailSummary.textContent = 'Pembayaran ke-' + (button.dataset.updatePaymentNumber || '-') + ' - ' + (button.dataset.updateAmount || '-');
          paymentUpdateWorkItem.textContent = button.dataset.updateWorkItemName || '-';
          paymentUpdateDetailSearch.value = button.dataset.updateSearchKeyword || '';

          paymentUpdateDetailOptions.forEach(function (option) {
            option.dataset.blocked = option.dataset.value && option.dataset.value === blockedWorkItemId ? '1' : '0';
          });

          const currentOption = paymentUpdateDetailOptions.find(function (option) {
            return option.dataset.value === currentServiceDetailId;
          });

          selectRincianOption(currentOption?.dataset.blocked === '1' ? paymentUpdateDetailOptions[0] : (currentOption || paymentUpdateDetailOptions[0]));

          filterPaymentUpdateOptions();
        });
      });

      paymentUpdateDetailSearch.addEventListener('input', filterPaymentUpdateOptions);

      document.querySelectorAll('.term-payment-delete-action').forEach(function (button) {
        button.addEventListener('click', function () {
          paymentDeleteForm.action = button.dataset.deleteAction || '#';
          paymentDeleteSummary.textContent = 'Hapus pembayaran ke-' + (button.dataset.deletePaymentNumber || '-') + ' sebesar ' + (button.dataset.deleteAmount || '-') + '?';
        });
      });

      paymentDetailPreview.addEventListener('wheel', function (event) {
        if (paymentDetailImage.classList.contains('d-none')) {
          return;
        }

        event.preventDefault();

        setPaymentDetailZoom(paymentDetailZoom + (event.deltaY < 0 ? 0.15 : -0.15), event);
      }, { passive: false });

      paymentDetailPreview.addEventListener('mousedown', function (event) {
        if (paymentDetailZoom <= 1 || paymentDetailImage.classList.contains('d-none')) {
          return;
        }

        event.preventDefault();
        isPaymentDetailDragging = true;
        paymentDetailImage.classList.add('is-dragging');
        paymentDetailDragStartX = event.pageX;
        paymentDetailDragStartY = event.pageY;
        paymentDetailDragScrollLeft = paymentDetailPreview.scrollLeft;
        paymentDetailDragScrollTop = paymentDetailPreview.scrollTop;
      });

      window.addEventListener('mousemove', function (event) {
        if (! isPaymentDetailDragging) {
          return;
        }

        event.preventDefault();
        paymentDetailPreview.scrollLeft = paymentDetailDragScrollLeft - (event.pageX - paymentDetailDragStartX);
        paymentDetailPreview.scrollTop = paymentDetailDragScrollTop - (event.pageY - paymentDetailDragStartY);
      });

      window.addEventListener('mouseup', function () {
        isPaymentDetailDragging = false;
        paymentDetailImage.classList.remove('is-dragging');
      });
    });
  </script>
@endpush
