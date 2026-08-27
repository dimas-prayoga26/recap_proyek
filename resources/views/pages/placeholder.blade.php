@extends('layouts.app')

@section('title', $title)
@section('page_title', $title)

@section('content')
  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-start">
            <div class="avtar avtar-lg bg-light-primary">
              <i class="{{ $icon }} text-primary"></i>
            </div>
            <div class="ms-3">
              <h4 class="mb-2">{{ $title }}</h4>
              <p class="text-muted mb-4">{{ $description }}</p>
              <div class="alert alert-primary mb-0">
                Layout Berry sudah aktif. Bagian ini siap dilanjutkan menjadi form, tabel data, filter, dan aksi sesuai kebutuhan modul.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
