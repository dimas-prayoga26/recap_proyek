<!doctype html>
<html lang="id">
  <head>
    <title>@yield('title', 'Dashboard') | Pencatatan Proyek</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Sistem pencatatan credit dan debit untuk project." />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <link rel="icon" href="{{ asset('assets/berry/images/favicon.svg') }}" type="image/x-icon" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" id="main-font-link" />
    <link rel="stylesheet" href="{{ asset('assets/berry/fonts/phosphor/duotone/style.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/berry/fonts/tabler-icons.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/berry/fonts/feather.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/berry/fonts/fontawesome.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/berry/fonts/material.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/berry/css/style.css') }}" id="main-style-link" />
    <link rel="stylesheet" href="{{ asset('assets/berry/css/style-preset.css') }}" />
    <style>
      .pc-sidebar .m-header .b-brand {
        gap: 10px;
      }

      .app-brand-mark {
        align-items: center;
        background: #ede7f6;
        border-radius: 8px;
        display: inline-flex;
        height: 42px;
        justify-content: center;
        width: 42px;
      }

      .app-brand-text {
        color: #202939;
        font-size: 16px;
        font-weight: 700;
        line-height: 1.1;
      }

      .app-brand-subtitle {
        color: #697586;
        display: block;
        font-size: 11px;
        font-weight: 500;
        margin-top: 2px;
      }

      .finance-stat {
        height: 186px;
        min-height: 0;
      }

      .project-balance-card .card-body {
        display: flex;
        flex-direction: column;
      }

      .balance-usd-chip {
        align-items: center;
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-radius: 8px;
        color: #fff;
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-height: 40px;
        min-width: 72px;
        padding: 6px 12px;
        text-align: center;
      }

      .balance-usd-chip span {
        color: rgba(255, 255, 255, 0.78);
        display: block;
        font-size: 10px;
        font-weight: 600;
        line-height: 1;
      }

      .balance-usd-chip strong {
        display: block;
        font-size: 16px;
        font-weight: 700;
        line-height: 1.1;
      }

      .dashboard-equal-card {
        height: 552px;
        margin-bottom: 0;
      }

      .dashboard-section-gap {
        margin-bottom: 24px;
      }

      .page-header .page-block {
        width: 100%;
      }

      .page-actions {
        align-items: center;
      }

      .project-switcher {
        width: 252px;
      }

      .project-switcher-toggle {
        align-items: center;
        background: #fff;
        border: 1px solid #cdd5df;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
        color: #202939;
        display: inline-flex;
        justify-content: flex-start;
        height: 50px;
        padding: 7px 14px 7px 10px;
        text-align: left;
        width: 100%;
      }

      .project-switcher-toggle:hover,
      .project-switcher-toggle:focus,
      .project-switcher-toggle.show {
        background: #fff;
        border-color: #2196f3;
        box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.12);
        color: #202939;
      }

      .project-switcher-toggle::after {
        margin-left: auto;
      }

      .project-switcher-icon {
        align-items: center;
        background: #e3f2fd;
        border-radius: 8px;
        color: #2196f3;
        display: inline-flex;
        flex: 0 0 auto;
        height: 34px;
        justify-content: center;
        margin-right: 10px;
        width: 34px;
      }

      .project-switcher-label {
        color: #697586;
        display: block;
        font-size: 11px;
        font-weight: 500;
        line-height: 1.1;
      }

      .project-switcher-name {
        display: block;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.25;
        margin-top: 2px;
      }

      .project-switcher .dropdown-menu {
        border-color: #e3e8ef;
        border-radius: 8px;
        box-shadow: 0 12px 24px rgba(16, 24, 40, 0.12);
        min-width: 252px;
        padding: 8px;
      }

      .project-switcher .dropdown-item {
        align-items: center;
        border-radius: 6px;
        display: flex;
        gap: 10px;
        padding: 10px;
      }

      .project-switcher .dropdown-item.active,
      .project-switcher .dropdown-item:active {
        background: #e3f2fd;
        color: #2196f3;
      }

      .project-option-title {
        display: block;
        font-weight: 600;
        line-height: 1.2;
      }

      .project-option-meta {
        color: #697586;
        display: block;
        font-size: 12px;
        margin-top: 3px;
      }

      .project-add-option {
        color: #2196f3;
        font-weight: 600;
      }

      .project-option-check {
        align-items: center;
        background: #eef6ff;
        border-radius: 7px;
        color: #2196f3;
        display: inline-flex;
        flex: 0 0 auto;
        height: 30px;
        justify-content: center;
        width: 30px;
      }

      .project-option-copy {
        min-width: 0;
      }

      .dashboard-export-button {
        align-items: center;
        display: inline-flex;
        height: 42px;
        justify-content: center;
        padding-left: 18px;
        padding-right: 18px;
      }

      .table-nowrap td,
      .table-nowrap th {
        white-space: nowrap;
      }

      @media (max-width: 767.98px) {
        .page-actions {
          justify-content: flex-start !important;
        }

        .project-switcher {
          width: 100%;
        }

        .dashboard-export {
          width: 100%;
        }

        .dashboard-export-button {
          width: 100%;
        }

        .dashboard-equal-card {
          height: auto;
        }
      }
    </style>
    @stack('styles')
  </head>
  <body>
    <div class="loader-bg">
      <div class="loader-track">
        <div class="loader-fill"></div>
      </div>
    </div>

    @include('layouts.sidebar')
    @include('layouts.header')
    @include('layouts.main')
    @include('layouts.footer')

    <script src="{{ asset('assets/berry/js/plugins/popper.min.js') }}"></script>
    <script src="{{ asset('assets/berry/js/plugins/simplebar.min.js') }}"></script>
    <script src="{{ asset('assets/berry/js/plugins/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/berry/js/fonts/custom-font.js') }}"></script>
    <script src="{{ asset('assets/berry/js/script.js') }}"></script>
    <script src="{{ asset('assets/berry/js/theme.js') }}"></script>
    <script src="{{ asset('assets/berry/js/plugins/feather.min.js') }}"></script>
    <script>
      layout_change('light');
      font_change('Roboto');
      change_box_container('false');
      layout_caption_change('true');
      layout_rtl_change('false');
      preset_change('preset-1');
    </script>
    @stack('scripts')
  </body>
</html>
