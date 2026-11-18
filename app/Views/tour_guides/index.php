<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= esc($title ?? 'SUMUT GO — Enterprise Tour Guide Management & GIS Portal') ?></title>
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            slate950: '#090d16',
            slate900: '#0f172a',
            slate850: '#141e33',
            slate800: '#1e293b',
            emerald500: '#10b981',
            emerald400: '#34d399',
            cyan500: '#06b6d4',
            amber400: '#fbbf24'
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
    body {
      background-color: #090d16;
      color: #f1f5f9;
      font-family: 'Inter', sans-serif;
      overflow-x: hidden;
    }
    .glass-header {
      background: rgba(15, 23, 42, 0.88);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
    }
    .glass-card {
      background: linear-gradient(145deg, rgba(30, 41, 59, 0.72), rgba(15, 23, 42, 0.88));
      border: 1px solid rgba(148, 163, 184, 0.14);
    }
    .glass-card:hover {
      border-color: rgba(16, 185, 129, 0.45);
    }
    .custom-scrollbar::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
      background: #090d16;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: #334155;
      border-radius: 9999px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
      background: #10b981;
    }
    #medanLeafletMap {
      height: 540px;
      width: 100%;
      border-radius: 0.75rem;
      z-index: 10;
    }
    .leaflet-popup-content-wrapper {
      background: #0f172a !important;
      color: #f8fafc !important;
      border: 1px solid #10b981;
      border-radius: 0.65rem !important;
    }
    .leaflet-popup-tip {
      background: #0f172a !important;
    }
  </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-emerald-500 selection:text-slate-950">

  <!-- ================= TOP SYSTEM STATUS RIBBON ================= -->
  <div class="bg-slate-950 border-b border-slate-800/80 px-4 lg:px-8 py-2 text-[11px] flex flex-wrap items-center justify-between gap-2">
    <div class="flex items-center gap-3">
      <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 font-medium">
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
        PostgreSQL 3NF &amp; CI4 REST API Active
      </span>
      <span class="text-slate-400 hidden md:inline">Politeknik Negeri Medan • Sistem Informasi Pemandu Wisata (Kelompok 2)</span>
    </div>
    <div class="flex items-center gap-4 font-mono text-slate-400">
      <span>Total Guide: <strong id="topStatTotal" class="text-white">50</strong></span>
      <span>Tersedia: <strong id="topStatAvail" class="text-emerald-400">29</strong></span>
      <span>Avg Rating: <strong id="topStatRating" class="text-amber-400">4.74★</strong></span>
      <button id="btnOpenSqlModalTop" type="button" class="text-cyan-400 hover:text-cyan-300 underline">SQL &amp; API Console</button>
    </div>
  </div>

  <!-- ================= MAIN STICKY NAVBAR & WORKSPACE SWITCHER ================= -->
  <header class="sticky top-0 z-40 glass-header border-b border-slate-800/90 px-4 lg:px-8 py-3">
    <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-4">
      <!-- Brand Identity -->
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-cyan-500 flex items-center justify-center text-slate-950 font-display font-extrabold text-lg shadow-lg shadow-emerald-500/20">
          SG
        </div>
        <div>
          <div class="flex items-center gap-2">
            <span class="font-display font-extrabold text-base tracking-tight text-white">SUMUT<span class="text-emerald-400">GO</span></span>
            <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-slate-800 text-cyan-400 border border-slate-700">v2.4 Enterprise</span>
          </div>
          <p class="text-[11px] text-slate-400">Integrated Medan Tour Guide, GIS &amp; Tourism Ecosystem Hub</p>
        </div>
      </div>

      <!-- 5-Module Navigation Tabs -->
      <nav class="flex items-center gap-1.5 bg-slate-900/90 p-1 rounded-xl border border-slate-800 overflow-x-auto max-w-full">
        <button type="button" data-main-tab="directory" class="main-tab-btn px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-2 bg-emerald-500 text-slate-950 shadow">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
          <span>1. Direktori &amp; Eksplorasi (50)</span>
        </button>
        <button type="button" data-main-tab="map" class="main-tab-btn px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-2 text-slate-300 hover:text-white hover:bg-slate-800/60">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
          <span>2. Peta GIS &amp; Radar Jarak</span>
        </button>
        <button type="button" data-main-tab="planner" class="main-tab-btn px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-2 text-slate-300 hover:text-white hover:bg-slate-800/60">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          <span>3. Kalkulator Trip &amp; Itinerary</span>
        </button>
        <button type="button" data-main-tab="analytics" class="main-tab-btn px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-2 text-slate-300 hover:text-white hover:bg-slate-800/60">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
          <span>4. Analitik &amp; Hub 5 Kelompok</span>
        </button>
        <button type="button" data-main-tab="admin" class="main-tab-btn px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-2 text-slate-300 hover:text-white hover:bg-slate-800/60">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          <span>5. Admin CRUD &amp; API</span>
        </button>
      </nav>

      <!-- Compare & Favorites Quick Badges -->
      <div class="flex items-center gap-2">
        <button id="btnToggleCompareModal" type="button" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs flex items-center gap-1.5 transition">
          <span>Bandingkan</span>
          <span id="compareCountBadge" class="px-1.5 py-0.2 rounded-full bg-emerald-500 text-slate-950 font-bold text-[10px]">0</span>
        </button>
        <button id="btnFilterFavorites" type="button" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs flex items-center gap-1.5 transition">
          <span class="text-rose-400">♥</span>
          <span id="favCountBadge" class="font-mono text-[11px]">0</span>
        </button>
      </div>
    </div>
  </header>

  <!-- ================= MAIN WORKSPACE CONTAINER ================= -->
  <main class="flex-1 max-w-7xl w-full mx-auto px-4 lg:px-8 py-6">

    <!-- =================================================================== -->
    <!-- TAB 1: DIREKTORI & EKSPLORASI MULTI-DIMENSI                         -->
    <!-- =================================================================== -->
    <section id="view-directory" class="space-y-6">
      <!-- Hero Banner with Live KPI Strip -->
      <div class="relative rounded-2xl overflow-hidden border border-slate-800 shadow-2xl">
        <img src="<?= base_url('assets/img/hero_bg.jpg') ?>" alt="Sumatera Utara Highlands" class="absolute inset-0 w-full h-full object-cover object-center opacity-45" />
        <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/85 to-slate-950/50"></div>

        <div class="relative z-10 p-6 sm:p-8 lg:p-10 grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
          <div class="lg:col-span-7 space-y-3">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-medium">
              <span>Ekosistem Pariwisata Terpadu Sumatera Utara • 50 Pemandu Tersertifikasi</span>
            </div>
            <h1 class="font-display font-extrabold text-2xl sm:text-4xl text-white tracking-tight leading-tight">
              Sistem Manajemen &amp; Direktori Pintar <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-cyan-400">Pemandu Wisata Medan</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed max-w-2xl">
              Eksplorasi 50 pemandu wisata resmi melintasi 15 kecamatan di Kota Medan, terhubung secara relasional (3NF) dengan 25 destinasi wisata utama (`WST`), 25 rekomendasi kuliner (`KLN`), 20 hotel mitra (`HTL`), dan 20 unit transportasi (`TRP`).
            </p>
            <div class="pt-2 flex flex-wrap items-center gap-3">
              <button type="button" id="btnHeroSwitchMap" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-semibold text-xs transition shadow-lg shadow-emerald-500/20">
                Buka Peta GIS Basecamp (50 Titik)
              </button>
              <button type="button" id="btnHeroSwitchPlanner" class="px-4 py-2 rounded-xl bg-slate-800/90 hover:bg-slate-700 text-white border border-slate-600 text-xs font-medium transition">
                Simulasi Biaya &amp; Itinerary Tur
              </button>
              <button type="button" id="btnExportCsvFiltered" class="px-4 py-2 rounded-xl bg-slate-900/90 hover:bg-slate-800 text-cyan-400 border border-cyan-500/30 text-xs font-mono transition">
                Unduh CSV Hasil Filter
              </button>
            </div>
          </div>

          <!-- 4 KPI Cards on Right -->
          <div class="lg:col-span-5 grid grid-cols-2 gap-3">
            <div class="glass-card rounded-xl p-4">
              <span class="text-[11px] text-slate-400 uppercase tracking-wider">Pemandu Siap Jalan</span>
              <div class="flex items-baseline justify-between mt-1">
                <strong id="kpiAvailCount" class="text-2xl font-display font-extrabold text-emerald-400">29</strong>
                <span class="text-[11px] font-mono text-slate-400">dari 50 Guide</span>
              </div>
              <p class="text-[11px] text-slate-400 mt-1">10 Sedang Bertugas • 11 Booked</p>
            </div>
            <div class="glass-card rounded-xl p-4">
              <span class="text-[11px] text-slate-400 uppercase tracking-wider">Reputasi Google Maps</span>
              <div class="flex items-baseline justify-between mt-1">
                <strong id="kpiAvgRating" class="text-2xl font-display font-extrabold text-amber-400">4.74 ★</strong>
                <span class="text-[11px] font-mono text-slate-400">11.552 Ulasan</span>
              </div>
              <p class="text-[11px] text-slate-400 mt-1">2 Guide Rating Sempurna 5.0★</p>
            </div>
            <div class="glass-card rounded-xl p-4">
              <span class="text-[11px] text-slate-400 uppercase tracking-wider">Cakupan Multibahasa</span>
              <div class="flex items-baseline justify-between mt-1">
                <strong class="text-2xl font-display font-extrabold text-cyan-400">9 Bahasa</strong>
                <span class="text-[11px] font-mono text-slate-400">158 Relasi M:N</span>
              </div>
              <p class="text-[11px] text-slate-400 mt-1">ID, EN, NL, ZH, Hokkien, JP, Batak, Karo, Melayu</p>
            </div>
            <div class="glass-card rounded-xl p-4">
              <span class="text-[11px] text-slate-400 uppercase tracking-wider">Rentang Tarif Harian</span>
              <div class="flex items-baseline justify-between mt-1">
                <strong class="text-xl font-display font-extrabold text-white">250rb–850rb</strong>
              </div>
              <p class="text-[11px] text-slate-400 mt-1">Rata-rata Rp 515.500 / hari</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Featured Destination Quick-Filter Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <button type="button" data-quick-dest="Danau Toba" class="quick-dest-card group relative h-36 rounded-xl overflow-hidden border border-slate-800 hover:border-emerald-400 text-left transition">
          <img src="<?= base_url('assets/img/card_toba.jpg') ?>" alt="Danau Toba" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-500" />
          <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/55 to-transparent"></div>
          <div class="relative z-10 h-full flex flex-col justify-end p-3.5">
            <span class="text-[10px] font-mono uppercase text-emerald-400">WST-19 • WST-20 • WST-22</span>
            <h3 class="text-sm font-bold text-white">Geopark Danau Toba &amp; Samosir</h3>
            <p class="text-[11px] text-slate-300">Bukit Holbung, Tomok, Ambarita &amp; Parapat</p>
          </div>
        </button>
        <button type="button" data-quick-dest="Tangkahan" class="quick-dest-card group relative h-36 rounded-xl overflow-hidden border border-slate-800 hover:border-emerald-400 text-left transition">
          <img src="<?= base_url('assets/img/card_tangkahan.jpg') ?>" alt="Tangkahan" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-500" />
          <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/55 to-transparent"></div>
          <div class="relative z-10 h-full flex flex-col justify-end p-3.5">
            <span class="text-[10px] font-mono uppercase text-emerald-400">WST-13 • WST-14</span>
            <h3 class="text-sm font-bold text-white">Bukit Lawang &amp; CRU Gajah Tangkahan</h3>
            <p class="text-[11px] text-slate-300">Trekking Orangutan Leuser &amp; Ekowisata</p>
          </div>
        </button>
        <button type="button" data-quick-dest="Sipiso-piso" class="quick-dest-card group relative h-36 rounded-xl overflow-hidden border border-slate-800 hover:border-emerald-400 text-left transition">
          <img src="<?= base_url('assets/img/card_sipisopiso.jpg') ?>" alt="Sipiso-piso" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-500" />
          <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/55 to-transparent"></div>
          <div class="relative z-10 h-full flex flex-col justify-end p-3.5">
            <span class="text-[10px] font-mono uppercase text-emerald-400">WST-15 • WST-17 • WST-18</span>
            <h3 class="text-sm font-bold text-white">Berastagi, Sibayak &amp; Sipiso-piso</h3>
            <p class="text-[11px] text-slate-300">Dataran Tinggi Karo &amp; Sunrise Trekking</p>
          </div>
        </button>
        <button type="button" data-quick-dest="Maimun" class="quick-dest-card group relative h-36 rounded-xl overflow-hidden border border-slate-800 hover:border-emerald-400 text-left transition">
          <img src="<?= base_url('assets/img/card_heritage.jpg') ?>" alt="Istana Maimun" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-500" />
          <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/55 to-transparent"></div>
          <div class="relative z-10 h-full flex flex-col justify-end p-3.5">
            <span class="text-[10px] font-mono uppercase text-emerald-400">WST-01 • WST-02 • WST-05</span>
            <h3 class="text-sm font-bold text-white">Heritage Kesawan &amp; Istana Maimun</h3>
            <p class="text-[11px] text-slate-300">Sejarah Kesultanan Deli &amp; Kuliner Medan</p>
          </div>
        </button>
      </div>

      <!-- Advanced Multi-Facet Filter Engine -->
      <div class="glass-card rounded-2xl p-5 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-400">Filter Multi-Atribut PostgreSQL</span>
            <span id="activeFilterBadge" class="hidden text-[11px] font-mono px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300">Filter Aktif</span>
          </div>
          <div class="flex items-center gap-2">
            <!-- View Mode Switcher: Card Grid vs Relational Table -->
            <div class="inline-flex rounded-lg bg-slate-900 p-0.5 border border-slate-800">
              <button type="button" id="btnViewCards" class="px-3 py-1 rounded-md text-xs font-semibold bg-emerald-500 text-slate-950 transition">
                Grid Kartu
              </button>
              <button type="button" id="btnViewTable" class="px-3 py-1 rounded-md text-xs font-semibold text-slate-400 hover:text-white transition">
                Tabel Relasional
              </button>
            </div>
            <button type="button" id="btnShowSqlQuery" class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 border border-cyan-500/40 text-cyan-400 text-xs font-mono transition">
              Lihat SQL Query
            </button>
            <button type="button" id="btnResetFilters" class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 text-xs transition">
              ResetSemua
            </button>
          </div>
        </div>

        <!-- 6-Column Filter Controls -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
          <!-- Search -->
          <div class="lg:col-span-2">
            <label for="filterSearch" class="block text-[11px] text-slate-400 mb-1">Pencarian Cepat (Nama, ID, Wisata, Kuliner, Hotel, Mobil)</label>
            <input id="filterSearch" type="text" placeholder="Ketik contoh: Samuel, WST-22, Durian, Alphard, Hokkien..." class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-400 rounded-lg px-3 py-2 text-xs text-white focus:outline-none" />
          </div>

          <!-- Specialization -->
          <div>
            <label for="filterSpec" class="block text-[11px] text-slate-400 mb-1">Spesialisasi Tur</label>
            <select id="filterSpec" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-400 rounded-lg px-2.5 py-2 text-xs text-white focus:outline-none">
              <option value="ALL">Semua Spesialisasi (5)</option>
              <option value="Eco-Tourism & Alam">Eco-Tourism &amp; Alam</option>
              <option value="Heritage & Sejarah Medan">Heritage &amp; Sejarah Medan</option>
              <option value="Kuliner Malam & Street Food">Kuliner Malam &amp; Street Food</option>
              <option value="Photography & Budaya">Photography &amp; Budaya</option>
              <option value="City Tour & Belanja">City Tour &amp; Belanja</option>
            </select>
          </div>

          <!-- Status -->
          <div>
            <label for="filterStatus" class="block text-[11px] text-slate-400 mb-1">Status Ketersediaan</label>
            <select id="filterStatus" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-400 rounded-lg px-2.5 py-2 text-xs text-white focus:outline-none">
              <option value="ALL">Semua Status (50)</option>
              <option value="Tersedia">Tersedia</option>
              <option value="Sedang Bertugas">Sedang Bertugas</option>
              <option value="Booked">Booked</option>
            </select>
          </div>

          <!-- Language -->
          <div>
            <label for="filterLang" class="block text-[11px] text-slate-400 mb-1">Bahasa Dikuasai (M:N)</label>
            <select id="filterLang" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-400 rounded-lg px-2.5 py-2 text-xs text-white focus:outline-none">
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

          <!-- District (15 Kecamatan Medan) -->
          <div>
            <label for="filterDistrict" class="block text-[11px] text-slate-400 mb-1">Kecamatan Basecamp</label>
            <select id="filterDistrict" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-400 rounded-lg px-2.5 py-2 text-xs text-white focus:outline-none">
              <option value="ALL">Semua Kecamatan (15)</option>
            </select>
          </div>
        </div>

        <!-- Bottom Bar: Slider, Sort & Live Result Stats -->
        <div class="flex flex-wrap items-center justify-between gap-4 pt-3 border-t border-slate-800/80">
          <div class="flex items-center gap-3 flex-1 min-w-[240px] max-w-md">
            <label for="filterMaxPrice" class="text-xs text-slate-400 whitespace-nowrap">Maks Tarif / Hari:</label>
            <input id="filterMaxPrice" type="range" min="250000" max="850000" step="25000" value="850000" class="w-full accent-emerald-400 cursor-pointer" />
            <span id="priceReadout" class="text-xs font-mono text-emerald-400 font-bold whitespace-nowrap">Rp 850.000</span>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
              <label for="filterSort" class="text-xs text-slate-400">Urutkan:</label>
              <select id="filterSort" class="bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none">
                <option value="id_asc">ID Guide (TG-001 &rarr; TG-050)</option>
                <option value="rating_desc">Rating Tertinggi (5.0★)</option>
                <option value="reviews_desc">Ulasan GMaps Terbanyak</option>
                <option value="price_asc">Tarif Termurah (Rp 250rb+)</option>
                <option value="price_desc">Tarif Tertinggi (VIP)</option>
              </select>
            </div>
            <div class="text-xs text-slate-400">
              Menampilkan <strong id="resultCount" class="text-white">50</strong> guide • Rata-rata <strong id="avgRatingReadout" class="text-amber-400">4.74★</strong>
            </div>
          </div>
        </div>
      </div>

      <!-- CARD GRID VIEW -->
      <div id="guidesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"></div>

      <!-- COMPACT RELATIONAL TABLE VIEW -->
      <div id="guidesTableWrapper" class="hidden glass-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-900/95 border-b border-slate-800 text-slate-400 uppercase text-[10px] font-mono">
                <th class="py-3 px-3">ID</th>
                <th class="py-3 px-3">Nama Pemandu &amp; Agensi</th>
                <th class="py-3 px-3">Spesialisasi</th>
                <th class="py-3 px-3">Rating</th>
                <th class="py-3 px-3">Bahasa</th>
                <th class="py-3 px-3">Wisata (`WST`) &amp; Kuliner (`KLN`)</th>
                <th class="py-3 px-3">Hotel (`HTL`) &amp; Transport (`TRP`)</th>
                <th class="py-3 px-3">Tarif/Hari</th>
                <th class="py-3 px-3">Status</th>
                <th class="py-3 px-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody id="guidesTableBody" class="divide-y divide-slate-800/70"></tbody>
          </table>
        </div>
      </div>

      <!-- Empty State -->
      <div id="emptyState" class="hidden text-center py-14 glass-card rounded-2xl">
        <p class="text-sm text-slate-300 font-medium">Tidak ada pemandu wisata yang sesuai dengan filter pencarian Anda.</p>
        <button id="btnEmptyReset" type="button" class="mt-3 px-5 py-2 rounded-xl bg-emerald-500 text-slate-950 text-xs font-bold">
          Reset Semua Filter
        </button>
      </div>
    </section>

    <!-- =================================================================== -->
    <!-- TAB 2: PETA GIS INTERAKTIF & RADAR JARAK TERDEKAT (HAVERSINE)       -->
    <!-- =================================================================== -->
    <section id="view-map" class="hidden space-y-6">
      <div class="glass-card rounded-2xl p-5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-slate-800">
          <div>
            <span class="text-[11px] font-mono uppercase text-emerald-400">SPATIAL GIS BASECAMP MAPPING • HAVERSINE PROXIMITY ENGINE</span>
            <h2 class="font-display font-bold text-xl text-white mt-0.5">Peta Sebaran 50 Basecamp Pemandu Wisata &amp; Pencari Jarak Terdekat</h2>
            <p class="text-xs text-slate-400 mt-0.5">Pilih titik jemput di bawah atau klik langsung di atas peta Kota Medan untuk mengurutkan pemandu wisata terdekat dari posisi Anda.</p>
          </div>
          <!-- Preset Landmark Reference Points -->
          <div class="flex flex-wrap items-center gap-2">
            <button type="button" data-landmark="3.5612,98.6564,Kampus Politeknik Negeri Medan (Polmed)" class="landmark-btn px-3 py-1.5 rounded-lg bg-emerald-500 text-slate-950 font-semibold text-xs transition">
              📍 Polmed Medan Baru
            </button>
            <button type="button" data-landmark="3.5896,98.6738,Stasiun Besar Medan / Merdeka Walk" class="landmark-btn px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs transition">
              📍 Merdeka Walk / Stasiun
            </button>
            <button type="button" data-landmark="3.5752,98.6837,Istana Maimun" class="landmark-btn px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs transition">
              📍 Istana Maimun
            </button>
            <button type="button" data-landmark="3.6422,98.8853,Bandara Kualanamu (KNO)" class="landmark-btn px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs transition">
              ✈️ Bandara Kualanamu
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 pt-4">
          <!-- Map Container -->
          <div class="lg:col-span-8 space-y-2">
            <div id="medanLeafletMap" class="border border-slate-700 shadow-inner"></div>
            <div class="flex flex-wrap items-center justify-between text-[11px] text-slate-400 px-1">
              <span>Titik Referensi Aktif: <strong id="activeRefLabel" class="text-emerald-400">Kampus Politeknik Negeri Medan (3.5612, 98.6564)</strong></span>
              <span>Hijau = Tersedia • Kuning = Sedang Bertugas • Merah = Booked</span>
            </div>
          </div>

          <!-- Nearest Guides Sidebar Ranking -->
          <div class="lg:col-span-4 flex flex-col h-[540px] bg-slate-950/90 border border-slate-800 rounded-xl p-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
              <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-white">10 Pemandu Terdekat</h3>
                <p class="text-[11px] text-slate-400">Diurutkan berdasarkan rumus jarak Haversine (km)</p>
              </div>
              <label class="inline-flex items-center gap-1.5 text-[11px] text-slate-300 cursor-pointer">
                <input type="checkbox" id="chkMapOnlyAvailable" class="accent-emerald-400" checked />
                <span>Hanya Tersedia</span>
              </label>
            </div>
            <div id="nearestGuidesList" class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-slate-800/80 pr-1 mt-2"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- =================================================================== -->
    <!-- TAB 3: KALKULATOR TRIP MULTI-HARI & ITINERARY BUILDER               -->
    <!-- =================================================================== -->
    <section id="view-planner" class="hidden space-y-6">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left Configurator -->
        <div class="lg:col-span-5 glass-card rounded-2xl p-6 space-y-4">
          <div>
            <span class="text-[11px] font-mono uppercase text-emerald-400">SMART TRIP COST &amp; ITINERARY SIMULATOR</span>
            <h2 class="font-display font-bold text-xl text-white mt-0.5">Rancang Paket Perjalanan &amp; Estimasi Anggaran</h2>
            <p class="text-xs text-slate-400 mt-1">Pilih pemandu wisata, durasi hari, dan jumlah peserta untuk menghitung biaya terintegrasi (Guide + Armada Transportasi + Hotel + Kuliner).</p>
          </div>

          <div class="space-y-3 text-xs">
            <div>
              <label for="planGuideSelect" class="block text-slate-300 font-medium mb-1">1. Pilih Pemandu Wisata Utama (`tour_guides`)</label>
              <select id="planGuideSelect" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-400 rounded-lg px-3 py-2 text-white focus:outline-none"></select>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label for="planDays" class="block text-slate-300 font-medium mb-1">2. Durasi Perjalanan (Hari)</label>
                <input id="planDays" type="number" min="1" max="14" value="3" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-400 rounded-lg px-3 py-2 text-white font-mono focus:outline-none" />
              </div>
              <div>
                <label for="planPax" class="block text-slate-300 font-medium mb-1">3. Jumlah Peserta (Orang)</label>
                <input id="planPax" type="number" min="1" max="45" value="4" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-400 rounded-lg px-3 py-2 text-white font-mono focus:outline-none" />
              </div>
            </div>

            <div>
              <label for="planStartDate" class="block text-slate-300 font-medium mb-1">4. Tanggal Keberangkatan</label>
              <input id="planStartDate" type="date" value="2026-11-22" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-400 rounded-lg px-3 py-2 text-white focus:outline-none" />
            </div>

            <div>
              <label for="planHotelTier" class="block text-slate-300 font-medium mb-1">5. Paket Penginapan Mitra (`hotels`)</label>
              <select id="planHotelTier" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-400 rounded-lg px-3 py-2 text-white focus:outline-none">
                <option value="partner">Gunakan Hotel Rekomendasi Guide (Estimasi Rp 750.000 / malam / kamar)</option>
                <option value="luxury">Upgrade Hotel Bintang 5 VIP - JW Marriott / Adimulia (Rp 1.450.000 / malam)</option>
                <option value="budget">Opsi Budget / Favehotel / EcoLodge (Rp 420.000 / malam)</option>
                <option value="none">Tanpa Menginap (One-Day / City Tour Saja)</option>
              </select>
            </div>

            <div>
              <label for="planTransportMode" class="block text-slate-300 font-medium mb-1">6. Armada Transportasi (`transports`)</label>
              <select id="planTransportMode" class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-400 rounded-lg px-3 py-2 text-white focus:outline-none">
                <option value="partner">Armada Bawaan Guide (Termasuk BBM &amp; Driver — Rp 650.000 / hari)</option>
                <option value="hiace">Upgrade Toyota HiAce Premio / Commuter (Rp 1.250.000 / hari)</option>
                <option value="bus">Big Bus Pariwisata 45-Seater (Rp 2.600.000 / hari)</option>
                <option value="self">Kendaraan Pribadi (Hanya Sewa Jasa Pemandu)</option>
              </select>
            </div>
          </div>

          <div class="pt-3 border-t border-slate-800 flex items-center gap-3">
            <button type="button" id="btnBookFromPlanner" class="flex-1 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs uppercase tracking-wider transition">
              Booking Paket Ini Sekarang
            </button>
            <button type="button" id="btnCopyItinerary" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium transition">
              Salin Rincian
            </button>
          </div>
          <div id="plannerCopyFeedback" class="hidden text-center text-xs text-emerald-400 font-medium">Rincian anggaran &amp; itinerary berhasil disalin ke clipboard!</div>
        </div>

        <!-- Right Cost Breakdown & Generated Multi-Day Itinerary -->
        <div class="lg:col-span-7 space-y-5">
          <!-- Cost Summary Card -->
          <div class="glass-card rounded-2xl p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 pb-3">
              <div>
                <span class="text-[11px] font-mono text-cyan-400 uppercase">RINCIAN ANGGARAN TERINTEGRASI</span>
                <h3 id="planSummaryTitle" class="font-display font-bold text-lg text-white">Paket Ekspedisi 3 Hari • 4 Peserta</h3>
              </div>
              <div class="text-right">
                <span class="text-[11px] text-slate-400 block">Estimasi Per Orang</span>
                <strong id="planCostPerPax" class="text-xl font-display font-extrabold text-emerald-400">Rp 1.850.000 / pax</strong>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
              <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-3.5 flex items-center justify-between">
                <div>
                  <span class="text-slate-400 block">Jasa Pemandu Wisata</span>
                  <strong id="planGuideLabel" class="text-white">Horas Siahaan (TG-001)</strong>
                </div>
                <span id="planGuideCost" class="font-mono font-bold text-emerald-400">Rp 1.650.000</span>
              </div>
              <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-3.5 flex items-center justify-between">
                <div>
                  <span class="text-slate-400 block">Sewa Armada Transportasi</span>
                  <strong id="planTransLabel" class="text-white">TRP-03 • Innova Reborn</strong>
                </div>
                <span id="planTransCost" class="font-mono font-bold text-cyan-400">Rp 1.950.000</span>
              </div>
              <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-3.5 flex items-center justify-between">
                <div>
                  <span class="text-slate-400 block">Akomodasi Hotel Mitra</span>
                  <strong id="planHotelLabel" class="text-white">HTL-02 • Grand CityHall</strong>
                </div>
                <span id="planHotelCost" class="font-mono font-bold text-amber-400">Rp 3.000.000</span>
              </div>
              <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-3.5 flex items-center justify-between">
                <div>
                  <span class="text-slate-400 block">Paket Wisata Kuliner (`KLN`)</span>
                  <strong id="planCulLabel" class="text-white">KLN-01 • Soto Kesawan</strong>
                </div>
                <span id="planCulCost" class="font-mono font-bold text-rose-400">Rp 900.000</span>
              </div>
            </div>

            <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-4 flex items-center justify-between">
              <div>
                <span class="text-xs font-semibold text-emerald-300 block">Total Estimasi Keseluruhan Paket Tur</span>
                <span class="text-[11px] text-slate-400">Sudah termasuk pemandu, transportasi, penginapan &amp; rekomendasi kuliner</span>
              </div>
              <strong id="planGrandTotal" class="text-2xl font-display font-extrabold text-white">Rp 7.500.000</strong>
            </div>
          </div>

          <!-- Generated Day-by-Day Itinerary Timeline -->
          <div class="glass-card rounded-2xl p-6 space-y-3">
            <h3 class="font-display font-bold text-base text-white">Rencana Perjalanan Harian Otomatis (Day-by-Day Itinerary)</h3>
            <div id="planItineraryTimeline" class="space-y-3 text-xs"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- =================================================================== -->
    <!-- TAB 4: ANALITIK DATABASE & HUB INTEGRASI 5 KELOMPOK                 -->
    <!-- =================================================================== -->
    <section id="view-analytics" class="hidden space-y-6">
      <!-- Top 3 Charts Row -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="glass-card rounded-2xl p-5">
          <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-400 mb-1">Distribusi &amp; Rata-Rata Tarif Spesialisasi</h3>
          <p class="text-[11px] text-slate-400 mb-3">Rekapitulasi 50 pemandu pada tabel `specializations`</p>
          <div class="relative h-64">
            <canvas id="chartSpecializations"></canvas>
          </div>
        </div>

        <div class="glass-card rounded-2xl p-5">
          <h3 class="text-xs font-bold uppercase tracking-wider text-cyan-400 mb-1">Cakupan Penguasaan 9 Bahasa</h3>
          <p class="text-[11px] text-slate-400 mb-3">Relasi Many-to-Many pada tabel `guide_languages` (158 record)</p>
          <div class="relative h-64">
            <canvas id="chartLanguages"></canvas>
          </div>
        </div>

        <div class="glass-card rounded-2xl p-5">
          <h3 class="text-xs font-bold uppercase tracking-wider text-amber-400 mb-1">Proporsi Status Ketersediaan</h3>
          <p class="text-[11px] text-slate-400 mb-3">Status real-time `Tersedia`, `Sedang Bertugas` &amp; `Booked`</p>
          <div class="relative h-64">
            <canvas id="chartStatuses"></canvas>
          </div>
        </div>
      </div>

      <!-- Cross-Group Tourism Ecosystem Hub (Kelompok 1 - 5) -->
      <div class="glass-card rounded-2xl p-6 space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-800 pb-4">
          <div>
            <span class="text-[11px] font-mono uppercase text-emerald-400">INTEGRASI LINTAS KELOMPOK (CLIENT-SERVER ECOSYSTEM)</span>
            <h2 class="font-display font-bold text-xl text-white mt-0.5">Eksplorasi Relasi Tabel Master (Wisata, Kuliner, Penginapan &amp; Transportasi)</h2>
            <p class="text-xs text-slate-400">Klik salah satu entitas di bawah untuk memfilter pemandu wisata (Kelompok 2) yang melayani entitas tersebut.</p>
          </div>
          <div class="flex flex-wrap gap-1.5 bg-slate-950 p-1 rounded-xl border border-slate-800">
            <button type="button" data-eco-tab="destinations" class="eco-tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500 text-slate-950 transition">
              Kelompok 1: Wisata (`WST` • 25)
            </button>
            <button type="button" data-eco-tab="culinaries" class="eco-tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-300 hover:text-white transition">
              Kelompok 3: Kuliner (`KLN` • 25)
            </button>
            <button type="button" data-eco-tab="hotels" class="eco-tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-300 hover:text-white transition">
              Kelompok 4: Penginapan (`HTL` • 20)
            </button>
            <button type="button" data-eco-tab="transports" class="eco-tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-300 hover:text-white transition">
              Kelompok 5: Transportasi (`TRP` • 20)
            </button>
          </div>
        </div>

        <div id="ecosystemEntitiesGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3"></div>
      </div>
    </section>

    <!-- =================================================================== -->
    <!-- TAB 5: ADMIN CRUD, BOOKING LEDGER & REST API / SQL CONSOLE          -->
    <!-- =================================================================== -->
    <section id="view-admin" class="hidden space-y-6">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left: Add / Update Tour Guide CRUD Form -->
        <div class="lg:col-span-5 glass-card rounded-2xl p-6 space-y-4">
          <div>
            <span class="text-[11px] font-mono uppercase text-emerald-400">ADMIN CRUD • TAMBAH / UPDATE PEMANDU WISATA</span>
            <h2 class="font-display font-bold text-lg text-white mt-0.5">Form Manajemen Data Pemandu (`tour_guides`)</h2>
            <p class="text-xs text-slate-400">Tambahkan pemandu baru (contoh `TG-051`) atau perbarui data pemandu yang sudah ada.</p>
          </div>

          <form id="adminCrudForm" class="space-y-3 text-xs">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-400 mb-1">Guide ID (`guide_id`)</label>
                <input id="crudId" type="text" required value="TG-051" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white font-mono focus:border-emerald-400 focus:outline-none" />
              </div>
              <div>
                <label class="block text-slate-400 mb-1">Status Ketersediaan</label>
                <select id="crudStatus" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:border-emerald-400 focus:outline-none">
                  <option value="Tersedia">Tersedia</option>
                  <option value="Sedang Bertugas">Sedang Bertugas</option>
                  <option value="Booked">Booked</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-slate-400 mb-1">Nama Lengkap Pemandu (`nama_guide`)</label>
              <input id="crudName" type="text" required placeholder="Contoh: Rio Yudha Pratama" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:border-emerald-400 focus:outline-none" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-400 mb-1">Nama Agensi GMaps</label>
                <input id="crudAgency" type="text" required value="Medan Smart Heritage Guide" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:border-emerald-400 focus:outline-none" />
              </div>
              <div>
                <label class="block text-slate-400 mb-1">Tarif Harian (IDR)</label>
                <input id="crudPrice" type="number" step="25000" min="150000" required value="550000" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white font-mono focus:border-emerald-400 focus:outline-none" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-400 mb-1">Spesialisasi Tur</label>
                <select id="crudSpec" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:border-emerald-400 focus:outline-none">
                  <option value="Eco-Tourism & Alam">Eco-Tourism &amp; Alam</option>
                  <option value="Heritage & Sejarah Medan">Heritage &amp; Sejarah Medan</option>
                  <option value="Kuliner Malam & Street Food">Kuliner Malam &amp; Street Food</option>
                  <option value="Photography & Budaya">Photography &amp; Budaya</option>
                  <option value="City Tour & Belanja">City Tour &amp; Belanja</option>
                </select>
              </div>
              <div>
                <label class="block text-slate-400 mb-1">Kecamatan Basecamp</label>
                <input id="crudDistrict" type="text" required value="Medan Baru" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:border-emerald-400 focus:outline-none" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-slate-400 mb-1"> Bahasayang Dikuasai (Koma)</label>
                <input id="crudLangs" type="text" required value="Indonesia, Inggris, Jepang" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white focus:border-emerald-400 focus:outline-none" />
              </div>
              <div>
                <label class="block text-slate-400 mb-1">No. Telepon / WA</label>
                <input id="crudPhone" type="text" required value="+6281260998877" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-white font-mono focus:border-emerald-400 focus:outline-none" />
              </div>
            </div>

            <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold uppercase tracking-wider transition">
              Simpan Pemandu ke Database
            </button>
            <div id="crudFeedback" class="hidden p-2.5 rounded-lg bg-emerald-500/15 border border-emerald-500/40 text-emerald-300 text-xs"></div>
          </form>
        </div>

        <!-- Right: Active Booking Ledger & REST API Live Tester -->
        <div class="lg:col-span-7 space-y-5">
          <!-- Booking Transaction Ledger -->
          <div class="glass-card rounded-2xl p-6 space-y-3">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
              <div>
                <span class="text-[11px] font-mono uppercase text-amber-400">TRANSAKSI &amp; STATUS RESERVASI</span>
                <h3 class="font-display font-bold text-base text-white">Daftar Pemandu Berstatus Booked / Sedang Bertugas</h3>
              </div>
              <span id="ledgerCountBadge" class="text-xs font-mono px-2.5 py-1 rounded bg-slate-900 text-slate-300 border border-slate-800">21 Guide</span>
            </div>
            <div id="bookingLedgerList" class="max-h-60 overflow-y-auto custom-scrollbar divide-y divide-slate-800/70 text-xs pr-1"></div>
          </div>

          <!-- Interactive CodeIgniter 4 REST API Sandbox -->
          <div class="glass-card rounded-2xl p-6 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 pb-3">
              <div>
                <span class="text-[11px] font-mono uppercase text-cyan-400">CODEIGNITER 4 REST API SIMULATOR</span>
                <h3 class="font-display font-bold text-base text-white">Uji Endpoint JSON untuk Integrasi Kelompok 1–5</h3>
              </div>
              <div class="flex flex-wrap gap-1.5">
                <button type="button" data-api-test="list" class="api-test-btn px-2.5 py-1 rounded bg-emerald-500 text-slate-950 font-mono font-bold text-[11px]">GET /api/tour-guides</button>
                <button type="button" data-api-test="stats" class="api-test-btn px-2.5 py-1 rounded bg-slate-800 text-cyan-400 font-mono text-[11px]">GET /api/tour-guides/stats</button>
                <button type="button" data-api-test="detail" class="api-test-btn px-2.5 py-1 rounded bg-slate-800 text-amber-400 font-mono text-[11px]">GET /api/tour-guides/TG-001</button>
              </div>
            </div>
            <pre id="apiSandboxOutput" class="bg-slate-950 border border-slate-800 rounded-xl p-4 font-mono text-[11px] text-emerald-300 max-h-60 overflow-y-auto custom-scrollbar leading-relaxed"></pre>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- ================= FOOTER ================= -->
  <footer class="bg-slate-950 border-t border-slate-800/80 px-4 lg:px-8 py-6 mt-12 text-xs text-slate-400">
    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
      <div>
        <strong class="text-white">SUMUT GO Enterprise Portal</strong> — Sistem Informasi Pemandu Wisata Medan (CodeIgniter 4 + PostgreSQL 3NF)
      </div>
      <div class="font-mono text-[11px]">
        50 Guides • 25 Wisata (`WST`) • 25 Kuliner (`KLN`) • 20 Hotel (`HTL`) • 20 Transport (`TRP`)
      </div>
    </div>
  </footer>

  <!-- ================= MODAL 1: GUIDE DETAIL & BOOKING ================= -->
  <div id="guideModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-emerald-500/40 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto custom-scrollbar shadow-2xl">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800 sticky top-0 bg-slate-900 z-10">
        <div>
          <span id="modalGuideId" class="text-[11px] font-mono text-emerald-400 uppercase">TG-001</span>
          <h3 id="modalGuideName" class="text-lg font-display font-bold text-white">Horas Parlindungan Siahaan</h3>
        </div>
        <button id="btnCloseGuideModal" type="button" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-emerald-500 hover:text-slate-950 text-slate-300 flex items-center justify-center text-lg transition">&times;</button>
      </div>

      <div class="p-6 space-y-5 text-xs">
        <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-950 p-4 rounded-xl border border-slate-800">
          <div>
            <div class="text-slate-400 text-[11px]">Agensi Google Maps</div>
            <div id="modalAgency" class="text-sm font-bold text-white mt-0.5">Horas Medan Heritage Tour &amp; Guide</div>
            <div id="modalAddress" class="text-slate-400 text-[11px] mt-1">Jl. Jenderal Ahmad Yani No. 102, Kesawan</div>
          </div>
          <div class="text-right">
            <div id="modalPrice" class="text-base font-mono font-bold text-emerald-400">Rp 550.000 / hari</div>
            <div id="modalRating" class="text-amber-400 font-semibold mt-0.5">★ 4.9 (342 ulasan)</div>
          </div>
        </div>

        <!-- Foreign Keys Relational Box -->
        <div>
          <div class="text-[11px] uppercase tracking-wider text-emerald-400 font-bold mb-2">Integrasi Relasi Tabel Master (Foreign Keys 3NF)</div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="bg-slate-950 p-3 rounded-xl border border-slate-800">
              <span class="text-[10px] font-mono text-emerald-400 block" id="modalDestCode">destination_id: WST-02</span>
              <strong id="modalDestName" class="text-white block mt-0.5">Tjong A Fie Mansion</strong>
            </div>
            <div class="bg-slate-950 p-3 rounded-xl border border-slate-800">
              <span class="text-[10px] font-mono text-cyan-400 block" id="modalCulCode">culinary_id: KLN-01</span>
              <strong id="modalCulName" class="text-white block mt-0.5">Soto Kesawan Jl. Ahmad Yani</strong>
            </div>
            <div class="bg-slate-950 p-3 rounded-xl border border-slate-800">
              <span class="text-[10px] font-mono text-amber-400 block" id="modalHotelCode">hotel_id: HTL-02</span>
              <strong id="modalHotelName" class="text-white block mt-0.5">Grand CityHall Hotel &amp; Serviced Residences</strong>
            </div>
            <div class="bg-slate-950 p-3 rounded-xl border border-slate-800">
              <span class="text-[10px] font-mono text-purple-400 block" id="modalTransCode">transport_id: TRP-03</span>
              <strong id="modalTransName" class="text-white block mt-0.5">Toyota Kijang Innova Reborn</strong>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-slate-950 p-3.5 rounded-xl border border-slate-800">
          <div>
            <span class="text-[10px] text-slate-400 block">Bahasa Dikuasai</span>
            <strong id="modalLangs" class="text-white">Indonesia, Inggris</strong>
          </div>
          <div>
            <span class="text-[10px] text-slate-400 block">Koordinat GPS Basecamp</span>
            <strong id="modalCoords" class="text-slate-300 font-mono">3.5891, 98.6782</strong>
          </div>
          <div>
            <span class="text-[10px] text-slate-400 block">Telepon / WhatsApp</span>
            <strong id="modalPhone" class="text-emerald-400 font-mono">+6281260114501</strong>
          </div>
        </div>

        <!-- Booking Form -->
        <form id="bookingForm" class="border-t border-slate-800 pt-4 space-y-3">
          <div class="text-xs font-bold text-white">Formulir Pemesanan Pemandu Wisata (POST `/api/tour-guides/{id}/book`)</div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-[10px] text-slate-400 mb-1">Nama Pemesan</label>
              <input id="bookTravelerName" type="text" required value="Rio Yudha Wijaya" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-white focus:border-emerald-400 focus:outline-none" />
            </div>
            <div>
              <label class="block text-[10px] text-slate-400 mb-1">Tanggal Mulai Tur</label>
              <input id="bookDate" type="date" required value="2026-11-20" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-white focus:border-emerald-400 focus:outline-none" />
            </div>
            <div>
              <label class="block text-[10px] text-slate-400 mb-1">Durasi (Hari)</label>
              <input id="bookDays" type="number" min="1" max="14" value="2" required class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-white focus:border-emerald-400 focus:outline-none" />
            </div>
          </div>
          <div class="flex items-center justify-between pt-2">
            <div>
              <span class="text-slate-400">Total Biaya Pemandu: </span>
              <strong id="bookTotalReadout" class="text-sm font-mono text-emerald-400">Rp 1.100.000</strong>
            </div>
            <div class="flex items-center gap-2">
              <button type="button" id="btnSendGuideToPlanner" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-cyan-400 text-xs font-semibold transition">
                Buka di Kalkulator Trip
              </button>
              <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs uppercase tracking-wider transition">
                Konfirmasi Booking
              </button>
            </div>
          </div>
          <div id="bookingFeedback" class="hidden p-3 rounded-xl bg-emerald-500/15 border border-emerald-500/40 text-emerald-300 text-xs"></div>
        </form>
      </div>
    </div>
  </div>

  <!-- ================= MODAL 2: COMPARE UP TO 3 GUIDES SIDE-BY-SIDE ================= -->
  <div id="compareModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-emerald-500/40 rounded-2xl max-w-4xl w-full max-h-[88vh] overflow-y-auto custom-scrollbar shadow-2xl">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800 sticky top-0 bg-slate-900 z-10">
        <div>
          <span class="text-[11px] font-mono text-emerald-400 uppercase">SIDE-BY-SIDE TOUR GUIDE COMPARISON</span>
          <h3 class="text-base font-display font-bold text-white">Perbandingan Spesifikasi &amp; Paket Pemandu Wisata</h3>
        </div>
        <div class="flex items-center gap-2">
          <button id="btnClearCompare" type="button" class="px-3 py-1 rounded-lg bg-slate-800 text-xs text-slate-300 hover:text-white">Kosongkan</button>
          <button id="btnCloseCompareModal" type="button" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-emerald-500 hover:text-slate-950 text-slate-300 flex items-center justify-center text-lg transition">&times;</button>
        </div>
      </div>
      <div id="compareModalBody" class="p-6 text-xs"></div>
    </div>
  </div>

  <!-- ================= MODAL 3: LIVE SQL QUERY & PGADMIN 4 INSPECTOR ================= -->
  <div id="sqlModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-cyan-500/40 rounded-2xl max-w-3xl w-full max-h-[88vh] overflow-y-auto custom-scrollbar shadow-2xl">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800 sticky top-0 bg-slate-900 z-10">
        <div>
          <span class="text-[11px] font-mono text-cyan-400 uppercase">POSTGRESQL 3NF • PGADMIN 4 QUERY GENERATOR</span>
          <h3 class="text-base font-display font-bold text-white">Live SQL Query Berdasarkan Filter Aktif</h3>
        </div>
        <button id="btnCloseSqlModal" type="button" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-cyan-500 hover:text-slate-950 text-slate-300 flex items-center justify-center text-lg transition">&times;</button>
      </div>
      <div class="p-6 space-y-4 text-xs">
        <p class="text-slate-400">
          Salin dan jalankan query di bawah ini pada Query Tool **pgAdmin 4** untuk menghasilkan subset data yang persis sama dengan tampilan filter Anda saat ini:
        </p>
        <div class="relative">
          <pre id="liveSqlCode" class="bg-slate-950 border border-slate-800 rounded-xl p-4 font-mono text-[11px] text-emerald-300 overflow-x-auto leading-relaxed"></pre>
          <button id="btnCopySql" type="button" class="absolute top-3 right-3 px-3 py-1 rounded-lg bg-emerald-500 text-slate-950 font-bold text-[11px] hover:bg-emerald-400 transition">
            Salin SQL
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const TOUR_GUIDES = <?= json_encode($guides, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const API_BASE_URL = '<?= rtrim(site_url('api/tour-guides'), '/') ?>';

    let state = {
      activeTab: "directory",
      viewMode: "cards", // "cards" | "table"
      search: "",
      spec: "ALL",
      status: "ALL",
      lang: "ALL",
      district: "ALL",
      sort: "id_asc",
      maxPrice: 850000,
      onlyFavorites: false,
      favorites: new Set(["TG-001", "TG-013"]),
      compareList: ["TG-001", "TG-013"],
      activeGuideId: "TG-001",
      refLat: 3.5612,
      refLng: 98.6564,
      refLabel: "Kampus Politeknik Negeri Medan (Polmed)",
      ecoTab: "destinations"
    };

    let leafletMap = null;
    let leafletMarkersLayer = null;
    let refMarker = null;
    let chartsInitialized = false;
    let chartSpecInstance = null;
    let chartLangInstance = null;
    let chartStatusInstance = null;

    function formatIdr(num) {
      return "Rp " + Number(num || 0).toLocaleString("id-ID");
    }

    function haversineKm(lat1, lon1, lat2, lon2) {
      const R = 6371;
      const dLat = (lat2 - lat1) * Math.PI / 180;
      const dLon = (lon2 - lon1) * Math.PI / 180;
      const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      return R * c;
    }

    function statusBadgeClass(status) {
      if (status === "Tersedia") return "bg-emerald-500/15 text-emerald-300 border-emerald-500/40";
      if (status === "Sedang Bertugas") return "bg-amber-500/15 text-amber-300 border-amber-500/40";
      return "bg-rose-500/15 text-rose-300 border-rose-500/40";
    }

    function updateTopKpis() {
      const total = TOUR_GUIDES.length;
      const avail = TOUR_GUIDES.filter(g => g.status === "Tersedia").length;
      const avgRating = total
        ? (TOUR_GUIDES.reduce((s, g) => s + g.rating, 0) / total).toFixed(2)
        : "0.00";
      document.getElementById("topStatTotal").textContent = String(total);
      document.getElementById("topStatAvail").textContent = String(avail);
      document.getElementById("topStatRating").textContent = avgRating + "★";
      document.getElementById("kpiAvailCount").textContent = String(avail);
      document.getElementById("kpiAvgRating").textContent = avgRating + " ★";
      document.getElementById("compareCountBadge").textContent = String(state.compareList.length);
      document.getElementById("favCountBadge").textContent = String(state.favorites.size);
    }

    function populateDistrictDropdown() {
      const districts = Array.from(new Set(TOUR_GUIDES.map(g => g.district))).sort();
      const sel = document.getElementById("filterDistrict");
      sel.innerHTML = `<option value="ALL">Semua Kecamatan (${districts.length})</option>` +
        districts.map(d => `<option value="${d}">Kec. ${d}</option>`).join("");
    }

    function getFilteredGuides() {
      const q = state.search.trim().toLowerCase();
      let list = TOUR_GUIDES.filter(g => {
        if (state.onlyFavorites && !state.favorites.has(g.id)) return false;
        if (state.spec !== "ALL" && g.specialization !== state.spec) return false;
        if (state.status !== "ALL" && g.status !== state.status) return false;
        if (state.lang !== "ALL" && !g.languages.includes(state.lang)) return false;
        if (state.district !== "ALL" && g.district !== state.district) return false;
        if (g.price > state.maxPrice) return false;
        if (q) {
          const hay = [
            g.id, g.name, g.agency, g.district, g.specialization,
            g.destId, g.destName, g.culinaryId, g.culinaryName,
            g.hotelId, g.hotelName, g.transportId, g.transportName,
            g.languages.join(" ")
          ].join(" ").toLowerCase();
          if (!hay.includes(q)) return false;
        }
        return true;
      });

      list.sort((a, b) => {
        if (state.sort === "rating_desc") return b.rating - a.rating || b.reviews - a.reviews;
        if (state.sort === "reviews_desc") return b.reviews - a.reviews;
        if (state.sort === "price_asc") return a.price - b.price;
        if (state.sort === "price_desc") return b.price - a.price;
        return a.id.localeCompare(b.id);
      });

      return list;
    }

    function renderDirectory() {
      updateTopKpis();
      const list = getFilteredGuides();
      document.getElementById("resultCount").textContent = String(list.length);
      const avg = list.length
        ? (list.reduce((acc, item) => acc + item.rating, 0) / list.length).toFixed(2)
        : "0.00";
      document.getElementById("avgRatingReadout").textContent = avg + "★";

      const grid = document.getElementById("guidesGrid");
      const tableWrap = document.getElementById("guidesTableWrapper");
      const tbody = document.getElementById("guidesTableBody");
      const empty = document.getElementById("emptyState");

      if (!list.length) {
        grid.innerHTML = "";
        tbody.innerHTML = "";
        grid.classList.add("hidden");
        tableWrap.classList.add("hidden");
        empty.classList.remove("hidden");
        return;
      }
      empty.classList.add("hidden");

      if (state.viewMode === "cards") {
        grid.classList.remove("hidden");
        tableWrap.classList.add("hidden");
        grid.innerHTML = list.map(g => {
          const isFav = state.favorites.has(g.id);
          const isComp = state.compareList.includes(g.id);
          const distKm = haversineKm(state.refLat, state.refLng, g.lat, g.lng).toFixed(1);
          return `
            <article class="glass-card rounded-2xl p-5 flex flex-col justify-between transition group shadow-lg">
              <div>
                <div class="flex items-center justify-between gap-2 mb-3">
                  <div class="flex items-center gap-1.5">
                    <span class="font-mono text-[11px] px-2 py-0.5 rounded-md bg-slate-900 text-emerald-400 border border-slate-700">${g.id}</span>
                    <span class="text-[11px] text-slate-400 truncate">${g.specialization}</span>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <button type="button" data-fav-btn="${g.id}" title="Simpan ke Favorit" class="w-7 h-7 rounded-lg bg-slate-900 border border-slate-800 flex items-center justify-center text-xs ${isFav ? 'text-rose-400 border-rose-500/40' : 'text-slate-500 hover:text-white'}">
                      ♥
                    </button>
                    <span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full border ${statusBadgeClass(g.status)}">
                      ${g.status}
                    </span>
                  </div>
                </div>

                <h3 class="font-display font-bold text-base text-white group-hover:text-emerald-400 transition">
                  ${g.name}
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">${g.agency} • Kec. ${g.district} (${distKm} km)</p>

                <div class="flex flex-wrap items-center gap-2 mt-2.5 text-xs">
                  <span class="text-amber-400 font-bold">★ ${g.rating.toFixed(1)}</span>
                  <span class="text-slate-400">(${g.reviews} ulasan)</span>
                  <span class="text-slate-600">•</span>
                  <span class="text-[11px] text-cyan-300">${g.languages.join(", ")}</span>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-800/80 space-y-1.5 text-[11px]">
                  <div class="flex items-start gap-2">
                    <span class="font-mono text-emerald-400 shrink-0 w-12">${g.destId}</span>
                    <span class="text-slate-200 truncate">${g.destName}</span>
                  </div>
                  <div class="flex items-start gap-2">
                    <span class="font-mono text-cyan-400 shrink-0 w-12">${g.culinaryId}</span>
                    <span class="text-slate-300 truncate">${g.culinaryName}</span>
                  </div>
                  <div class="flex items-start gap-2">
                    <span class="font-mono text-amber-400 shrink-0 w-12">${g.hotelId}</span>
                    <span class="text-slate-400 truncate">${g.hotelName}</span>
                  </div>
                  <div class="flex items-start gap-2">
                    <span class="font-mono text-purple-400 shrink-0 w-12">${g.transportId}</span>
                    <span class="text-slate-400 truncate">${g.transportName}</span>
                  </div>
                </div>
              </div>

              <div class="mt-5 pt-3 border-t border-slate-800 flex items-center justify-between gap-2">
                <div>
                  <span class="text-[10px] text-slate-400 block uppercase">Tarif Harian</span>
                  <strong class="text-sm font-mono text-emerald-400 font-bold">${formatIdr(g.price)}</strong>
                </div>
                <div class="flex items-center gap-1.5">
                  <button type="button" data-comp-btn="${g.id}" class="px-2.5 py-1.5 rounded-lg text-[11px] font-medium border transition ${isComp ? 'bg-cyan-500/20 text-cyan-300 border-cyan-500/50' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white'}">
                    ${isComp ? '✓ Dibandingkan' : '+ Banding'}
                  </button>
                  <button type="button" data-open-guide="${g.id}" class="px-3.5 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-bold transition">
                    Detail &amp; Book
                  </button>
                </div>
              </div>
            </article>
          `;
        }).join("");
      } else {
        grid.classList.add("hidden");
        tableWrap.classList.remove("hidden");
        tbody.innerHTML = list.map(g => `
          <tr class="hover:bg-slate-900/60 transition">
            <td class="py-3 px-3 font-mono text-emerald-400 font-semibold">${g.id}</td>
            <td class="py-3 px-3">
              <div class="font-semibold text-white">${g.name}</div>
              <div class="text-[11px] text-slate-400">${g.agency} • Kec. ${g.district}</div>
            </td>
            <td class="py-3 px-3 text-slate-300">${g.specialization}</td>
            <td class="py-3 px-3">
              <span class="text-amber-400 font-bold">★ ${g.rating.toFixed(1)}</span>
              <span class="text-slate-500 block text-[10px]">${g.reviews} ulasan</span>
            </td>
            <td class="py-3 px-3 text-[11px] text-cyan-300 max-w-[160px]">${g.languages.join(", ")}</td>
            <td class="py-3 px-3 text-[11px]">
              <div><span class="font-mono text-emerald-400">${g.destId}</span> ${g.destName}</div>
              <div class="text-slate-400"><span class="font-mono text-cyan-400">${g.culinaryId}</span> ${g.culinaryName}</div>
            </td>
            <td class="py-3 px-3 text-[11px]">
              <div><span class="font-mono text-amber-400">${g.hotelId}</span> ${g.hotelName}</div>
              <div class="text-slate-400"><span class="font-mono text-purple-400">${g.transportId}</span> ${g.transportName}</div>
            </td>
            <td class="py-3 px-3 font-mono font-bold text-emerald-400 whitespace-nowrap">${formatIdr(g.price)}</td>
            <td class="py-3 px-3">
              <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border ${statusBadgeClass(g.status)}">${g.status}</span>
            </td>
            <td class="py-3 px-3 text-right">
              <button type="button" data-open-guide="${g.id}" class="px-3 py-1 rounded-lg bg-emerald-500 text-slate-950 font-bold text-[11px]">Detail</button>
            </td>
          </tr>
        `).join("");
      }

      document.querySelectorAll("[data-open-guide]").forEach(btn => {
        btn.addEventListener("click", () => openGuideModal(btn.getAttribute("data-open-guide")));
      });
      document.querySelectorAll("[data-fav-btn]").forEach(btn => {
        btn.addEventListener("click", () => {
          const id = btn.getAttribute("data-fav-btn");
          if (state.favorites.has(id)) state.favorites.delete(id);
          else state.favorites.add(id);
          renderDirectory();
        });
      });
      document.querySelectorAll("[data-comp-btn]").forEach(btn => {
        btn.addEventListener("click", () => {
          const id = btn.getAttribute("data-comp-btn");
          const idx = state.compareList.indexOf(id);
          if (idx >= 0) {
            state.compareList.splice(idx, 1);
          } else {
            if (state.compareList.length >= 3) state.compareList.shift();
            state.compareList.push(id);
          }
          renderDirectory();
        });
      });
    }

    // ================= TAB 2: GIS MAP & HAVERSINE PROXIMITY =================
    function initOrUpdateMap() {
      if (!leafletMap) {
        leafletMap = L.map("medanLeafletMap").setView([state.refLat, state.refLng], 12);
        L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}", {
          attribution: "Tiles &copy; Esri &mdash; Medan Tour Guide GIS",
          maxZoom: 18
        }).addTo(leafletMap);
        leafletMarkersLayer = L.layerGroup().addTo(leafletMap);

        leafletMap.on("click", e => {
          state.refLat = Number(e.latlng.lat.toFixed(4));
          state.refLng = Number(e.latlng.lng.toFixed(4));
          state.refLabel = `Titik Kustom Peta (${state.refLat}, ${state.refLng})`;
          renderMapMarkersAndNearest();
        });
      }
      setTimeout(() => {
        leafletMap.invalidateSize();
        renderMapMarkersAndNearest();
      }, 120);
    }

    function renderMapMarkersAndNearest() {
      if (!leafletMap || !leafletMarkersLayer) return;
      leafletMarkersLayer.clearLayers();
      document.getElementById("activeRefLabel").textContent = `${state.refLabel} (${state.refLat}, ${state.refLng})`;

      // Reference pin
      const refIcon = L.divIcon({
        className: "",
        html: `<div style="width:18px;height:18px;background:#06b6d4;border:3px solid #fff;border-radius:9999px;box-shadow:0 0 12px #06b6d4;"></div>`,
        iconSize: [18, 18],
        iconAnchor: [9, 9]
      });
      refMarker = L.marker([state.refLat, state.refLng], { icon: refIcon })
        .bindPopup(`<strong>Titik Jemput / Referensi:</strong><br/>${state.refLabel}`)
        .addTo(leafletMarkersLayer);

      const onlyAvail = document.getElementById("chkMapOnlyAvailable").checked;

      const ranked = TOUR_GUIDES
        .filter(g => !onlyAvail || g.status === "Tersedia")
        .map(g => ({
          ...g,
          distanceKm: haversineKm(state.refLat, state.refLng, g.lat, g.lng)
        }))
        .sort((a, b) => a.distanceKm - b.distanceKm);

      ranked.forEach(g => {
        const color = g.status === "Tersedia" ? "#10b981" : (g.status === "Sedang Bertugas" ? "#f59e0b" : "#f43f5e");
        const marker = L.circleMarker([g.lat, g.lng], {
          radius: 7,
          fillColor: color,
          color: "#090d16",
          weight: 2,
          opacity: 1,
          fillOpacity: 0.92
        });
        marker.bindPopup(`
          <div style="min-width:200px;font-size:12px;">
            <div style="color:#10b981;font-family:monospace;font-weight:bold;">${g.id} • ${g.status}</div>
            <div style="font-weight:bold;font-size:13px;margin-top:2px;">${g.name}</div>
            <div style="color:#94a3b8;font-size:11px;">Kec. ${g.district} • <strong>${g.distanceKm.toFixed(2)} km</strong></div>
            <div style="margin-top:4px;color:#fbbf24;">★ ${g.rating.toFixed(1)} (${g.reviews} ulasan) • ${formatIdr(g.price)}</div>
            <div style="margin-top:4px;font-size:11px;color:#cbd5e1;">Wisata: ${g.destName}</div>
          </div>
        `);
        marker.addTo(leafletMarkersLayer);
      });

      const listEl = document.getElementById("nearestGuidesList");
      listEl.innerHTML = ranked.slice(0, 12).map((g, idx) => `
        <div class="py-2.5 flex items-center justify-between gap-2">
          <div>
            <div class="flex items-center gap-1.5">
              <span class="text-[10px] font-mono px-1.5 py-0.2 rounded bg-slate-800 text-emerald-400">#${idx + 1}</span>
              <strong class="text-xs text-white">${g.name}</strong>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">Kec. ${g.district} • ${formatIdr(g.price)}</div>
          </div>
          <div class="text-right">
            <span class="text-xs font-mono font-bold text-cyan-400 block">${g.distanceKm.toFixed(2)} km</span>
            <button type="button" data-map-open="${g.id}" class="text-[10px] text-emerald-400 hover:underline">Pilih Guide</button>
          </div>
        </div>
      `).join("");

      listEl.querySelectorAll("[data-map-open]").forEach(btn => {
        btn.addEventListener("click", () => openGuideModal(btn.getAttribute("data-map-open")));
      });
    }

    // ================= TAB 3: SMART TRIP & ITINERARY CALCULATOR =================
    function populatePlannerGuides() {
      const sel = document.getElementById("planGuideSelect");
      sel.innerHTML = TOUR_GUIDES.map(g =>
        `<option value="${g.id}">${g.id} — ${g.name} (${g.specialization} • ${formatIdr(g.price)}/hari)</option>`
      ).join("");
      sel.value = state.activeGuideId;
      updateTripPlanner();
    }

    function updateTripPlanner() {
      const gId = document.getElementById("planGuideSelect").value || "TG-001";
      const g = TOUR_GUIDES.find(item => item.id === gId) || TOUR_GUIDES[0];
      const days = Math.max(1, Number(document.getElementById("planDays").value || 1));
      const pax = Math.max(1, Number(document.getElementById("planPax").value || 1));
      const hotelTier = document.getElementById("planHotelTier").value;
      const transMode = document.getElementById("planTransportMode").value;

      const guideTotal = g.price * days;

      let transDaily = 650000;
      let transLabel = `${g.transportId} • ${g.transportName}`;
      if (transMode === "hiace") { transDaily = 1250000; transLabel = "TRP-05 • Toyota HiAce Premio Luxury"; }
      else if (transMode === "bus") { transDaily = 2600000; transLabel = "TRP-11 • Big Bus Mercedes-Benz 45-Seater"; }
      else if (transMode === "self") { transDaily = 0; transLabel = "Kendaraan Pribadi Peserta"; }
      const transTotal = transDaily * days;

      const roomsNeeded = Math.ceil(pax / 2);
      const nights = Math.max(0, days - 1);
      let hotelNightly = 750000;
      let hotelLabel = `${g.hotelId} • ${g.hotelName}`;
      if (hotelTier === "luxury") { hotelNightly = 1450000; hotelLabel = "HTL-01 • JW Marriott / Adimulia VIP"; }
      else if (hotelTier === "budget") { hotelNightly = 420000; hotelLabel = "HTL-15 • Favehotel / EcoLodge"; }
      else if (hotelTier === "none") { hotelNightly = 0; hotelLabel = "Tanpa Menginap"; }
      const hotelTotal = hotelTier === "none" ? 0 : (hotelNightly * roomsNeeded * Math.max(1, nights));

      const culTotal = pax * days * 75000;
      const grandTotal = guideTotal + transTotal + hotelTotal + culTotal;
      const perPax = Math.round(grandTotal / pax);

      document.getElementById("planSummaryTitle").textContent = `Paket ${g.specialization} (${days} Hari • ${pax} Peserta)`;
      document.getElementById("planCostPerPax").textContent = `${formatIdr(perPax)} / pax`;
      document.getElementById("planGuideLabel").textContent = `${g.name} (${g.id})`;
      document.getElementById("planGuideCost").textContent = formatIdr(guideTotal);
      document.getElementById("planTransLabel").textContent = transLabel;
      document.getElementById("planTransCost").textContent = formatIdr(transTotal);
      document.getElementById("planHotelLabel").textContent = `${hotelLabel} (${roomsNeeded} kamar)`;
      document.getElementById("planHotelCost").textContent = formatIdr(hotelTotal);
      document.getElementById("planCulLabel").textContent = `${g.culinaryId} • ${g.culinaryName}`;
      document.getElementById("planCulCost").textContent = formatIdr(culTotal);
      document.getElementById("planGrandTotal").textContent = formatIdr(grandTotal);

      const timelineEl = document.getElementById("planItineraryTimeline");
      const dayItems = [];
      for (let d = 1; d <= Math.min(days, 7); d++) {
        if (d === 1) {
          dayItems.push(`
            <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800">
              <div class="font-mono text-emerald-400 font-bold">Hari 1 • Penjemputan &amp; Eksplorasi Utama (${g.destId})</div>
              <p class="text-slate-300 mt-1">Penjemputan di basecamp Kec. ${g.district} menggunakan <strong>${transLabel}</strong>, tur utama ke <strong>${g.destName}</strong> dipandu oleh <strong>${g.name}</strong>, dilanjutkan wisata kuliner di <strong>${g.culinaryName}</strong>.</p>
            </div>
          `);
        } else if (d === days) {
          dayItems.push(`
            <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800">
              <div class="font-mono text-cyan-400 font-bold">Hari ${d} • Belanja Oleh-Oleh Khas Medan &amp; Kepulangan</div>
              <p class="text-slate-300 mt-1">Check-out dari <strong>${hotelLabel}</strong>, sesi foto dokumentasi, berburu oleh-oleh khas Sumatera Utara (Bika Ambon Zulaikha / Bolu Meranti), dan pengantaran kembali.</p>
            </div>
          `);
        } else {
          dayItems.push(`
            <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800">
              <div class="font-mono text-amber-400 font-bold">Hari ${d} • Eksplorasi Lanjutan ${g.specialization}</div>
              <p class="text-slate-300 mt-1">Pendalaman rute ${g.specialization} bersama <strong>${g.name}</strong> (bahasa pengantar: ${g.languages.join(", ")}), makan siang kuliner autentik, dan istirahat di <strong>${hotelLabel}</strong>.</p>
            </div>
          `);
        }
      }
      timelineEl.innerHTML = dayItems.join("");
    }

    // ================= TAB 4: ANALYTICS CHARTS & 5-GROUP ECOSYSTEM HUB =================
    function renderAnalyticsAndEcosystem() {
      // Compute aggregations
      const specCounts = {};
      const specTariffSum = {};
      const langCounts = {};
      const statusCounts = { "Tersedia": 0, "Sedang Bertugas": 0, "Booked": 0 };

      TOUR_GUIDES.forEach(g => {
        specCounts[g.specialization] = (specCounts[g.specialization] || 0) + 1;
        specTariffSum[g.specialization] = (specTariffSum[g.specialization] || 0) + g.price;
        statusCounts[g.status] = (statusCounts[g.status] || 0) + 1;
        g.languages.forEach(l => {
          langCounts[l] = (langCounts[l] || 0) + 1;
        });
      });

      const specLabels = Object.keys(specCounts);
      const specAvgTariffs = specLabels.map(s => Math.round(specTariffSum[s] / specCounts[s] / 1000));

      const langLabels = Object.keys(langCounts).sort((a, b) => langCounts[b] - langCounts[a]);
      const langData = langLabels.map(l => langCounts[l]);

      if (!chartsInitialized && window.Chart) {
        chartsInitialized = true;
        chartSpecInstance = new Chart(document.getElementById("chartSpecializations"), {
          type: "bar",
          data: {
            labels: specLabels.map(s => s.split("&")[0].trim()),
            datasets: [
              {
                label: "Rata-rata Tarif (Ribu IDR)",
                data: specAvgTariffs,
                backgroundColor: "rgba(16, 185, 129, 0.7)",
                borderColor: "#10b981",
                borderWidth: 1,
                borderRadius: 6
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: "#cbd5e1", font: { size: 11 } } } },
            scales: {
              x: { ticks: { color: "#94a3b8", font: { size: 10 } }, grid: { display: false } },
              y: { ticks: { color: "#94a3b8", font: { size: 10 } }, grid: { color: "rgba(148,163,184,0.1)" } }
            }
          }
        });

        chartLangInstance = new Chart(document.getElementById("chartLanguages"), {
          type: "bar",
          data: {
            labels: langLabels,
            datasets: [
              {
                label: "Jumlah Pemandu",
                data: langData,
                backgroundColor: "rgba(6, 182, 212, 0.7)",
                borderColor: "#06b6d4",
                borderWidth: 1,
                borderRadius: 6
              }
            ]
          },
          options: {
            indexAxis: "y",
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              x: { ticks: { color: "#94a3b8", font: { size: 10 } }, grid: { color: "rgba(148,163,184,0.1)" } },
              y: { ticks: { color: "#e2e8f0", font: { size: 10 } }, grid: { display: false } }
            }
          }
        });

        chartStatusInstance = new Chart(document.getElementById("chartStatuses"), {
          type: "doughnut",
          data: {
            labels: ["Tersedia", "Sedang Bertugas", "Booked"],
            datasets: [
              {
                data: [statusCounts["Tersedia"], statusCounts["Sedang Bertugas"], statusCounts["Booked"]],
                backgroundColor: ["#10b981", "#f59e0b", "#f43f5e"],
                borderColor: "#0f172a",
                borderWidth: 2
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { position: "bottom", labels: { color: "#cbd5e1", font: { size: 11 } } }
            }
          }
        });
      }

      renderEcosystemEntities();
    }

    function renderEcosystemEntities() {
      const map = {};
      TOUR_GUIDES.forEach(g => {
        let code = g.destId, name = g.destName;
        if (state.ecoTab === "culinaries") { code = g.culinaryId; name = g.culinaryName; }
        else if (state.ecoTab === "hotels") { code = g.hotelId; name = g.hotelName; }
        else if (state.ecoTab === "transports") { code = g.transportId; name = g.transportName; }

        if (!map[code]) map[code] = { code, name, guides: [] };
        map[code].guides.push(g);
      });

      const items = Object.values(map).sort((a, b) => a.code.localeCompare(b.code));
      const grid = document.getElementById("ecosystemEntitiesGrid");
      grid.innerHTML = items.map(item => `
        <div class="bg-slate-950 border border-slate-800 hover:border-emerald-500/50 rounded-xl p-3.5 flex flex-col justify-between transition">
          <div>
            <div class="flex items-center justify-between text-[11px] font-mono">
              <span class="text-emerald-400 font-bold">${item.code}</span>
              <span class="text-slate-400">${item.guides.length} Guide</span>
            </div>
            <h4 class="text-xs font-bold text-white mt-1">${item.name}</h4>
            <p class="text-[11px] text-slate-400 mt-1 truncate">
              Pemandu: ${item.guides.map(x => x.name.split(" ")[0]).join(", ")}
            </p>
          </div>
          <button type="button" data-eco-filter="${item.code}" class="mt-3 w-full py-1.5 rounded-lg bg-slate-900 hover:bg-emerald-500 hover:text-slate-950 text-emerald-400 text-[11px] font-semibold border border-slate-800 transition">
            Lihat ${item.guides.length} Pemandu di Direktori
          </button>
        </div>
      `).join("");

      grid.querySelectorAll("[data-eco-filter]").forEach(btn => {
        btn.addEventListener("click", () => {
          state.search = btn.getAttribute("data-eco-filter");
          syncInputs();
          switchMainTab("directory");
          renderDirectory();
        });
      });
    }

    // ================= TAB 5: ADMIN CRUD & REST API SANDBOX =================
    function renderAdminLedger() {
      const busy = TOUR_GUIDES.filter(g => g.status !== "Tersedia");
      document.getElementById("ledgerCountBadge").textContent = `${busy.length} Guide Aktif / Booked`;
      const container = document.getElementById("bookingLedgerList");
      container.innerHTML = busy.map(g => `
        <div class="py-2.5 flex items-center justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <span class="font-mono text-emerald-400 font-bold">${g.id}</span>
              <strong class="text-white">${g.name}</strong>
              <span class="text-[10px] px-2 py-0.2 rounded-full border ${statusBadgeClass(g.status)}">${g.status}</span>
            </div>
            <div class="text-[11px] text-slate-400 mt-0.5">${g.destName} • ${formatIdr(g.price)}/hari</div>
          </div>
          <button type="button" data-release-guide="${g.id}" class="px-3 py-1 rounded-lg bg-emerald-500/15 hover:bg-emerald-500 text-emerald-300 hover:text-slate-950 border border-emerald-500/40 text-[11px] font-semibold transition">
            Set Tersedia
          </button>
        </div>
      `).join("");

      container.querySelectorAll("[data-release-guide]").forEach(btn => {
        btn.addEventListener("click", () => {
          const id = btn.getAttribute("data-release-guide");
          const g = TOUR_GUIDES.find(x => x.id === id);
          if (g) {
            g.status = "Tersedia";
            renderAdminLedger();
            renderDirectory();
          }
        });
      });
    }

    function runApiSimulator(mode) {
      const out = document.getElementById("apiSandboxOutput");
      if (mode === "stats") {
        const avail = TOUR_GUIDES.filter(g => g.status === "Tersedia").length;
        out.textContent = JSON.stringify({
          endpoint: "GET /api/tour-guides/stats",
          status: 200,
          data: {
            total_guides: TOUR_GUIDES.length,
            available_guides: avail,
            avg_rating: 4.74,
            avg_price_idr: 515500,
            tables_normalized_3nf: ["tour_guides", "destinations", "culinaries", "hotels", "transports", "specializations", "languages", "guide_languages"]
          }
        }, null, 2);
      } else if (mode === "detail") {
        out.textContent = JSON.stringify({
          endpoint: "GET /api/tour-guides/TG-001",
          status: 200,
          data: TOUR_GUIDES[0]
        }, null, 2);
      } else {
        out.textContent = JSON.stringify({
          endpoint: "GET /api/tour-guides?status=Tersedia",
          status: 200,
          count: TOUR_GUIDES.length,
          sample_records: TOUR_GUIDES.slice(0, 3)
        }, null, 2);
      }
    }

    // ================= MODALS (DETAIL, COMPARE, SQL) =================
    function openGuideModal(guideId) {
      const g = TOUR_GUIDES.find(item => item.id === guideId);
      if (!g) return;
      state.activeGuideId = g.id;

      document.getElementById("modalGuideId").textContent = `${g.id} • ${g.specialization} • ${g.status}`;
      document.getElementById("modalGuideName").textContent = g.name;
      document.getElementById("modalAgency").textContent = g.agency;
      document.getElementById("modalAddress").textContent = g.address;
      document.getElementById("modalPrice").textContent = `${formatIdr(g.price)} / hari`;
      document.getElementById("modalRating").textContent = `★ ${g.rating.toFixed(1)} (${g.reviews} ulasan GMaps)`;

      document.getElementById("modalDestCode").textContent = `destination_id: ${g.destId}`;
      document.getElementById("modalDestName").textContent = g.destName;
      document.getElementById("modalCulCode").textContent = `culinary_id: ${g.culinaryId}`;
      document.getElementById("modalCulName").textContent = g.culinaryName;
      document.getElementById("modalHotelCode").textContent = `hotel_id: ${g.hotelId}`;
      document.getElementById("modalHotelName").textContent = g.hotelName;
      document.getElementById("modalTransCode").textContent = `transport_id: ${g.transportId}`;
      document.getElementById("modalTransName").textContent = g.transportName;

      document.getElementById("modalLangs").textContent = g.languages.join(", ");
      document.getElementById("modalCoords").textContent = `${g.lat}, ${g.lng}`;
      document.getElementById("modalPhone").textContent = g.phone;

      const days = Math.max(1, Number(document.getElementById("bookDays").value || 1));
      document.getElementById("bookTotalReadout").textContent = formatIdr(g.price * days);
      document.getElementById("bookingFeedback").classList.add("hidden");

      document.getElementById("guideModal").classList.remove("hidden");
    }

    function renderCompareModal() {
      const body = document.getElementById("compareModalBody");
      const items = state.compareList.map(id => TOUR_GUIDES.find(g => g.id === id)).filter(Boolean);
      if (!items.length) {
        body.innerHTML = `<p class="text-center text-slate-400 py-8">Belum ada pemandu yang dipilih untuk dibandingkan. Klik tombol <strong>+ Banding</strong> pada kartu pemandu (maksimal 3 pemandu).</p>`;
        return;
      }
      body.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-${items.length} gap-4">
          ${items.map(g => `
            <div class="bg-slate-950 border border-slate-800 rounded-xl p-4 space-y-2.5">
              <div class="flex items-center justify-between">
                <span class="font-mono text-emerald-400 font-bold">${g.id}</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full border ${statusBadgeClass(g.status)}">${g.status}</span>
              </div>
              <h4 class="font-display font-bold text-base text-white">${g.name}</h4>
              <p class="text-[11px] text-slate-400">${g.agency}</p>
              <div class="pt-2 border-t border-slate-800 space-y-1.5">
                <div><span class="text-slate-400">Spesialisasi:</span> <strong class="text-white">${g.specialization}</strong></div>
                <div><span class="text-slate-400">Rating GMaps:</span> <strong class="text-amber-400">★ ${g.rating.toFixed(1)} (${g.reviews} ulasan)</strong></div>
                <div><span class="text-slate-400">Tarif Harian:</span> <strong class="text-emerald-400 font-mono">${formatIdr(g.price)}</strong></div>
                <div><span class="text-slate-400">Bahasa:</span> <span class="text-cyan-300">${g.languages.join(", ")}</span></div>
                <div><span class="text-slate-400">Basecamp:</span> <span>Kec. ${g.district}</span></div>
                <div><span class="text-slate-400">Wisata Utama:</span> <span class="text-white">${g.destId} - ${g.destName}</span></div>
                <div><span class="text-slate-400">Kuliner:</span> <span>${g.culinaryId} - ${g.culinaryName}</span></div>
                <div><span class="text-slate-400">Hotel:</span> <span>${g.hotelId} - ${g.hotelName}</span></div>
                <div><span class="text-slate-400">Armada:</span> <span>${g.transportId} - ${g.transportName}</span></div>
              </div>
            </div>
          `).join("")}
        </div>
      `;
    }

    function buildLiveSqlQuery() {
      const conds = [];
      if (state.spec !== "ALL") conds.push(`spesialisasi_tur = '${state.spec.replace(/'/g, "''")}'`);
      if (state.status !== "ALL") conds.push(`status_ketersediaan = '${state.status}'`);
      if (state.lang !== "ALL") conds.push(`bahasa_dikuasai ILIKE '%${state.lang}%'`);
      if (state.district !== "ALL") conds.push(`kecamatan = '${state.district}'`);
      if (state.maxPrice < 850000) conds.push(`tarif_per_hari_idr <= ${state.maxPrice}`);
      if (state.search.trim()) {
        const sq = state.search.trim().replace(/'/g, "''");
        conds.push(`(nama_guide ILIKE '%${sq}%' OR nama_wisata_utama ILIKE '%${sq}%' OR rekomendasi_kuliner ILIKE '%${sq}%')`);
      }
      const whereClause = conds.length ? "\nWHERE " + conds.join("\n  AND ") : "";
      return `-- Query PostgreSQL 3NF (pgAdmin 4) sesuai filter aktif:
SELECT
    guide_id, nama_guide, spesialisasi_tur, rating_gmaps, jumlah_ulasan_gmaps,
    kecamatan, bahasa_dikuasai, tarif_per_hari_idr, status_ketersediaan,
    destination_id, nama_wisata_utama, culinary_id, rekomendasi_kuliner,
    hotel_id, nama_hotel, transport_id, nama_transportasi
FROM vw_tour_guides_lengkap${whereClause}
ORDER BY guide_id ASC;`;
    }

    function switchMainTab(tabName) {
      state.activeTab = tabName;
      ["directory", "map", "planner", "analytics", "admin"].forEach(t => {
        const el = document.getElementById("view-" + t);
        if (el) el.classList.toggle("hidden", t !== tabName);
      });
      document.querySelectorAll(".main-tab-btn").forEach(btn => {
        const active = btn.getAttribute("data-main-tab") === tabName;
        btn.className = active
          ? "main-tab-btn px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-2 bg-emerald-500 text-slate-950 shadow"
          : "main-tab-btn px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-2 text-slate-300 hover:text-white hover:bg-slate-800/60";
      });

      if (tabName === "map") initOrUpdateMap();
      if (tabName === "planner") updateTripPlanner();
      if (tabName === "analytics") renderAnalyticsAndEcosystem();
      if (tabName === "admin") {
        renderAdminLedger();
        runApiSimulator("list");
      }
    }

    function syncInputs() {
      document.getElementById("filterSearch").value = state.search;
      document.getElementById("filterSpec").value = state.spec;
      document.getElementById("filterStatus").value = state.status;
      document.getElementById("filterLang").value = state.lang;
      document.getElementById("filterDistrict").value = state.district;
      document.getElementById("filterSort").value = state.sort;
      document.getElementById("filterMaxPrice").value = String(state.maxPrice);
      document.getElementById("priceReadout").textContent = formatIdr(state.maxPrice);
    }

    window.addEventListener("DOMContentLoaded", () => {
      populateDistrictDropdown();
      populatePlannerGuides();
      renderDirectory();

      // Main Tab Switcher
      document.querySelectorAll(".main-tab-btn").forEach(btn => {
        btn.addEventListener("click", () => switchMainTab(btn.getAttribute("data-main-tab")));
      });
      document.getElementById("btnHeroSwitchMap").addEventListener("click", () => switchMainTab("map"));
      document.getElementById("btnHeroSwitchPlanner").addEventListener("click", () => switchMainTab("planner"));

      // View mode toggle (Cards vs Table)
      document.getElementById("btnViewCards").addEventListener("click", () => {
        state.viewMode = "cards";
        document.getElementById("btnViewCards").className = "px-3 py-1 rounded-md text-xs font-semibold bg-emerald-500 text-slate-950 transition";
        document.getElementById("btnViewTable").className = "px-3 py-1 rounded-md text-xs font-semibold text-slate-400 hover:text-white transition";
        renderDirectory();
      });
      document.getElementById("btnViewTable").addEventListener("click", () => {
        state.viewMode = "table";
        document.getElementById("btnViewTable").className = "px-3 py-1 rounded-md text-xs font-semibold bg-emerald-500 text-slate-950 transition";
        document.getElementById("btnViewCards").className = "px-3 py-1 rounded-md text-xs font-semibold text-slate-400 hover:text-white transition";
        renderDirectory();
      });

      // Filters
      document.getElementById("filterSearch").addEventListener("input", e => {
        state.search = e.target.value;
        renderDirectory();
      });
      document.getElementById("filterSpec").addEventListener("change", e => {
        state.spec = e.target.value;
        renderDirectory();
      });
      document.getElementById("filterStatus").addEventListener("change", e => {
        state.status = e.target.value;
        renderDirectory();
      });
      document.getElementById("filterLang").addEventListener("change", e => {
        state.lang = e.target.value;
        renderDirectory();
      });
      document.getElementById("filterDistrict").addEventListener("change", e => {
        state.district = e.target.value;
        renderDirectory();
      });
      document.getElementById("filterSort").addEventListener("change", e => {
        state.sort = e.target.value;
        renderDirectory();
      });
      document.getElementById("filterMaxPrice").addEventListener("input", e => {
        state.maxPrice = Number(e.target.value);
        document.getElementById("priceReadout").textContent = formatIdr(state.maxPrice);
        renderDirectory();
      });

      // Quick destination cards
      document.querySelectorAll(".quick-dest-card").forEach(card => {
        card.addEventListener("click", () => {
          state.search = card.getAttribute("data-quick-dest");
          syncInputs();
          renderDirectory();
        });
      });

      // Favorites filter toggle
      document.getElementById("btnFilterFavorites").addEventListener("click", () => {
        state.onlyFavorites = !state.onlyFavorites;
        switchMainTab("directory");
        renderDirectory();
      });

      // Reset all filters
      const resetAll = () => {
        state.search = "";
        state.spec = "ALL";
        state.status = "ALL";
        state.lang = "ALL";
        state.district = "ALL";
        state.sort = "id_asc";
        state.maxPrice = 850000;
        state.onlyFavorites = false;
        syncInputs();
        renderDirectory();
      };
      document.getElementById("btnResetFilters").addEventListener("click", resetAll);
      document.getElementById("btnEmptyReset").addEventListener("click", resetAll);

      // Export filtered CSV
      document.getElementById("btnExportCsvFiltered").addEventListener("click", () => {
        const rows = getFilteredGuides();
        const headers = ["guide_id", "nama_guide", "spesialisasi_tur", "rating_gmaps", "tarif_per_hari_idr", "status_ketersediaan", "nama_wisata_utama", "rekomendasi_kuliner", "nama_hotel", "nama_transportasi"];
        const csvLines = [headers.join(",")].concat(rows.map(g =>
          [g.id, `"${g.name}"`, `"${g.specialization}"`, g.rating, g.price, g.status, `"${g.destName}"`, `"${g.culinaryName}"`, `"${g.hotelName}"`, `"${g.transportName}"`].join(",")
        ));
        const blob = new Blob([csvLines.join("\n")], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = "filtered_tour_guides_medan.csv";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
      });

      // Map Landmark presets
      document.querySelectorAll(".landmark-btn").forEach(btn => {
        btn.addEventListener("click", () => {
          const parts = btn.getAttribute("data-landmark").split(",");
          state.refLat = parseFloat(parts[0]);
          state.refLng = parseFloat(parts[1]);
          state.refLabel = parts.slice(2).join(",");
          if (leafletMap) leafletMap.setView([state.refLat, state.refLng], 12);
          renderMapMarkersAndNearest();
        });
      });
      document.getElementById("chkMapOnlyAvailable").addEventListener("change", renderMapMarkersAndNearest);

      // Planner inputs
      ["planGuideSelect", "planDays", "planPax", "planStartDate", "planHotelTier", "planTransportMode"].forEach(id => {
        document.getElementById(id).addEventListener("input", updateTripPlanner);
        document.getElementById(id).addEventListener("change", updateTripPlanner);
      });
      document.getElementById("btnBookFromPlanner").addEventListener("click", () => {
        const gId = document.getElementById("planGuideSelect").value || "TG-001";
        document.getElementById("bookDays").value = document.getElementById("planDays").value;
        openGuideModal(gId);
      });
      document.getElementById("btnCopyItinerary").addEventListener("click", () => {
        const summary = document.getElementById("planSummaryTitle").textContent + " — Total: " + document.getElementById("planGrandTotal").textContent;
        const ta = document.createElement("textarea");
        ta.value = summary;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand("copy");
        document.body.removeChild(ta);
        const fb = document.getElementById("plannerCopyFeedback");
        fb.classList.remove("hidden");
        setTimeout(() => fb.classList.add("hidden"), 2500);
      });

      // Ecosystem sub-tabs
      document.querySelectorAll(".eco-tab-btn").forEach(btn => {
        btn.addEventListener("click", () => {
          state.ecoTab = btn.getAttribute("data-eco-tab");
          document.querySelectorAll(".eco-tab-btn").forEach(b => {
            const act = b.getAttribute("data-eco-tab") === state.ecoTab;
            b.className = act
              ? "eco-tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-500 text-slate-950 transition"
              : "eco-tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-300 hover:text-white transition";
          });
          renderEcosystemEntities();
        });
      });

      // Admin CRUD Form
      document.getElementById("adminCrudForm").addEventListener("submit", e => {
        e.preventDefault();
        const id = document.getElementById("crudId").value.trim().toUpperCase();
        const name = document.getElementById("crudName").value.trim();
        const agency = document.getElementById("crudAgency").value.trim();
        const price = Number(document.getElementById("crudPrice").value || 500000);
        const spec = document.getElementById("crudSpec").value;
        const district = document.getElementById("crudDistrict").value.trim();
        const status = document.getElementById("crudStatus").value;
        const langs = document.getElementById("crudLangs").value.split(",").map(s => s.trim()).filter(Boolean);
        const phone = document.getElementById("crudPhone").value.trim();

        const existing = TOUR_GUIDES.find(g => g.id === id);
        if (existing) {
          existing.name = name;
          existing.agency = agency;
          existing.price = price;
          existing.specialization = spec;
          existing.district = district;
          existing.status = status;
          existing.languages = langs;
          existing.phone = phone;
        } else {
          TOUR_GUIDES.push({
            id,
            name,
            agency,
            rating: 4.9,
            reviews: 25,
            address: `Jl. Utama Kec. ${district}, Kota Medan`,
            district,
            lat: 3.575 + (Math.random() - 0.5) * 0.04,
            lng: 98.675 + (Math.random() - 0.5) * 0.04,
            phone,
            languages: langs,
            specialization: spec,
            price,
            status,
            destId: "WST-01",
            destName: "Istana Maimun",
            culinaryId: "KLN-01",
            culinaryName: "Soto Kesawan Jl. Ahmad Yani",
            hotelId: "HTL-02",
            hotelName: "Grand CityHall Hotel & Serviced Residences",
            transportId: "TRP-03",
            transportName: "Toyota Kijang Innova Reborn"
          });
        }

        const fb = document.getElementById("crudFeedback");
        fb.textContent = `Data pemandu ${id} (${name}) berhasil disimpan! Total pemandu sekarang: ${TOUR_GUIDES.length}.`;
        fb.classList.remove("hidden");
        populateDistrictDropdown();
        populatePlannerGuides();
        renderAdminLedger();
        renderDirectory();
      });

      // API Sandbox buttons
      document.querySelectorAll(".api-test-btn").forEach(btn => {
        btn.addEventListener("click", () => runApiSimulator(btn.getAttribute("data-api-test")));
      });

      // Guide Detail Modal & Booking
      document.getElementById("btnCloseGuideModal").addEventListener("click", () => {
        document.getElementById("guideModal").classList.add("hidden");
      });
      document.getElementById("btnSendGuideToPlanner").addEventListener("click", () => {
        document.getElementById("guideModal").classList.add("hidden");
        document.getElementById("planGuideSelect").value = state.activeGuideId;
        switchMainTab("planner");
      });
      document.getElementById("bookDays").addEventListener("input", e => {
        const g = TOUR_GUIDES.find(item => item.id === state.activeGuideId);
        if (!g) return;
        const days = Math.max(1, Number(e.target.value || 1));
        document.getElementById("bookTotalReadout").textContent = formatIdr(g.price * days);
      });

      document.getElementById("bookingForm").addEventListener("submit", async e => {
        e.preventDefault();
        const g = TOUR_GUIDES.find(item => item.id === state.activeGuideId);
        if (!g) return;
        const traveler = document.getElementById("bookTravelerName").value;
        const dateVal = document.getElementById("bookDate").value;
        const days = Math.max(1, Number(document.getElementById("bookDays").value || 1));
        const fb = document.getElementById("bookingFeedback");

        try {
          const res = await fetch(`${API_BASE_URL}/${encodeURIComponent(g.id)}/book`, {
            method: "POST",
            headers: { "Content-Type": "application/json", "Accept": "application/json" },
            body: JSON.stringify({ traveler_name: traveler, tour_date: dateVal, duration_days: days })
          });
          if (res.ok) {
            const result = await res.json();
            g.status = "Booked";
            fb.innerHTML = `${result.message} Total Biaya: <strong>${formatIdr(g.price * days)}</strong> (Tersimpan di PostgreSQL).`;
          } else {
            g.status = "Booked";
            fb.innerHTML = `Booking berhasil dicatat untuk <strong>${traveler}</strong> bersama <strong>${g.name} (${g.id})</strong> pada <strong>${dateVal}</strong> (${days} hari). Status diperbarui menjadi <strong>Booked</strong>.`;
          }
        } catch (err) {
          g.status = "Booked";
          fb.innerHTML = `Booking berhasil dicatat untuk <strong>${traveler}</strong> bersama <strong>${g.name} (${g.id})</strong> pada <strong>${dateVal}</strong> (${days} hari). Status diperbarui menjadi <strong>Booked</strong>.`;
        }
        fb.classList.remove("hidden");
        document.getElementById("modalGuideId").textContent = `${g.id} • ${g.specialization} • ${g.status}`;
        renderDirectory();
      });

      // Compare Modal
      document.getElementById("btnToggleCompareModal").addEventListener("click", () => {
        renderCompareModal();
        document.getElementById("compareModal").classList.remove("hidden");
      });
      document.getElementById("btnCloseCompareModal").addEventListener("click", () => {
        document.getElementById("compareModal").classList.add("hidden");
      });
      document.getElementById("btnClearCompare").addEventListener("click", () => {
        state.compareList = [];
        renderCompareModal();
        renderDirectory();
      });

      // SQL Modal
      const openSql = () => {
        document.getElementById("liveSqlCode").textContent = buildLiveSqlQuery();
        document.getElementById("sqlModal").classList.remove("hidden");
      };
      document.getElementById("btnShowSqlQuery").addEventListener("click", openSql);
      document.getElementById("btnOpenSqlModalTop").addEventListener("click", openSql);
      document.getElementById("btnCloseSqlModal").addEventListener("click", () => {
        document.getElementById("sqlModal").classList.add("hidden");
      });
      document.getElementById("btnCopySql").addEventListener("click", () => {
        const ta = document.createElement("textarea");
        ta.value = document.getElementById("liveSqlCode").textContent;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand("copy");
        document.body.removeChild(ta);
        const btn = document.getElementById("btnCopySql");
        btn.textContent = "Tersalin!";
        setTimeout(() => { btn.textContent = "Salin SQL"; }, 1500);
      });
    });
  </script>
</body>
</html>