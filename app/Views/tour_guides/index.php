<?php
/**
 * @var string $title
 * @var array<int, array<string, mixed>> $guides
 * @var array<string, mixed> $stats
 * @var array<int, array<string, mixed>> $specializations
 * @var array<int, array<string, mixed>> $languages
 * @var array<int, array<string, mixed>> $destinations
 * @var array<int, array<string, mixed>> $culinaries
 * @var array<int, array<string, mixed>> $hotels
 * @var array<int, array<string, mixed>> $transports
 */
$title = $title ?? 'SumutGO | Pemandu Wisata Lokal Medan, Menemani Perjalananmu';
$guides = $guides ?? [];
$stats = $stats ?? [];
$specializations = $specializations ?? [];
$languages = $languages ?? [];
$destinations = $destinations ?? [];
$culinaries = $culinaries ?? [];
$hotels = $hotels ?? [];
$transports = $transports ?? [];

$totalGuides = count($guides);
$availableGuides = (int) ($stats['available_guides'] ?? count(array_filter($guides, static fn ($g) => ($g['status'] ?? '') === 'Tersedia')));
$totalReviews = array_sum(array_map(static fn ($g) => (int) ($g['reviews'] ?? 0), $guides));
$avgRatingFormatted = number_format((float) ($stats['avg_rating'] ?? 4.73), 2, '.', '');
$totalLanguages = count($languages);
$totalSpecs = count($specializations);
$districtList = array_values(array_unique(array_filter(array_map(static fn ($g) => (string) ($g['district'] ?? ''), $guides), static fn ($d) => $d !== '' && $d !== 'Kota Medan' && $d !== 'Medan')));
$districtCount = count($districtList) ?: 15;
$prices = array_column($guides, 'price');
$minGuidePrice = ! empty($prices) ? (int) (floor(min($prices) / 25000) * 25000) : 250000;
$maxGuidePrice = ! empty($prices) ? (int) (ceil(max($prices) / 25000) * 25000) : 850000;
$lastGuideCode = ! empty($guides) ? (string) ($guides[count($guides) - 1]['id'] ?? 'TG-150') : 'TG-150';
$defaultBookDate = date('Y-m-d', strtotime('+1 day'));
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= esc($title) ?></title>
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;family=JetBrains+Mono:wght@400;500;600&amp;family=Plus+Jakarta+Sans:wght@500;600;700;800&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            lokale: '#07779d',
            lokaledark: '#055b78',
            lokaledeep: '#044257',
            lokalesoft: '#ebf5f9',
            lokaleborder: '#d0e6ef',
            sand: '#f6f2eb',
            ink: '#14242b',
            muted: '#5b6e77'
          },
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
            display: ['Plus Jakarta Sans', 'sans-serif'],
            mono: ['JetBrains Mono', 'monospace']
          }
        }
      }
    }
  </script>
  <style>
    body { background-color: #ffffff; color: #14242b; font-family: 'Inter', sans-serif; overflow-x: hidden; }
    .nav-blur { background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); }
    .lokale-card { background: #ffffff; border: 1px solid #e2eef3; transition: all 0.25s ease; }
    .lokale-card:hover { border-color: #07779d; box-shadow: 0 14px 34px -12px rgba(7, 119, 157, 0.16); transform: translateY(-2px); }
    #medanLeafletMap { height: 520px; width: 100%; border-radius: 1rem; z-index: 10; }
    .leaflet-control-zoom { border: 1px solid #d0e6ef !important; box-shadow: 0 2px 10px rgba(20, 36, 43, 0.12) !important; border-radius: 12px !important; overflow: hidden; }
    .leaflet-control-zoom a { color: #14242b !important; border-bottom-color: #e2eef3 !important; width: 32px !important; height: 32px !important; line-height: 32px !important; font-weight: 600; }
    .leaflet-control-zoom a:hover { background: #ebf5f9 !important; color: #07779d !important; }
    .leaflet-control-locate { border: none !important; background: transparent !important; box-shadow: none !important; margin: 10px 10px 0 0 !important; }
    .leaflet-control-locate-btn {
      display: inline-flex; align-items: center; gap: 6px;
      background: #ffffff; color: #14242b; border: 1px solid #d0e6ef;
      padding: 7px 14px; font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 600;
      border-radius: 9999px; box-shadow: 0 2px 10px rgba(20, 36, 43, 0.14);
      cursor: pointer; transition: all 0.2s ease; line-height: 1.2;
    }
    .leaflet-control-locate-btn:hover { background: #ebf5f9; color: #07779d; border-color: #07779d; }
    .leaflet-control-locate-btn:disabled { opacity: 0.75; cursor: wait; }
    .leaflet-control-locate-btn.is-active { background: #07779d; color: #ffffff; border-color: #07779d; }
    .leaflet-popup-content-wrapper { border-radius: 16px !important; box-shadow: 0 12px 28px -8px rgba(20, 36, 43, 0.25) !important; padding: 2px !important; }
    .leaflet-popup-content { margin: 12px 14px !important; min-width: 230px; max-width: 280px; font-family: 'Inter', sans-serif; }
    .blur-locked {
      filter: blur(5.5px);
      -webkit-filter: blur(5.5px);
      user-select: none;
      -webkit-user-select: none;
      pointer-events: none;
      transition: filter 0.25s ease;
    }
  </style>
</head>
<body class="bg-white text-ink antialiased selection:bg-lokale selection:text-white auth-guest">

  <!-- STICKY CLEAN NAVBAR (LOKALE.COFFEE STYLE) -->
  <header class="sticky top-0 z-40 nav-blur border-b border-slate-200/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-3">
      <a href="#beranda" id="brandHomeLink" class="flex items-center gap-2.5 group shrink-0">
        <div class="w-10 h-10 rounded-full bg-lokale text-white flex items-center justify-center shadow-md shadow-lokale/20 group-hover:bg-lokaledark transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-6-5.373-6-10a6 6 0 1112 0c0 4.627-6 10-6 10z"/>
            <circle cx="12" cy="11" r="2.2" fill="currentColor" stroke="none"/>
          </svg>
        </div>
        <div class="leading-none">
          <span class="font-display font-extrabold text-2xl tracking-tight text-lokale">Sumut<span class="text-ink font-extrabold">GO</span></span>
          <span class="block text-[10px] tracking-[0.18em] text-muted uppercase mt-0.5">Medan Tour Guide</span>
        </div>
      </a>

      <nav class="hidden lg:flex items-center gap-1.5 bg-lokalesoft/70 p-1 rounded-full border border-lokaleborder">
        <button type="button" data-main-tab="directory" class="main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition bg-lokale text-white shadow-sm">Beranda &amp; Pemandu</button>
        <button type="button" data-main-tab="map" class="main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition text-ink/80 hover:text-lokale">Peta Basecamp</button>
        <button type="button" data-main-tab="planner" class="main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition text-ink/80 hover:text-lokale">Kalkulator Trip</button>
        <button type="button" data-main-tab="analytics" class="main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition text-ink/80 hover:text-lokale">Destinasi &amp; Mitra</button>
      </nav>

      <div class="flex items-center gap-2 shrink-0">
        <button id="btnToggleCompareModal" type="button" class="px-3 py-2 rounded-full bg-lokalesoft hover:bg-lokale hover:text-white text-lokale text-xs font-semibold border border-lokaleborder transition flex items-center gap-1.5">
          <span class="hidden sm:inline">Bandingkan</span>
          <span class="sm:hidden">Banding</span>
          <span id="compareCountBadge" class="px-1.5 py-0.5 rounded-full bg-lokale text-white text-[10px] font-bold">0</span>
        </button>
        <button id="btnFilterFavorites" type="button" class="px-3 py-2 rounded-full bg-white hover:bg-rose-50 border border-slate-200 text-xs font-semibold text-ink flex items-center gap-1 transition">
          <span class="text-rose-500">♥</span>
          <span id="favCountBadge" class="font-mono text-[11px]">0</span>
        </button>

        <!-- AUTH: GUEST STATE -->
        <div id="navAuthGuest" class="flex items-center gap-1.5 pl-1 border-l border-slate-200">
          <button id="btnNavLogin" type="button" class="px-3.5 py-2 rounded-full bg-white hover:bg-lokalesoft text-lokale border border-lokaleborder text-xs font-semibold transition">Masuk</button>
          <button id="btnNavRegister" type="button" class="px-3.5 py-2 rounded-full bg-lokale hover:bg-lokaledark text-white text-xs font-semibold shadow-sm shadow-lokale/20 transition">Daftar</button>
        </div>

        <!-- AUTH: LOGGED IN STATE -->
        <div id="navAuthUser" class="hidden">
          <div class="flex items-center gap-1.5 pl-1 border-l border-slate-200">
            <button id="btnOpenMyBookings" type="button" class="px-3 py-2 rounded-full bg-lokalesoft hover:bg-lokale hover:text-white text-lokale border border-lokaleborder text-xs font-semibold transition flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              <span id="btnOpenMyBookingsText" class="hidden md:inline">Booking Saya</span>
            </button>
            <button id="btnOpenAdminConfirm" type="button" class="hidden px-3 py-2 rounded-full bg-amber-50 hover:bg-amber-600 text-amber-800 hover:text-white border border-amber-200 text-xs font-semibold transition">
              <span class="inline-flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
                <span class="hidden md:inline">Konfirmasi Admin</span>
              </span>
            </button>
            <a id="navAdminQuickLink" href="<?= site_url('admin') ?>" class="hidden px-3 py-2 rounded-full bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white border border-emerald-200 text-xs font-semibold transition">Portal Admin</a>
            <div class="flex items-center gap-2 bg-white border border-lokaleborder rounded-full pl-1.5 pr-1.5 py-1 shadow-sm">
              <span id="navUserAvatar" class="w-6 h-6 rounded-full bg-lokale text-white text-[10px] font-bold flex items-center justify-center shrink-0">U</span>
              <div class="hidden sm:flex items-center gap-1.5">
                <span id="navUserName" class="text-xs font-semibold text-ink max-w-[110px] truncate">-</span>
                <span id="navUserRoleBadge" class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide border bg-lokalesoft text-lokale border-lokaleborder">wisatawan</span>
              </div>
              <button id="btnNavLogout" type="button" class="px-2.5 py-1 rounded-full bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white text-[11px] font-semibold transition">Keluar</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="lg:hidden flex items-center gap-1.5 px-4 pb-3 overflow-x-auto">
      <button type="button" data-main-tab="directory" class="main-tab-mobile px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-lokale text-white">Beranda &amp; Pemandu</button>
      <button type="button" data-main-tab="map" class="main-tab-mobile px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-lokalesoft text-lokale">Peta Basecamp</button>
      <button type="button" data-main-tab="planner" class="main-tab-mobile px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-lokalesoft text-lokale">Kalkulator Trip</button>
      <button type="button" data-main-tab="analytics" class="main-tab-mobile px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-lokalesoft text-lokale">Destinasi &amp; Mitra</button>
    </div>
  </header>

  <main class="flex-1">

    <!-- TAB 1: BERANDA + DIREKTORI PEMANDU -->
    <section id="view-directory">
      <!-- HERO -->
      <div id="beranda" class="bg-gradient-to-b from-lokalesoft/70 via-white to-white pt-10 pb-14 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
            <div class="lg:col-span-6 space-y-5">
              <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white border border-lokaleborder text-lokale text-xs font-semibold shadow-sm">
                <span class="w-2 h-2 rounded-full bg-lokale animate-pulse"></span>
                #TemanJalanMedan • <?= $totalGuides ?> Pemandu Wisata Lokal Resmi
              </span>
              <h1 class="font-display font-extrabold text-4xl sm:text-5xl lg:text-[3.4rem] text-ink leading-[1.12] tracking-tight">
                Teman Perjalananmu<br />
                <span class="text-lokale">yang Setia.</span>
              </h1>
              <p class="text-sm sm:text-base text-muted leading-relaxed max-w-xl">
                Jelajah sejarah Kesawan, berburu kuliner malam Medan, atau menyusuri keindahan Danau Toba dan Bukit Lawang—Teman SumutGO nggak pernah jalan sendirian. Kami selalu sedia pemandu lokal ramah dan senyum hangat untuk merayakan setiap langkah perjalananmu.
              </p>
              <div class="flex flex-wrap items-center gap-3 pt-2">
                <a href="#direktori-pemandu" class="px-7 py-3.5 rounded-full bg-lokale hover:bg-lokaledark text-white font-semibold text-sm shadow-lg shadow-lokale/25 transition">
                  Temukan Pemandumu
                </a>
                <button type="button" id="btnHeroSwitchMap" class="px-6 py-3.5 rounded-full bg-white hover:bg-lokalesoft text-lokale border border-lokaleborder font-semibold text-sm transition">
                  Lihat Peta Basecamp
                </button>
              </div>
            </div>

            <div class="lg:col-span-6">
              <div class="relative rounded-[2rem] overflow-hidden shadow-2xl shadow-lokale/10 border-4 border-white">
                <img src="<?= base_url('assets/img/hero_bg.jpg') ?>" alt="Pemandu Wisata Medan" class="w-full h-80 sm:h-[420px] object-cover" />
                <div class="absolute inset-0 bg-gradient-to-t from-lokaledeep/70 via-transparent to-transparent"></div>
                <div class="absolute bottom-5 left-5 right-5 bg-white/95 backdrop-blur-md rounded-2xl p-4 shadow-lg flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <span class="text-[11px] font-bold text-lokale uppercase tracking-wider">Siap Menemani Hari Ini</span>
                    <p class="text-xs sm:text-sm font-semibold text-ink mt-0.5">Tim Pemandu Wisata Medan siap melayani dengan senyum hangat</p>
                  </div>
                  <span id="heroAvailPill" class="px-3.5 py-1.5 rounded-full bg-lokale text-white text-xs font-bold"><?= $availableGuides ?> Tersedia</span>
                </div>
              </div>
            </div>
          </div>

          <!-- STATS STRIP (MIRRORS KAB/KOTA, TEMAN LOKALE, OUTLET) -->
          <div class="mt-14 bg-white rounded-3xl border border-lokaleborder shadow-sm p-6 sm:p-8 grid grid-cols-2 lg:grid-cols-4 gap-6 text-center lg:divide-x divide-slate-100">
            <div class="py-2 lg:py-0">
              <div class="font-display font-extrabold text-3xl sm:text-4xl text-lokale"><?= $districtCount ?></div>
              <div class="text-xs sm:text-sm font-medium text-muted mt-1">Kecamatan Medan</div>
            </div>
            <div class="py-2 lg:py-0">
              <div class="font-display font-extrabold text-3xl sm:text-4xl text-lokale"><?= number_format($totalReviews, 0, ',', '.') ?>+</div>
              <div class="text-xs sm:text-sm font-medium text-muted mt-1">Ulasan Teman Jalan</div>
            </div>
            <div class="py-2 lg:py-0">
              <div id="statTotalGuides" class="font-display font-extrabold text-3xl sm:text-4xl text-lokale"><?= $totalGuides ?></div>
              <div class="text-xs sm:text-sm font-medium text-muted mt-1">Pemandu Resmi</div>
            </div>
            <div class="py-2 lg:py-0">
              <div class="font-display font-extrabold text-3xl sm:text-4xl text-lokale"><?= $totalLanguages ?></div>
              <div class="text-xs sm:text-sm font-medium text-muted mt-1">Bahasa Dikuasai</div>
            </div>
          </div>
        </div>
      </div>

      <!-- FAVORIT TEMAN JALAN (MIRRORS FAVORIT TEMAN LOKALE) -->
      <div class="py-16 px-4 sm:px-6 lg:px-8 bg-sand/60 border-y border-slate-100">
        <div class="max-w-7xl mx-auto">
          <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-10">
            <div>
              <span class="text-xs font-bold uppercase tracking-[0.2em] text-lokale">Destinasi Pilihan</span>
              <h2 class="font-display font-extrabold text-2xl sm:text-3xl text-ink mt-1">Favorit Teman Jalan</h2>
              <p class="text-xs sm:text-sm text-muted mt-1">Klik salah satu destinasi favorit untuk langsung melihat pemandu spesialisnya.</p>
            </div>
            <button type="button" id="btnHeroSwitchPlanner" class="inline-flex items-center gap-2 text-xs font-bold text-lokale hover:text-lokaledark transition">
              <span>Hitung Estimasi Biaya Trip &rarr;</span>
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <button type="button" data-quick-dest="Danau Toba" class="quick-dest-card lokale-card rounded-3xl overflow-hidden text-left group p-3">
              <div class="h-60 rounded-2xl overflow-hidden bg-lokalesoft">
                <img src="<?= base_url('assets/img/card_toba.jpg') ?>" alt="Danau Toba" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
              </div>
              <div class="pt-4 pb-2 px-2">
                <span class="text-[11px] font-semibold text-lokale">Wisata Alam &amp; Budaya</span>
                <h3 class="font-display font-bold text-base text-ink group-hover:text-lokale transition mt-0.5">Danau Toba &amp; Holbung</h3>
                <p class="text-xs text-muted mt-1">Panorama Kaldera Toba &amp; budaya Batak Samosir.</p>
              </div>
            </button>

            <button type="button" data-quick-dest="Tangkahan" class="quick-dest-card lokale-card rounded-3xl overflow-hidden text-left group p-3">
              <div class="h-60 rounded-2xl overflow-hidden bg-lokalesoft">
                <img src="<?= base_url('assets/img/card_tangkahan.jpg') ?>" alt="Tangkahan" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
              </div>
              <div class="pt-4 pb-2 px-2">
                <span class="text-[11px] font-semibold text-lokale">Ekowisata Hutan Tropis</span>
                <h3 class="font-display font-bold text-base text-ink group-hover:text-lokale transition mt-0.5">Tangkahan &amp; Leuser</h3>
                <p class="text-xs text-muted mt-1">Konservasi gajah Sumatera &amp; trekking orangutan.</p>
              </div>
            </button>

            <button type="button" data-quick-dest="Sipiso-piso" class="quick-dest-card lokale-card rounded-3xl overflow-hidden text-left group p-3">
              <div class="h-60 rounded-2xl overflow-hidden bg-lokalesoft">
                <img src="<?= base_url('assets/img/card_sipisopiso.jpg') ?>" alt="Sipiso-piso" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
              </div>
              <div class="pt-4 pb-2 px-2">
                <span class="text-[11px] font-semibold text-lokale">Dataran Tinggi Karo</span>
                <h3 class="font-display font-bold text-base text-ink group-hover:text-lokale transition mt-0.5">Berastagi &amp; Sipiso-piso</h3>
                <p class="text-xs text-muted mt-1">Dataran tinggi Karo, Sibayak &amp; air terjun ikonik.</p>
              </div>
            </button>

            <button type="button" data-quick-dest="Maimun" class="quick-dest-card lokale-card rounded-3xl overflow-hidden text-left group p-3">
              <div class="h-60 rounded-2xl overflow-hidden bg-lokalesoft">
                <img src="<?= base_url('assets/img/card_heritage.jpg') ?>" alt="Istana Maimun" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
              </div>
              <div class="pt-4 pb-2 px-2">
                <span class="text-[11px] font-semibold text-lokale">Sejarah &amp; Kuliner Kota</span>
                <h3 class="font-display font-bold text-base text-ink group-hover:text-lokale transition mt-0.5">Kesawan &amp; Istana Maimun</h3>
                <p class="text-xs text-muted mt-1">Jejak Kesultanan Deli, Tjong A Fie &amp; kuliner khas.</p>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- DIREKTORI LENGKAP PEMANDU -->
      <div id="direktori-pemandu" class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
        <div class="max-w-7xl mx-auto space-y-6">
          <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
              <span class="text-xs font-bold uppercase tracking-[0.2em] text-lokale">Katalog Pemandu Resmi</span>
              <h2 class="font-display font-extrabold text-2xl sm:text-3xl text-ink mt-1">Temukan Teman Jalanmu di Medan</h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <div class="inline-flex rounded-full bg-lokalesoft p-1 border border-lokaleborder">
                <button type="button" id="btnViewCards" class="px-4 py-1.5 rounded-full text-xs font-semibold bg-lokale text-white transition">Grid Kartu</button>
                <button type="button" id="btnViewTable" class="px-4 py-1.5 rounded-full text-xs font-semibold text-lokale hover:text-lokaledark transition">Tabel Data</button>
              </div>
              <button type="button" id="btnResetFilters" class="px-4 py-2 rounded-full bg-slate-100 hover:bg-slate-200 text-ink text-xs font-semibold transition">Reset Filter</button>
            </div>
          </div>

          <div class="bg-lokalesoft/50 border border-lokaleborder rounded-3xl p-5 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
              <div class="lg:col-span-2">
                <label for="filterSearch" class="block text-[11px] font-semibold text-muted mb-1">Cari Nama, Wisata, Kuliner, atau Bahasa</label>
                <input id="filterSearch" type="text" placeholder="Contoh: Horas, Toba, Durian Ucok, Mandarin..." class="w-full bg-white border border-lokaleborder focus:border-lokale rounded-xl px-3.5 py-2.5 text-xs text-ink focus:outline-none shadow-sm" />
              </div>
              <div>
                <label for="filterSpec" class="block text-[11px] font-semibold text-muted mb-1">Spesialisasi Tur</label>
                <select id="filterSpec" class="w-full bg-white border border-lokaleborder rounded-xl px-3 py-2.5 text-xs text-ink focus:outline-none">
                  <option value="ALL">Semua Spesialisasi (<?= $totalSpecs ?>)</option>
                  <?php foreach ($specializations as $sp): ?>
                    <option value="<?= esc($sp['specialization_name']) ?>"><?= esc($sp['specialization_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="filterStatus" class="block text-[11px] font-semibold text-muted mb-1">Status</label>
                <select id="filterStatus" class="w-full bg-white border border-lokaleborder rounded-xl px-3 py-2.5 text-xs text-ink focus:outline-none">
                  <option value="ALL">Semua Status (<?= $totalGuides ?>)</option>
                  <option value="Tersedia">Tersedia</option>
                  <option value="Sedang Bertugas">Sedang Bertugas</option>
                  <option value="Booked">Booked</option>
                </select>
              </div>
              <div>
                <label for="filterLang" class="block text-[11px] font-semibold text-muted mb-1">Bahasa</label>
                <select id="filterLang" class="w-full bg-white border border-lokaleborder rounded-xl px-3 py-2.5 text-xs text-ink focus:outline-none">
                  <option value="ALL">Semua Bahasa (<?= $totalLanguages ?>)</option>
                  <?php foreach ($languages as $lg): ?>
                    <option value="<?= esc($lg['language_name']) ?>"><?= esc($lg['language_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="filterDistrict" class="block text-[11px] font-semibold text-muted mb-1">Kecamatan</label>
                <select id="filterDistrict" class="w-full bg-white border border-lokaleborder rounded-xl px-3 py-2.5 text-xs text-ink focus:outline-none">
                  <option value="ALL">Semua Kecamatan (<?= $districtCount ?>)</option>
                </select>
              </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 pt-3 border-t border-lokaleborder/70">
              <div class="flex items-center gap-3 flex-1 min-w-[230px] max-w-md">
                <label for="filterMaxPrice" class="text-xs font-medium text-muted whitespace-nowrap">Maks Tarif / Hari:</label>
                <input id="filterMaxPrice" type="range" min="<?= $minGuidePrice ?>" max="<?= $maxGuidePrice ?>" step="25000" value="<?= $maxGuidePrice ?>" class="w-full accent-lokale cursor-pointer" />
                <span id="priceReadout" class="text-xs font-mono font-bold text-lokale whitespace-nowrap">Rp <?= number_format($maxGuidePrice, 0, ',', '.') ?></span>
              </div>
              <div class="flex flex-wrap items-center gap-3">
                <select id="filterSort" class="bg-white border border-lokaleborder rounded-xl px-3 py-1.5 text-xs text-ink focus:outline-none">
                  <option value="id_asc">ID Guide (TG-001 &rarr; <?= esc($lastGuideCode) ?>)</option>
                  <option value="rating_desc">Rating Tertinggi (5.0★)</option>
                  <option value="reviews_desc">Ulasan Terbanyak</option>
                  <option value="price_asc">Tarif Termurah</option>
                  <option value="price_desc">Tarif Tertinggi (VIP)</option>
                </select>
                <span class="text-xs text-muted">Menampilkan <strong id="resultCount" class="text-ink"><?= $totalGuides ?></strong> pemandu • <strong id="avgRatingReadout" class="text-amber-500"><?= $avgRatingFormatted ?>★</strong></span>
              </div>
            </div>
          </div>

          <div id="directoryGuestGateNotice" class="bg-sand/70 border border-lokaleborder rounded-2xl px-4 py-3 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2.5 text-ink">
              <span class="w-7 h-7 rounded-full bg-lokale/10 text-lokale flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
              </span>
              <span><strong>Mode Tamu (Guest):</strong> Informasi kontak, basecamp, serta detail mitra hotel &amp; armada disamarkan. Masuk atau daftar untuk membuka seluruh detail dan melakukan pemesanan.</span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <button id="btnDirectoryGuestLogin" type="button" class="px-4 py-1.5 rounded-full bg-lokale hover:bg-lokaledark text-white font-semibold transition">Masuk</button>
              <button id="btnDirectoryGuestRegister" type="button" class="px-4 py-1.5 rounded-full bg-white hover:bg-lokalesoft text-lokale border border-lokaleborder font-semibold transition">Daftar Gratis</button>
            </div>
          </div>

          <div id="guidesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"></div>

          <div id="guidesTableWrapper" class="hidden bg-white border border-lokaleborder rounded-3xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse text-xs">
                <thead>
                  <tr class="bg-lokalesoft border-b border-lokaleborder text-lokaledeep uppercase text-[10px] font-mono">
                    <th class="py-3.5 px-3">Kode</th>
                    <th class="py-3.5 px-3">Pemandu &amp; Agensi</th>
                    <th class="py-3.5 px-3">Spesialisasi</th>
                    <th class="py-3.5 px-3">Rating</th>
                    <th class="py-3.5 px-3">Bahasa</th>
                    <th class="py-3.5 px-3">Destinasi Utama</th>
                    <th class="py-3.5 px-3">Hotel &amp; Armada</th>
                    <th class="py-3.5 px-3">Tarif/Hari</th>
                    <th class="py-3.5 px-3">Status</th>
                    <th class="py-3.5 px-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody id="guidesTableBody" class="divide-y divide-slate-100"></tbody>
              </table>
            </div>
          </div>

          <div id="emptyState" class="hidden text-center py-14 bg-lokalesoft/40 border border-lokaleborder rounded-3xl">
            <p class="text-sm text-ink font-semibold">Tidak ada pemandu yang cocok dengan pencarian Anda.</p>
            <button id="btnEmptyReset" type="button" class="mt-3 px-6 py-2.5 rounded-full bg-lokale text-white text-xs font-semibold">Tampilkan Semua <?= $totalGuides ?> Pemandu</button>
          </div>
        </div>
      </div>

      <!-- SERTIFIKASI & STANDAR LAYANAN -->
      <div class="py-16 px-4 sm:px-6 lg:px-8 bg-lokalesoft/50 border-y border-lokaleborder/60">
        <div class="max-w-5xl mx-auto bg-white rounded-[2rem] border border-lokaleborder p-8 sm:p-12 shadow-sm grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
          <div class="md:col-span-4 flex flex-col items-center justify-center text-center p-6 rounded-3xl bg-lokalesoft border border-lokaleborder">
            <div class="w-16 h-16 rounded-full bg-lokale text-white flex items-center justify-center mb-3 shadow-md shadow-lokale/20">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <span class="font-display font-extrabold text-sm text-lokale uppercase tracking-wider">Standar Layanan Resmi</span>
            <span class="text-[11px] font-mono text-muted mt-1">Rata-Rata Rating <?= $avgRatingFormatted ?> / 5.0 ★</span>
          </div>
          <div class="md:col-span-8 space-y-3">
            <h2 class="font-display font-extrabold text-2xl sm:text-3xl text-ink">Sertifikasi &amp; Jaminan Kualitas Pemandu</h2>
            <p class="text-sm text-muted leading-relaxed">
              Kami berkomitmen memberikan pengalaman perjalanan terbaik di Medan dan Sumatera Utara. Seluruh <?= $totalGuides ?> pemandu terverifikasi di Google Maps, tersebar di <?= $districtCount ?> kecamatan, menguasai <?= $totalLanguages ?> bahasa, serta terintegrasi dengan mitra Wisata, Kuliner, Hotel, dan Transportasi.
            </p>
          </div>
        </div>
      </div>

      <!-- PAKET LENGKAP WISATA SUMATERA UTARA -->
      <div class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
        <div class="max-w-7xl mx-auto rounded-[2rem] bg-lokale text-white p-8 sm:p-14 shadow-xl shadow-lokale/20">
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
            <div class="lg:col-span-8 space-y-3">
              <span class="text-xs font-bold uppercase tracking-[0.2em] text-sky-200">Paket Wisata Lengkap Sumatera Utara</span>
              <h2 class="font-display font-extrabold text-3xl sm:text-4xl leading-tight">Jelajah Sumut Lebih Nyaman &amp; Terencana</h2>
              <p class="text-sm sm:text-base text-sky-50/90 max-w-2xl leading-relaxed">
                Setiap pemandu SumutGO telah terhubung dengan rekomendasi destinasi wisata unggulan, kuliner autentik, penginapan terpercaya, serta armada transportasi nyaman untuk menemani perjalanan Anda.
              </p>
            </div>
            <div class="lg:col-span-4 flex lg:justify-end">
              <button type="button" id="btnPartnershipOpenEco" class="px-8 py-3.5 rounded-full bg-white hover:bg-sand text-lokale font-bold text-sm shadow-lg transition">
                Lihat Daftar Destinasi &amp; Mitra
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- TAB 2: PETA GIS BASECAMP -->
    <section id="view-map" class="hidden py-10 px-4 sm:px-6 lg:px-8 bg-lokalesoft/30 min-h-[75vh]">
      <div class="max-w-7xl mx-auto bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-slate-100">
          <div>
            <span class="text-xs font-bold uppercase tracking-wider text-lokale">Peta Sebaran <?= $totalGuides ?> Basecamp Medan</span>
            <h2 class="font-display font-extrabold text-2xl text-ink mt-0.5">Cari Pemandu Wisata Terdekat</h2>
          </div>
          <p class="text-xs text-muted">Klik langsung pada peta atau gunakan tombol <strong class="text-lokale">Lokasi Saya</strong> di sudut kanan peta untuk menentukan titik jemput.</p>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 rounded-2xl bg-lokalesoft/50 border border-lokaleborder text-xs">
          <div class="flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-2 font-semibold text-ink cursor-pointer">
              <input type="checkbox" id="chkShowTransportNodes" class="accent-lokale" />
              <span>Tampilkan Titik Simpul Transportasi</span>
            </label>
            <select id="filterTransportNodeType" class="bg-white border border-lokaleborder rounded-xl px-2.5 py-1 text-[11px] text-ink font-medium focus:outline-none">
              <option value="ALL">Semua Tipe Simpul</option>
              <option value="bandara">Bandara</option>
              <option value="stasiun">Stasiun</option>
              <option value="terminal">Terminal</option>
              <option value="halte">Halte</option>
              <option value="pelabuhan">Pelabuhan</option>
            </select>
          </div>
          <div class="flex items-center gap-2">
            <span id="transportNodesStatus" class="text-[11px] text-muted">Centang untuk memuat simpul Bandara, Stasiun &amp; Terminal</span>
            <button type="button" id="btnRetryTransportNodes" class="hidden px-3 py-1 rounded-full bg-lokale hover:bg-lokaledark text-white text-[10px] font-bold transition">Coba Lagi</button>
          </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
          <div class="lg:col-span-8 space-y-2">
            <div id="medanLeafletMap" class="border border-lokaleborder"></div>
            <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted">
              <div>Titik Jemput: <strong id="activeRefLabel" class="text-lokale">Pusat Kota Medan</strong></div>
              <div id="mapLocationStatus" class="hidden font-medium"></div>
            </div>
          </div>
          <div class="lg:col-span-4 flex flex-col h-[520px] bg-lokalesoft/40 border border-lokaleborder rounded-2xl p-4">
            <div class="flex items-center justify-between pb-3 border-b border-lokaleborder">
              <h3 class="text-xs font-bold uppercase text-lokaledeep">Pemandu Terdekat</h3>
              <label class="inline-flex items-center gap-1.5 text-[11px] text-ink"><input type="checkbox" id="chkMapOnlyAvailable" class="accent-lokale" checked /><span>Hanya Tersedia</span></label>
            </div>
            <div id="nearestGuidesList" class="flex-1 overflow-y-auto divide-y divide-lokaleborder/60 pr-1 mt-2"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- TAB 3: KALKULATOR TRIP -->
    <section id="view-planner" class="hidden py-10 px-4 sm:px-6 lg:px-8 bg-lokalesoft/30 min-h-[75vh]">
      <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-5 bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-4 text-xs">
          <h2 class="font-display font-extrabold text-xl text-ink">Kalkulator Trip &amp; Itinerary</h2>
          <div><label class="block font-semibold mb-1">Pemandu Wisata</label><select id="planGuideSelect" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2.5"></select></div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="block font-semibold mb-1">Durasi (Hari)</label><input id="planDays" type="number" min="1" max="14" value="3" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2.5 font-mono" /></div>
            <div><label class="block font-semibold mb-1">Peserta</label><input id="planPax" type="number" min="1" max="45" value="4" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2.5 font-mono" /></div>
          </div>
          <div><label class="block font-semibold mb-1">Paket Hotel</label><select id="planHotelTier" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2.5"><option value="partner">Hotel Mitra Guide (Rp 750rb/malam)</option><option value="luxury">Hotel Bintang 5 VIP (Rp 1.45jt/malam)</option><option value="budget">Budget / EcoLodge (Rp 420rb/malam)</option><option value="none">Tanpa Menginap</option></select></div>
          <div><label class="block font-semibold mb-1">Armada</label><select id="planTransportMode" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2.5"><option value="partner">Armada Mitra Guide (Rp 650rb/hari)</option><option value="hiace">Toyota HiAce Premio (Rp 1.25jt/hari)</option><option value="bus">Big Bus 45-Seater (Rp 2.6jt/hari)</option><option value="public_api">Transportasi Publik / Multi-Moda (Transport API)</option><option value="self">Kendaraan Pribadi</option></select></div>
          <div id="planExternalTransportAppliedBox" class="hidden rounded-2xl bg-emerald-50/70 border border-emerald-200 p-3 space-y-1.5 text-[11px]">
            <div class="flex items-center justify-between gap-2">
              <span class="font-bold text-emerald-800">Rute Transport API Diterapkan</span>
              <button type="button" id="btnClearAppliedTransport" class="text-[10px] font-semibold text-rose-600 hover:underline">Reset</button>
            </div>
            <div id="planExternalTransportSummary" class="text-ink font-medium">-</div>
          </div>
          <button type="button" id="btnBookFromPlanner" class="w-full py-3 rounded-full bg-lokale hover:bg-lokaledark text-white font-bold">Booking Paket Ini</button>
        </div>
        <div class="lg:col-span-7 space-y-5">
          <div class="bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 id="planSummaryTitle" class="font-display font-bold text-lg text-ink">Paket 3 Hari</h3>
              <strong id="planCostPerPax" class="text-xl font-display font-extrabold text-lokale">Rp 0 / pax</strong>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
              <div class="bg-lokalesoft/50 border border-lokaleborder rounded-2xl p-3.5 flex justify-between"><div><span class="text-muted block">Pemandu</span><strong id="planGuideLabel">-</strong></div><span id="planGuideCost" class="font-mono font-bold text-lokale">0</span></div>
              <div class="bg-lokalesoft/50 border border-lokaleborder rounded-2xl p-3.5 flex justify-between"><div><span class="text-muted block">Armada</span><strong id="planTransLabel">-</strong></div><span id="planTransCost" class="font-mono font-bold text-lokale">0</span></div>
              <div class="bg-lokalesoft/50 border border-lokaleborder rounded-2xl p-3.5 flex justify-between"><div><span class="text-muted block">Hotel</span><strong id="planHotelLabel">-</strong></div><span id="planHotelCost" class="font-mono font-bold text-lokale">0</span></div>
              <div class="bg-lokalesoft/50 border border-lokaleborder rounded-2xl p-3.5 flex justify-between"><div><span class="text-muted block">Kuliner</span><strong id="planCulLabel">-</strong></div><span id="planCulCost" class="font-mono font-bold text-lokale">0</span></div>
            </div>
            <div class="bg-lokale text-white rounded-2xl p-4 flex items-center justify-between">
              <span class="text-xs font-semibold">Total Estimasi Keseluruhan</span>
              <strong id="planGrandTotal" class="text-2xl font-display font-extrabold">Rp 0</strong>
            </div>
          </div>
          <div class="bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-3">
            <h3 class="font-display font-bold text-base text-ink">Rencana Perjalanan Harian</h3>
            <div id="planItineraryTimeline" class="space-y-3 text-xs"></div>
          </div>
        </div>
      </div>

      <!-- INTEGRASI TRANSPORT APPLICATION API (MODA, LOKASI, TARIF & REKOMENDASI MULTI-MODA) -->
      <div class="max-w-7xl mx-auto mt-6 space-y-5">
        <div class="bg-white rounded-3xl border border-lokaleborder p-5 shadow-sm flex flex-wrap items-center justify-between gap-3">
          <div class="space-y-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-lokale">Integrasi REST API Transportasi</span>
            <h3 class="font-display font-extrabold text-lg text-ink">Tarif Simpul &amp; Rekomendasi Rute Multi-Moda</h3>
            <div id="transportModesBadges" class="flex flex-wrap items-center gap-1.5 pt-1 text-[11px] text-muted">
              <span>Menyiapkan daftar moda transportasi...</span>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <span id="transportApiHealthBadge" class="px-3 py-1 rounded-full bg-lokalesoft text-lokale border border-lokaleborder text-[11px] font-semibold">Memeriksa API...</span>
            <button type="button" id="btnRefreshTransportInit" class="px-4 py-2 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white border border-lokaleborder text-xs font-semibold transition">Muat Ulang Data Transport</button>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
          <!-- KIRI: CEK TARIF & JARAK ANTAR LOKASI -->
          <div class="lg:col-span-6 bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-4 text-xs">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
              <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-lokale">Haversine &amp; Estimasi Termurah</span>
                <h4 class="font-display font-bold text-base text-ink">Kalkulator Tarif Antar Titik Simpul</h4>
              </div>
              <select id="tarifNodeTypeFilter" class="bg-lokalesoft/50 border border-lokaleborder rounded-xl px-2.5 py-1.5 text-[11px] text-ink font-semibold focus:outline-none">
                <option value="ALL">Semua Tipe Lokasi</option>
                <option value="bandara">Bandara</option>
                <option value="stasiun">Stasiun</option>
                <option value="terminal">Terminal</option>
                <option value="halte">Halte</option>
                <option value="pelabuhan">Pelabuhan</option>
              </select>
            </div>

            <form id="transportTarifForm" class="space-y-3">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label for="tarifAsalSelect" class="block font-semibold text-muted mb-1">Titik Simpul Asal</label>
                  <select id="tarifAsalSelect" class="w-full bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3 py-2 text-xs text-ink focus:outline-none">
                    <option value="">Pilih dari daftar simpul...</option>
                  </select>
                  <input id="tarifAsalText" type="text" maxlength="100" placeholder="Atau ketik nama asal (opsional)" class="mt-1.5 w-full bg-white border border-lokaleborder focus:border-lokale rounded-xl px-3 py-1.5 text-[11px] text-ink focus:outline-none" />
                </div>
                <div>
                  <label for="tarifTujuanSelect" class="block font-semibold text-muted mb-1">Titik Simpul Tujuan</label>
                  <select id="tarifTujuanSelect" class="w-full bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3 py-2 text-xs text-ink focus:outline-none">
                    <option value="">Pilih dari daftar simpul...</option>
                  </select>
                  <input id="tarifTujuanText" type="text" maxlength="100" placeholder="Atau ketik nama tujuan (opsional)" class="mt-1.5 w-full bg-white border border-lokaleborder focus:border-lokale rounded-xl px-3 py-1.5 text-[11px] text-ink focus:outline-none" />
                </div>
              </div>

              <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="w-36">
                  <label for="tarifJamInput" class="block font-semibold text-muted mb-1">Jam Keberangkatan</label>
                  <input id="tarifJamInput" type="time" value="08:00" class="w-full bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3 py-2 font-mono text-xs text-ink focus:outline-none" />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                  <button type="button" id="btnSwapTarifNodes" class="px-3.5 py-2 rounded-full bg-lokalesoft hover:bg-lokale/15 text-lokale border border-lokaleborder font-semibold transition">⇄ Tukar</button>
                  <button type="submit" id="btnSubmitTarif" class="px-5 py-2 rounded-full bg-lokale hover:bg-lokaledark text-white font-bold shadow-sm transition">Hitung Tarif &amp; Jarak</button>
                </div>
              </div>
            </form>

            <div id="transportTarifFeedback" class="hidden p-3 rounded-2xl text-xs"></div>
            <div id="transportTarifResults" class="space-y-2.5 max-h-[340px] overflow-y-auto pr-1">
              <p class="text-[11px] text-muted py-4 text-center bg-lokalesoft/30 rounded-2xl border border-lokaleborder/60">Pilih titik asal &amp; tujuan untuk menghitung jarak dan estimasi tarif termurah.</p>
            </div>
          </div>

          <!-- KANAN: REKOMENDASI RUTE MULTI-MODA WISATA -->
          <div class="lg:col-span-6 bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-4 text-xs">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
              <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-lokale">Rute Multi-Moda Wisata</span>
                <h4 class="font-display font-bold text-base text-ink">Rekomendasi Transportasi ke Destinasi</h4>
              </div>
              <button type="button" id="btnSyncRouteWithGuide" class="px-3 py-1.5 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white border border-lokaleborder text-[11px] font-semibold transition">Ikuti Pemandu Terpilih</button>
            </div>

            <form id="transportRekomendasiForm" class="space-y-3">
              <div class="bg-lokalesoft/40 border border-lokaleborder rounded-2xl p-3 space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <span class="font-bold text-ink text-[11px]">Koordinat Posisi Asal Anda</span>
                  <div class="flex flex-wrap gap-1.5">
                    <button type="button" id="btnRouteUseMapRef" class="px-2.5 py-1 rounded-full bg-white hover:bg-lokalesoft text-lokale border border-lokaleborder text-[10px] font-semibold transition">Pakai Titik Peta</button>
                    <button type="button" id="btnRouteUseGps" class="px-2.5 py-1 rounded-full bg-white hover:bg-lokalesoft text-lokale border border-lokaleborder text-[10px] font-semibold transition">📍 GPS Saya</button>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <input id="routeUserLat" type="number" step="any" required value="3.5952" placeholder="user_lat" class="w-full bg-white border border-lokaleborder rounded-xl px-2.5 py-1.5 font-mono text-[11px] text-ink" />
                  <input id="routeUserLng" type="number" step="any" required value="98.6722" placeholder="user_lng" class="w-full bg-white border border-lokaleborder rounded-xl px-2.5 py-1.5 font-mono text-[11px] text-ink" />
                </div>
              </div>

              <div class="bg-sand/50 border border-lokaleborder rounded-2xl p-3 space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <span class="font-bold text-ink text-[11px]">Tujuan Wisata</span>
                  <div class="flex items-center gap-2">
                    <label for="routeTripType" class="text-[10px] font-semibold text-muted">Jenis:</label>
                    <select id="routeTripType" class="bg-white border border-lokaleborder rounded-full px-2.5 py-1 text-[11px] font-bold text-lokale">
                      <option value="roundtrip">Pergi–Pulang (Roundtrip)</option>
                      <option value="oneway">Sekali Jalan (Oneway)</option>
                    </select>
                  </div>
                </div>
                <input id="routeWisataName" type="text" maxlength="150" placeholder="Nama destinasi wisata (mis. Tjong A Fie Mansion)" class="w-full bg-white border border-lokaleborder rounded-xl px-3 py-1.5 text-xs text-ink" />
                <div class="grid grid-cols-2 gap-2">
                  <input id="routeWisataLat" type="number" step="any" required value="3.5891" placeholder="wisata_lat" class="w-full bg-white border border-lokaleborder rounded-xl px-2.5 py-1.5 font-mono text-[11px] text-ink" />
                  <input id="routeWisataLng" type="number" step="any" required value="98.6782" placeholder="wisata_lng" class="w-full bg-white border border-lokaleborder rounded-xl px-2.5 py-1.5 font-mono text-[11px] text-ink" />
                </div>
              </div>

              <div class="flex justify-end">
                <button type="submit" id="btnSubmitRekomendasi" class="px-5 py-2 rounded-full bg-lokale hover:bg-lokaledark text-white font-bold shadow-sm transition">Cari Rekomendasi Rute</button>
              </div>
            </form>

            <div id="transportRekomendasiFeedback" class="hidden p-3 rounded-2xl text-xs"></div>
            <div id="transportRekomendasiResults" class="space-y-2.5 max-h-[340px] overflow-y-auto pr-1">
              <p class="text-[11px] text-muted py-4 text-center bg-lokalesoft/30 rounded-2xl border border-lokaleborder/60">Klik &ldquo;Ikuti Pemandu Terpilih&rdquo; atau masukkan koordinat tujuan untuk melihat rute multi-moda &amp; estimasi biaya.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- TAB 4: DESTINASI & MITRA PERJALANAN -->
    <section id="view-analytics" class="hidden py-10 px-4 sm:px-6 lg:px-8 bg-lokalesoft/30 min-h-[75vh]">
      <div class="max-w-7xl mx-auto space-y-6">
        <div class="bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <div>
              <h2 class="font-display font-extrabold text-xl text-ink">Direktori Destinasi &amp; Mitra Perjalanan</h2>
              <p class="text-xs text-muted mt-0.5">Pilih destinasi, kuliner, hotel, atau armada untuk melihat pemandu yang melayaninya.</p>
            </div>
            <div class="flex flex-wrap gap-1.5 bg-lokalesoft p-1 rounded-full border border-lokaleborder">
              <button type="button" data-eco-tab="destinations" class="eco-tab-btn px-3.5 py-1.5 rounded-full text-xs font-semibold bg-lokale text-white transition">Wisata (<?= count($destinations ?? []) ?>)</button>
              <button type="button" data-eco-tab="culinaries" class="eco-tab-btn px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale transition">Kuliner (<?= count($culinaries ?? []) ?>)</button>
              <button type="button" data-eco-tab="hotels" class="eco-tab-btn px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale transition">Hotel (<?= count($hotels ?? []) ?>)</button>
              <button type="button" data-eco-tab="transports" class="eco-tab-btn px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale transition">Transportasi (<?= count($transports ?? []) ?>)</button>
            </div>
          </div>
          <div id="ecosystemEntitiesGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5"></div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
          <div class="bg-white rounded-3xl border border-lokaleborder p-5 shadow-sm"><h3 class="text-xs font-bold uppercase text-lokale mb-3">Rata-Rata Tarif Minat Wisata (Ribu Rp)</h3><div class="relative h-64"><canvas id="chartSpecializations"></canvas></div></div>
          <div class="bg-white rounded-3xl border border-lokaleborder p-5 shadow-sm"><h3 class="text-xs font-bold uppercase text-lokale mb-3">Bahasa yang Dikuasai (<?= $totalLanguages ?> Bahasa)</h3><div class="relative h-64"><canvas id="chartLanguages"></canvas></div></div>
          <div class="bg-white rounded-3xl border border-lokaleborder p-5 shadow-sm"><h3 class="text-xs font-bold uppercase text-lokale mb-3">Ketersediaan Pemandu Saat Ini</h3><div class="relative h-64"><canvas id="chartStatuses"></canvas></div></div>
        </div>
      </div>
    </section>
  </main>

  <!-- FOOTER -->
  <footer class="bg-lokaledeep text-white pt-14 pb-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-12 gap-10 pb-10 border-b border-white/15">
      <div class="md:col-span-5 space-y-3">
        <span class="font-display font-extrabold text-2xl tracking-tight text-white">Sumut<span class="text-sky-300 font-extrabold">GO</span></span>
        <p class="text-xs text-sky-100/80 leading-relaxed">Dari Kota Medan hingga ke Danau Toba dan Bukit Lawang, kami tumbuh jadi teman perjalanan yang setia menemani setiap langkah wisatamu.</p>
      </div>
      <div class="md:col-span-3 space-y-2 text-xs">
        <h4 class="font-bold uppercase tracking-wider mb-3">Hubungi Kami</h4>
        <p class="text-sky-100/80">Politeknik Negeri Medan, Padang Bulan, Kota Medan</p>
        <p class="text-sky-200 font-medium">Layanan Pemandu Wisata Resmi Sumatera Utara</p>
      </div>
      <div class="md:col-span-4 space-y-2 text-xs">
        <h4 class="font-bold uppercase tracking-wider mb-3">Jaringan Layanan</h4>
        <p class="text-sky-100/80"><?= $totalGuides ?> Pemandu Resmi • <?= count($destinations ?? []) ?> Destinasi Wisata • <?= count($culinaries ?? []) ?> Kuliner • <?= count($hotels ?? []) ?> Hotel Mitra • <?= count($transports ?? []) ?> Armada</p>
      </div>
    </div>
    <div class="max-w-7xl mx-auto pt-6 text-[11px] text-sky-200/70 flex flex-wrap items-center justify-between gap-2">
      <span>© Copyright 2026 SumutGO Medan. All Rights Reserved.</span>
      <a href="<?= site_url('admin') ?>" class="text-sky-200/70 hover:text-white underline-offset-2 hover:underline transition">Portal Admin &amp; API &rarr;</a>
    </div>
  </footer>

  <!-- MODAL DETAIL & BOOKING MULTI-HARI -->
  <div id="guideModal" class="hidden fixed inset-0 z-50 bg-ink/60 backdrop-blur-sm overflow-y-auto p-4">
    <div data-modal-backdrop="guideModal" class="min-h-full flex items-center justify-center">
    <div class="bg-white border border-lokaleborder rounded-3xl max-w-2xl w-full max-h-[92vh] overflow-y-auto shadow-2xl">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 sticky top-0 z-20 bg-white">
        <div>
          <div class="flex flex-wrap items-center gap-1.5">
            <span id="modalGuideId" class="text-[11px] font-mono font-bold text-lokale uppercase">TG-001</span>
            <span id="modalGuideDutyUntil" class="hidden"></span>
          </div>
          <h3 id="modalGuideName" class="text-lg font-display font-extrabold text-ink">-</h3>
        </div>
        <button id="btnCloseGuideModal" type="button" class="w-8 h-8 rounded-full bg-lokalesoft hover:bg-lokale hover:text-white text-lokale text-lg transition">&times;</button>
      </div>

      <div class="p-6 space-y-5 text-xs">
        <!-- Ringkasan Publik Pemandu (Selalu Tajam) -->
        <div class="flex flex-wrap justify-between gap-3 bg-lokalesoft/60 p-4 rounded-2xl border border-lokaleborder">
          <div>
            <div id="modalAgency" class="font-bold text-sm text-ink">-</div>
            <div class="flex flex-wrap items-center gap-2 mt-2">
              <span id="modalSpec" class="px-2.5 py-0.5 rounded-full bg-white border border-lokaleborder text-lokale font-semibold text-[11px]">-</span>
              <span id="modalLangs" class="text-[11px] font-medium text-ink">-</span>
            </div>
          </div>
          <div class="text-right">
            <div id="modalPrice" class="font-mono font-bold text-base text-lokale">-</div>
            <div id="modalRating" class="text-amber-500 font-bold mt-0.5">-</div>
          </div>
        </div>

        <!-- BLUR GATE: DETAIL LANJUTAN PEMANDU (Kontak, Basecamp, Mitra Ekosistem, Ulasan) -->
        <div id="guideModalProtectedDetails" class="relative rounded-3xl border border-lokaleborder bg-sand/30 p-4 overflow-hidden min-h-[235px]">
          <div id="guideModalProtectedContent" class="space-y-4 transition-all duration-200">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-white p-3.5 rounded-2xl border border-lokaleborder/80">
              <div>
                <span class="text-[10px] text-lokale font-bold uppercase tracking-wide block">Alamat Basecamp</span>
                <div id="modalAddress" class="text-ink font-medium mt-0.5 leading-relaxed">-</div>
                <div class="text-[10px] font-mono text-muted mt-1">Koordinat: <span id="modalCoords">-</span></div>
              </div>
              <div class="sm:text-right flex flex-col sm:items-end justify-between gap-2">
                <div>
                  <span class="text-[10px] text-lokale font-bold uppercase tracking-wide block">Kontak Pemandu</span>
                  <div id="modalPhone" class="font-mono font-bold text-sm text-ink mt-0.5">-</div>
                </div>
                <div class="flex flex-wrap items-center sm:justify-end gap-1.5">
                  <a id="modalWhatsappLink" href="#" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white border border-emerald-200 text-[11px] font-semibold transition">
                    <span>Chat WhatsApp</span>
                  </a>
                  <a id="modalCallLink" href="#" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white border border-lokaleborder text-[11px] font-semibold transition">
                    <span>Telepon</span>
                  </a>
                  <button id="btnModalCopyPhone" type="button" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white hover:bg-slate-100 text-ink border border-slate-200 text-[11px] font-semibold transition">
                    <span>Salin HP</span>
                  </button>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div class="bg-white border border-lokaleborder/70 p-3 rounded-2xl"><span class="text-[10px] text-lokale font-bold uppercase tracking-wide block">Destinasi Utama</span><strong id="modalDestName" class="text-ink block mt-0.5">-</strong></div>
              <div class="bg-white border border-lokaleborder/70 p-3 rounded-2xl"><span class="text-[10px] text-lokale font-bold uppercase tracking-wide block">Kuliner Rekomendasi</span><strong id="modalCulName" class="text-ink block mt-0.5">-</strong></div>
              <div class="bg-white border border-lokaleborder/70 p-3 rounded-2xl"><span class="text-[10px] text-lokale font-bold uppercase tracking-wide block">Hotel Mitra</span><strong id="modalHotelName" class="text-ink block mt-0.5">-</strong></div>
              <div class="bg-white border border-lokaleborder/70 p-3 rounded-2xl"><span class="text-[10px] text-lokale font-bold uppercase tracking-wide block">Armada Transportasi</span><strong id="modalTransName" class="text-ink block mt-0.5">-</strong></div>
            </div>

            <div class="space-y-2">
              <span class="text-[10px] text-lokale font-bold uppercase tracking-wide block">Ulasan Wisatawan Terbaru</span>
              <div id="modalRecentReviews" class="space-y-2"></div>
            </div>
          </div>

          <!-- OVERLAY BLUR GATE SAAT GUEST -->
          <div id="guideModalBlurOverlay" class="hidden absolute inset-0 z-10">
            <div class="w-full h-full flex items-center justify-center p-4 bg-white/55 backdrop-blur-[3px]">
            <div class="bg-white/95 border border-lokaleborder rounded-2xl p-5 shadow-xl max-w-sm w-full text-center space-y-3">
              <div class="w-11 h-11 mx-auto rounded-full bg-lokalesoft border border-lokaleborder text-lokale flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
              </div>
              <div>
                <h4 class="font-display font-extrabold text-sm text-ink">Detail Lengkap Pemandu Terkunci</h4>
                <p class="text-[11px] text-muted mt-1 leading-relaxed">Masuk atau daftar akun SumutGO untuk melihat kontak WhatsApp, alamat basecamp, mitra hotel &amp; armada, serta ulasan tamu.</p>
              </div>
              <div class="flex items-center justify-center gap-2 pt-1">
                <button id="btnModalGateLogin" type="button" class="px-5 py-2 rounded-full bg-lokale hover:bg-lokaledark text-white text-xs font-bold transition shadow-sm">Masuk</button>
                <button id="btnModalGateRegister" type="button" class="px-5 py-2 rounded-full bg-lokalesoft hover:bg-lokale/15 text-lokale border border-lokaleborder text-xs font-bold transition">Daftar Gratis</button>
              </div>
            </div>
            </div>
          </div>
        </div>

        <!-- BOOKING GATE UNTUK GUEST -->
        <div id="guideBookingAuthGate" class="border-t border-slate-100 pt-4">
          <div class="bg-lokalesoft/60 border border-lokaleborder rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <div class="font-display font-bold text-sm text-ink">Ingin memesan pemandu wisata ini?</div>
              <p class="text-[11px] text-muted mt-0.5">Silakan masuk atau daftar akun wisatawan untuk memilih tanggal tur multi-hari dan menerima kode booking resmi.</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <button id="btnBookingGateLogin" type="button" class="px-4 py-2 rounded-full bg-lokale hover:bg-lokaledark text-white text-xs font-bold transition">Masuk untuk Booking</button>
              <button id="btnBookingGateRegister" type="button" class="px-4 py-2 rounded-full bg-white hover:bg-lokalesoft text-lokale border border-lokaleborder text-xs font-bold transition">Daftar</button>
            </div>
          </div>
        </div>

        <!-- ALUR KONFIRMASI BOOKING MULTI-HARI (LOGGED IN) -->
        <div id="guideBookingFlow" class="hidden border-t border-slate-100 pt-4 space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="font-display font-extrabold text-sm text-ink">Pemesanan Tur Multi-Hari</h4>
            <div class="flex items-center gap-1.5">
              <span id="stepBadge1" class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-lokale text-white transition">1. Tanggal &amp; Hari</span>
              <span id="stepBadge2" class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-slate-100 text-muted transition">2. Konfirmasi</span>
              <span id="stepBadge3" class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-slate-100 text-muted transition">3. Bukti Resmi</span>
            </div>
          </div>

          <div id="guideRoleBookingNote" class="hidden p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 space-y-2">
            <div class="font-bold">Anda masuk sebagai akun Pemandu (Guide).</div>
            <p class="text-[11px] leading-relaxed">Pembuatan booking baru di sistem hanya tersedia untuk akun <strong>wisatawan</strong> atau <strong>admin</strong>. Anda dapat melihat dan mengelola permintaan tur yang masuk melalui menu Jadwal Tur.</p>
            <button id="btnGuideRoleViewBookings" type="button" class="px-4 py-1.5 rounded-full bg-amber-600 hover:bg-amber-700 text-white text-[11px] font-bold transition">Buka Jadwal Tur Saya</button>
          </div>

          <!-- STEP 1: PILIH TANGGAL & BERAPA HARI -->
          <div id="bookingStepForm">
            <form id="bookingForm" class="space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                  <label for="bookStartDate" class="block text-[11px] font-semibold text-muted mb-1">Tanggal Mulai Tur</label>
                  <input id="bookStartDate" type="date" required value="<?= esc($defaultBookDate) ?>" class="w-full bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3 py-2 text-xs text-ink focus:outline-none" />
                </div>
                <div>
                  <label for="bookEndDate" class="block text-[11px] font-semibold text-muted mb-1">Tanggal Selesai Tur</label>
                  <input id="bookEndDate" type="date" required value="<?= esc(date('Y-m-d', strtotime($defaultBookDate . ' +1 day'))) ?>" class="w-full bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3 py-2 text-xs text-ink focus:outline-none" />
                </div>
                <div>
                  <label for="bookGuests" class="block text-[11px] font-semibold text-muted mb-1">Jumlah Tamu (Orang)</label>
                  <input id="bookGuests" type="number" min="1" max="45" value="2" required class="w-full bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3 py-2 text-xs font-mono text-ink focus:outline-none" />
                </div>
              </div>

              <div class="bg-lokalesoft/40 border border-lokaleborder rounded-2xl p-3.5 space-y-2.5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <label for="bookDays" class="block text-[11px] font-bold text-ink">Durasi Pemesanan (Jumlah Hari)</label>
                    <span class="text-[10px] text-muted">Otomatis tersinkronisasi dua arah dengan Tanggal Selesai</span>
                  </div>
                  <div class="inline-flex items-center gap-1.5 bg-white border border-lokaleborder rounded-full p-1">
                    <button id="btnDaysMinus" type="button" aria-label="Kurangi hari" class="w-7 h-7 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white font-bold text-sm transition">&minus;</button>
                    <input id="bookDays" type="number" min="1" max="30" value="2" required class="w-12 text-center font-mono font-bold text-sm text-ink focus:outline-none bg-transparent" />
                    <span class="text-[11px] font-semibold text-muted pr-1">Hari</span>
                    <button id="btnDaysPlus" type="button" aria-label="Tambah hari" class="w-7 h-7 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white font-bold text-sm transition">+</button>
                  </div>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                  <span class="text-[10px] font-semibold text-muted mr-1">Preset cepat:</span>
                  <button type="button" data-duration-preset="1" class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-white text-lokale border border-lokaleborder">1 Hari</button>
                  <button type="button" data-duration-preset="2" class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-lokale text-white">2 Hari</button>
                  <button type="button" data-duration-preset="3" class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-white text-lokale border border-lokaleborder">3 Hari</button>
                  <button type="button" data-duration-preset="5" class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-white text-lokale border border-lokaleborder">5 Hari</button>
                  <button type="button" data-duration-preset="7" class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-white text-lokale border border-lokaleborder">7 Hari</button>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label for="bookDestinationId" class="block text-[11px] font-semibold text-muted mb-1">Destinasi Tujuan (Opsional)</label>
                  <select id="bookDestinationId" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2 text-xs text-ink focus:outline-none"></select>
                </div>
                <div>
                  <label for="bookTransportId" class="block text-[11px] font-semibold text-muted mb-1">Armada Transportasi (Opsional)</label>
                  <select id="bookTransportId" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2 text-xs text-ink focus:outline-none"></select>
                </div>
                <div>
                  <label for="bookMeetingPoint" class="block text-[11px] font-semibold text-muted mb-1">Titik Kumpul / Penjemputan</label>
                  <input id="bookMeetingPoint" type="text" maxlength="500" placeholder="Contoh: Lobby Hotel / Bandara Kualanamu" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2 text-xs text-ink focus:outline-none" />
                </div>
                <div>
                  <label for="bookNotes" class="block text-[11px] font-semibold text-muted mb-1">Catatan Tambahan (Opsional)</label>
                  <input id="bookNotes" type="text" maxlength="1000" placeholder="Misal: Jemput pukul 08.00 WIB, fokus kuliner halal" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2 text-xs text-ink focus:outline-none" />
                </div>
              </div>

              <!-- Widget Ringkas Rekomendasi Transportasi Eksternal -->
              <div class="rounded-2xl bg-lokalesoft/35 border border-lokaleborder p-3 space-y-2 text-[11px]">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <span class="font-bold text-ink">Estimasi Transportasi Publik (Transport API)</span>
                    <span class="block text-[10px] text-muted">Opsional: cek rute multi-moda menuju destinasi pemandu sebelum konfirmasi</span>
                  </div>
                  <button type="button" id="btnModalCheckTransportRoute" class="px-3 py-1 rounded-full bg-white hover:bg-lokale text-lokale hover:text-white border border-lokaleborder text-[10px] font-bold transition">Cek Rute ke Lokasi Pemandu</button>
                </div>
                <div id="modalTransportRouteResult" class="hidden"></div>
              </div>

              <div id="bookingFormError" class="hidden p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 font-semibold"></div>

              <div class="bg-lokale text-white rounded-2xl p-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                  <div id="bookDateRangeReadout" class="text-[11px] text-sky-100 font-medium">-</div>
                  <div class="text-xs font-semibold mt-0.5">Estimasi Biaya (<span id="bookBreakdownReadout" class="font-mono">-</span>)</div>
                  <strong id="bookTotalReadout" class="font-display font-extrabold text-xl">Rp 0</strong>
                </div>
                <button type="submit" id="btnProceedConfirm" class="px-6 py-2.5 rounded-full bg-white hover:bg-sand text-lokale font-bold text-xs shadow-md transition">
                  Lanjut ke Konfirmasi &rarr;
                </button>
              </div>
            </form>
          </div>

          <!-- STEP 2: RINGKASAN KONFIRMASI BOOKING -->
          <div id="bookingStepConfirm" class="hidden space-y-4">
            <div class="bg-sand/70 border border-lokaleborder rounded-2xl p-4 space-y-3">
              <div class="flex items-center justify-between border-b border-lokaleborder/70 pb-2.5">
                <div>
                  <span class="text-[10px] font-bold uppercase tracking-wider text-lokale">Tahap Konfirmasi Pemesanan</span>
                  <h5 class="font-display font-bold text-sm text-ink">Periksa Kembali Detail Booking Anda</h5>
                </div>
                <span id="confDuration" class="px-3 py-1 rounded-full bg-lokale text-white font-mono font-bold text-xs">2 Hari</span>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-[11px]">
                <div class="bg-white rounded-xl p-2.5 border border-lokaleborder/60"><span class="text-muted block">Pemandu Wisata</span><strong id="confGuideLabel" class="text-ink">-</strong></div>
                <div class="bg-white rounded-xl p-2.5 border border-lokaleborder/60"><span class="text-muted block">Rentang Tanggal Tur</span><strong id="confDateRange" class="text-ink">-</strong></div>
                <div class="bg-white rounded-xl p-2.5 border border-lokaleborder/60"><span class="text-muted block">Jumlah Tamu</span><strong id="confGuests" class="text-ink">-</strong></div>
                <div class="bg-white rounded-xl p-2.5 border border-lokaleborder/60"><span class="text-muted block">Destinasi Tujuan</span><strong id="confDestLabel" class="text-ink">-</strong></div>
                <div class="bg-white rounded-xl p-2.5 border border-lokaleborder/60"><span class="text-muted block">Armada Transportasi</span><strong id="confTransLabel" class="text-ink">-</strong></div>
                <div class="bg-white rounded-xl p-2.5 border border-lokaleborder/60"><span class="text-muted block">Titik Kumpul</span><strong id="confMeetingPoint" class="text-ink">-</strong></div>
              </div>

              <div class="bg-white rounded-xl p-2.5 border border-lokaleborder/60 text-[11px]">
                <span class="text-muted block">Catatan Tambahan</span>
                <span id="confNotes" class="text-ink font-medium">-</span>
              </div>

              <div class="bg-lokalesoft rounded-xl p-3 border border-lokaleborder flex items-center justify-between">
                <div>
                  <span class="text-[11px] text-muted block">Perhitungan Tarif (<span id="confRateBreakdown" class="font-mono">-</span>)</span>
                  <strong class="text-xs text-lokaledeep">Total Estimasi Biaya</strong>
                </div>
                <strong id="confTotalEstimate" class="font-display font-extrabold text-lg text-lokale">Rp 0</strong>
              </div>
            </div>

            <div id="bookingConfirmError" class="hidden p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 font-semibold"></div>

            <div class="flex flex-wrap items-center justify-between gap-2">
              <button id="btnBackToBookingForm" type="button" class="px-5 py-2.5 rounded-full bg-slate-100 hover:bg-slate-200 text-ink font-semibold text-xs transition">&larr; Ubah Detail</button>
              <button id="btnConfirmBookingSubmit" type="button" class="px-6 py-2.5 rounded-full bg-lokale hover:bg-lokaledark text-white font-bold text-xs shadow-md shadow-lokale/20 transition">Konfirmasi &amp; Ajukan Booking</button>
            </div>
          </div>

          <!-- STEP 3: BUKTI KONFIRMASI RESMI DARI BACKEND -->
          <div id="bookingStepSuccess" class="hidden space-y-4">
            <div class="bg-emerald-50/80 border border-emerald-200 rounded-2xl p-5 space-y-3">
              <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2.5">
                  <span class="w-9 h-9 rounded-full bg-emerald-600 text-white flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                  </span>
                  <div>
                    <h5 class="font-display font-extrabold text-sm text-emerald-950">Permintaan Booking Berhasil Diajukan!</h5>
                    <p class="text-[11px] text-emerald-800">Tercatat aman di database PostgreSQL SumutGO dengan proteksi anti double-booking.</p>
                  </div>
                </div>
                <span id="recStatusBadge" class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 border border-amber-300 font-bold uppercase text-[10px]">pending</span>
              </div>

              <div class="bg-white rounded-xl p-3.5 border border-emerald-200 grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-[11px]">
                <div><span class="text-muted block">Kode Booking Resmi</span><strong id="recBookingCode" class="font-mono text-sm text-lokale">-</strong></div>
                <div><span class="text-muted block">Total Biaya Resmi</span><strong id="recTotalPrice" class="font-mono text-sm text-emerald-700">-</strong></div>
                <div><span class="text-muted block">Pemandu Wisata</span><strong id="recGuideName" class="text-ink">-</strong></div>
                <div><span class="text-muted block">Durasi Resmi</span><strong id="recDurationDays" class="text-lokale">-</strong></div>
                <div><span class="text-muted block">Rentang Tanggal</span><strong id="recDateRange" class="text-ink">-</strong></div>
                <div><span class="text-muted block">Tamu &amp; Titik Kumpul</span><strong id="recGuests" class="text-ink">-</strong> • <span id="recMeetingPoint" class="text-muted">-</span></div>
                <div class="sm:col-span-2 pt-2 border-t border-emerald-100 flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <span class="text-muted block">Nomor HP Pemandu / Pemboking</span>
                    <strong id="recGuidePhone" class="font-mono text-xs text-ink">-</strong>
                  </div>
                  <div class="flex flex-wrap items-center gap-1.5">
                    <a id="btnReceiptWaGuide" href="#" target="_blank" rel="noopener noreferrer" class="px-3 py-1 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold transition">Chat WhatsApp</a>
                    <a id="btnReceiptCallGuide" href="#" class="px-3 py-1 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white border border-lokaleborder font-semibold transition">Telepon</a>
                    <button id="btnReceiptCopyPhone" type="button" class="px-3 py-1 rounded-full bg-slate-100 hover:bg-slate-200 text-ink font-semibold transition">Salin HP</button>
                  </div>
                </div>
              </div>

              <form id="receiptContactForm" class="bg-white rounded-xl p-3.5 border border-emerald-200 space-y-2.5 text-[11px]">
                <div class="flex items-center justify-between gap-2">
                  <span class="font-bold text-ink">Kirim Pesan / Konfirmasi Jadwal ke Pemandu &amp; Admin</span>
                  <span class="text-[10px] font-mono text-muted">POST /api/v1/booking-contacts</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <div>
                    <label for="recContactChannel" class="block text-[10px] font-semibold text-muted mb-1">Saluran Pilihan</label>
                    <select id="recContactChannel" class="w-full bg-lokalesoft/50 border border-lokaleborder rounded-xl px-2.5 py-1.5 text-xs text-ink">
                      <option value="whatsapp">WhatsApp</option>
                      <option value="phone">Telepon</option>
                      <option value="email">Email</option>
                    </select>
                  </div>
                  <div>
                    <label for="recContactPhone" class="block text-[10px] font-semibold text-muted mb-1">Nomor HP Pemesan</label>
                    <input id="recContactPhone" type="tel" maxlength="25" placeholder="081234567890" class="w-full bg-lokalesoft/50 border border-lokaleborder rounded-xl px-2.5 py-1.5 text-xs text-ink font-mono" />
                  </div>
                </div>
                <div>
                  <label for="recContactMessage" class="block text-[10px] font-semibold text-muted mb-1">Pesan untuk Pemandu / Admin</label>
                  <textarea id="recContactMessage" rows="2" required minlength="5" placeholder="Halo, saya ingin mengonfirmasi titik kumpul dan jam penjemputan..." class="w-full bg-lokalesoft/50 border border-lokaleborder rounded-xl px-3 py-2 text-xs text-ink"></textarea>
                </div>
                <div id="recContactFeedback" class="hidden p-2 rounded-xl text-[11px] font-medium"></div>
                <div class="flex justify-end">
                  <button id="btnReceiptSendContact" type="submit" class="px-4 py-1.5 rounded-full bg-lokale hover:bg-lokaledark text-white font-semibold text-xs transition">Kirim Pesan Kontak</button>
                </div>
              </form>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
              <button id="btnReceiptBookAgain" type="button" class="px-4 py-2 rounded-full bg-slate-100 hover:bg-slate-200 text-ink font-semibold text-xs transition">Buat Jadwal Lain</button>
              <button id="btnReceiptViewBookings" type="button" class="px-5 py-2.5 rounded-full bg-lokale hover:bg-lokaledark text-white font-bold text-xs shadow-sm transition">Lihat Daftar Booking Saya &rarr;</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>
  </div>

  <!-- MODAL AUTENTIKASI: MASUK & DAFTAR -->
  <div id="authModal" class="hidden fixed inset-0 z-[60] bg-ink/60 backdrop-blur-sm overflow-y-auto p-4">
    <div data-modal-backdrop="authModal" class="min-h-full flex items-center justify-center">
    <div class="bg-white border border-lokaleborder rounded-3xl max-w-md w-full max-h-[92vh] overflow-y-auto shadow-2xl">
      <div class="flex items-start justify-between px-6 py-4 border-b border-slate-100 sticky top-0 bg-white">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-lokale">Akun SumutGO</span>
          <h3 id="authModalTitle" class="text-lg font-display font-extrabold text-ink mt-0.5">Masuk ke SumutGO</h3>
          <p id="authModalSubtitle" class="text-[11px] text-muted mt-0.5">Gunakan email dan password akun SumutGO Anda.</p>
        </div>
        <button id="btnCloseAuthModal" type="button" class="w-8 h-8 rounded-full bg-lokalesoft hover:bg-lokale hover:text-white text-lokale text-lg transition">&times;</button>
      </div>

      <div class="p-6 space-y-4 text-xs">
        <div id="authContextNotice" class="hidden p-3 rounded-2xl bg-lokalesoft border border-lokaleborder text-lokaledeep text-[11px] font-medium leading-relaxed"></div>

        <div class="grid grid-cols-2 gap-1 bg-lokalesoft p-1 rounded-full border border-lokaleborder">
          <button id="btnAuthTabLogin" type="button" class="py-2 rounded-full text-xs font-bold transition bg-lokale text-white shadow-sm">Masuk</button>
          <button id="btnAuthTabRegister" type="button" class="py-2 rounded-full text-xs font-semibold transition text-lokale hover:text-lokaledark">Daftar Akun</button>
        </div>

        <!-- PANEL LOGIN -->
        <div id="authLoginPanel" class="space-y-4">
          <form id="loginForm" class="space-y-3" novalidate>
            <div>
              <label for="loginEmail" class="block text-[11px] font-semibold text-muted mb-1">Email</label>
              <input id="loginEmail" type="email" autocomplete="email" required placeholder="nama@email.com" class="w-full bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3.5 py-2.5 text-xs text-ink focus:outline-none" />
            </div>
            <div>
              <div class="flex items-center justify-between mb-1">
                <label for="loginPassword" class="text-[11px] font-semibold text-muted">Password</label>
                <button type="button" data-toggle-pw="loginPassword" class="text-[10px] font-semibold text-lokale hover:underline">Lihat</button>
              </div>
              <input id="loginPassword" type="password" autocomplete="current-password" required placeholder="Masukkan password Anda" class="w-full bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3.5 py-2.5 text-xs text-ink focus:outline-none" />
            </div>
            <div id="loginFeedback" class="hidden"></div>
            <button id="btnLoginSubmit" type="submit" class="w-full py-2.5 rounded-full bg-lokale hover:bg-lokaledark text-white font-bold text-xs shadow-md shadow-lokale/20 transition">Masuk Sekarang</button>
          </form>

          <div class="bg-sand/70 border border-lokaleborder rounded-2xl p-3.5 space-y-2">
            <div class="flex items-center justify-between gap-2">
              <span class="text-[10px] font-bold uppercase tracking-wider text-lokale">Isi Cepat Akun Uji (Email &amp; Password)</span>
              <span class="text-[10px] text-muted">Role diambil dari DB</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-1.5">
              <button type="button" data-demo-account="wisatawan" title="Isi kredensial budi@wisatawan.id" class="px-2 py-1.5 rounded-xl bg-white hover:bg-lokalesoft border border-lokaleborder text-[10px] font-mono font-semibold text-ink truncate transition">budi@wisatawan.id</button>
              <button type="button" data-demo-account="guide" title="Isi kredensial horas@guide.id" class="px-2 py-1.5 rounded-xl bg-white hover:bg-lokalesoft border border-lokaleborder text-[10px] font-mono font-semibold text-ink truncate transition">horas@guide.id</button>
              <button type="button" data-demo-account="admin" title="Isi kredensial admin@sumutgo.id" class="px-2 py-1.5 rounded-xl bg-white hover:bg-lokalesoft border border-lokaleborder text-[10px] font-mono font-semibold text-ink truncate transition">admin@sumutgo.id</button>
            </div>
          </div>
        </div>

        <!-- PANEL REGISTER -->
        <div id="authRegisterPanel" class="hidden space-y-4">
          <form id="registerForm" class="space-y-3" novalidate>
            <div>
              <label for="regFullName" class="block text-[11px] font-semibold text-muted mb-1">Nama Lengkap</label>
              <input id="regFullName" type="text" autocomplete="name" required minlength="2" maxlength="100" placeholder="Contoh: Nadia Putri Nasution" class="w-full bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3.5 py-2.5 text-xs text-ink focus:outline-none" />
            </div>
            <div>
              <label for="regEmail" class="block text-[11px] font-semibold text-muted mb-1">Alamat Email</label>
              <input id="regEmail" type="email" autocomplete="email" required maxlength="100" placeholder="nadia@email.com" class="w-full bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3.5 py-2.5 text-xs text-ink focus:outline-none" />
            </div>
            <div>
              <label for="regPhoneNumber" class="block text-[11px] font-semibold text-muted mb-1">Nomor HP / WhatsApp <span class="text-rose-500">*</span></label>
              <input id="regPhoneNumber" type="tel" autocomplete="tel" required minlength="8" maxlength="25" placeholder="081234567890 atau +6281234567890" class="w-full bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3.5 py-2.5 text-xs text-ink font-mono focus:outline-none" />
              <p class="text-[10px] text-muted mt-1">Nomor dinormalisasi otomatis ke format internasional (<code>62...</code>) untuk koordinasi tur &amp; konfirmasi admin.</p>
            </div>
            <div>
              <div class="flex items-center justify-between mb-1">
                <label for="regPassword" class="text-[11px] font-semibold text-muted">Password (min. 8 karakter)</label>
                <button type="button" data-toggle-pw="regPassword" class="text-[10px] font-semibold text-lokale hover:underline">Lihat</button>
              </div>
              <input id="regPassword" type="password" autocomplete="new-password" required minlength="8" placeholder="Minimal 8 karakter" class="w-full bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3.5 py-2.5 text-xs text-ink focus:outline-none" />
            </div>
            <div>
              <div class="flex items-center justify-between mb-1">
                <label for="regPasswordConfirm" class="text-[11px] font-semibold text-muted">Konfirmasi Password</label>
                <button type="button" data-toggle-pw="regPasswordConfirm" class="text-[10px] font-semibold text-lokale hover:underline">Lihat</button>
              </div>
              <input id="regPasswordConfirm" type="password" autocomplete="new-password" required minlength="8" placeholder="Ulangi password Anda" class="w-full bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3.5 py-2.5 text-xs text-ink focus:outline-none" />
            </div>
            <div id="registerFeedback" class="hidden"></div>
            <button id="btnRegisterSubmit" type="submit" class="w-full py-2.5 rounded-full bg-lokale hover:bg-lokaledark text-white font-bold text-xs shadow-md shadow-lokale/20 transition">Daftar &amp; Masuk</button>
          </form>
        </div>
      </div>
    </div>
    </div>
  </div>

  <!-- MODAL KONFIRMASI ADMIN (PENDAFTAR, KONTAK & BOOKING) -->
  <div id="adminConfirmModal" class="hidden fixed inset-0 z-50 bg-ink/60 backdrop-blur-sm overflow-y-auto p-4">
    <div data-modal-backdrop="adminConfirmModal" class="min-h-full flex items-center justify-center">
      <div class="bg-white border border-lokaleborder rounded-3xl max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="flex flex-wrap items-center justify-between gap-2 px-6 py-4 border-b border-slate-100 sticky top-0 z-10 bg-white">
          <div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Panel Verifikasi &amp; Konfirmasi Admin</span>
            <h3 class="text-lg font-display font-extrabold text-ink">Konfirmasi Pendaftar, Kontak &amp; Booking</h3>
          </div>
          <div class="flex items-center gap-2">
            <a href="<?= site_url('admin') ?>" class="px-3 py-1.5 rounded-full bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white border border-emerald-200 text-xs font-semibold transition">Portal Admin &rarr;</a>
            <button id="btnRefreshAdminConfirm" type="button" class="px-3 py-1.5 rounded-full bg-lokalesoft hover:bg-lokale hover:text-white text-lokale text-xs font-semibold transition">Muat Ulang</button>
            <button id="btnCloseAdminConfirmModal" type="button" class="w-8 h-8 rounded-full bg-lokalesoft hover:bg-lokale hover:text-white text-lokale text-lg transition">&times;</button>
          </div>
        </div>
        <div class="p-6 space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap gap-1.5 bg-lokalesoft p-1 rounded-full">
              <button type="button" data-admin-tab="users" class="px-3.5 py-1.5 rounded-full text-xs font-bold bg-lokale text-white transition">Verifikasi Pendaftar &amp; HP</button>
              <button type="button" data-admin-tab="contacts" class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale hover:bg-white/80 transition">Kontak &amp; Pesan</button>
              <button type="button" data-admin-tab="bookings" class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale hover:bg-white/80 transition">Konfirmasi Booking</button>
            </div>
            <span id="adminConfirmSummary" class="text-[11px] font-semibold text-muted">Memuat...</span>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <select id="adminConfirmStatusFilter" class="bg-lokalesoft/50 border border-lokaleborder rounded-xl px-3 py-2 text-xs text-ink font-semibold">
              <option value="ALL">Semua Status</option>
            </select>
            <input id="adminConfirmSearch" type="search" placeholder="Cari nama, kode booking, email, atau nomor HP..." class="flex-1 min-w-[200px] bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3.5 py-2 text-xs text-ink focus:outline-none" />
          </div>
          <div id="adminConfirmFeedback" class="hidden p-3 rounded-2xl text-xs font-medium"></div>
          <div id="adminConfirmBody" class="space-y-3 text-xs"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL BOOKING SAYA -->
  <div id="myBookingsModal" class="hidden fixed inset-0 z-50 bg-ink/60 backdrop-blur-sm overflow-y-auto p-4">
    <div data-modal-backdrop="myBookingsModal" class="min-h-full flex items-center justify-center">
    <div class="bg-white border border-lokaleborder rounded-3xl max-w-3xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 sticky top-0 z-10 bg-white">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-lokale">Riwayat &amp; Status Reservasi</span>
          <h3 class="text-lg font-display font-extrabold text-ink">Daftar Booking Anda</h3>
        </div>
        <div class="flex items-center gap-2">
          <button id="btnBookingsOpenAdminConfirm" type="button" class="hidden px-3 py-1.5 rounded-full bg-amber-50 hover:bg-amber-600 text-amber-800 hover:text-white border border-amber-200 text-xs font-semibold transition">Panel Konfirmasi Admin</button>
          <button id="btnRefreshMyBookings" type="button" class="px-3 py-1.5 rounded-full bg-lokalesoft hover:bg-lokale hover:text-white text-lokale text-xs font-semibold transition">Muat Ulang</button>
          <button id="btnCloseMyBookingsModal" type="button" class="w-8 h-8 rounded-full bg-lokalesoft hover:bg-lokale hover:text-white text-lokale text-lg transition">&times;</button>
        </div>
      </div>
      <div class="p-6 space-y-4">
        <div id="myBookingsUserSummary" class="hidden rounded-2xl bg-lokalesoft/50 border border-lokaleborder px-4 py-3 text-xs"></div>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex flex-wrap gap-1.5">
            <button type="button" data-booking-filter="ALL" class="px-3 py-1 rounded-full text-[11px] font-bold bg-lokale text-white transition">Semua</button>
            <button type="button" data-booking-filter="pending" class="px-3 py-1 rounded-full text-[11px] font-semibold bg-lokalesoft text-lokale hover:bg-lokale/10 transition">Pending</button>
            <button type="button" data-booking-filter="confirmed" class="px-3 py-1 rounded-full text-[11px] font-semibold bg-lokalesoft text-lokale hover:bg-lokale/10 transition">Confirmed</button>
            <button type="button" data-booking-filter="ongoing" class="px-3 py-1 rounded-full text-[11px] font-semibold bg-lokalesoft text-lokale hover:bg-lokale/10 transition">Ongoing</button>
            <button type="button" data-booking-filter="completed" class="px-3 py-1 rounded-full text-[11px] font-semibold bg-lokalesoft text-lokale hover:bg-lokale/10 transition">Completed</button>
            <button type="button" data-booking-filter="cancelled" class="px-3 py-1 rounded-full text-[11px] font-semibold bg-lokalesoft text-lokale hover:bg-lokale/10 transition">Cancelled</button>
          </div>
          <span id="myBookingsCountInfo" class="text-[11px] text-muted font-medium">0 reservasi</span>
        </div>
        <div id="myBookingsList" class="space-y-3"></div>
      </div>
    </div>
    </div>
  </div>

  <!-- MODAL COMPARE -->
  <div id="compareModal" class="hidden fixed inset-0 z-50 bg-ink/60 backdrop-blur-sm overflow-y-auto p-4">
    <div data-modal-backdrop="compareModal" class="min-h-full flex items-center justify-center">
    <div class="bg-white rounded-3xl max-w-4xl w-full max-h-[88vh] overflow-y-auto p-6 space-y-4">
      <div class="flex justify-between items-center border-b pb-3"><h3 class="font-display font-bold text-base">Bandingkan Pemandu</h3><div class="flex gap-2"><button id="btnClearCompare" type="button" class="px-3 py-1 rounded-full bg-slate-100 text-xs">Kosongkan</button><button id="btnCloseCompareModal" type="button" class="w-8 h-8 rounded-full bg-lokalesoft text-lokale">&times;</button></div></div>
      <div id="compareModalBody" class="text-xs"></div>
    </div>
    </div>
  </div>

  <!-- TOAST NOTIFICATION -->
  <div id="sumutgoToast" role="status" aria-live="polite" class="hidden fixed bottom-5 right-5 z-[70] max-w-sm">
    <div id="sumutgoToastCard" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-900 shadow-xl flex items-center gap-2.5">
      <span id="sumutgoToastDot" class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
      <span id="sumutgoToastMsg"></span>
    </div>
  </div>

<?php
  $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
  if ($scriptDir === '/') {
      $scriptDir = '';
  }
  $apiPathPrefix = $scriptDir . '/index.php/api/v1';
  $bootstrapPayload = [
      'apiV1BaseUrl'    => $apiPathPrefix,
      'siteUrl'         => $scriptDir !== '' ? $scriptDir : '/',
      'defaultMaxPrice' => (int) $maxGuidePrice,
      'defaultBookDate' => $defaultBookDate,
      'initialGuides'   => $guides,
      'masterEcosystem' => [
          'destinations' => $destinations,
          'culinaries'   => $culinaries,
          'hotels'       => $hotels,
          'transports'   => $transports,
      ],
  ];
?>
  <script id="sumutgo-bootstrap-data" type="application/json"><?= json_encode($bootstrapPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
  <script>
    window.SUMUTGO_CONFIG = JSON.parse(document.getElementById('sumutgo-bootstrap-data')?.textContent || '{}');
  </script>
  <script src="<?= esc($scriptDir . '/assets/js/api.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/api.js') ?>"></script>
  <script src="<?= esc($scriptDir . '/assets/js/auth.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/auth.js') ?>"></script>
  <script src="<?= esc($scriptDir . '/assets/js/app.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/app.js') ?>"></script>
</body>
</html>