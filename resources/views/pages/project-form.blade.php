@extends('layouts.app')

@section('title', $title)
@section('page_title', $title)

@push('styles')
  <style>
    .form-helper {
      color: #697586;
      display: block;
      font-size: 12px;
      margin-top: 6px;
    }

    .project-form-title {
      align-items: center;
      border-bottom: 1px solid #eef2f6;
      display: flex;
      gap: 12px;
      margin-bottom: 22px;
      padding-bottom: 16px;
    }

    .project-list-table th {
      background: #f8fafc;
      border-bottom: 0;
      color: #697586;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .project-list-table td {
      vertical-align: middle;
    }

    .project-name-cell {
      color: #202939;
      display: block;
      font-weight: 600;
    }

    .project-slug-cell {
      color: #697586;
      display: block;
      font-size: 12px;
      margin-top: 2px;
    }
  </style>
@endpush

@section('content')
  <div class="row">
    <div class="col-xl-5">
      <div class="card">
        <div class="card-body">
          <div class="project-form-title">
            <div class="avtar avtar-lg bg-light-primary">
              <i class="ti ti-folder-plus text-primary"></i>
            </div>
            <div>
              <h4 class="mb-1">Tambah Project Baru</h4>
              <p class="text-muted mb-0">Project baru otomatis jadi project aktif setelah disimpan.</p>
            </div>
          </div>

          @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
          @endif

          @if ($errors->any())
            <div class="alert alert-danger">Data belum lengkap. Cek lagi nama project.</div>
          @endif

          <form method="POST" action="{{ route('project.store') }}">
            @csrf
            <div class="mb-3">
              <label for="project-name" class="form-label">Nama Project</label>
              <input type="text" class="form-control @error('name') is-invalid @enderror" id="project-name" name="name" value="{{ old('name') }}" placeholder="Contoh: Project Menteng" required />
              @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <span class="form-helper">Area kerja default "Lainnya" akan otomatis dibuat untuk project ini.</span>
            </div>
            <div class="mb-4">
              <label for="project-description" class="form-label">Deskripsi (opsional)</label>
              <textarea class="form-control" id="project-description" name="description" rows="3" placeholder="Catatan singkat tentang project ini">{{ old('description') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">
              <i class="ti ti-device-floppy me-1"></i> Simpan &amp; Jadikan Aktif
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-xl-7">
      <div class="card">
        <div class="card-header">
          <h4 class="mb-0">Daftar Project</h4>
        </div>
        <div class="card-body pt-0">
          <div class="table-responsive">
            <table class="table table-hover project-list-table mb-0">
              <thead>
                <tr>
                  <th>Project</th>
                  <th>Status</th>
                  <th class="text-end">Area</th>
                  <th class="text-end">Pekerjaan</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @forelse ($projects as $project)
                  <tr>
                    <td>
                      <span class="project-name-cell">{{ $project->name }}</span>
                      <span class="project-slug-cell">{{ $project->slug }}</span>
                    </td>
                    <td>
                      <span class="badge {{ $project->status === 'active' ? 'bg-light-success text-success' : 'bg-light-secondary text-secondary' }}">
                        {{ ucfirst($project->status) }}
                      </span>
                    </td>
                    <td class="text-end">{{ $project->areas_count }}</td>
                    <td class="text-end">{{ $project->work_items_count }}</td>
                    <td class="text-end">
                      @if ($activeProject?->is($project))
                        <span class="badge bg-light-primary text-primary">Project Aktif</span>
                      @else
                        <form method="POST" action="{{ route('dashboard.active-project') }}">
                          @csrf
                          <input type="hidden" name="project_id" value="{{ $project->id }}" />
                          <button type="submit" class="btn btn-sm btn-light-secondary">Jadikan Aktif</button>
                        </form>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">Belum ada project.</td>
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
