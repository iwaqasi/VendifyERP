<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="VendifyERP - Enterprise POS & ERP Suite for Retail, Wholesale, Restaurant, Saloon, Clinic & Repair businesses">
    <title>{{ config('app.name', 'VendifyERP') }} — Enterprise POS & ERP Suite</title>

    <link rel="icon" href="{{ asset('img/vendify-logo-mark.png') }}" type="image/png">

    {{-- VendifyERP design system --}}
    <link rel="stylesheet" href="{{ asset('css/tailwind/app.css?v='.$asset_v) }}">
    <link rel="stylesheet" href="{{ asset('css/vendify.css?v='.$asset_v) }}">

    {{-- Google Fonts (already imported in vendify.css but ensure availability) --}}
    <style>
        /* ===== Landing Page Styles ===== */

        /* --- Hero Section --- */
        .lp-hero {
            background: linear-gradient(160deg, #16283F 0%, #1E3A5F 55%, #0D9488 100%);
            position: relative;
            overflow: hidden;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .lp-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(13, 148, 136, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
        .lp-hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(30, 58, 95, 0.2) 0%, transparent 70%);
            border-radius: 50%;
        }

        /* --- Navigation --- */
        .lp-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            padding: 1rem 2rem;
            transition: all 0.3s ease;
        }
        .lp-nav.scrolled {
            background: rgba(22, 40, 63, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }
        .lp-nav-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .lp-nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .lp-nav-brand img {
            width: 40px;
            height: 40px;
        }
        .lp-nav-brand span {
            font-family: var(--vf-font-heading);
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.02em;
        }
        .lp-nav-brand span em {
            font-style: normal;
            color: var(--vf-accent-light, #14B8A6);
        }
        .lp-nav-links {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        .lp-nav-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: color 0.2s;
        }
        .lp-nav-links a:hover {
            color: #fff;
        }
        .lp-nav-cta {
            background: var(--vf-accent, #0D9488) !important;
            color: #fff !important;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600 !important;
            transition: all 0.2s !important;
        }
        .lp-nav-cta:hover {
            background: var(--vf-accent-light, #14B8A6) !important;
            transform: translateY(-1px);
        }

        /* --- Hero Content --- */
        .lp-hero-content {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 0 auto;
            padding: 120px 2rem 80px;
            text-align: center;
        }
        .lp-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 100px;
            padding: 8px 20px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
        }
        .lp-hero-badge-dot {
            width: 8px;
            height: 8px;
            background: var(--vf-accent-light, #14B8A6);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .lp-hero h1 {
            font-family: var(--vf-font-heading);
            font-size: clamp(2.5rem, 6vw, 4rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.1;
            margin: 0 0 1.5rem;
            letter-spacing: -0.03em;
        }
        .lp-hero h1 em {
            font-style: normal;
            background: linear-gradient(135deg, var(--vf-accent-light, #14B8A6), #34D399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .lp-hero-sub {
            font-size: 1.15rem;
            color: rgba(255, 255, 255, 0.7);
            max-width: 600px;
            margin: 0 auto 2.5rem;
            line-height: 1.7;
        }
        .lp-hero-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .lp-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--vf-accent, #0D9488);
            color: #fff;
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .lp-btn-primary:hover {
            background: var(--vf-accent-light, #14B8A6);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(13, 148, 136, 0.35);
            color: #fff;
        }
        .lp-btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.2s;
            backdrop-filter: blur(10px);
        }
        .lp-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            color: #fff;
        }

        /* --- Trusted By / Stats --- */
        .lp-stats {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3rem;
            margin-top: 4rem;
            padding-top: 3rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .lp-stat {
            text-align: center;
        }
        .lp-stat-num {
            font-family: var(--vf-font-heading);
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
        }
        .lp-stat-label {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 4px;
        }

        /* --- Section Common --- */
        .lp-section {
            padding: 100px 2rem;
        }
        .lp-section-inner {
            max-width: 1200px;
            margin: 0 auto;
        }
        .lp-section-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        .lp-section-tag {
            display: inline-block;
            background: var(--vf-accent-soft, #ECFDFB);
            color: var(--vf-accent, #0D9488);
            padding: 6px 16px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
        }
        .lp-section-title {
            font-family: var(--vf-font-heading);
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 800;
            color: var(--vf-foreground, #0F172A);
            margin: 0 0 1rem;
            letter-spacing: -0.02em;
        }
        .lp-section-desc {
            font-size: 1.05rem;
            color: var(--vf-muted, #64748B);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.7;
        }

        /* --- Business Types Grid --- */
        .lp-types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .lp-type-card {
            background: #fff;
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid var(--vf-border, #E2E8F0);
            transition: all 0.3s ease;
            cursor: default;
        }
        .lp-type-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--vf-shadow-lg);
            border-color: var(--vf-accent, #0D9488);
        }
        .lp-type-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .lp-type-card h3 {
            font-family: var(--vf-font-heading);
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--vf-foreground, #0F172A);
            margin: 0 0 0.5rem;
        }
        .lp-type-card p {
            font-size: 0.9rem;
            color: var(--vf-muted, #64748B);
            line-height: 1.6;
            margin: 0;
        }

        /* --- Features Grid --- */
        .lp-features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        .lp-feature-card {
            background: #fff;
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid var(--vf-border, #E2E8F0);
            transition: all 0.3s ease;
        }
        .lp-feature-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--vf-shadow-lg);
        }
        .lp-feature-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--vf-accent-soft, #ECFDFB);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            color: var(--vf-accent, #0D9488);
            font-size: 1.25rem;
        }
        .lp-feature-card h3 {
            font-family: var(--vf-font-heading);
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--vf-foreground, #0F172A);
            margin: 0 0 0.5rem;
        }
        .lp-feature-card p {
            font-size: 0.9rem;
            color: var(--vf-muted, #64748B);
            line-height: 1.6;
            margin: 0;
        }

        /* --- Pricing Section --- */
        .lp-pricing {
            background: linear-gradient(180deg, #F8FAFC 0%, #EEF3F9 100%);
        }
        .lp-pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            align-items: start;
        }
        .lp-price-card {
            background: #fff;
            border-radius: 20px;
            padding: 2.5rem 2rem;
            border: 2px solid var(--vf-border, #E2E8F0);
            transition: all 0.3s ease;
            position: relative;
        }
        .lp-price-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--vf-shadow-lg);
        }
        .lp-price-card.popular {
            border-color: var(--vf-accent, #0D9488);
            transform: scale(1.02);
        }
        .lp-price-card.popular:hover {
            transform: scale(1.02) translateY(-4px);
        }
        .lp-price-popular {
            position: absolute;
            top: -14px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, var(--vf-accent, #0D9488), var(--vf-accent-light, #14B8A6));
            color: #fff;
            padding: 6px 20px;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        .lp-price-name {
            font-family: var(--vf-font-heading);
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--vf-foreground, #0F172A);
            margin: 0 0 0.5rem;
        }
        .lp-price-desc {
            font-size: 0.85rem;
            color: var(--vf-muted, #64748B);
            margin: 0 0 1.5rem;
            line-height: 1.5;
        }
        .lp-price-amount {
            font-family: var(--vf-font-heading);
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--vf-primary, #1E3A5F);
            margin: 0 0 0.25rem;
        }
        .lp-price-amount span {
            font-size: 1rem;
            font-weight: 500;
            color: var(--vf-muted, #64748B);
        }
        .lp-price-interval {
            font-size: 0.85rem;
            color: var(--vf-muted, #64748B);
            margin: 0 0 2rem;
        }
        .lp-price-features {
            list-style: none;
            padding: 0;
            margin: 0 0 2rem;
        }
        .lp-price-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 0;
            font-size: 0.9rem;
            color: var(--vf-foreground, #0F172A);
        }
        .lp-price-features li svg {
            flex-shrink: 0;
            width: 18px;
            height: 18px;
            color: var(--vf-accent, #0D9488);
            margin-top: 2px;
        }
        .lp-price-btn {
            display: block;
            width: 100%;
            text-align: center;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.2s;
            border: 2px solid var(--vf-primary, #1E3A5F);
            color: var(--vf-primary, #1E3A5F);
            background: transparent;
        }
        .lp-price-btn:hover {
            background: var(--vf-primary, #1E3A5F);
            color: #fff;
            transform: translateY(-1px);
        }
        .lp-price-btn.primary {
            background: linear-gradient(135deg, var(--vf-accent, #0D9488), var(--vf-accent-light, #14B8A6));
            border-color: transparent;
            color: #fff;
        }
        .lp-price-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(13, 148, 136, 0.35);
        }

        /* --- CTA Section --- */
        .lp-cta {
            background: linear-gradient(160deg, #16283F 0%, #1E3A5F 55%, #0D9488 100%);
            text-align: center;
        }
        .lp-cta h2 {
            font-family: var(--vf-font-heading);
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 800;
            color: #fff;
            margin: 0 0 1rem;
        }
        .lp-cta p {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.7);
            max-width: 500px;
            margin: 0 auto 2rem;
            line-height: 1.7;
        }

        /* --- Footer --- */
        .lp-footer {
            background: #0F172A;
            padding: 3rem 2rem;
            text-align: center;
        }
        .lp-footer-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .lp-footer-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .lp-footer-brand img {
            width: 32px;
            height: 32px;
        }
        .lp-footer-brand span {
            font-family: var(--vf-font-heading);
            font-weight: 700;
            color: #fff;
            font-size: 1.1rem;
        }
        .lp-footer-brand em {
            font-style: normal;
            color: var(--vf-accent-light, #14B8A6);
        }
        .lp-footer-links {
            display: flex;
            gap: 2rem;
        }
        .lp-footer-links a {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
        }
        .lp-footer-links a:hover {
            color: #fff;
        }
        .lp-footer-copy {
            color: rgba(255, 255, 255, 0.35);
            font-size: 0.8rem;
            width: 100%;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        /* --- Responsive --- */
        @media (max-width: 768px) {
            .lp-nav-links { display: none; }
            .lp-stats { flex-direction: column; gap: 1.5rem; }
            .lp-types-grid,
            .lp-features-grid,
            .lp-pricing-grid { grid-template-columns: 1fr; }
            .lp-price-card.popular { transform: none; }
            .lp-price-card.popular:hover { transform: translateY(-4px); }
            .lp-footer-inner { flex-direction: column; text-align: center; }
            .lp-footer-links { flex-wrap: wrap; justify-content: center; }
        }

        /* --- Mobile Menu Toggle --- */
        .lp-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 4px;
        }
        @media (max-width: 768px) {
            .lp-menu-toggle { display: block; }
        }
    </style>
</head>
<body style="background: #fff;">

    {{-- ======================== NAVIGATION ======================== --}}
    <nav class="lp-nav" id="lpNav">
        <div class="lp-nav-inner">
            <a href="{{ url('/') }}" class="lp-nav-brand">
                <img src="{{ asset('img/vendify-logo-mark.png') }}" alt="{{ config('app.name') }}">
                <span>Vendify<em>ERP</em></span>
            </a>

            <div class="lp-nav-links" id="lpNavLinks">
                <a href="#business-types">Business Types</a>
                <a href="#features">Features</a>
                @if (!empty($packages) && count($packages) > 0)
                    <a href="#pricing">Pricing</a>
                @endif
                <a href="/vendify-pos/" target="_blank" style="color: var(--vf-accent-light, #14B8A6);">🚀 Try POS</a>
                <a href="{{ action([App\Http\Controllers\Auth\LoginController::class, 'login']) }}">Sign In</a>
                @if (config('constants.allow_registration'))
                    <a href="{{ route('business.getRegister') }}" class="lp-nav-cta">Get Started Free</a>
                @endif
            </div>

            <button class="lp-menu-toggle" onclick="document.getElementById('lpNavLinks').classList.toggle('lp-nav-links-open')" aria-label="Menu">
                ☰
            </button>
        </div>
    </nav>

    {{-- ======================== HERO ======================== --}}
    <section class="lp-hero">
        <div class="lp-hero-content">
            <div class="lp-hero-badge">
                <span class="lp-hero-badge-dot"></span>
                Enterprise POS &amp; ERP Suite
            </div>

            <h1>
                Run Your Business<br>
                with <em>Vendify</em>ERP
            </h1>

            <p class="lp-hero-sub">
                The all-in-one platform for Retail, Wholesale, Restaurants, Salons, Clinics &amp; Repair shops.
                Manage inventory, sales, purchases, and accounts — from one powerful dashboard.
            </p>

            <div class="lp-hero-actions">
                @if (config('constants.allow_registration'))
                    <a href="{{ route('business.getRegister') }}" class="lp-btn-primary">
                        Start Free Trial
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                @endif
                <a href="/vendify-pos/" target="_blank" class="lp-btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/><path d="M14 9l3 3-3 3"/></svg>
                    Try VendifyPOS
                </a>
            </div>

            <div class="lp-stats">
                <div class="lp-stat">
                    <div class="lp-stat-num">6+</div>
                    <div class="lp-stat-label">Business Types</div>
                </div>
                <div class="lp-stat">
                    <div class="lp-stat-num">50+</div>
                    <div class="lp-stat-label">Reports & Analytics</div>
                </div>
                <div class="lp-stat">
                    <div class="lp-stat-num">24/7</div>
                    <div class="lp-stat-label">Cloud Access</div>
                </div>
                <div class="lp-stat">
                    <div class="lp-stat-num">Multi</div>
                    <div class="lp-stat-label">Location Support</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ======================== BUSINESS TYPES ======================== --}}
    <section class="lp-section" id="business-types" style="background: #fff;">
        <div class="lp-section-inner">
            <div class="lp-section-header">
                <span class="lp-section-tag">Industries</span>
                <h2 class="lp-section-title">Built for Every Business Type</h2>
                <p class="lp-section-desc">
                    Whether you run a retail store or a multi-branch wholesale operation, VendifyERP adapts to your workflow.
                </p>
            </div>

            <div class="lp-types-grid">
                {{-- Retail --}}
                <div class="lp-type-card">
                    <div class="lp-type-icon" style="background: #EEF3F9; color: #1E3A5F;">🛍️</div>
                    <h3>Retail POS</h3>
                    <p>Complete point-of-sale with barcode scanning, product variations, customer profiles, loyalty points, and real-time stock tracking.</p>
                </div>

                {{-- Wholesale --}}
                <div class="lp-type-card">
                    <div class="lp-type-icon" style="background: #ECFDFB; color: #0D9488;">📦</div>
                    <h3>Wholesale & Distribution</h3>
                    <p>Bulk pricing, multi-location inventory, purchase orders, stock transfers between branches, and credit management for B2B sales.</p>
                </div>

                {{-- Restaurant --}}
                <div class="lp-type-card">
                    <div class="lp-type-icon" style="background: #FEF3C7; color: #B45309;">🍽️</div>
                    <h3>Restaurant & Café</h3>
                    <p>Table management, kitchen display system, order modifiers, multi-course tracking, and waiter staff management.</p>
                </div>

                {{-- Saloon --}}
                <div class="lp-type-card">
                    <div class="lp-type-icon" style="background: #FCE7F3; color: #BE185D;">💇</div>
                    <h3>Salon & Spa</h3>
                    <p>Appointment booking, service staff scheduling, walk-in management, membership packages, and commission tracking.</p>
                </div>

                {{-- Clinic --}}
                <div class="lp-type-card">
                    <div class="lp-type-icon" style="background: #EDE9FE; color: #7C3AED;">🏥</div>
                    <h3>Clinic & Healthcare</h3>
                    <p>Patient management, appointment scheduling, prescription tracking, lab integration, and insurance billing support.</p>
                </div>

                {{-- Repair --}}
                <div class="lp-type-card">
                    <div class="lp-type-icon" style="background: #FEE2E2; color: #DC2626;">🔧</div>
                    <h3>Repair & Service</h3>
                    <p>Job ticketing, repair status tracking, customer notifications, parts inventory, and technician assignment workflow.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ======================== FEATURES ======================== --}}
    <section class="lp-section" id="features" style="background: #F8FAFC;">
        <div class="lp-section-inner">
            <div class="lp-section-header">
                <span class="lp-section-tag">Features</span>
                <h2 class="lp-section-title">Everything You Need to Scale</h2>
                <p class="lp-section-desc">
                    From daily operations to strategic insights — one platform covers it all.
                </p>
            </div>

            <div class="lp-features-grid">
                <div class="lp-feature-card">
                    <div class="lp-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 17h-11v-14h-2"/><path d="M6 5l14 1l-1 7h-13"/></svg>
                    </div>
                    <h3>Inventory Management</h3>
                    <p>Track stock across multiple locations, set reorder alerts, manage variants, and transfer stock between branches.</p>
                </div>

                <div class="lp-feature-card">
                    <div class="lp-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <h3>Financial Accounting</h3>
                    <p>Full double-entry accounting, balance sheets, trial balance, cash flow statements, and profit & loss reports.</p>
                </div>

                <div class="lp-feature-card">
                    <div class="lp-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/><path d="M12 8v4l3 3"/></svg>
                    </div>
                    <h3>Real-Time Reports</h3>
                    <p>50+ reports including sales trends, stock expiry, tax reports, sales representative performance, and profit analysis.</p>
                </div>

                <div class="lp-feature-card">
                    <div class="lp-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                    </div>
                    <h3>Multi-Location</h3>
                    <p>Manage unlimited branches with location-specific stock, pricing, staff permissions, and consolidated reporting.</p>
                </div>

                <div class="lp-feature-card">
                    <div class="lp-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h3>Role-Based Access</h3>
                    <p>Fine-grained permissions, cashier PIN login, assign staff to locations, and track activity across the platform.</p>
                </div>

                <div class="lp-feature-card">
                    <div class="lp-feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                    </div>
                    <h3>Cross-Platform POS</h3>
                    <p>Web, Windows, Android, iOS, tablets, and handheld devices — all synced with your central database in real time.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ======================== PRICING ======================== --}}
    @if (!empty($packages) && count($packages) > 0)
    <section class="lp-section lp-pricing" id="pricing">
        <div class="lp-section-inner">
            <div class="lp-section-header">
                <span class="lp-section-tag">Pricing</span>
                <h2 class="lp-section-title">Simple, Transparent Pricing</h2>
                <p class="lp-section-desc">
                    Choose the plan that fits your business. All plans include core features. Upgrade anytime.
                </p>
            </div>

            {{-- Duration Toggle --}}
            <div style="text-align: center; margin-bottom: 2.5rem;">
                <div style="display: inline-flex; align-items: center; gap: 12px; background: #fff; padding: 8px 16px; border-radius: 100px; border: 1px solid var(--vf-border);">
                    <span id="lpMonthlyLabel" style="font-weight: 600; color: var(--vf-primary); cursor: pointer;" onclick="lpSwitchDuration('months')">Monthly</span>
                    <label style="position: relative; display: inline-block; width: 48px; height: 26px; cursor: pointer;">
                        <input type="checkbox" id="lpDurationToggle" onchange="lpSwitchDuration(this.checked ? 'years' : 'months')" style="opacity: 0; width: 0; height: 0;">
                        <span style="position: absolute; inset: 0; background: #CBD5E1; border-radius: 100px; transition: 0.3s;"></span>
                        <span id="lpToggleDot" style="position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; background: #fff; border-radius: 50%; transition: 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.2);"></span>
                    </label>
                    <span id="lpYearlyLabel" style="font-weight: 500; color: var(--vf-muted); cursor: pointer;" onclick="lpSwitchDuration('years')">Annual <span style="color: var(--vf-accent); font-size: 0.75rem; font-weight: 700;">SAVE 20%</span></span>
                </div>
            </div>

            <div class="lp-pricing-grid" id="lpPricingGrid">
                @foreach ($packages as $index => $package)
                    @php
                        $is_popular = $package->mark_package_as_popular == 1;
                        $interval_label = $package->interval === 'years' ? 'year' : 'month';
                    @endphp
                    <div class="lp-price-card {{ $is_popular ? 'popular' : '' }}" data-interval="{{ $package->interval }}">
                        @if ($is_popular)
                            <div class="lp-price-popular">Most Popular</div>
                        @endif

                        <h3 class="lp-price-name">{{ $package->name }}</h3>
                        <p class="lp-price-desc">{{ $package->description }}</p>

                        <div class="lp-price-amount">
                            @if ($package->price != 0)
                                <span class="display_currency" data-currency_symbol="true">{{ $package->price }}</span>
                                <span>/ {{ $package->interval_count }} {{ $interval_label }}{{ $package->interval_count > 1 ? 's' : '' }}</span>
                            @else
                                Free
                                <span>/ {{ $package->interval_count }} {{ $interval_label }}{{ $package->interval_count > 1 ? 's' : '' }}</span>
                            @endif
                        </div>
                        <p class="lp-price-interval">
                            @if ($package->trial_days > 0)
                                {{ $package->trial_days }}-day free trial included
                            @else
                                Billed {{ $package->interval }}
                            @endif
                        </p>

                        <ul class="lp-price-features">
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                {{ $package->location_count == 0 ? 'Unlimited' : $package->location_count }} Location{{ $package->location_count != 1 ? 's' : '' }}
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                {{ $package->user_count == 0 ? 'Unlimited' : $package->user_count }} User{{ $package->user_count != 1 ? 's' : '' }}
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                {{ $package->product_count == 0 ? 'Unlimited' : number_format($package->product_count) }} Product{{ $package->product_count != 1 ? 's' : '' }}
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                {{ $package->invoice_count == 0 ? 'Unlimited' : number_format($package->invoice_count) }} Invoice{{ $package->invoice_count != 1 ? 's' : '' }}/mo
                            </li>

                            @if (!empty($package->custom_permissions))
                                @foreach ($package->custom_permissions as $permission => $value)
                                    @isset($permission_formatted[$permission])
                                        <li>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            {{ $permission_formatted[$permission] }}
                                        </li>
                                    @endisset
                                @endforeach
                            @endif
                        </ul>

                        @if ($package->enable_custom_link == 1)
                            <a href="{{ $package->custom_link }}" class="lp-price-btn {{ $is_popular ? 'primary' : '' }}">
                                {{ $package->custom_link_text }}
                            </a>
                        @else
                            @if (config('constants.allow_registration'))
                                <a href="{{ route('business.getRegister') }}?package={{ $package->id }}" class="lp-price-btn {{ $is_popular ? 'primary' : '' }}">
                                    @if ($package->price != 0)
                                        Start Free Trial
                                    @else
                                        Get Started Free
                                    @endif
                                </a>
                            @else
                                <a href="{{ action([App\Http\Controllers\Auth\LoginController::class, 'login']) }}" class="lp-price-btn {{ $is_popular ? 'primary' : '' }}">
                                    Sign In
                                </a>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ======================== CTA ======================== --}}
    <section class="lp-section lp-cta">
        <div class="lp-section-inner">
            <h2>Ready to Transform Your Business?</h2>
            <p>Join hundreds of businesses already using VendifyERP to streamline operations and boost profitability.</p>
            <div class="lp-hero-actions">
                @if (config('constants.allow_registration'))
                    <a href="{{ route('business.getRegister') }}" class="lp-btn-primary">
                        Start Your Free Trial
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                @endif
                <a href="/vendify-pos/" target="_blank" class="lp-btn-secondary">
                    🚀 Try VendifyPOS Now
                </a>
                <a href="{{ action([App\Http\Controllers\Auth\LoginController::class, 'login']) }}" class="lp-btn-secondary">
                    Sign In to Existing Account
                </a>
            </div>
        </div>
    </section>

    {{-- ======================== FOOTER ======================== --}}
    <footer class="lp-footer">
        <div class="lp-footer-inner">
            <div class="lp-footer-brand">
                <img src="{{ asset('img/vendify-logo-mark.png') }}" alt="{{ config('app.name') }}">
                <span>Vendify<em>ERP</em></span>
            </div>

            <div class="lp-footer-links">
                <a href="{{ action([App\Http\Controllers\Auth\LoginController::class, 'login']) }}">Sign In</a>
                @if (config('constants.allow_registration'))
                    <a href="{{ route('business.getRegister') }}">Register</a>
                @endif
                <a href="/vendify-pos/" target="_blank">VendifyPOS</a>
                <a href="mailto:support@arksoftsolutions.com">Support</a>
            </div>

            <div class="lp-footer-copy">
                &copy; {{ date('Y') }} {{ config('app.name') }} by Arksoft Solutions. All rights reserved.
            </div>
        </div>
    </footer>

    {{-- ======================== SCRIPTS ======================== --}}
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const nav = document.getElementById('lpNav');
            if (window.scrollY > 50) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        });

        // Duration toggle for pricing
        function lpSwitchDuration(interval) {
            const toggle = document.getElementById('lpDurationToggle');
            const monthlyLabel = document.getElementById('lpMonthlyLabel');
            const yearlyLabel = document.getElementById('lpYearlyLabel');
            const dot = document.getElementById('lpToggleDot');
            const cards = document.querySelectorAll('.lp-price-card[data-interval]');

            if (interval === 'years') {
                toggle.checked = true;
                dot.style.transform = 'translateX(22px)';
                monthlyLabel.style.fontWeight = '500';
                monthlyLabel.style.color = 'var(--vf-muted)';
                yearlyLabel.style.fontWeight = '600';
                yearlyLabel.style.color = 'var(--vf-primary)';
            } else {
                toggle.checked = false;
                dot.style.transform = 'translateX(0)';
                monthlyLabel.style.fontWeight = '600';
                monthlyLabel.style.color = 'var(--vf-primary)';
                yearlyLabel.style.fontWeight = '500';
                yearlyLabel.style.color = 'var(--vf-muted)';
            }

            // Show/hide cards based on interval
            cards.forEach(function(card) {
                if (card.dataset.interval === interval) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Initialize pricing display
        document.addEventListener('DOMContentLoaded', function() {
            lpSwitchDuration('months');
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                var target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>

</body>
</html>
