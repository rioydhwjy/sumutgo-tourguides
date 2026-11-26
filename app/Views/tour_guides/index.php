<?php
$totalGuides = count($guides ?? []);
$availableGuides = (int) ($stats['available_guides'] ?? count(array_filter($guides ?? [], static fn ($g) => ($g['status'] ?? '') === 'Tersedia')));
$totalReviews = array_sum(array_map(static fn ($g) => (int) ($g['reviews'] ?? 0), $guides ?? []));
$avgRatingFormatted = number_format((float) ($stats['avg_rating'] ?? 4.73), 2, '.', '');
$totalLanguages = count($languages ?? []);
$totalSpecs = count($specializations ?? []);
$districtList = array_values(array_unique(array_filter(array_map(static fn ($g) => (string) ($g['district'] ?? ''), $guides ?? []), static fn ($d) => $d !== '' && $d !== 'Kota Medan' && $d !== 'Medan')));
$districtCount = count($districtList) ?: 15;
$prices = array_column($guides ?? [], 'price');
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
  <title><?= esc($title ?? 'SumutGO | Pemandu Wisata Lokal Medan, Menemani Perjalananmu') ?></title>
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
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
  </style>
</head>
<body class="bg-white text-ink antialiased selection:bg-lokale selection:text-white">

  <!-- STICKY CLEAN NAVBAR (LOKALE.COFFEE STYLE) -->
  <header class="sticky top-0 z-40 nav-blur border-b border-slate-200/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4">
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

      <div class="flex items-center gap-2">
        <button id="btnToggleCompareModal" type="button" class="px-3.5 py-2 rounded-full bg-lokalesoft hover:bg-lokale hover:text-white text-lokale text-xs font-semibold border border-lokaleborder transition flex items-center gap-1.5">
          <span>Bandingkan</span>
          <span id="compareCountBadge" class="px-1.5 py-0.5 rounded-full bg-lokale text-white text-[10px] font-bold">0</span>
        </button>
        <button id="btnFilterFavorites" type="button" class="px-3 py-2 rounded-full bg-white hover:bg-rose-50 border border-slate-200 text-xs font-semibold text-ink flex items-center gap-1 transition">
          <span class="text-rose-500">♥</span>
          <span id="favCountBadge" class="font-mono text-[11px]">0</span>
        </button>
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

    <!-- TAB 1: BERANDA ALA LOKALE.COFFEE + DIREKTORI 50 PEMANDU -->
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

      <!-- DIREKTORI LENGKAP 50 PEMANDU -->
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
              <button type="button" id="btnExportCsvFiltered" class="px-4 py-2 rounded-full bg-white hover:bg-lokalesoft border border-lokaleborder text-lokale text-xs font-semibold transition">Unduh CSV</button>
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
          <div class="flex flex-wrap items-center gap-2">
            <button type="button" data-landmark="3.5612,98.6564,Kampus Politeknik Negeri Medan (Polmed)" class="landmark-btn px-3.5 py-2 rounded-full bg-lokale text-white font-semibold text-xs">📍 Polmed</button>
            <button type="button" data-landmark="3.5896,98.6738,Stasiun Besar Medan / Merdeka Walk" class="landmark-btn px-3.5 py-2 rounded-full bg-lokalesoft text-lokale font-semibold text-xs">📍 Merdeka Walk</button>
            <button type="button" data-landmark="3.5752,98.6837,Istana Maimun" class="landmark-btn px-3.5 py-2 rounded-full bg-lokalesoft text-lokale font-semibold text-xs">📍 Istana Maimun</button>
            <button type="button" data-landmark="3.6422,98.8853,Bandara Kualanamu (KNO)" class="landmark-btn px-3.5 py-2 rounded-full bg-lokalesoft text-lokale font-semibold text-xs">✈️ Kualanamu</button>
          </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
          <div class="lg:col-span-8 space-y-2">
            <div id="medanLeafletMap" class="border border-lokaleborder"></div>
            <div class="text-[11px] text-muted">Titik Jemput: <strong id="activeRefLabel" class="text-lokale">Politeknik Negeri Medan</strong></div>
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
          <div><label class="block font-semibold mb-1">Armada</label><select id="planTransportMode" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2.5"><option value="partner">Armada Mitra Guide (Rp 650rb/hari)</option><option value="hiace">Toyota HiAce Premio (Rp 1.25jt/hari)</option><option value="bus">Big Bus 45-Seater (Rp 2.6jt/hari)</option><option value="self">Kendaraan Pribadi</option></select></div>
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

  <!-- MODAL DETAIL & BOOKING -->
  <div id="guideModal" class="fixed inset-0 z-50 hidden bg-ink/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white border border-lokaleborder rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 sticky top-0 bg-white">
        <div>
          <span id="modalGuideId" class="text-[11px] font-mono font-bold text-lokale uppercase">TG-001</span>
          <h3 id="modalGuideName" class="text-lg font-display font-extrabold text-ink">-</h3>
        </div>
        <button id="btnCloseGuideModal" type="button" class="w-8 h-8 rounded-full bg-lokalesoft text-lokale text-lg">&times;</button>
      </div>
      <div class="p-6 space-y-4 text-xs">
        <div class="flex flex-wrap justify-between gap-3 bg-lokalesoft/60 p-4 rounded-2xl border border-lokaleborder">
          <div>
            <div id="modalAgency" class="font-bold text-sm text-ink">-</div>
            <div id="modalAddress" class="text-muted text-[11px] mt-1">-</div>
            <div class="flex flex-wrap items-center gap-2 mt-2">
              <span id="modalSpec" class="px-2.5 py-0.5 rounded-full bg-white border border-lokaleborder text-lokale font-semibold text-[11px]">-</span>
              <span id="modalLangs" class="text-[11px] font-medium text-ink">-</span>
            </div>
          </div>
          <div class="text-right">
            <div id="modalPrice" class="font-mono font-bold text-base text-lokale">-</div>
            <div id="modalRating" class="text-amber-500 font-bold mt-0.5">-</div>
            <div id="modalPhone" class="text-[11px] font-mono text-muted mt-1">-</div>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="bg-sand/60 p-3 rounded-2xl"><span class="text-[10px] text-lokale font-bold uppercase tracking-wide block">Destinasi Utama</span><strong id="modalDestName">-</strong></div>
          <div class="bg-sand/60 p-3 rounded-2xl"><span class="text-[10px] text-lokale font-bold uppercase tracking-wide block">Kuliner Rekomendasi</span><strong id="modalCulName">-</strong></div>
          <div class="bg-sand/60 p-3 rounded-2xl"><span class="text-[10px] text-lokale font-bold uppercase tracking-wide block">Hotel Mitra</span><strong id="modalHotelName">-</strong></div>
          <div class="bg-sand/60 p-3 rounded-2xl"><span class="text-[10px] text-lokale font-bold uppercase tracking-wide block">Armada Transportasi</span><strong id="modalTransName">-</strong></div>
        </div>
        <form id="bookingForm" class="border-t border-slate-100 pt-4 space-y-3">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div><label for="bookTravelerName" class="block text-[10px] font-semibold text-muted mb-1">Nama Pemesan</label><input id="bookTravelerName" type="text" required value="Rio Yudha Wijaya" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2" /></div>
            <div><label for="bookDate" class="block text-[10px] font-semibold text-muted mb-1">Tanggal Tur</label><input id="bookDate" type="date" required value="<?= esc($defaultBookDate) ?>" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2" /></div>
            <div><label for="bookDays" class="block text-[10px] font-semibold text-muted mb-1">Durasi (Hari)</label><input id="bookDays" type="number" min="1" max="14" value="2" required class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2" /></div>
          </div>
          <div class="flex items-center justify-between pt-2">
            <div>Total: <strong id="bookTotalReadout" class="font-mono text-lokale text-sm">-</strong></div>
            <button type="submit" id="btnSubmitBooking" class="px-6 py-2 rounded-full bg-lokale hover:bg-lokaledark text-white font-bold transition">Konfirmasi Booking</button>
          </div>
          <div id="bookingFeedback" class="hidden p-3 rounded-2xl bg-lokalesoft border border-lokale text-lokaledeep font-medium"></div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL COMPARE -->
  <div id="compareModal" class="fixed inset-0 z-50 hidden bg-ink/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-4xl w-full max-h-[88vh] overflow-y-auto p-6 space-y-4">
      <div class="flex justify-between items-center border-b pb-3"><h3 class="font-display font-bold text-base">Bandingkan Pemandu</h3><div class="flex gap-2"><button id="btnClearCompare" type="button" class="px-3 py-1 rounded-full bg-slate-100 text-xs">Kosongkan</button><button id="btnCloseCompareModal" type="button" class="w-8 h-8 rounded-full bg-lokalesoft text-lokale">&times;</button></div></div>
      <div id="compareModalBody" class="text-xs"></div>
    </div>
  </div>

  <script>
    const TOUR_GUIDES = <?= json_encode($guides, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const MASTER_ECOSYSTEM = <?= json_encode([
        'destinations' => $destinations ?? [],
        'culinaries'   => $culinaries ?? [],
        'hotels'       => $hotels ?? [],
        'transports'   => $transports ?? [],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const API_BASE_URL = '<?= rtrim(site_url('api/tour-guides'), '/') ?>';
    const DEFAULT_MAX_PRICE = <?= (int) $maxGuidePrice ?>;

    let state = {
      activeTab: "directory",
      viewMode: "cards",
      search: "",
      spec: "ALL",
      status: "ALL",
      lang: "ALL",
      district: "ALL",
      sort: "id_asc",
      maxPrice: DEFAULT_MAX_PRICE,
      onlyFavorites: false,
      favorites: new Set(["TG-001", "TG-013"]),
      compareList: ["TG-001", "TG-013"],
      activeGuideId: "TG-001",
      refLat: 3.5612,
      refLng: 98.6564,
      refLabel: "Politeknik Negeri Medan",
      ecoTab: "destinations"
    };
    let leafletMap = null, leafletMarkersLayer = null, chartsInitialized = false;

    const formatIdr = n => "Rp " + Number(n || 0).toLocaleString("id-ID");
    const escapeHtml = v => String(v ?? "").replace(/[&<>"']/g, ch => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[ch]));
    const getLangNames = g => {
      if (Array.isArray(g?.language_names) && g.language_names.length) return g.language_names;
      if (Array.isArray(g?.languages)) return g.languages.map(l => typeof l === "string" ? l : (l?.language_name || "")).filter(Boolean);
      if (typeof g?.spoken_languages === "string" && g.spoken_languages.trim()) return g.spoken_languages.split(",").map(s => s.trim()).filter(Boolean);
      return [];
    };
    const formatDistrict = d => {
      const val = String(d || "Kota Medan").trim();
      return val.startsWith("Medan ") ? `Kec. ${val}` : val;
    };
    const haversineKm = (a1, o1, a2, o2) => {
      const R = 6371, dA = (a2 - a1) * Math.PI / 180, dO = (o2 - o1) * Math.PI / 180;
      const a = Math.sin(dA / 2) ** 2 + Math.cos(a1 * Math.PI / 180) * Math.cos(a2 * Math.PI / 180) * Math.sin(dO / 2) ** 2;
      return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };
    const statusBadgeClass = s => s === "Tersedia" ? "bg-emerald-50 text-emerald-700 border-emerald-200" : (s === "Sedang Bertugas" ? "bg-amber-50 text-amber-700 border-amber-200" : "bg-rose-50 text-rose-700 border-rose-200");

    function updateTopKpis() {
      document.getElementById("heroAvailPill").textContent = `${TOUR_GUIDES.filter(g => g.status === "Tersedia").length} Tersedia`;
      document.getElementById("statTotalGuides").textContent = String(TOUR_GUIDES.length);
      document.getElementById("compareCountBadge").textContent = String(state.compareList.length);
      document.getElementById("favCountBadge").textContent = String(state.favorites.size);
      const favBtn = document.getElementById("btnFilterFavorites");
      favBtn.className = state.onlyFavorites
        ? "px-3 py-2 rounded-full bg-rose-500 text-white border border-rose-500 text-xs font-semibold flex items-center gap-1 transition shadow-sm"
        : "px-3 py-2 rounded-full bg-white hover:bg-rose-50 border border-slate-200 text-xs font-semibold text-ink flex items-center gap-1 transition";
      favBtn.querySelector("span").className = state.onlyFavorites ? "text-white" : "text-rose-500";
    }

    function updateViewModeButtons() {
      const btnCards = document.getElementById("btnViewCards");
      const btnTable = document.getElementById("btnViewTable");
      const activeCls = "px-4 py-1.5 rounded-full text-xs font-semibold bg-lokale text-white transition";
      const inactiveCls = "px-4 py-1.5 rounded-full text-xs font-semibold text-lokale hover:text-lokaledark transition";
      btnCards.className = state.viewMode === "cards" ? activeCls : inactiveCls;
      btnTable.className = state.viewMode === "table" ? activeCls : inactiveCls;
    }

    function getFilteredGuides() {
      const q = state.search.trim().toLowerCase();
      return TOUR_GUIDES.filter(g => {
        const langs = getLangNames(g);
        if (state.onlyFavorites && !state.favorites.has(g.id)) return false;
        if (state.spec !== "ALL" && g.specialization !== state.spec) return false;
        if (state.status !== "ALL" && g.status !== state.status) return false;
        if (state.lang !== "ALL" && !langs.includes(state.lang)) return false;
        if (state.district !== "ALL" && g.district !== state.district) return false;
        if (g.price > state.maxPrice) return false;
        if (q && ![g.id, g.name, g.agency, g.district, g.address, g.specialization, g.destId, g.destName, g.culinaryId, g.culinaryName, g.hotelId, g.hotelName, g.transportId, g.transportName, langs.join(" ")].join(" ").toLowerCase().includes(q)) return false;
        return true;
      }).sort((a, b) => state.sort === "rating_desc" ? b.rating - a.rating : state.sort === "reviews_desc" ? b.reviews - a.reviews : state.sort === "price_asc" ? a.price - b.price : state.sort === "price_desc" ? b.price - a.price : a.id.localeCompare(b.id));
    }

    function renderDirectory() {
      updateTopKpis();
      updateViewModeButtons();
      const list = getFilteredGuides();
      document.getElementById("resultCount").textContent = String(list.length);
      document.getElementById("avgRatingReadout").textContent = (list.length ? (list.reduce((a, b) => a + b.rating, 0) / list.length).toFixed(2) : "0.00") + "★";
      const grid = document.getElementById("guidesGrid"), wrap = document.getElementById("guidesTableWrapper"), tbody = document.getElementById("guidesTableBody"), empty = document.getElementById("emptyState");
      if (!list.length) {
        grid.classList.add("hidden");
        wrap.classList.add("hidden");
        empty.classList.remove("hidden");
        return;
      }
      empty.classList.add("hidden");
      if (state.viewMode === "cards") {
        grid.classList.remove("hidden");
        wrap.classList.add("hidden");
        grid.innerHTML = list.map(g => {
          const langs = getLangNames(g).join(", ");
          const distLabel = formatDistrict(g.district);
          return `<article class="lokale-card rounded-3xl p-5 flex flex-col justify-between">
            <div>
              <div class="flex items-center justify-between gap-2 mb-3">
                <div class="flex items-center gap-1.5 min-w-0">
                  <span class="font-mono text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-lokalesoft text-lokale shrink-0">${escapeHtml(g.id)}</span>
                  <span class="text-[11px] text-muted truncate">${escapeHtml(g.specialization)}</span>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                  <button type="button" data-fav-btn="${escapeHtml(g.id)}" aria-label="Favorit ${escapeHtml(g.name)}" class="w-7 h-7 rounded-full border text-xs transition ${state.favorites.has(g.id) ? 'bg-rose-50 border-rose-200 text-rose-500' : 'bg-white border-slate-200 text-slate-400 hover:text-rose-500'}">♥</button>
                  <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border ${statusBadgeClass(g.status)}">${escapeHtml(g.status)}</span>
                </div>
              </div>
              <h3 class="font-display font-bold text-base text-ink">${escapeHtml(g.name)}</h3>
              <p class="text-xs text-muted mt-0.5">${escapeHtml(g.agency)} • ${escapeHtml(distLabel)}</p>
              <div class="flex flex-wrap items-center gap-2 mt-2.5 text-xs">
                <span class="text-amber-500 font-bold">★ ${Number(g.rating).toFixed(1)}</span>
                <span class="text-muted">(${Number(g.reviews).toLocaleString("id-ID")})</span>
                <span class="text-[11px] font-medium text-lokale">${escapeHtml(langs || "Indonesia")}</span>
              </div>
              <div class="mt-4 pt-3 border-t border-slate-100 space-y-1 text-[11px]">
                <div class="truncate"><span class="font-semibold text-lokale">Wisata:</span> ${escapeHtml(g.destName)}</div>
                <div class="truncate text-muted"><span class="font-semibold text-ink/70">Kuliner:</span> ${escapeHtml(g.culinaryName)}</div>
                <div class="truncate text-muted"><span class="font-semibold text-ink/70">Mitra:</span> ${escapeHtml(g.hotelName)} • ${escapeHtml(g.transportName)}</div>
              </div>
            </div>
            <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
              <div><span class="text-[10px] text-muted block uppercase">Tarif/Hari</span><strong class="text-sm font-mono text-lokale">${formatIdr(g.price)}</strong></div>
              <div class="flex gap-1.5">
                <button type="button" data-comp-btn="${escapeHtml(g.id)}" class="px-3 py-1.5 rounded-full text-[11px] font-semibold border transition ${state.compareList.includes(g.id) ? 'bg-lokalesoft text-lokale border-lokale' : 'bg-white text-muted border-slate-200 hover:border-lokale'}">${state.compareList.includes(g.id) ? '✓' : '+'} Banding</button>
                <button type="button" data-open-guide="${escapeHtml(g.id)}" class="px-4 py-1.5 rounded-full bg-lokale hover:bg-lokaledark text-white text-xs font-semibold transition">Detail</button>
              </div>
            </div>
          </article>`;
        }).join("");
      } else {
        grid.classList.add("hidden");
        wrap.classList.remove("hidden");
        tbody.innerHTML = list.map(g => {
          const langs = getLangNames(g).join(", ");
          return `<tr class="hover:bg-lokalesoft/40">
            <td class="py-3 px-3 font-mono font-bold text-lokale whitespace-nowrap">${escapeHtml(g.id)}</td>
            <td class="py-3 px-3"><div class="font-bold">${escapeHtml(g.name)}</div><div class="text-[11px] text-muted">${escapeHtml(g.agency)}</div></td>
            <td class="py-3 px-3">${escapeHtml(g.specialization)}</td>
            <td class="py-3 px-3 text-amber-500 font-bold whitespace-nowrap">★ ${Number(g.rating).toFixed(1)}</td>
            <td class="py-3 px-3 text-lokale">${escapeHtml(langs || "Indonesia")}</td>
            <td class="py-3 px-3">${escapeHtml(g.destName)}</td>
            <td class="py-3 px-3 text-muted">${escapeHtml(g.hotelName)} • ${escapeHtml(g.transportName)}</td>
            <td class="py-3 px-3 font-mono font-bold text-lokale whitespace-nowrap">${formatIdr(g.price)}</td>
            <td class="py-3 px-3 whitespace-nowrap"><span class="text-[10px] font-bold px-2 py-0.5 rounded-full border ${statusBadgeClass(g.status)}">${escapeHtml(g.status)}</span></td>
            <td class="py-3 px-3 text-right"><button type="button" data-open-guide="${escapeHtml(g.id)}" class="px-3 py-1 rounded-full bg-lokale hover:bg-lokaledark text-white text-[11px] transition">Detail</button></td>
          </tr>`;
        }).join("");
      }
      document.querySelectorAll("[data-open-guide]").forEach(b => b.addEventListener("click", () => openGuideModal(b.dataset.openGuide)));
      document.querySelectorAll("[data-fav-btn]").forEach(b => b.addEventListener("click", () => {
        state.favorites.has(b.dataset.favBtn) ? state.favorites.delete(b.dataset.favBtn) : state.favorites.add(b.dataset.favBtn);
        renderDirectory();
      }));
      document.querySelectorAll("[data-comp-btn]").forEach(b => b.addEventListener("click", () => {
        const i = state.compareList.indexOf(b.dataset.compBtn);
        if (i >= 0) state.compareList.splice(i, 1);
        else {
          if (state.compareList.length >= 3) state.compareList.shift();
          state.compareList.push(b.dataset.compBtn);
        }
        renderDirectory();
      }));
    }

    function resetAllFilters() {
      state.search = "";
      state.spec = "ALL";
      state.status = "ALL";
      state.lang = "ALL";
      state.district = "ALL";
      state.sort = "id_asc";
      state.maxPrice = DEFAULT_MAX_PRICE;
      state.onlyFavorites = false;

      document.getElementById("filterSearch").value = "";
      document.getElementById("filterSpec").value = "ALL";
      document.getElementById("filterStatus").value = "ALL";
      document.getElementById("filterLang").value = "ALL";
      document.getElementById("filterDistrict").value = "ALL";
      document.getElementById("filterSort").value = "id_asc";
      document.getElementById("filterMaxPrice").value = String(DEFAULT_MAX_PRICE);
      document.getElementById("priceReadout").textContent = formatIdr(DEFAULT_MAX_PRICE);
      renderDirectory();
    }

    function exportFilteredCsv() {
      const list = getFilteredGuides();
      if (!list.length) return;
      const headers = ["ID", "Nama", "Agensi", "Spesialisasi", "Rating", "Ulasan", "Kecamatan", "Alamat", "Telepon", "Tarif/Hari", "Status", "Bahasa", "Destinasi", "Kuliner", "Hotel", "Transportasi"];
      const csvCell = v => `"${String(v ?? "").replace(/"/g, '""')}"`;
      const rows = list.map(g => [
        g.id, g.name, g.agency, g.specialization, g.rating, g.reviews,
        formatDistrict(g.district), g.address, g.phone, g.price, g.status,
        getLangNames(g).join("; "),
        `${g.destId} ${g.destName}`, `${g.culinaryId} ${g.culinaryName}`,
        `${g.hotelId} ${g.hotelName}`, `${g.transportId} ${g.transportName}`
      ].map(csvCell).join(","));
      const csvContent = "\uFEFF" + [headers.map(csvCell).join(","), ...rows].join("\r\n");
      const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "sumutgo-pemandu-medan.csv";
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }

    function initOrUpdateMap() {
      if (!leafletMap) {
        leafletMap = L.map("medanLeafletMap").setView([state.refLat, state.refLng], 12);
        L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}", { maxZoom: 18 }).addTo(leafletMap);
        leafletMarkersLayer = L.layerGroup().addTo(leafletMap);
        leafletMap.on("click", e => {
          state.refLat = Number(e.latlng.lat.toFixed(4));
          state.refLng = Number(e.latlng.lng.toFixed(4));
          state.refLabel = `Titik Peta (${state.refLat}, ${state.refLng})`;
          document.querySelectorAll(".landmark-btn").forEach(btn => {
            btn.className = "landmark-btn px-3.5 py-2 rounded-full bg-lokalesoft text-lokale font-semibold text-xs transition";
          });
          renderMap();
        });
      }
      setTimeout(() => { leafletMap.invalidateSize(); renderMap(); }, 150);
    }

    function renderMap() {
      if (!leafletMap) return;
      leafletMarkersLayer.clearLayers();
      document.getElementById("activeRefLabel").textContent = state.refLabel;
      const onlyAvail = document.getElementById("chkMapOnlyAvailable").checked;

      L.circleMarker([state.refLat, state.refLng], {
        radius: 9,
        fillColor: "#f59e0b",
        color: "#14242b",
        weight: 2.5,
        fillOpacity: 1
      }).bindPopup(`<strong>Titik Jemput Aktif</strong><br/>${escapeHtml(state.refLabel)}`).addTo(leafletMarkersLayer);

      const ranked = TOUR_GUIDES
        .filter(g => !onlyAvail || g.status === "Tersedia")
        .map(g => ({ ...g, d: haversineKm(state.refLat, state.refLng, g.lat, g.lng) }))
        .sort((a, b) => a.d - b.d);

      ranked.forEach(g => {
        L.circleMarker([g.lat, g.lng], {
          radius: 7,
          fillColor: g.status === "Tersedia" ? "#07779d" : "#e11d48",
          color: "#fff",
          weight: 2,
          fillOpacity: 0.9
        }).bindPopup(`<strong>${escapeHtml(g.name)}</strong> (${escapeHtml(g.id)})<br/>${g.d.toFixed(2)} km • ${formatIdr(g.price)}<br/><span style="color:#07779d">${escapeHtml(g.status)}</span>`).addTo(leafletMarkersLayer);
      });

      document.getElementById("nearestGuidesList").innerHTML = ranked.length
        ? ranked.slice(0, 12).map((g, i) => `<div class="py-2 flex justify-between gap-2 text-xs">
            <div class="min-w-0">
              <strong class="text-ink block truncate">#${i + 1} ${escapeHtml(g.name)}</strong>
              <div class="text-[11px] text-muted truncate">${escapeHtml(formatDistrict(g.district))} • ${escapeHtml(g.status)}</div>
            </div>
            <div class="text-right shrink-0">
              <span class="font-mono font-bold text-lokale block">${g.d.toFixed(2)} km</span>
              <button type="button" data-map-open="${escapeHtml(g.id)}" class="text-[10px] text-lokale underline">Pilih</button>
            </div>
          </div>`).join("")
        : `<p class="text-xs text-muted py-6 text-center">Tidak ada pemandu yang sesuai filter.</p>`;

      document.querySelectorAll("[data-map-open]").forEach(b => b.addEventListener("click", () => openGuideModal(b.dataset.mapOpen)));
    }

    function updatePlanner() {
      const g = TOUR_GUIDES.find(x => x.id === document.getElementById("planGuideSelect").value) || TOUR_GUIDES[0];
      if (!g) return;
      const days = Math.max(1, Number(document.getElementById("planDays").value || 1));
      const pax = Math.max(1, Number(document.getElementById("planPax").value || 1));
      const hTier = document.getElementById("planHotelTier").value, tMode = document.getElementById("planTransportMode").value;
      const gCost = g.price * days;
      const tCost = (tMode === "hiace" ? 1250000 : tMode === "bus" ? 2600000 : tMode === "self" ? 0 : 650000) * days;
      const hCost = hTier === "none" ? 0 : (hTier === "luxury" ? 1450000 : hTier === "budget" ? 420000 : 750000) * Math.ceil(pax / 2) * Math.max(1, days - 1);
      const cCost = pax * days * 75000, total = gCost + tCost + hCost + cCost;
      document.getElementById("planSummaryTitle").textContent = `Paket ${g.specialization} (${days} Hari • ${pax} Peserta)`;
      document.getElementById("planCostPerPax").textContent = `${formatIdr(Math.round(total / pax))} / pax`;
      document.getElementById("planGuideLabel").textContent = g.name;
      document.getElementById("planGuideCost").textContent = formatIdr(gCost);
      document.getElementById("planTransLabel").textContent = tMode === "self" ? "Kendaraan Pribadi" : g.transportName;
      document.getElementById("planTransCost").textContent = formatIdr(tCost);
      document.getElementById("planHotelLabel").textContent = hTier === "none" ? "Tanpa Menginap" : g.hotelName;
      document.getElementById("planHotelCost").textContent = formatIdr(hCost);
      document.getElementById("planCulLabel").textContent = g.culinaryName;
      document.getElementById("planCulCost").textContent = formatIdr(cCost);
      document.getElementById("planGrandTotal").textContent = formatIdr(total);
      const dayTwoLabel = days > 1 ? `Hari 2–${days}:` : `Sore – Malam:`;
      const stayNote = hTier === "none" ? "tanpa menginap" : `menginap di ${escapeHtml(g.hotelName)}`;
      document.getElementById("planItineraryTimeline").innerHTML = `
        <div class="p-3.5 rounded-2xl bg-lokalesoft/50 border border-lokaleborder"><strong class="text-lokale">Hari 1:</strong> Penjemputan di ${escapeHtml(formatDistrict(g.district))} menuju <strong>${escapeHtml(g.destName)}</strong> &amp; kuliner <strong>${escapeHtml(g.culinaryName)}</strong>.</div>
        <div class="p-3.5 rounded-2xl bg-sand/70 border border-slate-200"><strong>${dayTwoLabel}</strong> Eksplorasi ${escapeHtml(g.specialization)} bersama ${escapeHtml(g.name)}, ${stayNote}.</div>`;
    }

    function renderAnalytics() {
      if (!chartsInitialized && window.Chart) {
        chartsInitialized = true;
        const sc = {}, ss = {}, lc = {}, st = { Tersedia: 0, "Sedang Bertugas": 0, Booked: 0 };
        TOUR_GUIDES.forEach(g => {
          sc[g.specialization] = (sc[g.specialization] || 0) + 1;
          ss[g.specialization] = (ss[g.specialization] || 0) + g.price;
          st[g.status] = (st[g.status] || 0) + 1;
          getLangNames(g).forEach(l => { lc[l] = (lc[l] || 0) + 1; });
        });
        const sortedLangs = Object.entries(lc).sort((a, b) => b[1] - a[1]);
        new Chart(document.getElementById("chartSpecializations"), {
          type: "bar",
          data: { labels: Object.keys(sc).map(s => s.split("&")[0].trim()), datasets: [{ label: "Rata-rata (Ribu Rp)", data: Object.keys(sc).map(s => Math.round(ss[s] / sc[s] / 1000)), backgroundColor: "#07779d", borderRadius: 8 }] },
          options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
        new Chart(document.getElementById("chartLanguages"), {
          type: "bar",
          data: { labels: sortedLangs.map(([l]) => l), datasets: [{ label: "Jumlah Pemandu", data: sortedLangs.map(([, c]) => c), backgroundColor: "#389ec0", borderRadius: 8 }] },
          options: { indexAxis: "y", responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
        new Chart(document.getElementById("chartStatuses"), {
          type: "doughnut",
          data: { labels: Object.keys(st), datasets: [{ data: Object.values(st), backgroundColor: ["#07779d", "#f59e0b", "#e11d48"] }] },
          options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
        });
      }
      renderEco();
    }

    function renderEco() {
      document.querySelectorAll(".eco-tab-btn").forEach(b => {
        b.className = b.dataset.ecoTab === state.ecoTab
          ? "eco-tab-btn px-3.5 py-1.5 rounded-full text-xs font-semibold bg-lokale text-white transition"
          : "eco-tab-btn px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale hover:text-lokaledark transition";
      });

      const counts = {};
      TOUR_GUIDES.forEach(g => {
        const k = state.ecoTab === "culinaries" ? g.culinaryId : state.ecoTab === "hotels" ? g.hotelId : state.ecoTab === "transports" ? g.transportId : g.destId;
        if (k) counts[k] = (counts[k] || 0) + 1;
      });

      const categoryLabel = state.ecoTab === "culinaries" ? "Kuliner Pilihan" : state.ecoTab === "hotels" ? "Hotel Mitra" : state.ecoTab === "transports" ? "Armada Transportasi" : "Destinasi Wisata";
      const masterList = MASTER_ECOSYSTEM[state.ecoTab] || [];
      const items = masterList.map(row => {
        const k = row.destination_code || row.culinary_code || row.hotel_code || row.transport_code || "";
        const n = row.destination_name || row.culinary_name || row.hotel_name || row.transport_name || "";
        return { k, n, c: counts[k] || 0 };
      });

      document.getElementById("ecosystemEntitiesGrid").innerHTML = items.map(x => `
        <div class="bg-lokalesoft/40 border border-lokaleborder rounded-2xl p-3.5 text-xs flex flex-col justify-between">
          <div>
            <div class="flex justify-between items-center gap-2"><span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-white border border-lokaleborder text-lokale">${categoryLabel}</span><span class="text-muted text-[11px]">${x.c} Pemandu</span></div>
            <div class="font-bold text-ink mt-2">${escapeHtml(x.n)}</div>
          </div>
          <button type="button" data-eco-q="${escapeHtml(x.k)}" class="mt-2.5 w-full py-1.5 rounded-full bg-white hover:bg-lokale hover:text-white text-lokale border border-lokaleborder font-semibold text-[11px] transition">Lihat Pemandu Terkait</button>
        </div>`).join("");

      document.querySelectorAll("[data-eco-q]").forEach(b => b.addEventListener("click", () => {
        resetAllFilters();
        state.search = b.dataset.ecoQ;
        document.getElementById("filterSearch").value = state.search;
        switchTab("directory");
        renderDirectory();
        document.getElementById("direktori-pemandu").scrollIntoView({ behavior: "smooth" });
      }));
    }

    function openGuideModal(id) {
      const g = TOUR_GUIDES.find(x => x.id === id);
      if (!g) return;
      state.activeGuideId = g.id;
      document.getElementById("modalGuideId").textContent = `${g.id} • ${g.status}`;
      document.getElementById("modalGuideName").textContent = g.name;
      document.getElementById("modalAgency").textContent = g.agency;
      document.getElementById("modalAddress").textContent = g.address;
      document.getElementById("modalSpec").textContent = g.specialization;
      document.getElementById("modalLangs").textContent = getLangNames(g).join(", ") || "Indonesia";
      document.getElementById("modalPrice").textContent = formatIdr(g.price) + " / hari";
      document.getElementById("modalRating").textContent = `★ ${Number(g.rating).toFixed(1)} (${g.reviews} ulasan)`;
      document.getElementById("modalPhone").textContent = g.phone ? `+${g.phone}` : "-";
      document.getElementById("modalDestName").textContent = g.destName || "-";
      document.getElementById("modalCulName").textContent = g.culinaryName || "-";
      document.getElementById("modalHotelName").textContent = g.hotelName || "-";
      document.getElementById("modalTransName").textContent = g.transportName || "-";
      document.getElementById("bookTotalReadout").textContent = formatIdr(g.price * Math.max(1, Number(document.getElementById("bookDays").value || 1)));
      document.getElementById("bookingFeedback").classList.add("hidden");
      document.getElementById("guideModal").classList.remove("hidden");
    }

    function switchTab(t) {
      state.activeTab = t;
      ["directory", "map", "planner", "analytics"].forEach(k => document.getElementById("view-" + k).classList.toggle("hidden", k !== t));
      document.querySelectorAll(".main-tab-btn").forEach(b => {
        b.className = b.dataset.mainTab === t
          ? "main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition bg-lokale text-white shadow-sm"
          : "main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition text-ink/80 hover:text-lokale";
      });
      document.querySelectorAll(".main-tab-mobile").forEach(b => {
        b.className = b.dataset.mainTab === t
          ? "main-tab-mobile px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-lokale text-white transition"
          : "main-tab-mobile px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-lokalesoft text-lokale transition";
      });
      if (t === "map") initOrUpdateMap();
      if (t === "planner") updatePlanner();
      if (t === "analytics") renderAnalytics();
    }

    function closeAllModals() {
      document.getElementById("guideModal").classList.add("hidden");
      document.getElementById("compareModal").classList.add("hidden");
    }

    window.addEventListener("DOMContentLoaded", () => {
      const dists = Array.from(new Set(TOUR_GUIDES.map(g => g.district))).sort();
      document.getElementById("filterDistrict").innerHTML = `<option value="ALL">Semua Kecamatan (${dists.length})</option>` + dists.map(d => `<option value="${escapeHtml(d)}">${escapeHtml(formatDistrict(d))}</option>`).join("");
      document.getElementById("planGuideSelect").innerHTML = TOUR_GUIDES.map(g => `<option value="${escapeHtml(g.id)}">${escapeHtml(g.id)} — ${escapeHtml(g.name)} (${formatIdr(g.price)})</option>`).join("");
      renderDirectory();

      document.getElementById("brandHomeLink").addEventListener("click", () => switchTab("directory"));
      document.querySelectorAll("[data-main-tab]").forEach(b => b.addEventListener("click", () => switchTab(b.dataset.mainTab)));
      document.getElementById("btnHeroSwitchMap").addEventListener("click", () => switchTab("map"));
      document.getElementById("btnHeroSwitchPlanner").addEventListener("click", () => switchTab("planner"));
      document.getElementById("btnPartnershipOpenEco").addEventListener("click", () => switchTab("analytics"));
      document.getElementById("btnViewCards").addEventListener("click", () => { state.viewMode = "cards"; renderDirectory(); });
      document.getElementById("btnViewTable").addEventListener("click", () => { state.viewMode = "table"; renderDirectory(); });
      document.getElementById("btnExportCsvFiltered").addEventListener("click", exportFilteredCsv);

      const filterBindings = [
        ["filterSearch", "search", "input"],
        ["filterSpec", "spec", "change"],
        ["filterStatus", "status", "change"],
        ["filterLang", "lang", "change"],
        ["filterDistrict", "district", "change"],
        ["filterSort", "sort", "change"]
      ];
      filterBindings.forEach(([elId, key, ev]) => {
        const el = document.getElementById(elId);
        el.addEventListener(ev, e => { state[key] = e.target.value; renderDirectory(); });
        if (ev === "change") {
          el.addEventListener("input", e => { state[key] = e.target.value; renderDirectory(); });
        }
      });

      document.getElementById("filterMaxPrice").addEventListener("input", e => {
        state.maxPrice = Number(e.target.value);
        document.getElementById("priceReadout").textContent = formatIdr(state.maxPrice);
        renderDirectory();
      });

      document.querySelectorAll(".quick-dest-card").forEach(c => c.addEventListener("click", () => {
        resetAllFilters();
        state.search = c.dataset.quickDest;
        document.getElementById("filterSearch").value = state.search;
        renderDirectory();
        document.getElementById("direktori-pemandu").scrollIntoView({ behavior: "smooth" });
      }));

      document.getElementById("btnResetFilters").addEventListener("click", resetAllFilters);
      document.getElementById("btnEmptyReset").addEventListener("click", resetAllFilters);
      document.getElementById("btnFilterFavorites").addEventListener("click", () => {
        state.onlyFavorites = !state.onlyFavorites;
        switchTab("directory");
        renderDirectory();
      });

      document.querySelectorAll(".landmark-btn").forEach(b => b.addEventListener("click", () => {
        document.querySelectorAll(".landmark-btn").forEach(btn => {
          btn.className = btn === b
            ? "landmark-btn px-3.5 py-2 rounded-full bg-lokale text-white font-semibold text-xs transition"
            : "landmark-btn px-3.5 py-2 rounded-full bg-lokalesoft text-lokale font-semibold text-xs transition";
        });
        const p = b.dataset.landmark.split(",");
        state.refLat = parseFloat(p[0]);
        state.refLng = parseFloat(p[1]);
        state.refLabel = p.slice(2).join(",");
        if (leafletMap) leafletMap.setView([state.refLat, state.refLng], 12);
        renderMap();
      }));

      document.getElementById("chkMapOnlyAvailable").addEventListener("change", renderMap);
      ["planGuideSelect", "planDays", "planPax", "planHotelTier", "planTransportMode"].forEach(id => document.getElementById(id).addEventListener("change", updatePlanner));
      document.getElementById("btnBookFromPlanner").addEventListener("click", () => openGuideModal(document.getElementById("planGuideSelect").value));
      document.querySelectorAll(".eco-tab-btn").forEach(b => b.addEventListener("click", () => { state.ecoTab = b.dataset.ecoTab; renderEco(); }));

      document.getElementById("btnCloseGuideModal").addEventListener("click", () => document.getElementById("guideModal").classList.add("hidden"));
      document.getElementById("bookDays").addEventListener("input", e => {
        const g = TOUR_GUIDES.find(x => x.id === state.activeGuideId);
        if (g) document.getElementById("bookTotalReadout").textContent = formatIdr(g.price * Math.max(1, Number(e.target.value || 1)));
      });

      document.getElementById("bookingForm").addEventListener("submit", async e => {
        e.preventDefault();
        const g = TOUR_GUIDES.find(x => x.id === state.activeGuideId);
        if (!g) return;
        const traveler = document.getElementById("bookTravelerName").value.trim();
        const dateVal = document.getElementById("bookDate").value;
        const days = Math.max(1, Number(document.getElementById("bookDays").value || 1));
        const btn = document.getElementById("btnSubmitBooking");
        const fb = document.getElementById("bookingFeedback");
        btn.disabled = true;
        btn.textContent = "Memproses...";
        try {
          const resp = await fetch(`${API_BASE_URL}/${encodeURIComponent(g.id)}/book`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ traveler_name: traveler, tour_date: dateVal, duration_days: days })
          });
          const data = await resp.json();
          if (!resp.ok) throw new Error(data.message || "Gagal mencatat booking.");
          g.status = "Booked";
          document.getElementById("modalGuideId").textContent = `${g.id} • ${g.status}`;
          fb.className = "p-3 rounded-2xl bg-lokalesoft border border-lokale text-lokaledeep font-medium";
          fb.textContent = data.message || `Booking berhasil untuk ${traveler} bersama ${g.name} (${days} hari). Status diperbarui menjadi Booked.`;
          fb.classList.remove("hidden");
          renderDirectory();
        } catch (err) {
          fb.className = "p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 font-medium";
          fb.textContent = err.message || "Terjadi kesalahan saat menyimpan booking.";
          fb.classList.remove("hidden");
        } finally {
          btn.disabled = false;
          btn.textContent = "Konfirmasi Booking";
        }
      });

      document.getElementById("btnToggleCompareModal").addEventListener("click", () => {
        const items = state.compareList.map(id => TOUR_GUIDES.find(g => g.id === id)).filter(Boolean);
        const colClass = items.length === 1 ? "md:grid-cols-1" : items.length === 2 ? "md:grid-cols-2" : "md:grid-cols-3";
        document.getElementById("compareModalBody").innerHTML = items.length
          ? `<div class="grid grid-cols-1 ${colClass} gap-4">` + items.map(g => `
              <div class="bg-lokalesoft/50 border border-lokaleborder rounded-2xl p-4 space-y-1.5 flex flex-col justify-between">
                <div class="space-y-1.5">
                  <div class="flex items-center justify-between gap-2">
                    <strong class="text-lokale font-mono">${escapeHtml(g.id)}</strong>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border ${statusBadgeClass(g.status)}">${escapeHtml(g.status)}</span>
                  </div>
                  <h4 class="font-bold text-base text-ink">${escapeHtml(g.name)}</h4>
                  <div class="text-muted">${escapeHtml(g.specialization)}</div>
                  <div>Rating: <strong class="text-amber-500">★ ${Number(g.rating).toFixed(1)}</strong> (${g.reviews})</div>
                  <div>Tarif: <strong class="text-lokale font-mono">${formatIdr(g.price)}</strong>/hari</div>
                  <div>Bahasa: ${escapeHtml(getLangNames(g).join(", "))}</div>
                  <div>Wisata: ${escapeHtml(g.destName)}</div>
                  <div>Kuliner: ${escapeHtml(g.culinaryName)}</div>
                  <div>Hotel: ${escapeHtml(g.hotelName)}</div>
                </div>
                <button type="button" data-compare-detail="${escapeHtml(g.id)}" class="mt-3 w-full py-1.5 rounded-full bg-lokale text-white font-semibold text-[11px]">Lihat Detail &amp; Booking</button>
              </div>`).join("") + `</div>`
          : `<p class="text-center py-6 text-muted">Klik tombol + Banding pada kartu pemandu untuk membandingkan.</p>`;
        document.querySelectorAll("[data-compare-detail]").forEach(b => b.addEventListener("click", () => {
          document.getElementById("compareModal").classList.add("hidden");
          openGuideModal(b.dataset.compareDetail);
        }));
        document.getElementById("compareModal").classList.remove("hidden");
      });

      document.getElementById("btnCloseCompareModal").addEventListener("click", () => document.getElementById("compareModal").classList.add("hidden"));
      document.getElementById("btnClearCompare").addEventListener("click", () => { state.compareList = []; document.getElementById("compareModal").classList.add("hidden"); renderDirectory(); });

      ["guideModal", "compareModal"].forEach(modalId => {
        document.getElementById(modalId).addEventListener("click", e => {
          if (e.target.id === modalId) document.getElementById(modalId).classList.add("hidden");
        });
      });
      document.addEventListener("keydown", e => {
        if (e.key === "Escape") closeAllModals();
      });
    });
  </script>
</body>
</html>