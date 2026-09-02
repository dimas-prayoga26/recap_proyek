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

    .term-payment-button {
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

    .term-payment-button:hover {
      background: #dff0ff;
      color: #2196f3;
      text-decoration: none;
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
                        @php($payment = $row['payments']->get($number))
                        @if ($payment)
                          <div class="term-payment-action">
                            <span>{{ $formatRupiah($payment['amount']) }}</span>
                            <button
                              type="button"
                              class="term-payment-button"
                              data-bs-toggle="modal"
                              data-bs-target="#payment-detail-modal"
                              data-payment-number="{{ $payment['detail']['payment_number'] }}"
                              data-amount="{{ $formatRupiah($payment['detail']['amount']) }}"
                              data-notes="{{ $payment['detail']['notes'] }}"
                              data-receipt-url="{{ $payment['detail']['receipt_url'] }}"
                              data-receipt-mime="{{ $payment['detail']['receipt_mime'] }}"
                              data-receipt-name="{{ $payment['detail']['receipt_name'] }}"
                              aria-label="Lihat detail pembayaran ke-{{ $payment['detail']['payment_number'] }}"
                              title="Lihat detail"
                            >
                              <i class="ti ti-eye"></i>
                            </button>
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
          <div class="py-3">
            <span class="d-block text-muted fs-6 mb-2">Bukti Pembayaran</span>
            <div id="payment-detail-empty" class="alert alert-light-secondary mb-0">Bukti belum ada.</div>
            <img src="" id="payment-detail-image" class="img-fluid rounded d-none" alt="Bukti pembayaran" />
            <iframe src="" id="payment-detail-pdf" class="w-100 border rounded d-none" style="height: 60vh;" title="Bukti pembayaran PDF"></iframe>
            <a href="#" id="payment-detail-download" class="btn btn-light-primary mt-3 d-none" target="_blank" rel="noopener">
              Buka PDF
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
      const paymentDetailNotes = document.querySelector('#payment-detail-notes');
      const paymentDetailEmpty = document.querySelector('#payment-detail-empty');
      const paymentDetailImage = document.querySelector('#payment-detail-image');
      const paymentDetailPdf = document.querySelector('#payment-detail-pdf');
      const paymentDetailDownload = document.querySelector('#payment-detail-download');

      document.querySelectorAll('.term-payment-button').forEach(function (button) {
        button.addEventListener('click', function () {
          const receiptUrl = button.dataset.receiptUrl || '';
          const isPdf = button.dataset.receiptMime === 'application/pdf';

          paymentDetailTitle.textContent = 'Pembayaran ke-' + (button.dataset.paymentNumber || '-');
          paymentDetailAmount.textContent = button.dataset.amount || '-';
          paymentDetailNotes.textContent = button.dataset.notes || '-';

          paymentDetailEmpty.classList.toggle('d-none', receiptUrl !== '');
          paymentDetailImage.classList.toggle('d-none', !receiptUrl || isPdf);
          paymentDetailPdf.classList.toggle('d-none', !receiptUrl || !isPdf);
          paymentDetailDownload.classList.toggle('d-none', !receiptUrl || !isPdf);

          paymentDetailImage.src = receiptUrl && !isPdf ? receiptUrl : '';
          paymentDetailPdf.src = receiptUrl && isPdf ? receiptUrl : '';
          paymentDetailDownload.href = receiptUrl || '#';
          paymentDetailDownload.textContent = button.dataset.receiptName ? 'Buka ' + button.dataset.receiptName : 'Buka PDF';
        });
      });
    });
  </script>
@endpush
