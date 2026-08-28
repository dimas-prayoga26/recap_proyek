<header class="pc-header">
  <div class="header-wrapper">
    <div class="me-auto pc-mob-drp">
      <ul class="list-unstyled">
        <li class="pc-h-item header-mobile-collapse">
          <a href="#" class="pc-head-link head-link-secondary ms-0" id="sidebar-hide">
            <i class="ti ti-menu-2"></i>
          </a>
        </li>
        <li class="pc-h-item pc-sidebar-popup">
          <a href="#" class="pc-head-link head-link-secondary ms-0" id="mobile-collapse">
            <i class="ti ti-menu-2"></i>
          </a>
        </li>
        <li class="pc-h-item d-none d-md-inline-flex">
          <form class="header-search">
            <i data-feather="search" class="icon-search"></i>
            <input type="search" class="form-control" placeholder="Cari transaksi, project, kategori..." />
            <button class="btn btn-light-secondary btn-search" type="button"><i class="ti ti-adjustments-horizontal"></i></button>
          </form>
        </li>
      </ul>
    </div>
    <div class="ms-auto">
      <ul class="list-unstyled">
        <li class="dropdown pc-h-item">
          <a class="pc-head-link head-link-secondary dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button">
            <i class="ti ti-bell"></i>
          </a>
          <div class="dropdown-menu dropdown-notification dropdown-menu-end pc-h-dropdown">
            <div class="dropdown-header">
              <h5>Notifikasi <span class="badge bg-warning rounded-pill ms-1">3</span></h5>
            </div>
            <div class="dropdown-header px-0 text-wrap header-notification-scroll position-relative" style="max-height: calc(100vh - 215px)">
              <div class="list-group list-group-flush w-100">
                <a href="{{ route('termin-pembayaran.index') }}" class="list-group-item list-group-item-action">
                  <div class="d-flex">
                    <div class="flex-shrink-0">
                      <div class="user-avtar bg-light-warning"><i class="ti ti-alert-circle"></i></div>
                    </div>
                    <div class="flex-grow-1 ms-2">
                      <h5 class="mb-1">Pembayaran Perlu Dicek</h5>
                      <p class="text-body fs-6 mb-0">Ada pembayaran yang perlu ditindaklanjuti.</p>
                    </div>
                  </div>
                </a>
              </div>
            </div>
          </div>
        </li>
        <li class="dropdown pc-h-item header-user-profile">
          <a class="pc-head-link head-link-primary dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button">
            <img src="{{ asset('assets/berry/images/user/avatar-2.jpg') }}" alt="user-image" class="user-avtar" />
            <span><i class="ti ti-settings"></i></span>
          </a>
          <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
            <div class="dropdown-header">
              <h4>Halo, <span class="small text-muted">Admin</span></h4>
              <p class="text-muted">Project Finance</p>
              <hr />
              <a href="{{ route('pengaturan.index') }}" class="dropdown-item">
                <i class="ti ti-settings"></i>
                <span>Pengaturan Akun</span>
              </a>
              <a href="#" class="dropdown-item">
                <i class="ti ti-logout"></i>
                <span>Logout</span>
              </a>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</header>
