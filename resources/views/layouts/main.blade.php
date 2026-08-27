<main class="pc-container">
  <div class="pc-content">
    <div class="page-header">
      <div class="page-block">
        <div class="row align-items-center">
          <div class="col-md-6">
            <div class="page-header-title">
              <h2 class="mb-0">@yield('page_title', 'Dashboard')</h2>
            </div>
          </div>
          @hasSection('page_actions')
            <div class="col-md-6 mt-3 mt-md-0">
              <div class="page-actions d-flex flex-wrap justify-content-md-end gap-2">
                @yield('page_actions')
              </div>
            </div>
          @endif
        </div>
      </div>
    </div>
    @yield('content')
  </div>
</main>
