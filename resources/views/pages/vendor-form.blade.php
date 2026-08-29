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

    .vendor-list-table th {
      background: #f8fafc;
      border-bottom: 0;
      color: #697586;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .vendor-list-table td {
      vertical-align: middle;
    }

    .vendor-name-cell {
      color: #202939;
      display: block;
      font-weight: 600;
    }

    .vendor-contact-cell {
      color: #697586;
      display: block;
      font-size: 12px;
      margin-top: 2px;
    }

    .vendor-filter-grid {
      align-items: end;
      display: grid;
      gap: 12px;
      grid-template-columns: minmax(220px, 1fr) auto;
    }

    .vendor-table-footer {
      align-items: center;
      border-top: 1px solid #eef2f6;
      display: flex;
      gap: 12px;
      justify-content: space-between;
      padding-top: 16px;
    }

    @media (max-width: 575.98px) {
      .vendor-filter-grid {
        grid-template-columns: 1fr;
      }

      .vendor-filter-grid .btn {
        width: 100%;
      }

      .vendor-table-footer {
        align-items: stretch;
        flex-direction: column;
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
              <small class="text-muted">{{ $activeProject?->name ?? 'Belum ada project holding aktif' }}</small>
              <h4 class="mb-0">Daftar Vendor</h4>
            </div>
            <div class="col-auto d-flex align-items-center gap-2">
              <span class="badge bg-light-primary text-primary">{{ $vendors->total() }} vendor</span>
              <a href="{{ route('vendor.export', $filters) }}" class="btn btn-light-primary btn-sm">
                <i class="ti ti-download me-1"></i> Export Vendor
              </a>
              <button type="button" class="btn btn-light-warning btn-sm" data-bs-toggle="modal" data-bs-target="#vendor-import-modal">
                <i class="ti ti-upload me-1"></i> Import Vendor
              </button>
              <button type="button" class="btn btn-primary btn-sm" id="vendor-add-toggle" data-bs-toggle="modal" data-bs-target="#vendor-add-modal">
                <i class="ti ti-plus me-1"></i> Tambah Vendor
              </button>
            </div>
          </div>
        </div>
        <div class="card-body pt-0">
          @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
          @endif

          <form method="GET" action="{{ route('vendor.index') }}" class="vendor-filter-grid mb-3">
            <div>
              <label for="filter-search" class="form-label">Cari</label>
              <input
                type="search"
                class="form-control"
                id="filter-search"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Nama vendor, kontak, atau telepon"
              />
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="ti ti-filter me-1"></i> Cari
              </button>
              <a href="{{ route('vendor.index') }}" class="btn btn-light-secondary">
                Reset
              </a>
            </div>
          </form>

          <div class="table-responsive">
            <table class="table table-hover vendor-list-table mb-0">
              <thead>
                <tr>
                  <th>Vendor</th>
                  <th>Telepon</th>
                  <th class="text-end">Pekerjaan</th>
                  <th class="text-end">Penawaran</th>
                  <th class="text-end">Aksi</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($vendors as $vendor)
                  <tr>
                    <td>
                      <span class="vendor-name-cell">{{ $vendor->name }}</span>
                      @if ($vendor->contact_name)
                        <span class="vendor-contact-cell">{{ $vendor->contact_name }}</span>
                      @endif
                    </td>
                    <td>{{ $vendor->phone ?: '-' }}</td>
                    <td class="text-end">{{ $vendor->work_items_count }}</td>
                    <td class="text-end">{{ $vendor->offers_count }}</td>
                    <td class="text-end">
                      <div class="d-flex justify-content-end gap-2">
                        <button
                          type="button"
                          class="btn btn-sm btn-light-secondary vendor-edit-btn"
                          data-id="{{ $vendor->id }}"
                          data-name="{{ $vendor->name }}"
                          data-contact-name="{{ $vendor->contact_name }}"
                          data-phone="{{ $vendor->phone }}"
                          data-notes="{{ $vendor->notes }}"
                        >
                          <i class="ti ti-edit me-1"></i> Edit
                        </button>
                        <form method="POST" action="{{ route('vendor.destroy', $vendor) }}" onsubmit="return confirm('Hapus vendor ini? Referensi vendor di data terkait akan dikosongkan.');">
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="btn btn-sm btn-light-danger">
                            <i class="ti ti-trash me-1"></i> Delete
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">Vendor tidak ditemukan.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          @if ($vendors->hasPages())
            <div class="vendor-table-footer">
              <small class="text-muted">
                Menampilkan {{ $vendors->firstItem() }}-{{ $vendors->lastItem() }} dari {{ $vendors->total() }} vendor.
              </small>
              {{ $vendors->links() }}
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="vendor-add-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="{{ route('vendor.store') }}">
          @csrf
          <input type="hidden" name="form_context" value="vendor_store" />
          <div class="modal-header">
            <h5 class="modal-title">Tambah Vendor Baru</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="vendor-name" class="form-label">Nama Vendor</label>
              <input type="text" class="form-control @error('name') is-invalid @enderror" id="vendor-name" name="name" value="{{ old('name') }}" placeholder="Contoh: Dedi Besi" required />
              @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="mb-3">
              <label for="vendor-contact" class="form-label">Nama Kontak</label>
              <input type="text" class="form-control @error('contact_name') is-invalid @enderror" id="vendor-contact" name="contact_name" value="{{ old('contact_name') }}" placeholder="Contoh: Pak Dedi" />
              @error('contact_name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="mb-3">
              <label for="vendor-phone" class="form-label">No. Telepon</label>
              <input type="text" class="form-control @error('phone') is-invalid @enderror" id="vendor-phone" name="phone" value="{{ old('phone') }}" placeholder="Contoh: 0812xxxxxxx" />
              @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="mb-0">
              <label for="vendor-notes" class="form-label">Catatan</label>
              <textarea class="form-control @error('notes') is-invalid @enderror" id="vendor-notes" name="notes" rows="3" placeholder="Catatan tambahan mengenai vendor ini">{{ old('notes') }}</textarea>
              @error('notes')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">
              <i class="ti ti-device-floppy me-1"></i> Simpan Vendor
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="vendor-edit-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" id="vendor-edit-form" data-update-url-template="{{ route('vendor.update', ['vendor' => '__ID__']) }}">
          @csrf
          @method('PUT')
          <input type="hidden" name="form_context" value="vendor_update" />
          <input type="hidden" name="editing_vendor_id" id="vendor-edit-id" value="{{ old('editing_vendor_id') }}" />
          <div class="modal-header">
            <h5 class="modal-title">Edit Vendor</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="vendor-edit-name" class="form-label">Nama Vendor</label>
              <input type="text" class="form-control @error('name') is-invalid @enderror" id="vendor-edit-name" name="name" value="{{ old('form_context') === 'vendor_update' ? old('name') : '' }}" placeholder="Contoh: Dedi Besi" required />
              @if (old('form_context') === 'vendor_update')
                @error('name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              @endif
            </div>
            <div class="mb-3">
              <label for="vendor-edit-contact" class="form-label">Nama Kontak</label>
              <input type="text" class="form-control @if (old('form_context') === 'vendor_update') @error('contact_name') is-invalid @enderror @endif" id="vendor-edit-contact" name="contact_name" value="{{ old('form_context') === 'vendor_update' ? old('contact_name') : '' }}" placeholder="Contoh: Pak Dedi" />
              @if (old('form_context') === 'vendor_update')
                @error('contact_name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              @endif
            </div>
            <div class="mb-3">
              <label for="vendor-edit-phone" class="form-label">No. Telepon</label>
              <input type="text" class="form-control @if (old('form_context') === 'vendor_update') @error('phone') is-invalid @enderror @endif" id="vendor-edit-phone" name="phone" value="{{ old('form_context') === 'vendor_update' ? old('phone') : '' }}" placeholder="Contoh: 0812xxxxxxx" />
              @if (old('form_context') === 'vendor_update')
                @error('phone')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              @endif
            </div>
            <div class="mb-0">
              <label for="vendor-edit-notes" class="form-label">Catatan</label>
              <textarea class="form-control @if (old('form_context') === 'vendor_update') @error('notes') is-invalid @enderror @endif" id="vendor-edit-notes" name="notes" rows="3" placeholder="Catatan tambahan mengenai vendor ini">{{ old('form_context') === 'vendor_update' ? old('notes') : '' }}</textarea>
              @if (old('form_context') === 'vendor_update')
                @error('notes')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              @endif
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">
              <i class="ti ti-device-floppy me-1"></i> Update Vendor
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="vendor-import-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="{{ route('vendor.import') }}" enctype="multipart/form-data">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Import Vendor</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted">
              Upload file CSV berisi daftar vendor supaya tidak perlu input satu-satu, cocok dipakai saat mulai project holding baru.
              Kolomnya harus sama seperti hasil <a href="{{ route('vendor.export', $filters) }}">Export Vendor</a>: <strong>Nama Vendor, Nama Kontak, No. Telepon, Catatan</strong>.
              Vendor yang namanya sudah ada otomatis dilewati.
            </p>
            <div class="mb-0">
              <label for="vendor-import-file" class="form-label">File CSV</label>
              <input type="file" class="form-control @error('file') is-invalid @enderror" id="vendor-import-file" name="file" accept=".csv,text/csv" required />
              @error('file')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">
              <i class="ti ti-upload me-1"></i> Import
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
      const editForm = document.querySelector('#vendor-edit-form');
      const editModalEl = document.querySelector('#vendor-edit-modal');
      const editModal = new bootstrap.Modal(editModalEl);
      const editId = document.querySelector('#vendor-edit-id');
      const editName = document.querySelector('#vendor-edit-name');
      const editContact = document.querySelector('#vendor-edit-contact');
      const editPhone = document.querySelector('#vendor-edit-phone');
      const editNotes = document.querySelector('#vendor-edit-notes');

      function setEditForm(data) {
        editId.value = data.id || '';
        editName.value = data.name || '';
        editContact.value = data.contactName || '';
        editPhone.value = data.phone || '';
        editNotes.value = data.notes || '';
        editForm.action = editForm.dataset.updateUrlTemplate.replace('__ID__', data.id || '');
      }

      document.querySelectorAll('.vendor-edit-btn').forEach(function (button) {
        button.addEventListener('click', function () {
          setEditForm(button.dataset);
          editModal.show();
        });
      });

      @if ($errors->has('file'))
        new bootstrap.Modal(document.querySelector('#vendor-import-modal')).show();
      @elseif (old('form_context') === 'vendor_update')
        setEditForm({
          id: @json(old('editing_vendor_id')),
          name: @json(old('name')),
          contactName: @json(old('contact_name')),
          phone: @json(old('phone')),
          notes: @json(old('notes')),
        });
        editModal.show();
      @elseif ($errors->any())
        new bootstrap.Modal(document.querySelector('#vendor-add-modal')).show();
      @endif
    });
  </script>
@endpush
