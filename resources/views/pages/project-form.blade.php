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
              <h4 class="mb-1">Tambah Project Holding Baru</h4>
              <p class="text-muted mb-0">Project holding baru otomatis jadi project holding aktif setelah disimpan.</p>
            </div>
          </div>

          @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
          @endif

          @if ($errors->any())
            <div class="alert alert-danger">Data belum lengkap. Cek lagi nama project holding.</div>
          @endif

          <form method="POST" action="{{ route('project.store') }}">
            @csrf
            <input type="hidden" name="form_context" value="project_store" />
            <div class="mb-3">
              <label for="project-name" class="form-label">Nama Project Holding</label>
              <input type="text" class="form-control @error('name') is-invalid @enderror" id="project-name" name="name" value="{{ old('name') }}" placeholder="Contoh: Project Holding Menteng" required />
              @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <span class="form-helper">Area kerja default "Lainnya" akan otomatis dibuat untuk project holding ini.</span>
            </div>
            <div class="mb-4">
              <label for="project-description" class="form-label">Alamat</label>
              <textarea class="form-control" id="project-description" name="description" rows="3" placeholder="Alamat project holding">{{ old('description') }}</textarea>
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
          <h4 class="mb-0">Daftar Project Holding</h4>
        </div>
        <div class="card-body pt-0">
          <div class="table-responsive">
            <table class="table table-hover project-list-table mb-0">
              <thead>
                <tr>
                  <th>Project Holding</th>
                  <th>Status</th>
                  <th class="text-end">Vendor</th>
                  <th class="text-end">Pekerjaan</th>
                  <th>Is Active</th>
                  <th class="text-end">Aksi</th>
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
                    <td class="text-end">{{ $project->vendors_count }}</td>
                    <td class="text-end">{{ $project->work_items_count }}</td>
                    <td>
                      @if ($activeProject?->is($project))
                        <span class="badge bg-light-primary text-primary">Yes</span>
                      @else
                        <span class="badge bg-light-secondary text-secondary">No</span>
                      @endif
                    </td>
                    <td class="text-end">
                      <div class="d-flex justify-content-end gap-2">
                        @unless ($activeProject?->is($project))
                        <form method="POST" action="{{ route('dashboard.active-project') }}">
                          @csrf
                          <input type="hidden" name="project_id" value="{{ $project->id }}" />
                          <button type="submit" class="btn btn-sm btn-light-secondary">Jadikan Aktif</button>
                        </form>
                        @endunless
                        <button
                          type="button"
                          class="btn btn-sm btn-light-primary project-edit-btn"
                          data-id="{{ $project->id }}"
                          data-name="{{ $project->name }}"
                          data-description="{{ $project->description }}"
                        >
                          <i class="ti ti-edit me-1"></i> Edit
                        </button>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">Belum ada project holding.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="project-edit-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" id="project-edit-form" data-update-url-template="{{ route('project.update', ['project' => '__ID__']) }}">
          @csrf
          @method('PUT')
          <input type="hidden" name="form_context" value="project_update" />
          <input type="hidden" name="editing_project_id" id="project-edit-id" value="{{ old('editing_project_id') }}" />
          <div class="modal-header">
            <h5 class="modal-title">Edit Project Holding</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="project-edit-name" class="form-label">Nama Project Holding</label>
              <input type="text" class="form-control @if (old('form_context') === 'project_update') @error('name') is-invalid @enderror @endif" id="project-edit-name" name="name" value="{{ old('form_context') === 'project_update' ? old('name') : '' }}" placeholder="Contoh: Project Holding Menteng" required />
              @if (old('form_context') === 'project_update')
                @error('name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              @endif
            </div>
            <div class="mb-0">
              <label for="project-edit-description" class="form-label">Alamat</label>
              <textarea class="form-control @if (old('form_context') === 'project_update') @error('description') is-invalid @enderror @endif" id="project-edit-description" name="description" rows="3" placeholder="Alamat project holding">{{ old('form_context') === 'project_update' ? old('description') : '' }}</textarea>
              @if (old('form_context') === 'project_update')
                @error('description')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              @endif
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">
              <i class="ti ti-device-floppy me-1"></i> Update Project Holding
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const editForm = document.querySelector('#project-edit-form');
      const editModal = new bootstrap.Modal(document.querySelector('#project-edit-modal'));
      const editId = document.querySelector('#project-edit-id');
      const editName = document.querySelector('#project-edit-name');
      const editDescription = document.querySelector('#project-edit-description');

      function setEditForm(data) {
        editId.value = data.id || '';
        editName.value = data.name || '';
        editDescription.value = data.description || '';
        editForm.action = editForm.dataset.updateUrlTemplate.replace('__ID__', data.id || '');
      }

      document.querySelectorAll('.project-edit-btn').forEach(function (button) {
        button.addEventListener('click', function () {
          setEditForm(button.dataset);
          editModal.show();
        });
      });

      @if (old('form_context') === 'project_update')
        setEditForm({
          id: @json(old('editing_project_id')),
          name: @json(old('name')),
          description: @json(old('description')),
        });
        editModal.show();
      @endif
    });
  </script>
@endpush
