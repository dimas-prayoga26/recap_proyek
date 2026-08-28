@php
  $formatRupiah = fn (int $value) => 'Rp '.number_format($value, 0, ',', '.');
  $selectedAreaCode = $filters['area'] ?? 'K9';
@endphp

@extends('layouts.app')

@section('title', $title)
@section('page_title', $title)

@section('page_actions')
  <a href="{{ route('kategori-pekerjaan.index', ['area' => $selectedAreaCode]) }}" class="btn btn-light-secondary">
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
      grid-template-columns: minmax(120px, 0.5fr) minmax(130px, 0.6fr) auto;
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

    .term-work-meta {
      color: #697586;
      display: block;
      font-size: 12px;
      margin-top: 3px;
    }

    .term-amount-cell {
      font-weight: 700;
      text-align: right;
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
              <small class="text-muted">{{ $activeProject?->name ?? 'Belum ada project' }} · {{ $selectedAreaCode }}</small>
              <h4 class="mb-0">Rekap Termin Pembayaran</h4>
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
              <label for="term-area" class="form-label">Area</label>
              <select class="form-select" id="term-area" name="area">
                @foreach ($areas as $area)
                  <option value="{{ $area->code }}" @selected($selectedAreaCode === $area->code)>{{ $area->code }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label for="term-count" class="form-label">Total Termin Rencana</label>
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
                  <th class="text-end">Penawaran</th>
                  <th>Total Fix Termin</th>
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
                      <span class="term-work-meta">{{ $row['work_item']->brand ?: $row['work_item']->vendor?->name ?? '-' }}</span>
                    </td>
                    <td class="term-amount-cell">{{ $formatRupiah($row['summary']['offer']) }}</td>
                    <td>{{ $row['summary']['total_terms'] }}x</td>
                    @for ($number = 1; $number <= $maxTermsColumn; $number++)
                      <td class="term-amount-cell">
                        @if ($row['payments']->has($number))
                          {{ $formatRupiah($row['payments'][$number]->amount) }}
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
                    <td colspan="{{ 5 + $maxTermsColumn }}" class="text-center text-muted py-4">Belum ada pekerjaan di area ini.</td>
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
