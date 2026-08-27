@php
  $isIncome = ($mode ?? 'masuk') === 'masuk';
  $transactionType = $isIncome ? 'masuk' : 'keluar';
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
    {{ $isIncome ? 'Input Uang Keluar' : 'Input Uang Masuk' }}
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
              <p class="text-muted mb-0">Project Kemang</p>
            </div>
          </div>

          <form id="transaction-form">
            @csrf
            <input type="hidden" name="jenis_transaksi" id="transaction-type" value="{{ $transactionType }}" />
            <input type="hidden" name="kelompok_pembayaran" value="termin" />

            <div class="mb-4">
              <label class="form-label d-block">Jenis Transaksi</label>
              <div class="transaction-kind">
                <a href="{{ route('uang-masuk.index') }}" class="btn {{ $isIncome ? 'btn-success' : 'btn-light-secondary' }}">
                  <i class="ti ti-arrow-down-left me-1"></i> Uang Masuk
                </a>
                <a href="{{ route('uang-keluar.index') }}" class="btn {{ $isIncome ? 'btn-light-secondary' : 'btn-primary' }}">
                  <i class="ti ti-arrow-up-right me-1"></i> Uang Keluar
                </a>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="main-category" class="form-label">Project / Kategori Utama</label>
                  <select class="form-select" id="main-category" name="kategori_utama" required>
                    <option value="Project Kemang - K9" selected>Project Kemang - K9</option>
                    <option value="Project Kemang - K8">Project Kemang - K8</option>
                    <option value="Project Kemang - C21">Project Kemang - C21</option>
                    <option value="Project Baru">Project Baru</option>
                  </select>
                  <span class="form-helper">Dipakai untuk memisahkan laporan per project atau area kerja.</span>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="category" class="form-label">Kategori</label>
                  <select class="form-select" id="category" name="kategori" required>
                    @if ($isIncome)
                      <option selected>Dana Client</option>
                      <option>DP Project</option>
                      <option>Pelunasan</option>
                      <option>Reimbursement</option>
                      <option>Modal Tambahan</option>
                    @else
                      <option selected>Material</option>
                      <option>Jasa Tukang</option>
                      <option>Transportasi</option>
                      <option>Konsumsi</option>
                      <option>Operasional</option>
                    @endif
                  </select>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="activity-name" class="form-label">Nama Barang / Nama Kegiatan</label>
                  <input type="text" class="form-control" id="activity-name" name="nama" placeholder="{{ $isIncome ? 'Contoh: DP pekerjaan interior' : 'Contoh: Pembelian marmer ruang tamu' }}" required />
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="vendor-name" class="form-label">Nama Vendor</label>
                  <input type="text" class="form-control" id="vendor-name" name="nama_vendor" placeholder="{{ $isIncome ? 'Contoh: Client / owner project' : 'Contoh: Toko material / vendor jasa' }}" />
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="amount" class="form-label">Nominal</label>
                  <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" class="form-control" id="amount" name="nominal" min="0" step="1000" placeholder="0" required />
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="record-date" class="form-label">Tanggal Pencatatan</label>
                  <input type="date" class="form-control" id="record-date" name="tanggal" value="{{ now()->toDateString() }}" required />
                </div>
              </div>
            </div>

            <div class="mb-4">
              <label for="notes" class="form-label">Catatan</label>
              <textarea class="form-control" id="notes" name="catatan" rows="3" placeholder="Tambahan detail transaksi bila diperlukan"></textarea>
            </div>

            <div class="mb-4">
              <label class="form-label d-block">Kelompok Pembayaran</label>
              <div class="termin-panel" id="termin-panel">
                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="receipt-code" class="form-label">Nomor / Nama Kuitansi</label>
                      <input type="text" class="form-control" id="receipt-code" name="kode_kuitansi" placeholder="Contoh: Kuitansi #001" />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="receipt-total" class="form-label">Total Nilai Kuitansi</label>
                      <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" class="form-control" id="receipt-total" name="total_kuitansi" min="0" step="1000" placeholder="0" />
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-0">
                      <label for="payment-number" class="form-label">Pembayaran ke</label>
                      <input type="number" class="form-control" id="payment-number" name="pembayaran_ke" min="1" value="1" />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-0">
                      <label for="payment-total" class="form-label">Total Termin</label>
                      <input type="number" class="form-control" id="payment-total" name="total_termin" min="1" value="3" />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label d-block">Bukti Transaksi</label>
              <label class="receipt-upload" for="receipt-file">
                <span class="avtar avtar-lg bg-light-primary">
                  <i class="ti ti-photo-plus text-primary"></i>
                </span>
                <strong class="d-block">Upload / Capture JPEG</strong>
                <input type="file" id="receipt-file" name="bukti" accept="image/jpeg,image/jpg" capture="environment" />
              </label>
              <div class="receipt-preview" id="receipt-preview">
                <img alt="Preview bukti transaksi" id="receipt-image" />
                <div>
                  <strong id="receipt-name">Belum ada file</strong>
                  <span class="form-helper mb-0" id="receipt-size">-</span>
                </div>
              </div>
            </div>

            <div class="alert alert-primary d-none" id="draft-status"></div>

            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="ti ti-device-floppy me-1"></i> Simpan Draft
              </button>
              <button type="reset" class="btn btn-light-secondary">
                <i class="ti ti-refresh me-1"></i> Reset
              </button>
            </div>
          </form>
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
              {{ $isIncome ? 'Uang Masuk' : 'Uang Keluar' }}
            </span>
          </div>

          <div class="summary-line">
            <span>Project</span>
            <strong id="summary-project">Project Kemang - K9</strong>
          </div>
          <div class="summary-line">
            <span>Nama</span>
            <strong id="summary-name">Belum diisi</strong>
          </div>
          <div class="summary-line">
            <span>Vendor</span>
            <strong id="summary-vendor">-</strong>
          </div>
          <div class="summary-line">
            <span>Kategori</span>
            <strong id="summary-category">{{ $isIncome ? 'Dana Client' : 'Material' }}</strong>
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
      const projectInput = document.querySelector('#main-category');
      const categoryInput = document.querySelector('#category');
      const activityInput = document.querySelector('#activity-name');
      const vendorInput = document.querySelector('#vendor-name');
      const amountInput = document.querySelector('#amount');
      const receiptCodeInput = document.querySelector('#receipt-code');
      const paymentNumberInput = document.querySelector('#payment-number');
      const paymentTotalInput = document.querySelector('#payment-total');
      const receiptFileInput = document.querySelector('#receipt-file');
      const receiptPreview = document.querySelector('#receipt-preview');
      const receiptImage = document.querySelector('#receipt-image');
      const receiptName = document.querySelector('#receipt-name');
      const receiptSize = document.querySelector('#receipt-size');
      const draftStatus = document.querySelector('#draft-status');

      const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
      const rupiahFormatter = new Intl.NumberFormat('id-ID');

      function formatCurrency(value) {
        return 'Rp ' + rupiahFormatter.format(Number(value || 0));
      }

      function selectedDayName() {
        if (!dateInput.value) {
          return '';
        }

        const selectedDate = new Date(dateInput.value + 'T00:00:00');
        return dayNames[selectedDate.getDay()];
      }

      function updateSummary() {
        const receiptLabel = receiptCodeInput.value.trim() || 'Kuitansi';
        const paymentLabel = receiptLabel + ' - ' + (paymentNumberInput.value || 1) + '/' + (paymentTotalInput.value || 1);

        document.querySelector('#summary-project').textContent = projectInput.value;
        document.querySelector('#summary-name').textContent = activityInput.value.trim() || 'Belum diisi';
        document.querySelector('#summary-vendor').textContent = vendorInput.value.trim() || '-';
        document.querySelector('#summary-category').textContent = categoryInput.value;
        document.querySelector('#summary-amount').textContent = formatCurrency(amountInput.value);
        document.querySelector('#summary-date').textContent = dateInput.value ? selectedDayName() + ', ' + dateInput.value : '-';
        document.querySelector('#summary-payment').textContent = paymentLabel;
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
          document.querySelector('#summary-receipt').textContent = 'Belum ada';
          return;
        }

        if (!file.type.includes('jpeg') && !file.name.toLowerCase().endsWith('.jpg')) {
          receiptName.textContent = 'Format harus JPEG';
          receiptSize.textContent = 'Pilih file .jpg atau .jpeg';
          receiptPreview.classList.add('is-visible');
          document.querySelector('#summary-receipt').textContent = 'Format tidak sesuai';
          return;
        }

        receiptName.textContent = file.name;
        receiptSize.textContent = 'Memproses resize...';
        receiptPreview.classList.add('is-visible');

        const compressedBlob = await compressImage(file);
        const previewUrl = URL.createObjectURL(compressedBlob);

        receiptImage.src = previewUrl;
        receiptSize.textContent = 'Asli ' + sizeLabel(file.size) + ' | Resize ' + sizeLabel(compressedBlob.size);
        document.querySelector('#summary-receipt').textContent = 'JPEG ' + sizeLabel(compressedBlob.size);
      }

      [dateInput, projectInput, categoryInput, activityInput, vendorInput, amountInput, receiptCodeInput, paymentNumberInput, paymentTotalInput].forEach(function (input) {
        input.addEventListener('input', function () {
          updateSummary();
        });
      });

      receiptFileInput.addEventListener('change', handleReceiptFile);

      form.addEventListener('reset', function () {
        setTimeout(function () {
          receiptPreview.classList.remove('is-visible');
          draftStatus.classList.add('d-none');
          updateSummary();
        });
      });

      form.addEventListener('submit', function (event) {
        event.preventDefault();
        draftStatus.textContent = 'Draft form sudah siap. Tahap berikutnya baru kita sambungkan ke tabel database dan penyimpanan bukti.';
        draftStatus.classList.remove('d-none');
      });

      updateSummary();
    });
  </script>
@endpush
