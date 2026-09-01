<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') - {{ config('app.name', 'POS') }}</title>

    @include('layouts.partials.css')

    @include('layouts.partials.extracss_auth')

    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <script src='https://www.google.com/recaptcha/api.js'></script>

</head>

<body class="pace-done">
    @inject('request', 'Illuminate\Http\Request')
    @if (session('status') && session('status.success'))
        <input type="hidden" id="status_span" data-status="{{ session('status.success') }}"
            data-msg="{{ session('status.msg') }}">
    @endif

    <div class="vf-auth-shell">
        {{-- Left brand panel (hidden on smaller screens) --}}
        <div class="vf-auth-brand-panel">
            <div class="vf-auth-brand-logo">
                <img src="{{ asset('img/vendify-logo-mark.png') }}" alt="{{ config('app.name') }} logo" />
                <span class="brand-name">Vendify<span>ERP</span></span>
            </div>

            <div class="vf-auth-brand-mid">
                <h2>{{ __('auth.brand_headline', [], config('app.locale')) }}</h2>
                <p>{{ __('auth.brand_subline', [], config('app.locale')) }}</p>

                <div class="vf-auth-feature">
                    <span class="vf-auth-feature-icon">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2" />
                            <path d="M14.8 8a2 2 0 0 0 -1.8 -1h-2a2 2 0 1 0 0 4h2a2 2 0 1 1 0 4h-2a2 2 0 0 1 -1.8 -1" />
                            <path d="M12 6v10" />
                        </svg>
                    </span>
                    <span>{{ __('auth.feature_pos', [], config('app.locale')) }}</span>
                </div>
                <div class="vf-auth-feature">
                    <span class="vf-auth-feature-icon">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                            <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                            <path d="M17 17h-11v-14h-2" />
                            <path d="M6 5l14 1l-1 7h-13" />
                        </svg>
                    </span>
                    <span>{{ __('auth.feature_inventory', [], config('app.locale')) }}</span>
                </div>
                <div class="vf-auth-feature">
                    <span class="vf-auth-feature-icon">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" />
                            <path d="M12 8v4" />
                            <path d="M12 16h.01" />
                        </svg>
                    </span>
                    <span>{{ __('auth.feature_reports', [], config('app.locale')) }}</span>
                </div>
            </div>

            <div class="vf-auth-brand-foot">
                <span>
                    <svg aria-hidden="true" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                        <path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855" />
                    </svg>
                    © {{ date('Y') }} {{ config('app.name') }}
                </span>
                <span>v{{ config('author.app_version', '1.0') }}</span>
            </div>
        </div>

        {{-- Right content panel --}}
        <div class="vf-auth-main">
            <div class="vf-auth-topbar">
                <a href="{{ url('/') }}" class="tw-flex tw-items-center tw-gap-2">
                    <img class="logo-sm" src="{{ asset('img/vendify-logo-mark.png') }}" alt="{{ config('app.name') }}" />
                    <span class="tw-hidden sm:tw-inline tw-font-bold">{{ config('app.name') }}</span>
                </a>

                <div class="tw-flex tw-items-center tw-gap-3">
                    @if (config('constants.SHOW_REPAIR_STATUS_LOGIN_SCREEN') && Route::has('repair-status'))
                        <a href="{{ action([\Modules\Repair\Http\Controllers\CustomerRepairStatusController::class, 'index']) }}">
                            @lang('repair::lang.repair_status')
                        </a>
                    @endif

                    @if (Route::has('member_scanner'))
                        <a href="{{ action([\Modules\Gym\Http\Controllers\MemberController::class, 'member_scanner']) }}">
                            @lang('gym::lang.gym_member_profile')
                        </a>
                    @endif

                    @if (!($request->segment(1) == 'business' && $request->segment(2) == 'register'))
                        @if (config('constants.allow_registration'))
                            <a href="{{ route('business.getRegister') }}@if (!empty(request()->lang)) {{ '?lang=' . request()->lang }} @endif"
                                class="btn-outline-sm">
                                {{ __('business.register') }}
                            </a>
                            @if (Route::has('pricing') && config('app.env') != 'demo' && $request->segment(1) != 'pricing')
                                <a href="{{ action([\Modules\Superadmin\Http\Controllers\PricingController::class, 'index']) }}">
                                    @lang('superadmin::lang.pricing')
                                </a>
                            @endif
                        @endif
                    @endif

                    @if ($request->segment(1) != 'login')
                        <a href="{{ action([\App\Http\Controllers\Auth\LoginController::class, 'login']) }}@if (!empty(request()->lang)) {{ '?lang=' . request()->lang }} @endif">
                            {{ __('business.sign_in') }}
                        </a>
                    @endif

                    @include('layouts.partials.language_btn')
                </div>
            </div>

            @yield('content')
        </div>
    </div>

    @include('layouts.partials.javascripts')

    <!-- Scripts -->
    <script src="{{ asset('js/login.js?v=' . $asset_v) }}"></script>

    @yield('javascript')

    <script type="text/javascript">
        $(document).ready(function() {
            $('.select2_register').select2();

            // $('input').iCheck({
            //     checkboxClass: 'icheckbox_square-blue',
            //     radioClass: 'iradio_square-blue',
            //     increaseArea: '20%' // optional
            // });
        });
    </script>
    <style>
        .wizard>.content {
            background-color: white !important;
        }
    </style>
</body>

</html>
