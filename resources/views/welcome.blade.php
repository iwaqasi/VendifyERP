@extends('layouts.auth2')
@section('title', config('app.name'))
@inject('request', 'Illuminate\Http\Request')
@section('content')
    <div class="tw-flex tw-flex-col tw-items-center tw-text-center" style="max-width: 620px; width: 100%;">
        <span class="vf-hero-badge">
            <span class="vf-hero-badge-dot"></span>
            Enterprise POS &amp; ERP Suite
        </span>

        <div class="tw-flex tw-items-center tw-justify-center tw-gap-4 tw-mt-8">
            <img src="{{ asset('img/vendify-logo-mark.png') }}" alt="{{ config('app.name') }} logo"
                style="width: 64px; height: 64px; object-fit: contain;" />
            <h1 style="font-family: var(--vf-font-heading); font-size: 42px; font-weight: 800; letter-spacing: -0.02em; color: var(--vf-primary); margin: 0;">
                Vendify<span style="color: var(--vf-accent);">ERP</span>
            </h1>
        </div>

        <p style="font-size: 16px; line-height: 1.7; color: var(--vf-muted); margin: 18px 0 0; max-width: 460px;">
            {{ env('APP_TITLE', __('auth.brand_subline')) }}
        </p>

        <div class="tw-flex tw-flex-col tw-gap-3 tw-mt-10 sm:tw-flex-row">
            <a href="{{ action([\App\Http\Controllers\Auth\LoginController::class, 'login']) }}"
                class="vf-btn-block tw-inline-flex tw-items-center tw-justify-center tw-px-10 tw-text-base"
                style="width: auto;">
                @lang('lang_v1.login')
            </a>
            @if (config('constants.allow_registration'))
                <a href="{{ route('business.getRegister') }}"
                    class="tw-inline-flex tw-items-center tw-justify-center tw-h-12 tw-px-10 tw-rounded-lg tw-text-base tw-font-semibold tw-text-[var(--vf-primary)] tw-border-2 tw-border-[var(--vf-primary)] hover:tw-bg-[var(--vf-primary)] hover:tw-text-white tw-transition-all tw-duration-200">
                    {{ __('business.register') }}
                </a>
            @endif
        </div>
    </div>
@endsection
