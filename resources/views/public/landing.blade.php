<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    {{-- SEO Meta Tags --}}
    <title>{{ $business->name }} — Reservasi & Jadwal Booking Online</title>
    <meta name="description" content="{{ Str::limit(strip_tags($business->description ?: 'Reservasi jadwal layanan online di ' . $business->name . '. Pilih waktu sendiri, terkonfirmasi instan tanpa antre.'), 155) }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="robots" content="index, follow">

    {{-- Open Graph / Facebook --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $business->name }} — Reservasi Booking Online">
    <meta property="og:description" content="{{ Str::limit(strip_tags($business->description ?: 'Layanan profesional dengan kemudahan reservasi online cepat, pasti dan terkonfirmasi otomatis.'), 155) }}">
    @if($business->logo_url)
        <meta property="og:image" content="{{ $business->logo_url }}">
    @endif
    <meta property="og:site_name" content="{{ $business->name }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $business->name }} — Reservasi Booking Online">
    <meta name="twitter:description" content="{{ Str::limit(strip_tags($business->description ?: 'Layanan profesional dengan kemudahan reservasi online cepat, pasti dan terkonfirmasi otomatis.'), 155) }}">
    @if($business->logo_url)
        <meta name="twitter:image" content="{{ $business->logo_url }}">
    @endif

    {{-- JSON-LD Structured Data for LocalBusiness --}}
    <script type="application/ld+json">
    {!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    {{-- Preconnect Typography --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Ultra-Lightweight Scoped CSS (< 18 KB inlined, 0 external CSS render blocking, zero JS) --}}
    <style>
        *, ::after, ::before { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; font-size: 16px; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            padding-bottom: 72px; /* space for mobile sticky bar */
        }
        @media (min-width: 768px) { body { padding-bottom: 0; } }

        .container {
            width: 100%;
            max-width: 1120px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 1.25rem;
            padding-right: 1.25rem;
        }

        /* Typography */
        h1, h2, h3, h4 { color: #0f172a; font-weight: 700; line-height: 1.25; }
        p { color: #64748b; font-size: 0.9375rem; }

        /* Navigation */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 40;
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid #e2e8f0;
        }
        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 64px;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: #0f172a;
            font-weight: 700;
            font-size: 1.125rem;
        }
        .nav-logo {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
        }
        .nav-avatar {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background-color: #2563eb;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
        }
        .nav-links {
            display: none;
            gap: 1.75rem;
            list-style: none;
        }
        @media (min-width: 768px) { .nav-links { display: flex; align-items: center; } }
        .nav-links a {
            text-decoration: none;
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color 0.15s ease;
        }
        .nav-links a:hover { color: #2563eb; }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.15s ease;
            cursor: pointer;
            border: 1px solid transparent;
            text-align: center;
        }
        .btn-primary {
            background-color: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }
        .btn-primary:hover { background-color: #1d4ed8; border-color: #1d4ed8; }
        .btn-secondary {
            background-color: #ffffff;
            color: #0f172a;
            border-color: #e2e8f0;
        }
        .btn-secondary:hover { background-color: #f8fafc; border-color: #cbd5e1; }
        .btn-whatsapp {
            background-color: #22c55e;
            color: #ffffff;
            border-color: #22c55e;
        }
        .btn-whatsapp:hover { background-color: #16a34a; border-color: #16a34a; }
        .btn-sm { padding: 0.4rem 0.875rem; font-size: 0.8125rem; }
        .btn-lg { padding: 0.875rem 1.75rem; font-size: 1rem; border-radius: 10px; }

        /* Status Pill */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
        }
        .status-pill.open {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .status-pill.closed {
            background-color: #fef9c3;
            color: #854d0e;
            border: 1px solid #fef08a;
        }
        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 9999px;
            background-color: currentColor;
        }

        /* Hero Section */
        .hero {
            padding: 3.5rem 0 3rem;
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
        }
        @media (min-width: 768px) { .hero { padding: 5rem 0 4.5rem; } }
        .hero-badge { margin-bottom: 1rem; }
        .hero-title {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 1rem;
        }
        @media (min-width: 768px) { .hero-title { font-size: 3rem; } }
        .hero-desc {
            font-size: 1.0625rem;
            color: #64748b;
            max-width: 640px;
            line-height: 1.6;
            margin-bottom: 1.75rem;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.875rem;
            margin-bottom: 2rem;
        }
        .hero-trust {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f1f5f9;
        }
        .trust-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: #475569;
            font-weight: 500;
        }
        .trust-icon { color: #2563eb; }

        /* Section Commons */
        .section {
            padding: 3.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        @media (min-width: 768px) { .section { padding: 4.5rem 0; } }
        .section-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 2.5rem;
        }
        .section-title {
            font-size: 1.75rem;
            margin-bottom: 0.625rem;
            letter-spacing: -0.02em;
        }
        .section-subtitle { font-size: 0.9375rem; color: #64748b; }

        /* Services Grid */
        .services-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
        @media (min-width: 640px) { .services-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1024px) { .services-grid { grid-template-columns: repeat(3, 1fr); } }

        .service-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: border-color 0.15s ease;
        }
        .service-card:hover { border-color: #94a3b8; }
        .service-top { margin-bottom: 1.25rem; }
        .service-tag {
            display: inline-block;
            font-size: 0.6875rem;
            font-weight: 600;
            color: #2563eb;
            background-color: #eff6ff;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .service-name {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .service-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            font-size: 0.8125rem;
            color: #64748b;
        }
        .service-desc {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.5;
        }
        .service-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
        }
        .service-price {
            font-size: 1.125rem;
            font-weight: 800;
            color: #0f172a;
        }

        /* Features / Why Us */
        .features-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
        @media (min-width: 640px) { .features-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1024px) { .features-grid { grid-template-columns: repeat(4, 1fr); } }

        .feature-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.5rem;
        }
        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background-color: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .feature-title { font-size: 1rem; font-weight: 700; margin-bottom: 0.5rem; }
        .feature-desc { font-size: 0.875rem; color: #64748b; line-height: 1.5; }

        /* About & Hours Layout */
        .about-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        @media (min-width: 768px) { .about-layout { grid-template-columns: 1.2fr 0.8fr; } }

        .about-card, .hours-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.75rem;
        }
        .hours-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.875rem;
        }
        .hours-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .hours-row:last-child { border-bottom: none; }
        .hours-row.is-today {
            font-weight: 700;
            color: #2563eb;
            background-color: #eff6ff;
            margin: 0 -0.5rem;
            padding: 0.5rem 0.5rem;
            border-radius: 6px;
        }
        .hours-day { display: flex; align-items: center; gap: 0.375rem; }
        .today-pill {
            font-size: 0.6875rem;
            background-color: #2563eb;
            color: #ffffff;
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
        }

        /* FAQ Accordion */
        .faq-list {
            max-width: 720px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
        }
        .faq-item {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }
        .faq-summary {
            padding: 1rem 1.25rem;
            font-weight: 600;
            font-size: 0.9375rem;
            color: #0f172a;
            cursor: pointer;
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
        }
        .faq-summary::-webkit-details-marker { display: none; }
        .faq-summary::after {
            content: "+";
            font-size: 1.25rem;
            color: #64748b;
            font-weight: 400;
            transition: transform 0.2s ease;
        }
        details[open] .faq-summary::after {
            content: "−";
            color: #2563eb;
        }
        .faq-content {
            padding: 0 1.25rem 1.25rem;
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.6;
            border-top: 1px solid #f8fafc;
        }

        /* Contact Section */
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
        @media (min-width: 768px) { .contact-grid { grid-template-columns: repeat(3, 1fr); } }
        .contact-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.5rem;
            text-align: center;
        }
        .contact-icon {
            width: 44px;
            height: 44px;
            border-radius: 9999px;
            background-color: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        .contact-title { font-size: 0.9375rem; font-weight: 700; margin-bottom: 0.375rem; }
        .contact-detail { font-size: 0.875rem; color: #64748b; margin-bottom: 1rem; }

        /* Sticky Mobile Bar */
        .mobile-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            z-index: 50;
            display: flex;
            gap: 0.75rem;
        }
        @media (min-width: 768px) { .mobile-bar { display: none; } }
        .mobile-bar .btn { flex: 1; }

        /* Footer */
        .footer {
            background-color: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 2.5rem 0;
            text-align: center;
            font-size: 0.8125rem;
            color: #94a3b8;
        }
        .footer a { color: #2563eb; text-decoration: none; }

        /* Stroke icon styling */
        .icon { display: inline-block; width: 1.25em; height: 1.25em; vertical-align: -0.2em; stroke-width: 2; stroke: currentColor; fill: none; stroke-linecap: round; stroke-linejoin: round; }
    </style>
</head>
<body>

    {{-- Top Navigation --}}
    <nav class="navbar">
        <div class="container nav-inner">
            <a href="{{ url("/{$business->slug}") }}" class="nav-brand">
                @if($business->logo_url)
                    <img src="{{ $business->logo_url }}" alt="{{ $business->name }}" class="nav-logo" loading="lazy">
                @else
                    <div class="nav-avatar">{{ strtoupper(substr($business->name, 0, 1)) }}</div>
                @endif
                <span>{{ $business->name }}</span>
            </a>

            <ul class="nav-links">
                <li><a href="#layanan">Layanan</a></li>
                <li><a href="#tentang">Tentang</a></li>
                <li><a href="#faq">FAQ</a></li>
                <li><a href="#kontak">Kontak</a></li>
            </ul>

            <div style="display: flex; gap: 0.5rem; align-items: center;">
                @if($whatsappUrl)
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm" aria-label="Chat WhatsApp">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                        <span style="display: none;" class="md-inline">WhatsApp</span>
                    </a>
                @endif
                <a href="{{ $bookingUrl }}" class="btn btn-primary btn-sm">
                    Booking Sekarang
                </a>
            </div>
        </div>
    </nav>

    {{-- Hero Section --}}
    <header class="hero">
        <div class="container">
            <div class="hero-badge">
                @if($isOpenNow)
                    <span class="status-pill open">
                        <span class="status-dot"></span> Buka Sekarang ({{ $todayHoursText }})
                    </span>
                @else
                    <span class="status-pill closed">
                        <span class="status-dot"></span> {{ $todayHoursText }}
                    </span>
                @endif
            </div>

            <h1 class="hero-title">
                {{ $sections['hero']['headline'] ?? $business->name }}
            </h1>

            <p class="hero-desc">
                {{ $sections['hero']['subheadline'] ?? ($business->description ?: 'Layanan profesional dengan kemudahan reservasi online cepat, pasti dan terkonfirmasi otomatis tanpa perlu menunggu konfirmasi admin.') }}
            </p>

            <div class="hero-actions">
                <a href="{{ $bookingUrl }}" class="btn btn-primary btn-lg">
                    <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Booking Sekarang
                </a>
                @if($whatsappUrl)
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-lg">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                        Chat WhatsApp
                    </a>
                @endif
            </div>

            <div class="hero-trust">
                <div class="trust-item">
                    <svg class="icon trust-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Konfirmasi Instan</span>
                </div>
                <div class="trust-item">
                    <svg class="icon trust-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Pilih Slot Jadwal Real-Time</span>
                </div>
                <div class="trust-item">
                    <svg class="icon trust-icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span>Bebas Reschedule Mandiri</span>
                </div>
            </div>
        </div>
    </header>

    {{-- Layanan / Services Section --}}
    <section id="layanan" class="section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Katalog Layanan</h2>
                <p class="section-subtitle">Pilih layanan yang Anda inginkan dan tentukan jadwal kunjungan secara online</p>
            </div>

            @if($services->isEmpty())
                <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 3rem; text-align: center;">
                    <p style="color: #64748b;">Belum ada layanan yang dipublikasikan saat ini.</p>
                </div>
            @else
                <div class="services-grid">
                    @foreach($services as $service)
                        <article class="service-card">
                            <div class="service-top">
                                @if($service->category)
                                    <span class="service-tag">{{ $service->category->name }}</span>
                                @endif
                                <h3 class="service-name">{{ $service->name }}</h3>
                                
                                <div class="service-meta">
                                    <span>⏱ {{ $service->duration_minutes }} Menit</span>
                                    @if($service->capacity > 1)
                                        <span>• Kapasitas {{ $service->capacity }} Org</span>
                                    @endif
                                </div>

                                @if($service->description)
                                    <p class="service-desc">{{ Str::limit($service->description, 100) }}</p>
                                @endif
                            </div>

                            <div class="service-bottom">
                                <div>
                                    <span style="font-size: 0.6875rem; color: #94a3b8; display: block; text-transform: uppercase;">Mulai dari</span>
                                    <span class="service-price">Rp {{ number_format($service->price_idr, 0, ',', '.') }}</span>
                                </div>
                                <a href="{{ $bookingUrl }}?service={{ $service->id }}" class="btn btn-primary btn-sm">
                                    Pilih
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- Keunggulan / Value Proposition --}}
    <section class="section" style="background-color: #ffffff;">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Mengapa Memilih Kami?</h2>
                <p class="section-subtitle">Pengalaman reservasi modern yang dirancang untuk kenyamanan jadwal Anda</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg class="icon" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                    </div>
                    <h3 class="feature-title">Reservasi Cepat 24/7</h3>
                    <p class="feature-desc">Booking layanan kapan saja dari perangkat apa pun tanpa perlu menunggu balasan pesan admin.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                    <h3 class="feature-title">Jadwal Slot Terbuka</h3>
                    <p class="feature-desc">Ketersediaan waktu dan staf langsung sinkron secara real-time tanpa risiko double-booking.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"></path></svg>
                    </div>
                    <h3 class="feature-title">Pengingat Otomatis</h3>
                    <p class="feature-desc">Dapatkan konfirmasi tiket dan notifikasi pengingat jadwal sebelum waktu kunjungan Anda.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M23 4v6h-6"></path><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                    </div>
                    <h3 class="feature-title">Reschedule Mandiri</h3>
                    <p class="feature-desc">Rencana mendadak berubah? Anda dapat mengubah waktu kunjungan dengan mudah melalui tautan tiket Anda.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Tentang & Jam Operasional --}}
    <section id="tentang" class="section">
        <div class="container">
            <div class="about-layout">
                <div class="about-card">
                    <h2 class="section-title" style="margin-bottom: 1rem; text-align: left;">Tentang {{ $business->name }}</h2>
                    <p style="margin-bottom: 1.25rem; line-height: 1.7;">
                        {{ $business->description ?: ($business->name . ' berkomitmen memberikan pelayanan terbaik dengan staf profesional dan fasilitas yang higienis serta nyaman.') }}
                    </p>
                    
                    @if($business->address)
                        <div style="display: flex; gap: 0.75rem; align-items: flex-start; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #f1f5f9;">
                            <svg class="icon" style="color: #2563eb; flex-shrink: 0; margin-top: 0.2rem;" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            <div>
                                <strong style="display: block; font-size: 0.875rem; color: #0f172a; margin-bottom: 0.25rem;">Alamat Lokasi:</strong>
                                <p style="font-size: 0.875rem;">{{ $business->address }}{{ $business->city ? ', ' . $business->city : '' }}{{ $business->province ? ', ' . $business->province : '' }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="hours-card">
                    <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.25rem;">Jam Operasional</h3>
                    <p style="font-size: 0.8125rem; color: #64748b; margin-bottom: 1rem;">Zona Waktu: {{ $timezone }}</p>

                    <div class="hours-table">
                        @foreach($scheduleList as $sched)
                            <div class="hours-row {{ $sched['is_today'] ? 'is-today' : '' }}">
                                <span class="hours-day">
                                    {{ $sched['day_name'] }}
                                    @if($sched['is_today'])
                                        <span class="today-pill">Hari Ini</span>
                                    @endif
                                </span>
                                <span>{{ $sched['hours_text'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ Section --}}
    <section id="faq" class="section" style="background-color: #ffffff;">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Pertanyaan yang Sering Diajukan</h2>
                <p class="section-subtitle">Semua informasi penting seputar proses reservasi dan layanan</p>
            </div>

            <div class="faq-list">
                <details class="faq-item" open>
                    <summary class="faq-summary">Bagaimana cara memesan jadwal reservasi?</summary>
                    <div class="faq-content">
                        Klik tombol <strong>Booking Sekarang</strong>, pilih layanan yang Anda inginkan, tentukan tanggal serta jam kedatangan yang tersedia, lalu lengkapi nama dan nomor WhatsApp Anda. Tiket booking langsung terbit seketika.
                    </div>
                </details>

                <details class="faq-item">
                    <summary class="faq-summary">Apakah saya bisa mengubah jadwal (reschedule)?</summary>
                    <div class="faq-content">
                        Bisa. Pada konfirmasi booking Anda akan disertakan link kelola reservasi. Anda dapat mengubah ke slot tanggal atau jam lain yang masih tersedia sesuai kebijakan waktu minimal pembatalan.
                    </div>
                </details>

                <details class="faq-item">
                    <summary class="faq-summary">Bagaimana jika saya terlambat datang?</summary>
                    <div class="faq-content">
                        Kami menyarankan Anda tiba 10 menit sebelum waktu reservasi. Jika terjadi keterlambatan, silakan hubungi kami via WhatsApp agar tim kami dapat mengoordinasikan jadwal dengan terapis/petugas yang bertugas.
                    </div>
                </details>

                <details class="faq-item">
                    <summary class="faq-summary">Metode pembayaran apa saja yang didukung?</summary>
                    <div class="faq-content">
                        Kami melayani pembayaran di tempat (tunai / transfer / QRIS) saat kunjungan Anda tiba di lokasi.
                    </div>
                </details>
            </div>
        </div>
    </section>

    {{-- Kontak & Lokasi Section --}}
    <section id="kontak" class="section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Hubungi Kami</h2>
                <p class="section-subtitle">Pertanyaan lebih lanjut atau bantuan seputar reservasi</p>
            </div>

            <div class="contact-grid">
                @if($whatsappUrl)
                    <div class="contact-card">
                        <div class="contact-icon" style="background-color: #dcfce7; color: #16a34a;">
                            <svg class="icon" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                        </div>
                        <h3 class="contact-title">WhatsApp Resmi</h3>
                        <p class="contact-detail">{{ $business->whatsapp ?: $business->phone }}</p>
                        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm w-full" style="width: 100%;">
                            Kirim Pesan
                        </a>
                    </div>
                @endif

                @if($business->phone)
                    <div class="contact-card">
                        <div class="contact-icon">
                            <svg class="icon" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                        </div>
                        <h3 class="contact-title">Telepon</h3>
                        <p class="contact-detail">{{ $business->phone }}</p>
                        <a href="tel:{{ $business->phone }}" class="btn btn-secondary btn-sm" style="width: 100%;">
                            Panggil
                        </a>
                    </div>
                @endif

                @if($business->address)
                    <div class="contact-card">
                        <div class="contact-icon">
                            <svg class="icon" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        </div>
                        <h3 class="contact-title">Alamat Lokasi</h3>
                        <p class="contact-detail">{{ Str::limit($business->address, 65) }}</p>
                        <a href="https://maps.google.com/?q={{ urlencode($business->name . ' ' . $business->address) }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm" style="width: 100%;">
                            Buka Google Maps
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Sticky Mobile CTA Bar --}}
    <aside class="mobile-bar" aria-label="Aksi Cepat Mobile">
        @if($whatsappUrl)
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp" aria-label="Chat WhatsApp">
                <svg class="icon" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                WhatsApp
            </a>
        @endif
        <a href="{{ $bookingUrl }}" class="btn btn-primary">
            Booking Sekarang
        </a>
    </aside>

    {{-- Footer --}}
    <footer class="footer">
        <div class="container">
            <p>&copy; {{ date('Y') }} {{ $business->name }}. Hak Cipta Dilindungi.</p>
            <p style="margin-top: 0.375rem; font-size: 0.75rem;">
                Platform Reservasi didukung oleh <a href="{{ url('/') }}">AMAN BOOKING</a>
            </p>
        </div>
    </footer>

</body>
</html>
