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
              <label for="vendor-project-search" class="form-label">Project Holding</label>
              <div class="searchable-select js-searchable-select">
                <input type="text" class="form-control searchable-select-input" id="vendor-project-search" data-role="search-input" placeholder="Cari project holding..." autocomplete="off" />
                <div class="searchable-select-menu" data-role="menu"></div>
                <select class="form-select d-none" id="vendor-project-select" data-role="source">
                  @forelse (($projects ?? collect()) as $project)
                    <option value="{{ $project->id }}" data-active="{{ $activeProject?->id === $project->id ? '1' : '0' }}" @selected($activeProject?->id === $project->id)>{{ $project->name }}</option>
                  @empty
                    <option value="">Project holding belum tersedia</option>
                  @endforelse
                </select>
              </div>
              <span class="form-helper">Vendor tersimpan secara umum, tapi pilih dulu project holding yang lagi dikerjakan.</span>
            </div>
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

  <div class="modal fade" id="project-activate-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="{{ route('dashboard.active-project') }}">
          @csrf
          <input type="hidden" name="project_id" id="project-activate-id" value="" />
          <input type="hidden" name="redirect_to" value="{{ route('vendor.index', [], false) }}" />
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

      function enhanceSearchableSelect(wrapper) {
        const select = wrapper.querySelector('[data-role="source"]');
        const input = wrapper.querySelector('[data-role="search-input"]');
        const menu = wrapper.querySelector('[data-role="menu"]');

        function options() {
          return Array.from(select.options).filter(function (option) {
            return option.value !== '';
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
            return option.textContent.toLowerCase().includes(query);
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

        select.addEventListener('change', syncInputFromSelect);

        syncInputFromSelect();

        return { sync: syncInputFromSelect };
      }

      const vendorProjectSelect = document.querySelector('#vendor-project-select');
      const vendorProjectSearchableSelect = enhanceSearchableSelect(vendorProjectSelect.closest('.js-searchable-select'));
      const projectActivateModalEl = document.querySelector('#project-activate-modal');

      if (projectActivateModalEl) {
        const projectActivateModal = new bootstrap.Modal(projectActivateModalEl);
        const projectActivateIdInput = document.querySelector('#project-activate-id');
        const projectActivateNameEl = document.querySelector('#project-activate-name');
        const activeProjectId = '{{ $activeProject?->id }}';
        let lastConfirmedProjectId = vendorProjectSelect.value;
        let projectActivateConfirmed = false;

        vendorProjectSelect.addEventListener('change', function () {
          if (!activeProjectId || vendorProjectSelect.value === activeProjectId) {
            lastConfirmedProjectId = vendorProjectSelect.value;
            return;
          }

          const option = vendorProjectSelect.selectedOptions[0];
          projectActivateIdInput.value = vendorProjectSelect.value;
          projectActivateNameEl.textContent = option ? option.textContent.trim() : '';
          projectActivateConfirmed = false;
          projectActivateModal.show();
        });

        projectActivateModalEl.querySelector('form').addEventListener('submit', function () {
          projectActivateConfirmed = true;
        });

        projectActivateModalEl.addEventListener('hidden.bs.modal', function () {
          if (!projectActivateConfirmed) {
            vendorProjectSelect.value = lastConfirmedProjectId;
            vendorProjectSearchableSelect.sync();
          }
        });
      }

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
