<!doctype html>
<html lang="id">
  <head>
    <title>Login | Pencatatan Proyek</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Masuk ke sistem pencatatan credit dan debit project." />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <link rel="icon" href="{{ asset('assets/berry/images/app-favicon.svg') }}" type="image/svg+xml" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" id="main-font-link" />
    <link rel="stylesheet" href="{{ asset('assets/berry/fonts/phosphor/duotone/style.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/berry/fonts/tabler-icons.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/berry/fonts/feather.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/berry/fonts/fontawesome.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/berry/fonts/material.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/berry/css/style.css') }}" id="main-style-link" />
    <link rel="stylesheet" href="{{ asset('assets/berry/css/style-preset.css') }}" />
    <style>
      .auth-main {
        background:
          radial-gradient(circle at 12% 18%, rgba(33, 150, 243, 0.13), transparent 30%),
          radial-gradient(circle at 88% 12%, rgba(103, 58, 183, 0.12), transparent 28%),
          #f3f7fb;
      }

      .auth-form .card {
        border: 1px solid #e3e8ef;
        border-radius: 8px;
        box-shadow: 0 18px 45px rgba(16, 24, 40, 0.12);
      }

      .login-brand {
        align-items: center;
        display: flex;
        gap: 10px;
        justify-content: center;
        text-decoration: none;
      }

      .login-brand-mark {
        align-items: center;
        background: #ede7f6;
        border-radius: 8px;
        color: #2196f3;
        display: inline-flex;
        height: 44px;
        justify-content: center;
        width: 44px;
      }

      .login-brand-text {
        color: #202939;
        font-size: 18px;
        font-weight: 700;
        line-height: 1.1;
      }

      .login-brand-text small {
        color: #697586;
        display: block;
        font-size: 11px;
        font-weight: 500;
        margin-top: 2px;
      }

      .login-submit {
        align-items: center;
        display: inline-flex;
        gap: 8px;
        justify-content: center;
      }

      @media (max-width: 575.98px) {
        .auth-form .card {
          margin-left: 12px;
          margin-right: 12px;
        }
      }
    </style>
  </head>
  <body>
    <div class="loader-bg">
      <div class="loader-track">
        <div class="loader-fill"></div>
      </div>
    </div>

    <div class="auth-main">
      <div class="auth-wrapper v3">
        <div class="auth-form">
          <div class="card my-5">
            <div class="card-body">
              <a href="{{ route('dashboard') }}" class="login-brand">
                <span class="login-brand-mark">
                  <i class="ti ti-report-money f-24"></i>
                </span>
                <span class="login-brand-text">
                  Pencatatan
                  <small>Keuangan Proyek</small>
                </span>
              </a>
              
              <h5 class="my-4 d-flex justify-content-center">Masuk dengan email</h5>

              <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="form-floating mb-3">
                  <input
                    type="email"
                    class="form-control @error('email') is-invalid @enderror"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="Email"
                    autocomplete="email"
                    required
                    autofocus
                  />
                  <label for="email">Email</label>
                  @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <div class="form-floating mb-3">
                  <input
                    type="password"
                    class="form-control @error('password') is-invalid @enderror"
                    id="password"
                    name="password"
                    placeholder="Password"
                    autocomplete="current-password"
                    required
                  />
                  <label for="password">Password</label>
                  @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <div class="d-flex mt-1 justify-content-between">
                  <div class="form-check">
                    <input class="form-check-input input-primary" type="checkbox" id="remember" name="remember" value="1" @checked(old('remember', true)) />
                    <label class="form-check-label text-muted" for="remember">Ingat saya</label>
                  </div>
                </div>
                <div class="d-grid mt-4">
                  <button type="submit" class="btn btn-primary login-submit">
                    <i class="ti ti-login"></i>
                    Masuk
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

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
  </body>
</html>
