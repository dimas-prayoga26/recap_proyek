<nav class="pc-sidebar">
  <div class="navbar-wrapper">
    <div class="m-header">
      <a href="{{ route('dashboard') }}" class="b-brand text-primary">
        <span class="app-brand-mark"><i class="ti ti-report-money f-24"></i></span>
        <span class="app-brand-text">
          Pencatatan
          <span class="app-brand-subtitle">Keuangan Proyek</span>
        </span>
      </a>
    </div>
    <div class="navbar-content">
      <ul class="pc-navbar">
        <li class="pc-item pc-caption">
          <label>Dashboard</label>
          <i class="ti ti-dashboard"></i>
        </li>
        <li class="pc-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
          <a href="{{ route('dashboard') }}" class="pc-link">
            <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
            <span class="pc-mtext">Ringkasan</span>
          </a>
        </li>

        <li class="pc-item pc-caption">
          <label>Master Data</label>
          <i class="ti ti-database"></i>
        </li>
        <li class="pc-item {{ request()->routeIs('project.*') ? 'active' : '' }}">
          <a href="{{ route('project.index') }}" class="pc-link">
            <span class="pc-micon"><i class="ti ti-folders"></i></span>
            <span class="pc-mtext">Project Holding</span>
          </a>
        </li>
        <li class="pc-item {{ request()->routeIs('kategori-pekerjaan.*') ? 'active' : '' }}">
          <a href="{{ route('kategori-pekerjaan.index') }}" class="pc-link">
            <span class="pc-micon"><i class="ti ti-businessplan"></i></span>
            <span class="pc-mtext">Kategori Pekerjaan</span>
          </a>
        </li>

        <li class="pc-item pc-caption">
          <label>Transaksi</label>
          <i class="ti ti-arrows-exchange"></i>
        </li>
        <li class="pc-item {{ request()->routeIs('uang-masuk.*') ? 'active' : '' }}">
          <a href="{{ route('uang-masuk.index') }}" class="pc-link">
            <span class="pc-micon"><i class="ti ti-wallet"></i></span>
            <span class="pc-mtext">Credit</span>
          </a>
        </li>
        <li class="pc-item {{ request()->routeIs('uang-keluar.*') ? 'active' : '' }}">
          <a href="{{ route('uang-keluar.index') }}" class="pc-link">
            <span class="pc-micon"><i class="ti ti-receipt-2"></i></span>
            <span class="pc-mtext">Debit</span>
          </a>
        </li>
        <li class="pc-item {{ request()->routeIs('termin-pembayaran.*') ? 'active' : '' }}">
          <a href="{{ route('termin-pembayaran.index') }}" class="pc-link">
            <span class="pc-micon"><i class="ti ti-list-check"></i></span>
            <span class="pc-mtext">Termin Pembayaran</span>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
