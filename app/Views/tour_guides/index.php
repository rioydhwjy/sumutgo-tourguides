<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= esc($title ?? 'lokale.tour | Pemandu Wisata Lokal Medan, Menemani Perjalananmu') ?></title>
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
          <span class="font-display font-extrabold text-2xl tracking-tight text-lokale">lokale<span class="text-ink font-semibold text-lg">.tour</span></span>
          <span class="block text-[10px] tracking-[0.18em] text-muted uppercase mt-0.5">Medan Tour Guide</span>
        </div>
      </a>

      <nav class="hidden lg:flex items-center gap-1.5 bg-lokalesoft/70 p-1 rounded-full border border-lokaleborder">
        <button type="button" data-main-tab="directory" class="main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition bg-lokale text-white shadow-sm">Beranda &amp; Pemandu</button>
        <button type="button" data-main-tab="map" class="main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition text-ink/80 hover:text-lokale">Peta Basecamp</button>
        <button type="button" data-main-tab="planner" class="main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition text-ink/80 hover:text-lokale">Kalkulator Trip</button>
        <button type="button" data-main-tab="analytics" class="main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition text-ink/80 hover:text-lokale">Analitik &amp; Mitra</button>
        <button type="button" data-main-tab="admin" class="main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition text-ink/80 hover:text-lokale">Admin &amp; API</button>
      </nav>

      <div class="flex items-center gap-2">
        <button id="btnToggleCompareModal" type="button" class="px-3.5 py-2 rounded-full bg-lokalesoft hover:bg-lokale hover:text-white text-lokale text-xs font-semibold border border-lokaleborder transition flex items-center gap-1.5">
          <span>Bandingkan</span>
          <span id="compareCountBadge" class="px-1.5 py-0.2 rounded-full bg-lokale text-white text-[10px] font-bold">0</span>
        </button>
        <button id="btnFilterFavorites" type="button" class="px-3 py-2 rounded-full bg-white hover:bg-rose-50 border border-slate-200 text-xs font-semibold text-ink flex items-center gap-1 transition">
          <span class="text-rose-500">♥</span>
          <span id="favCountBadge" class="font-mono text-[11px]">0</span>
        </button>
        <button id="btnOpenSqlModalTop" type="button" class="hidden sm:inline-flex px-4 py-2 rounded-full bg-lokale hover:bg-lokaledark text-white text-xs font-semibold shadow-sm transition">
          SQL Query
        </button>
      </div>
    </div>

    <div class="lg:hidden flex items-center gap-1.5 px-4 pb-3 overflow-x-auto">
      <button type="button" data-main-tab="directory" class="main-tab-mobile px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-lokale text-white">Beranda &amp; Pemandu</button>
      <button type="button" data-main-tab="map" class="main-tab-mobile px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-lokalesoft text-lokale">Peta Basecamp</button>
      <button type="button" data-main-tab="planner" class="main-tab-mobile px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-lokalesoft text-lokale">Kalkulator Trip</button>
      <button type="button" data-main-tab="analytics" class="main-tab-mobile px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-lokalesoft text-lokale">Analitik &amp; Mitra</button>
      <button type="button" data-main-tab="admin" class="main-tab-mobile px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-lokalesoft text-lokale">Admin &amp; API</button>
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
                #TemanJalanMedan • 50 Pemandu Wisata Lokal Resmi
              </span>
              <h1 class="font-display font-extrabold text-4xl sm:text-5xl lg:text-[3.4rem] text-ink leading-[1.12] tracking-tight">
                Teman Perjalananmu<br />
                <span class="text-lokale">yang Setia.</span>
              </h1>
              <p class="text-sm sm:text-base text-muted leading-relaxed max-w-xl">
                Jelajah sejarah Kesawan, berburu kuliner malam Medan, atau menyusuri keindahan Danau Toba dan Bukit Lawang—Teman lokale nggak pernah jalan sendirian. Kami selalu sedia pemandu lokal ramah dan senyum hangat untuk merayakan setiap langkah perjalananmu.
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
                  <span id="heroAvailPill" class="px-3.5 py-1.5 rounded-full bg-lokale text-white text-xs font-bold">29 Tersedia</span>
                </div>
              </div>
            </div>
          </div>

          <!-- STATS STRIP (MIRRORS KAB/KOTA, TEMAN LOKALE, OUTLET) -->
          <div class="mt-14 bg-white rounded-3xl border border-lokaleborder shadow-sm p-6 sm:p-8 grid grid-cols-2 lg:grid-cols-4 gap-6 text-center divide-y lg:divide-y-0 lg:divide-x divide-slate-100">
            <div class="pt-2 lg:pt-0">
              <div class="font-display font-extrabold text-3xl sm:text-4xl text-lokale">15</div>
              <div class="text-xs sm:text-sm font-medium text-muted mt-1">Kecamatan Medan</div>
            </div>
            <div class="pt-2 lg:pt-0">
              <div class="font-display font-extrabold text-3xl sm:text-4xl text-lokale">11.552+</div>
              <div class="text-xs sm:text-sm font-medium text-muted mt-1">Ulasan Teman Jalan</div>
            </div>
            <div class="pt-4 lg:pt-0">
              <div id="statTotalGuides" class="font-display font-extrabold text-3xl sm:text-4xl text-lokale">50</div>
              <div class="text-xs sm:text-sm font-medium text-muted mt-1">Pemandu Resmi</div>
            </div>
            <div class="pt-4 lg:pt-0">
              <div class="font-display font-extrabold text-3xl sm:text-4xl text-lokale">9</div>
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
                <span class="text-[11px] font-mono text-lokale font-semibold">WST-19 • WST-22</span>
                <h3 class="font-display font-bold text-base text-ink group-hover:text-lokale transition mt-0.5">Danau Toba &amp; Holbung</h3>
                <p class="text-xs text-muted mt-1">Panorama Kaldera Toba &amp; budaya Batak Samosir.</p>
              </div>
            </button>

            <button type="button" data-quick-dest="Tangkahan" class="quick-dest-card lokale-card rounded-3xl overflow-hidden text-left group p-3">
              <div class="h-60 rounded-2xl overflow-hidden bg-lokalesoft">
                <img src="<?= base_url('assets/img/card_tangkahan.jpg') ?>" alt="Tangkahan" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
              </div>
              <div class="pt-4 pb-2 px-2">
                <span class="text-[11px] font-mono text-lokale font-semibold">WST-13 • WST-14</span>
                <h3 class="font-display font-bold text-base text-ink group-hover:text-lokale transition mt-0.5">Tangkahan &amp; Leuser</h3>
                <p class="text-xs text-muted mt-1">Konservasi gajah Sumatera &amp; trekking orangutan.</p>
              </div>
            </button>

            <button type="button" data-quick-dest="Sipiso-piso" class="quick-dest-card lokale-card rounded-3xl overflow-hidden text-left group p-3">
              <div class="h-60 rounded-2xl overflow-hidden bg-lokalesoft">
                <img src="<?= base_url('assets/img/card_sipisopiso.jpg') ?>" alt="Sipiso-piso" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
              </div>
              <div class="pt-4 pb-2 px-2">
                <span class="text-[11px] font-mono text-lokale font-semibold">WST-17 • WST-18</span>
                <h3 class="font-display font-bold text-base text-ink group-hover:text-lokale transition mt-0.5">Berastagi &amp; Sipiso-piso</h3>
                <p class="text-xs text-muted mt-1">Dataran tinggi Karo, Sibayak &amp; air terjun ikonik.</p>
              </div>
            </button>

            <button type="button" data-quick-dest="Maimun" class="quick-dest-card lokale-card rounded-3xl overflow-hidden text-left group p-3">
              <div class="h-60 rounded-2xl overflow-hidden bg-lokalesoft">
                <img src="<?= base_url('assets/img/card_heritage.jpg') ?>" alt="Istana Maimun" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
              </div>
              <div class="pt-4 pb-2 px-2">
                <span class="text-[11px] font-mono text-lokale font-semibold">WST-01 • WST-02</span>
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
                  <option value="ALL">Semua Spesialisasi (5)</option>
                  <option value="Eco-Tourism & Alam">Eco-Tourism &amp; Alam</option>
                  <option value="Heritage & Sejarah Medan">Heritage &amp; Sejarah Medan</option>
                  <option value="Kuliner Malam & Street Food">Kuliner Malam &amp; Street Food</option>
                  <option value="Photography & Budaya">Photography &amp; Budaya</option>
                  <option value="City Tour & Belanja">City Tour &amp; Belanja</option>
                </select>
              </div>
              <div>
                <label for="filterStatus" class="block text-[11px] font-semibold text-muted mb-1">Status</label>
                <select id="filterStatus" class="w-full bg-white border border-lokaleborder rounded-xl px-3 py-2.5 text-xs text-ink focus:outline-none">
                  <option value="ALL">Semua Status (50)</option>
                  <option value="Tersedia">Tersedia</option>
                  <option value="Sedang Bertugas">Sedang Bertugas</option>
                  <option value="Booked">Booked</option>
                </select>
              </div>
              <div>
                <label for="filterLang" class="block text-[11px] font-semibold text-muted mb-1">Bahasa</label>
                <select id="filterLang" class="w-full bg-white border border-lokaleborder rounded-xl px-3 py-2.5 text-xs text-ink focus:outline-none">
                  <option value="ALL">Semua Bahasa (9)</option>
                  <option value="Indonesia">Indonesia</option>
                  <option value="Inggris">Inggris</option>
                  <option value="Bahasa Batak">Bahasa Batak</option>
                  <option value="Mandarin">Mandarin</option>
                  <option value="Hokkien Medan">Hokkien Medan</option>
                  <option value="Belanda">Belanda</option>
                  <option value="Bahasa Karo">Bahasa Karo</option>
                  <option value="Melayu">Melayu</option>
                  <option value="Jepang">Jepang</option>
                </select>
              </div>
              <div>
                <label for="filterDistrict" class="block text-[11px] font-semibold text-muted mb-1">Kecamatan</label>
                <select id="filterDistrict" class="w-full bg-white border border-lokaleborder rounded-xl px-3 py-2.5 text-xs text-ink focus:outline-none">
                  <option value="ALL">Semua Kecamatan (15)</option>
                </select>
              </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 pt-3 border-t border-lokaleborder/70">
              <div class="flex items-center gap-3 flex-1 min-w-[230px] max-w-md">
                <label for="filterMaxPrice" class="text-xs font-medium text-muted whitespace-nowrap">Maks Tarif / Hari:</label>
                <input id="filterMaxPrice" type="range" min="250000" max="850000" step="25000" value="850000" class="w-full accent-lokale cursor-pointer" />
                <span id="priceReadout" class="text-xs font-mono font-bold text-lokale whitespace-nowrap">Rp 850.000</span>
              </div>
              <div class="flex flex-wrap items-center gap-3">
                <select id="filterSort" class="bg-white border border-lokaleborder rounded-xl px-3 py-1.5 text-xs text-ink focus:outline-none">
                  <option value="id_asc">ID Guide (TG-001 &rarr; TG-050)</option>
                  <option value="rating_desc">Rating Tertinggi (5.0★)</option>
                  <option value="reviews_desc">Ulasan Terbanyak</option>
                  <option value="price_asc">Tarif Termurah</option>
                  <option value="price_desc">Tarif Tertinggi (VIP)</option>
                </select>
                <span class="text-xs text-muted">Menampilkan <strong id="resultCount" class="text-ink">50</strong> pemandu • <strong id="avgRatingReadout" class="text-amber-500">4.74★</strong></span>
              </div>
            </div>
          </div>

          <div id="guidesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"></div>

          <div id="guidesTableWrapper" class="hidden bg-white border border-lokaleborder rounded-3xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse text-xs">
                <thead>
                  <tr class="bg-lokalesoft border-b border-lokaleborder text-lokaledeep uppercase text-[10px] font-mono">
                    <th class="py-3.5 px-3">ID</th>
                    <th class="py-3.5 px-3">Pemandu &amp; Agensi</th>
                    <th class="py-3.5 px-3">Spesialisasi</th>
                    <th class="py-3.5 px-3">Rating</th>
                    <th class="py-3.5 px-3">Bahasa</th>
                    <th class="py-3.5 px-3">Wisata &amp; Kuliner</th>
                    <th class="py-3.5 px-3">Hotel &amp; Transport</th>
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
            <button id="btnEmptyReset" type="button" class="mt-3 px-6 py-2.5 rounded-full bg-lokale text-white text-xs font-semibold">Tampilkan Semua 50 Pemandu</button>
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
            <span class="text-[11px] font-mono text-muted mt-1">Rata-Rata Rating 4.74 / 5.0 ★</span>
          </div>
          <div class="md:col-span-8 space-y-3">
            <h2 class="font-display font-extrabold text-2xl sm:text-3xl text-ink">Sertifikasi &amp; Jaminan Kualitas Pemandu</h2>
            <p class="text-sm text-muted leading-relaxed">
              Kami berkomitmen memberikan pengalaman perjalanan terbaik di Medan dan Sumatera Utara. Seluruh 50 pemandu terverifikasi di Google Maps, tersebar di 15 kecamatan, menguasai 9 bahasa, serta terintegrasi dengan mitra Wisata, Kuliner, Hotel, dan Transportasi.
            </p>
          </div>
        </div>
      </div>

      <!-- MARI MELANGKAH BERSAMA (MIRRORS LOKALE PARTNERSHIP CTA) -->
      <div class="py-16 px-4 sm:px-6 lg:px-8 bg-white">
        <div class="max-w-7xl mx-auto rounded-[2rem] bg-lokale text-white p-8 sm:p-14 shadow-xl shadow-lokale/20">
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
            <div class="lg:col-span-8 space-y-3">
              <span class="text-xs font-bold uppercase tracking-[0.2em] text-sky-200">Ekosistem Terintegrasi 5 Kelompok</span>
              <h2 class="font-display font-extrabold text-3xl sm:text-4xl leading-tight">Mari Melangkah Bersama</h2>
              <p class="text-sm sm:text-base text-sky-50/90 max-w-2xl leading-relaxed">
                Satukan langkah untuk pariwisata Sumatera Utara. Sistem Pemandu Wisata (Kelompok 2) terhubung via RESTful API JSON dengan Kelompok Wisata (WST), Kuliner (KLN), Penginapan (HTL), dan Transportasi (TRP).
              </p>
            </div>
            <div class="lg:col-span-4 flex lg:justify-end">
              <button type="button" id="btnPartnershipOpenEco" class="px-8 py-3.5 rounded-full bg-white hover:bg-sand text-lokale font-bold text-sm shadow-lg transition">
                Lihat Integrasi Kemitraan
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
            <span class="text-xs font-bold uppercase tracking-wider text-lokale">Peta Sebaran 50 Basecamp Medan</span>
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

    <!-- TAB 4: ANALITIK & MITRA 5 KELOMPOK -->
    <section id="view-analytics" class="hidden py-10 px-4 sm:px-6 lg:px-8 bg-lokalesoft/30 min-h-[75vh]">
      <div class="max-w-7xl mx-auto space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
          <div class="bg-white rounded-3xl border border-lokaleborder p-5 shadow-sm"><h3 class="text-xs font-bold uppercase text-lokale mb-3">Rata-Rata Tarif Spesialisasi</h3><div class="relative h-64"><canvas id="chartSpecializations"></canvas></div></div>
          <div class="bg-white rounded-3xl border border-lokaleborder p-5 shadow-sm"><h3 class="text-xs font-bold uppercase text-lokale mb-3">Cakupan 9 Bahasa</h3><div class="relative h-64"><canvas id="chartLanguages"></canvas></div></div>
          <div class="bg-white rounded-3xl border border-lokaleborder p-5 shadow-sm"><h3 class="text-xs font-bold uppercase text-lokale mb-3">Status Ketersediaan</h3><div class="relative h-64"><canvas id="chartStatuses"></canvas></div></div>
        </div>
        <div class="bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <h2 class="font-display font-extrabold text-xl text-ink">Hub Integrasi Lintas 5 Kelompok</h2>
            <div class="flex flex-wrap gap-1.5 bg-lokalesoft p-1 rounded-full border border-lokaleborder">
              <button type="button" data-eco-tab="destinations" class="eco-tab-btn px-3.5 py-1.5 rounded-full text-xs font-semibold bg-lokale text-white">Wisata (25)</button>
              <button type="button" data-eco-tab="culinaries" class="eco-tab-btn px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale">Kuliner (25)</button>
              <button type="button" data-eco-tab="hotels" class="eco-tab-btn px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale">Hotel (20)</button>
              <button type="button" data-eco-tab="transports" class="eco-tab-btn px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale">Transportasi (20)</button>
            </div>
          </div>
          <div id="ecosystemEntitiesGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5"></div>
        </div>
      </div>
    </section>

    <!-- TAB 5: ADMIN & API -->
    <section id="view-admin" class="hidden py-10 px-4 sm:px-6 lg:px-8 bg-lokalesoft/30 min-h-[75vh]">
      <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-5 bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-3 text-xs">
          <h2 class="font-display font-extrabold text-xl text-ink">Status Reservasi &amp; Update Cepat</h2>
          <p class="text-muted">Klik tombol <strong>Set Tersedia</strong> untuk langsung memperbarui status pemandu di database PostgreSQL melalui endpoint <code>PUT /api/tour-guides/{id}/status</code>.</p>
          <div id="bookingLedgerList" class="max-h-96 overflow-y-auto divide-y divide-slate-100"></div>
        </div>
        <div class="lg:col-span-7 bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <h3 class="font-display font-bold text-base text-ink">Live RESTful API CodeIgniter 4</h3>
            <div class="flex gap-1.5">
              <button type="button" data-api-test="list" class="api-test-btn px-3 py-1.5 rounded-full bg-lokale text-white font-mono text-[11px]">GET /api/tour-guides</button>
              <button type="button" data-api-test="stats" class="api-test-btn px-3 py-1.5 rounded-full bg-lokalesoft text-lokale font-mono text-[11px]">GET /stats</button>
              <button type="button" data-api-test="detail" class="api-test-btn px-3 py-1.5 rounded-full bg-lokalesoft text-lokale font-mono text-[11px]">GET /TG-001</button>
            </div>
          </div>
          <pre id="apiSandboxOutput" class="bg-slate-900 text-sky-200 rounded-2xl p-4 font-mono text-[11px] max-h-96 overflow-y-auto"></pre>
        </div>
      </div>
    </section>
  </main>

  <!-- FOOTER ALA LOKALE.COFFEE -->
  <footer class="bg-lokaledeep text-white pt-14 pb-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-12 gap-10 pb-10 border-b border-white/15">
      <div class="md:col-span-5 space-y-3">
        <span class="font-display font-extrabold text-2xl tracking-tight text-white">lokale<span class="text-sky-300 font-semibold text-lg">.tour</span></span>
        <p class="text-xs text-sky-100/80 leading-relaxed">Dari Kota Medan hingga ke Danau Toba dan Bukit Lawang, kami tumbuh jadi teman perjalanan yang setia menemani setiap langkah wisatamu.</p>
      </div>
      <div class="md:col-span-3 space-y-2 text-xs">
        <h4 class="font-bold uppercase tracking-wider mb-3">Hubungi Kami</h4>
        <p class="text-sky-100/80">Politeknik Negeri Medan, Padang Bulan, Kota Medan</p>
        <p class="text-sky-200 font-mono">Kelompok 2 • Sistem Pemandu Wisata</p>
      </div>
      <div class="md:col-span-4 space-y-2 text-xs">
        <h4 class="font-bold uppercase tracking-wider mb-3">Database Terintegrasi</h4>
        <p class="text-sky-100/80">CodeIgniter 4 + PostgreSQL 3NF (50 Pemandu • 25 Wisata • 25 Kuliner • 20 Hotel • 20 Transportasi)</p>
      </div>
    </div>
    <div class="max-w-7xl mx-auto pt-6 text-[11px] text-sky-200/70 flex justify-between">
      <span>© Copyright 2026 lokale.tour Medan. All Rights Reserved.</span>
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
        <div class="flex justify-between bg-lokalesoft/60 p-4 rounded-2xl border border-lokaleborder">
          <div><div id="modalAgency" class="font-bold text-sm text-ink">-</div><div id="modalAddress" class="text-muted text-[11px] mt-1">-</div></div>
          <div class="text-right"><div id="modalPrice" class="font-mono font-bold text-base text-lokale">-</div><div id="modalRating" class="text-amber-500 font-bold mt-0.5">-</div></div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="bg-sand/60 p-3 rounded-2xl"><span id="modalDestCode" class="font-mono text-[10px] text-lokale font-bold block">-</span><strong id="modalDestName">-</strong></div>
          <div class="bg-sand/60 p-3 rounded-2xl"><span id="modalCulCode" class="font-mono text-[10px] text-lokale font-bold block">-</span><strong id="modalCulName">-</strong></div>
          <div class="bg-sand/60 p-3 rounded-2xl"><span id="modalHotelCode" class="font-mono text-[10px] text-lokale font-bold block">-</span><strong id="modalHotelName">-</strong></div>
          <div class="bg-sand/60 p-3 rounded-2xl"><span id="modalTransCode" class="font-mono text-[10px] text-lokale font-bold block">-</span><strong id="modalTransName">-</strong></div>
        </div>
        <form id="bookingForm" class="border-t border-slate-100 pt-4 space-y-3">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div><label class="block text-[10px] font-semibold text-muted mb-1">Nama Pemesan</label><input id="bookTravelerName" type="text" required value="Rio Yudha Wijaya" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2" /></div>
            <div><label class="block text-[10px] font-semibold text-muted mb-1">Tanggal Tur</label><input id="bookDate" type="date" required value="2026-11-20" class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2" /></div>
            <div><label class="block text-[10px] font-semibold text-muted mb-1">Durasi (Hari)</label><input id="bookDays" type="number" min="1" max="14" value="2" required class="w-full bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-2" /></div>
          </div>
          <div class="flex items-center justify-between pt-2">
            <div>Total: <strong id="bookTotalReadout" class="font-mono text-lokale text-sm">-</strong></div>
            <button type="submit" class="px-6 py-2 rounded-full bg-lokale hover:bg-lokaledark text-white font-bold">Konfirmasi Booking</button>
          </div>
          <div id="bookingFeedback" class="hidden p-3 rounded-2xl bg-lokalesoft border border-lokale text-lokaledeep font-medium"></div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL COMPARE & SQL -->
  <div id="compareModal" class="fixed inset-0 z-50 hidden bg-ink/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-4xl w-full max-h-[88vh] overflow-y-auto p-6 space-y-4">
      <div class="flex justify-between items-center border-b pb-3"><h3 class="font-display font-bold text-base">Bandingkan Pemandu</h3><div class="flex gap-2"><button id="btnClearCompare" type="button" class="px-3 py-1 rounded-full bg-slate-100 text-xs">Kosongkan</button><button id="btnCloseCompareModal" type="button" class="w-8 h-8 rounded-full bg-lokalesoft text-lokale">&times;</button></div></div>
      <div id="compareModalBody" class="text-xs"></div>
    </div>
  </div>
  <div id="sqlModal" class="fixed inset-0 z-50 hidden bg-ink/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-3xl w-full p-6 space-y-4">
      <div class="flex justify-between items-center border-b pb-3"><h3 class="font-display font-bold text-base">Live SQL Query (PostgreSQL 3NF)</h3><button id="btnCloseSqlModal" type="button" class="w-8 h-8 rounded-full bg-lokalesoft text-lokale">&times;</button></div>
      <pre id="liveSqlCode" class="bg-slate-900 text-sky-200 rounded-2xl p-4 font-mono text-[11px] overflow-x-auto"></pre>
    </div>
  </div>

  <script>
    const TOUR_GUIDES = <?= json_encode($guides, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const API_BASE_URL = '<?= rtrim(site_url('api/tour-guides'), '/') ?>';

    let state = { activeTab: "directory", viewMode: "cards", search: "", spec: "ALL", status: "ALL", lang: "ALL", district: "ALL", sort: "id_asc", maxPrice: 850000, onlyFavorites: false, favorites: new Set(["TG-001", "TG-013"]), compareList: ["TG-001", "TG-013"], activeGuideId: "TG-001", refLat: 3.5612, refLng: 98.6564, refLabel: "Politeknik Negeri Medan", ecoTab: "destinations" };
    let leafletMap = null, leafletMarkersLayer = null, chartsInitialized = false;

    const formatIdr = n => "Rp " + Number(n || 0).toLocaleString("id-ID");
    const haversineKm = (a1, o1, a2, o2) => { const R = 6371, dA = (a2-a1)*Math.PI/180, dO = (o2-o1)*Math.PI/180; const a = Math.sin(dA/2)**2 + Math.cos(a1*Math.PI/180)*Math.cos(a2*Math.PI/180)*Math.sin(dO/2)**2; return R*2*Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); };
    const statusBadgeClass = s => s === "Tersedia" ? "bg-emerald-50 text-emerald-700 border-emerald-200" : (s === "Sedang Bertugas" ? "bg-amber-50 text-amber-700 border-amber-200" : "bg-rose-50 text-rose-700 border-rose-200");

    function updateTopKpis() {
      document.getElementById("heroAvailPill").textContent = `${TOUR_GUIDES.filter(g => g.status === "Tersedia").length} Tersedia`;
      document.getElementById("statTotalGuides").textContent = String(TOUR_GUIDES.length);
      document.getElementById("compareCountBadge").textContent = String(state.compareList.length);
      document.getElementById("favCountBadge").textContent = String(state.favorites.size);
    }

    function getFilteredGuides() {
      const q = state.search.trim().toLowerCase();
      return TOUR_GUIDES.filter(g => {
        if (state.onlyFavorites && !state.favorites.has(g.id)) return false;
        if (state.spec !== "ALL" && g.specialization !== state.spec) return false;
        if (state.status !== "ALL" && g.status !== state.status) return false;
        if (state.lang !== "ALL" && !g.languages.includes(state.lang)) return false;
        if (state.district !== "ALL" && g.district !== state.district) return false;
        if (g.price > state.maxPrice) return false;
        if (q && ![g.id, g.name, g.agency, g.district, g.specialization, g.destId, g.destName, g.culinaryId, g.culinaryName, g.hotelId, g.hotelName, g.transportId, g.transportName, g.languages.join(" ")].join(" ").toLowerCase().includes(q)) return false;
        return true;
      }).sort((a, b) => state.sort === "rating_desc" ? b.rating - a.rating : state.sort === "reviews_desc" ? b.reviews - a.reviews : state.sort === "price_asc" ? a.price - b.price : state.sort === "price_desc" ? b.price - a.price : a.id.localeCompare(b.id));
    }

    function renderDirectory() {
      updateTopKpis();
      const list = getFilteredGuides();
      document.getElementById("resultCount").textContent = String(list.length);
      document.getElementById("avgRatingReadout").textContent = (list.length ? (list.reduce((a,b)=>a+b.rating,0)/list.length).toFixed(2) : "0.00") + "★";
      const grid = document.getElementById("guidesGrid"), wrap = document.getElementById("guidesTableWrapper"), tbody = document.getElementById("guidesTableBody"), empty = document.getElementById("emptyState");
      if (!list.length) { grid.classList.add("hidden"); wrap.classList.add("hidden"); empty.classList.remove("hidden"); return; }
      empty.classList.add("hidden");
      if (state.viewMode === "cards") {
        grid.classList.remove("hidden"); wrap.classList.add("hidden");
        grid.innerHTML = list.map(g => `<article class="lokale-card rounded-3xl p-5 flex flex-col justify-between"><div><div class="flex items-center justify-between gap-2 mb-3"><div class="flex items-center gap-1.5"><span class="font-mono text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-lokalesoft text-lokale">${g.id}</span><span class="text-[11px] text-muted truncate">${g.specialization}</span></div><div class="flex items-center gap-1.5"><button type="button" data-fav-btn="${g.id}" class="w-7 h-7 rounded-full border text-xs ${state.favorites.has(g.id)?'bg-rose-50 border-rose-200 text-rose-500':'bg-white border-slate-200 text-slate-400'}">♥</button><span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border ${statusBadgeClass(g.status)}">${g.status}</span></div></div><h3 class="font-display font-bold text-base text-ink">${g.name}</h3><p class="text-xs text-muted mt-0.5">${g.agency} • Kec. ${g.district}</p><div class="flex flex-wrap items-center gap-2 mt-2.5 text-xs"><span class="text-amber-500 font-bold">★ ${g.rating.toFixed(1)}</span><span class="text-muted">(${g.reviews})</span><span class="text-[11px] font-medium text-lokale">${g.languages.join(", ")}</span></div><div class="mt-4 pt-3 border-t border-slate-100 space-y-1 text-[11px]"><div class="truncate"><span class="font-mono font-bold text-lokale">${g.destId}</span> ${g.destName}</div><div class="truncate text-muted"><span class="font-mono">${g.culinaryId}</span> ${g.culinaryName}</div><div class="truncate text-muted"><span class="font-mono">${g.hotelId}</span> ${g.hotelName} • ${g.transportId}</div></div></div><div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between"><div><span class="text-[10px] text-muted block uppercase">Tarif/Hari</span><strong class="text-sm font-mono text-lokale">${formatIdr(g.price)}</strong></div><div class="flex gap-1.5"><button type="button" data-comp-btn="${g.id}" class="px-3 py-1.5 rounded-full text-[11px] font-semibold border ${state.compareList.includes(g.id)?'bg-lokalesoft text-lokale border-lokale':'bg-white text-muted border-slate-200'}">${state.compareList.includes(g.id)?'✓':'+'} Banding</button><button type="button" data-open-guide="${g.id}" class="px-4 py-1.5 rounded-full bg-lokale text-white text-xs font-semibold">Detail</button></div></div></article>`).join("");
      } else {
        grid.classList.add("hidden"); wrap.classList.remove("hidden");
        tbody.innerHTML = list.map(g => `<tr class="hover:bg-lokalesoft/40"><td class="py-3 px-3 font-mono font-bold text-lokale">${g.id}</td><td class="py-3 px-3"><div class="font-bold">${g.name}</div><div class="text-[11px] text-muted">${g.agency}</div></td><td class="py-3 px-3">${g.specialization}</td><td class="py-3 px-3 text-amber-500 font-bold">★ ${g.rating.toFixed(1)}</td><td class="py-3 px-3 text-lokale">${g.languages.join(", ")}</td><td class="py-3 px-3">${g.destId} ${g.destName}</td><td class="py-3 px-3">${g.hotelId} • ${g.transportId}</td><td class="py-3 px-3 font-mono font-bold text-lokale">${formatIdr(g.price)}</td><td class="py-3 px-3"><span class="text-[10px] font-bold px-2 py-0.5 rounded-full border ${statusBadgeClass(g.status)}">${g.status}</span></td><td class="py-3 px-3 text-right"><button type="button" data-open-guide="${g.id}" class="px-3 py-1 rounded-full bg-lokale text-white text-[11px]">Detail</button></td></tr>`).join("");
      }
      document.querySelectorAll("[data-open-guide]").forEach(b => b.addEventListener("click", () => openGuideModal(b.dataset.openGuide)));
      document.querySelectorAll("[data-fav-btn]").forEach(b => b.addEventListener("click", () => { state.favorites.has(b.dataset.favBtn) ? state.favorites.delete(b.dataset.favBtn) : state.favorites.add(b.dataset.favBtn); renderDirectory(); }));
      document.querySelectorAll("[data-comp-btn]").forEach(b => b.addEventListener("click", () => { const i = state.compareList.indexOf(b.dataset.compBtn); if (i >= 0) state.compareList.splice(i,1); else { if (state.compareList.length >= 3) state.compareList.shift(); state.compareList.push(b.dataset.compBtn); } renderDirectory(); }));
    }

    function initOrUpdateMap() {
      if (!leafletMap) {
        leafletMap = L.map("medanLeafletMap").setView([state.refLat, state.refLng], 12);
        L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}", { maxZoom: 18 }).addTo(leafletMap);
        leafletMarkersLayer = L.layerGroup().addTo(leafletMap);
        leafletMap.on("click", e => { state.refLat = Number(e.latlng.lat.toFixed(4)); state.refLng = Number(e.latlng.lng.toFixed(4)); state.refLabel = `Titik Peta (${state.refLat}, ${state.refLng})`; renderMap(); });
      }
      setTimeout(() => { leafletMap.invalidateSize(); renderMap(); }, 120);
    }

    function renderMap() {
      if (!leafletMap) return;
      leafletMarkersLayer.clearLayers();
      document.getElementById("activeRefLabel").textContent = state.refLabel;
      const onlyAvail = document.getElementById("chkMapOnlyAvailable").checked;
      const ranked = TOUR_GUIDES.filter(g => !onlyAvail || g.status === "Tersedia").map(g => ({ ...g, d: haversineKm(state.refLat, state.refLng, g.lat, g.lng) })).sort((a,b) => a.d - b.d);
      ranked.forEach(g => L.circleMarker([g.lat, g.lng], { radius: 7, fillColor: g.status === "Tersedia" ? "#07779d" : "#e11d48", color: "#fff", weight: 2, fillOpacity: 0.9 }).bindPopup(`<strong>${g.name}</strong><br/>${g.d.toFixed(2)} km • ${formatIdr(g.price)}`).addTo(leafletMarkersLayer));
      document.getElementById("nearestGuidesList").innerHTML = ranked.slice(0,12).map((g,i) => `<div class="py-2 flex justify-between text-xs"><div><strong class="text-ink">#${i+1} ${g.name}</strong><div class="text-[11px] text-muted">Kec. ${g.district}</div></div><div class="text-right"><span class="font-mono font-bold text-lokale block">${g.d.toFixed(2)} km</span><button type="button" data-map-open="${g.id}" class="text-[10px] text-lokale underline">Pilih</button></div></div>`).join("");
      document.querySelectorAll("[data-map-open]").forEach(b => b.addEventListener("click", () => openGuideModal(b.dataset.mapOpen)));
    }

    function updatePlanner() {
      const g = TOUR_GUIDES.find(x => x.id === document.getElementById("planGuideSelect").value) || TOUR_GUIDES[0];
      const days = Math.max(1, Number(document.getElementById("planDays").value || 1)), pax = Math.max(1, Number(document.getElementById("planPax").value || 1));
      const hTier = document.getElementById("planHotelTier").value, tMode = document.getElementById("planTransportMode").value;
      const gCost = g.price * days;
      const tCost = (tMode === "hiace" ? 1250000 : tMode === "bus" ? 2600000 : tMode === "self" ? 0 : 650000) * days;
      const hCost = hTier === "none" ? 0 : (hTier === "luxury" ? 1450000 : hTier === "budget" ? 420000 : 750000) * Math.ceil(pax/2) * Math.max(1, days-1);
      const cCost = pax * days * 75000, total = gCost + tCost + hCost + cCost;
      document.getElementById("planSummaryTitle").textContent = `Paket ${g.specialization} (${days} Hari • ${pax} Peserta)`;
      document.getElementById("planCostPerPax").textContent = `${formatIdr(Math.round(total/pax))} / pax`;
      document.getElementById("planGuideLabel").textContent = g.name; document.getElementById("planGuideCost").textContent = formatIdr(gCost);
      document.getElementById("planTransLabel").textContent = g.transportName; document.getElementById("planTransCost").textContent = formatIdr(tCost);
      document.getElementById("planHotelLabel").textContent = g.hotelName; document.getElementById("planHotelCost").textContent = formatIdr(hCost);
      document.getElementById("planCulLabel").textContent = g.culinaryName; document.getElementById("planCulCost").textContent = formatIdr(cCost);
      document.getElementById("planGrandTotal").textContent = formatIdr(total);
      document.getElementById("planItineraryTimeline").innerHTML = `<div class="p-3.5 rounded-2xl bg-lokalesoft/50 border border-lokaleborder"><strong class="text-lokale">Hari 1:</strong> Penjemputan di Kec. ${g.district} menuju <strong>${g.destName}</strong> &amp; kuliner <strong>${g.culinaryName}</strong>.</div><div class="p-3.5 rounded-2xl bg-sand/70 border border-slate-200"><strong>Hari 2–${days}:</strong> Eksplorasi ${g.specialization} bersama ${g.name}, menginap di ${g.hotelName}.</div>`;
    }

    function renderAnalytics() {
      if (!chartsInitialized && window.Chart) {
        chartsInitialized = true;
        const sc = {}, ss = {}, lc = {}, st = { Tersedia: 0, "Sedang Bertugas": 0, Booked: 0 };
        TOUR_GUIDES.forEach(g => { sc[g.specialization]=(sc[g.specialization]||0)+1; ss[g.specialization]=(ss[g.specialization]||0)+g.price; st[g.status]=(st[g.status]||0)+1; g.languages.forEach(l=>lc[l]=(lc[l]||0)+1); });
        new Chart(document.getElementById("chartSpecializations"), { type: "bar", data: { labels: Object.keys(sc).map(s=>s.split("&")[0]), datasets: [{ data: Object.keys(sc).map(s=>Math.round(ss[s]/sc[s]/1000)), backgroundColor: "#07779d", borderRadius: 8 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } });
        new Chart(document.getElementById("chartLanguages"), { type: "bar", data: { labels: Object.keys(lc), datasets: [{ data: Object.values(lc), backgroundColor: "#389ec0", borderRadius: 8 }] }, options: { indexAxis: "y", responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } });
        new Chart(document.getElementById("chartStatuses"), { type: "doughnut", data: { labels: Object.keys(st), datasets: [{ data: Object.values(st), backgroundColor: ["#07779d", "#f59e0b", "#e11d48"] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } } });
      }
      renderEco();
    }

    function renderEco() {
      const m = {};
      TOUR_GUIDES.forEach(g => {
        const k = state.ecoTab === "culinaries" ? g.culinaryId : state.ecoTab === "hotels" ? g.hotelId : state.ecoTab === "transports" ? g.transportId : g.destId;
        const n = state.ecoTab === "culinaries" ? g.culinaryName : state.ecoTab === "hotels" ? g.hotelName : state.ecoTab === "transports" ? g.transportName : g.destName;
        if (!m[k]) m[k] = { k, n, c: 0 }; m[k].c++;
      });
      document.getElementById("ecosystemEntitiesGrid").innerHTML = Object.values(m).sort((a,b)=>a.k.localeCompare(b.k)).map(x => `<div class="bg-lokalesoft/40 border border-lokaleborder rounded-2xl p-3.5 text-xs"><div class="flex justify-between font-mono"><span class="text-lokale font-bold">${x.k}</span><span class="text-muted">${x.c} Guide</span></div><div class="font-bold text-ink mt-1">${x.n}</div><button type="button" data-eco-q="${x.k}" class="mt-2.5 w-full py-1 rounded-full bg-white text-lokale border border-lokaleborder font-semibold text-[11px]">Lihat di Katalog</button></div>`).join("");
      document.querySelectorAll("[data-eco-q]").forEach(b => b.addEventListener("click", () => { state.search = b.dataset.ecoQ; document.getElementById("filterSearch").value = state.search; switchTab("directory"); renderDirectory(); }));
    }

    function renderAdmin() {
      const busy = TOUR_GUIDES.filter(g => g.status !== "Tersedia");
      document.getElementById("bookingLedgerList").innerHTML = busy.map(g => `<div class="py-2.5 flex justify-between items-center"><div><strong class="text-ink">${g.id} • ${g.name}</strong><div class="text-[11px] text-muted">${g.status} • ${g.destName}</div></div><button type="button" data-rel="${g.id}" class="px-3 py-1 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white font-semibold">Set Tersedia</button></div>`).join("");
      document.querySelectorAll("[data-rel]").forEach(b => b.addEventListener("click", async () => {
        const id = b.dataset.rel;
        try { await fetch(`${API_BASE_URL}/${encodeURIComponent(id)}/status`, { method: "PUT", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ status_ketersediaan: "Tersedia" }) }); } catch (e) {}
        const g = TOUR_GUIDES.find(x => x.id === id); if (g) g.status = "Tersedia"; renderAdmin(); renderDirectory();
      }));
      fetchApiTest("list");
    }

    async function fetchApiTest(mode) {
      const url = mode === "stats" ? `${API_BASE_URL}/stats` : mode === "detail" ? `${API_BASE_URL}/TG-001` : API_BASE_URL;
      try { const r = await fetch(url); const d = await r.json(); document.getElementById("apiSandboxOutput").textContent = JSON.stringify(d, null, 2); }
      catch (e) { document.getElementById("apiSandboxOutput").textContent = JSON.stringify(TOUR_GUIDES.slice(0,3), null, 2); }
    }

    function openGuideModal(id) {
      const g = TOUR_GUIDES.find(x => x.id === id); if (!g) return; state.activeGuideId = g.id;
      document.getElementById("modalGuideId").textContent = `${g.id} • ${g.status}`; document.getElementById("modalGuideName").textContent = g.name;
      document.getElementById("modalAgency").textContent = g.agency; document.getElementById("modalAddress").textContent = g.address;
      document.getElementById("modalPrice").textContent = formatIdr(g.price) + " / hari"; document.getElementById("modalRating").textContent = `★ ${g.rating.toFixed(1)} (${g.reviews} ulasan)`;
      document.getElementById("modalDestCode").textContent = g.destId; document.getElementById("modalDestName").textContent = g.destName;
      document.getElementById("modalCulCode").textContent = g.culinaryId; document.getElementById("modalCulName").textContent = g.culinaryName;
      document.getElementById("modalHotelCode").textContent = g.hotelId; document.getElementById("modalHotelName").textContent = g.hotelName;
      document.getElementById("modalTransCode").textContent = g.transportId; document.getElementById("modalTransName").textContent = g.transportName;
      document.getElementById("bookTotalReadout").textContent = formatIdr(g.price * Number(document.getElementById("bookDays").value || 1));
      document.getElementById("bookingFeedback").classList.add("hidden");
      document.getElementById("guideModal").classList.remove("hidden");
    }

    function switchTab(t) {
      ["directory","map","planner","analytics","admin"].forEach(k => document.getElementById("view-"+k).classList.toggle("hidden", k !== t));
      document.querySelectorAll(".main-tab-btn").forEach(b => b.className = b.dataset.mainTab === t ? "main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition bg-lokale text-white shadow-sm" : "main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition text-ink/80 hover:text-lokale");
      if (t === "map") initOrUpdateMap(); if (t === "planner") updatePlanner(); if (t === "analytics") renderAnalytics(); if (t === "admin") renderAdmin();
    }

    window.addEventListener("DOMContentLoaded", () => {
      const dists = Array.from(new Set(TOUR_GUIDES.map(g => g.district))).sort();
      document.getElementById("filterDistrict").innerHTML = `<option value="ALL">Semua Kecamatan (${dists.length})</option>` + dists.map(d => `<option value="${d}">Kec. ${d}</option>`).join("");
      document.getElementById("planGuideSelect").innerHTML = TOUR_GUIDES.map(g => `<option value="${g.id}">${g.id} — ${g.name} (${formatIdr(g.price)})</option>`).join("");
      renderDirectory();

      document.querySelectorAll("[data-main-tab]").forEach(b => b.addEventListener("click", () => switchTab(b.dataset.mainTab)));
      document.getElementById("btnHeroSwitchMap").addEventListener("click", () => switchTab("map"));
      document.getElementById("btnHeroSwitchPlanner").addEventListener("click", () => switchTab("planner"));
      document.getElementById("btnPartnershipOpenEco").addEventListener("click", () => switchTab("analytics"));
      document.getElementById("btnViewCards").addEventListener("click", () => { state.viewMode = "cards"; renderDirectory(); });
      document.getElementById("btnViewTable").addEventListener("click", () => { state.viewMode = "table"; renderDirectory(); });
      ["filterSearch","filterSpec","filterStatus","filterLang","filterDistrict","filterSort"].forEach(id => document.getElementById(id).addEventListener("input", e => { state[id.replace("filter","").toLowerCase().replace("district","district")] = e.target.value; renderDirectory(); }));
      document.getElementById("filterMaxPrice").addEventListener("input", e => { state.maxPrice = Number(e.target.value); document.getElementById("priceReadout").textContent = formatIdr(state.maxPrice); renderDirectory(); });
      document.querySelectorAll(".quick-dest-card").forEach(c => c.addEventListener("click", () => { state.search = c.dataset.quickDest; document.getElementById("filterSearch").value = state.search; renderDirectory(); document.getElementById("direktori-pemandu").scrollIntoView({ behavior: "smooth" }); }));
      document.getElementById("btnResetFilters").addEventListener("click", () => { state.search=""; state.spec="ALL"; state.status="ALL"; state.lang="ALL"; state.district="ALL"; state.maxPrice=850000; state.onlyFavorites=false; document.getElementById("filterSearch").value=""; renderDirectory(); });
      document.getElementById("btnEmptyReset").addEventListener("click", () => document.getElementById("btnResetFilters").click());
      document.getElementById("btnFilterFavorites").addEventListener("click", () => { state.onlyFavorites = !state.onlyFavorites; switchTab("directory"); renderDirectory(); });
      document.querySelectorAll(".landmark-btn").forEach(b => b.addEventListener("click", () => { const p = b.dataset.landmark.split(","); state.refLat=parseFloat(p[0]); state.refLng=parseFloat(p[1]); state.refLabel=p.slice(2).join(","); if(leafletMap) leafletMap.setView([state.refLat,state.refLng],12); renderMap(); }));
      document.getElementById("chkMapOnlyAvailable").addEventListener("change", renderMap);
      ["planGuideSelect","planDays","planPax","planHotelTier","planTransportMode"].forEach(id => document.getElementById(id).addEventListener("change", updatePlanner));
      document.getElementById("btnBookFromPlanner").addEventListener("click", () => openGuideModal(document.getElementById("planGuideSelect").value));
      document.querySelectorAll(".eco-tab-btn").forEach(b => b.addEventListener("click", () => { state.ecoTab = b.dataset.ecoTab; renderEco(); }));
      document.querySelectorAll(".api-test-btn").forEach(b => b.addEventListener("click", () => fetchApiTest(b.dataset.apiTest)));
      document.getElementById("btnCloseGuideModal").addEventListener("click", () => document.getElementById("guideModal").classList.add("hidden"));
      document.getElementById("bookDays").addEventListener("input", e => { const g = TOUR_GUIDES.find(x => x.id === state.activeGuideId); if (g) document.getElementById("bookTotalReadout").textContent = formatIdr(g.price * Math.max(1, Number(e.target.value||1))); });
      document.getElementById("bookingForm").addEventListener("submit", async e => {
        e.preventDefault(); const g = TOUR_GUIDES.find(x => x.id === state.activeGuideId); if (!g) return;
        const traveler = document.getElementById("bookTravelerName").value, dateVal = document.getElementById("bookDate").value, days = Number(document.getElementById("bookDays").value||1);
        try { await fetch(`${API_BASE_URL}/${encodeURIComponent(g.id)}/book`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ traveler_name: traveler, tour_date: dateVal, duration_days: days }) }); } catch (err) {}
        g.status = "Booked"; const fb = document.getElementById("bookingFeedback"); fb.textContent = `Booking berhasil untuk ${traveler} bersama ${g.name} (${days} hari). Status diperbarui menjadi Booked.`; fb.classList.remove("hidden"); renderDirectory();
      });
      document.getElementById("btnToggleCompareModal").addEventListener("click", () => {
        const items = state.compareList.map(id => TOUR_GUIDES.find(g => g.id === id)).filter(Boolean);
        document.getElementById("compareModalBody").innerHTML = items.length ? `<div class="grid grid-cols-1 md:grid-cols-${items.length} gap-4">` + items.map(g => `<div class="bg-lokalesoft/50 border border-lokaleborder rounded-2xl p-4 space-y-1.5"><strong class="text-lokale font-mono">${g.id}</strong><h4 class="font-bold text-base">${g.name}</h4><div>Rating: ★ ${g.rating}</div><div>Tarif: <strong>${formatIdr(g.price)}</strong></div><div>Bahasa: ${g.languages.join(", ")}</div><div>Wisata: ${g.destName}</div><div>Kuliner: ${g.culinaryName}</div></div>`).join("") + `</div>` : `<p class="text-center py-6 text-muted">Klik tombol + Banding pada kartu pemandu.</p>`;
        document.getElementById("compareModal").classList.remove("hidden");
      });
      document.getElementById("btnCloseCompareModal").addEventListener("click", () => document.getElementById("compareModal").classList.add("hidden"));
      document.getElementById("btnClearCompare").addEventListener("click", () => { state.compareList = []; document.getElementById("compareModal").classList.add("hidden"); renderDirectory(); });
      document.getElementById("btnOpenSqlModalTop").addEventListener("click", () => { document.getElementById("liveSqlCode").textContent = `SELECT * FROM vw_tour_guides_lengkap ORDER BY guide_id ASC;`; document.getElementById("sqlModal").classList.remove("hidden"); });
      document.getElementById("btnCloseSqlModal").addEventListener("click", () => document.getElementById("sqlModal").classList.add("hidden"));
    });
  </script>
</body>
</html>