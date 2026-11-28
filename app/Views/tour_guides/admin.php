<?php
/**
 * @var string $title
 * @var array<int, array<string, mixed>> $guides
 * @var array<string, mixed> $stats
 */
$title = $title ?? 'SumutGO | Portal Admin, Live API & SQL';
$guides = $guides ?? [];
$stats = $stats ?? [];

$totalGuides = count($guides);
$availableGuides = (int) ($stats['available_guides'] ?? count(array_filter($guides, static fn ($g) => ($g['status'] ?? '') === 'Tersedia')));
$bookedGuides = (int) ($stats['booked_guides'] ?? count(array_filter($guides, static fn ($g) => ($g['status'] ?? '') === 'Booked')));
$onDutyGuides = (int) ($stats['on_duty_guides'] ?? count(array_filter($guides, static fn ($g) => ($g['status'] ?? '') === 'Sedang Bertugas')));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= esc($title) ?></title>
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=JetBrains+Mono:wght@400;500;600&amp;family=Plus+Jakarta+Sans:wght@600;700;800&amp;display=swap" rel="stylesheet">
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
</head>
<body class="bg-lokalesoft/40 text-ink antialiased min-h-screen flex flex-col">

  <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 min-h-[4.5rem] py-3 flex items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <a href="<?= site_url('/') ?>" class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-full bg-lokale text-white flex items-center justify-center shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-6-5.373-6-10a6 6 0 1112 0c0 4.627-6 10-6 10z"/>
              <circle cx="12" cy="11" r="2.2" fill="currentColor" stroke="none"/>
            </svg>
          </div>
          <div class="leading-none">
            <span class="font-display font-extrabold text-xl tracking-tight text-lokale">Sumut<span class="text-ink">GO</span></span>
            <span class="block text-[10px] tracking-[0.16em] text-muted uppercase mt-0.5">Portal Admin &amp; API</span>
          </div>
        </a>
        <span class="hidden sm:inline-flex px-2.5 py-1 rounded-full bg-lokalesoft border border-lokaleborder text-lokale font-mono text-[10px] font-semibold">CodeIgniter 4 + PostgreSQL 3NF</span>
      </div>

      <a href="<?= site_url('/') ?>" class="px-4 py-2 rounded-full bg-lokale hover:bg-lokaledark text-white text-xs font-semibold transition shadow-sm">
        &larr; Kembali ke Halaman Pengunjung
      </a>
    </div>
  </header>

  <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Ringkasan Status -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white rounded-2xl border border-lokaleborder p-4">
        <span class="text-[11px] font-semibold text-muted uppercase">Total Pemandu</span>
        <div id="kpiTotal" class="font-display font-extrabold text-2xl text-lokale mt-1"><?= $totalGuides ?></div>
      </div>
      <div class="bg-white rounded-2xl border border-lokaleborder p-4">
        <span class="text-[11px] font-semibold text-muted uppercase">Tersedia</span>
        <div id="kpiAvail" class="font-display font-extrabold text-2xl text-emerald-600 mt-1"><?= $availableGuides ?></div>
      </div>
      <div class="bg-white rounded-2xl border border-lokaleborder p-4">
        <span class="text-[11px] font-semibold text-muted uppercase">Sedang Bertugas</span>
        <div id="kpiDuty" class="font-display font-extrabold text-2xl text-amber-600 mt-1"><?= $onDutyGuides ?></div>
      </div>
      <div class="bg-white rounded-2xl border border-lokaleborder p-4">
        <span class="text-[11px] font-semibold text-muted uppercase">Booked</span>
        <div id="kpiBooked" class="font-display font-extrabold text-2xl text-rose-600 mt-1"><?= $bookedGuides ?></div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Manajemen Status Reservasi -->
      <div class="lg:col-span-5 bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-3 text-xs">
        <h1 class="font-display font-extrabold text-xl text-ink">Status Reservasi &amp; Update Cepat</h1>
        <p class="text-muted">Klik tombol <strong>Set Tersedia</strong> untuk langsung memperbarui status pemandu di database PostgreSQL melalui endpoint <code>PUT /api/tour-guides/{id}/status</code>.</p>
        <div id="adminActionFeedback" class="hidden p-2.5 rounded-xl border text-[11px] font-medium"></div>
        <div id="bookingLedgerList" class="max-h-[420px] overflow-y-auto divide-y divide-slate-100"></div>
      </div>

      <!-- Live API & SQL -->
      <div class="lg:col-span-7 space-y-6">
        <div class="bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <h2 class="font-display font-bold text-base text-ink">Live RESTful API CodeIgniter 4</h2>
            <div class="flex flex-wrap gap-1.5">
              <button type="button" data-api-test="list" class="api-test-btn px-3 py-1.5 rounded-full bg-lokale text-white font-mono text-[11px]">GET /api/tour-guides</button>
              <button type="button" data-api-test="stats" class="api-test-btn px-3 py-1.5 rounded-full bg-lokalesoft text-lokale font-mono text-[11px]">GET /stats</button>
              <button type="button" data-api-test="detail" class="api-test-btn px-3 py-1.5 rounded-full bg-lokalesoft text-lokale font-mono text-[11px]">GET /TG-001</button>
              <button type="button" data-api-test="v1" class="api-test-btn px-3 py-1.5 rounded-full bg-lokalesoft text-lokale font-mono text-[11px]">GET /api/v1/guides</button>
              <button type="button" data-api-test="transport" class="api-test-btn px-3 py-1.5 rounded-full bg-lokalesoft text-lokale font-mono text-[11px]">GET /transport/status</button>
            </div>
          </div>
          <pre id="apiSandboxOutput" class="bg-slate-900 text-sky-200 rounded-2xl p-4 font-mono text-[11px] max-h-80 overflow-y-auto"></pre>
        </div>

        <div class="bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-3">
          <h2 class="font-display font-bold text-base text-ink">Live SQL Query (PostgreSQL 3NF)</h2>
          <pre class="bg-slate-900 text-sky-200 rounded-2xl p-4 font-mono text-[11px] overflow-x-auto">SELECT * FROM vw_guides_detail ORDER BY guide_id ASC;</pre>
        </div>
      </div>
    </div>

    <!-- Konfirmasi Pendaftar, Kontak Pemesanan & Booking (API v1) -->
    <section class="bg-white rounded-3xl border border-lokaleborder p-6 shadow-sm space-y-4 text-xs">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Verifikasi &amp; Konfirmasi Admin Web (REST API v1)</span>
          <h2 class="font-display font-extrabold text-xl text-ink mt-0.5">Konfirmasi Pendaftar, Kontak &amp; Booking</h2>
        </div>
        <div id="v1AdminSessionBadge" class="hidden flex-wrap items-center gap-2"></div>
      </div>

      <!-- Form Login Admin API v1 (Tanpa pilihan role; role diverifikasi dari tabel users PostgreSQL) -->
      <div id="v1AdminLoginBox" class="rounded-2xl bg-lokalesoft/50 border border-lokaleborder p-4 space-y-3">
        <p class="text-xs text-ink font-semibold">Masuk sebagai Admin untuk memverifikasi nomor HP pendaftar, kontak pemesan, dan status reservasi via API v1:</p>
        <form id="v1AdminLoginForm" class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 items-end">
          <div>
            <label for="v1AdminEmail" class="block text-[11px] font-semibold text-muted mb-1">Email Admin</label>
            <input id="v1AdminEmail" type="email" required placeholder="admin@sumutgo.id" class="w-full bg-white border border-lokaleborder rounded-xl px-3 py-2 text-xs text-ink focus:outline-none focus:border-lokale" />
          </div>
          <div>
            <label for="v1AdminPassword" class="block text-[11px] font-semibold text-muted mb-1">Password</label>
            <input id="v1AdminPassword" type="password" required placeholder="Masukkan password" class="w-full bg-white border border-lokaleborder rounded-xl px-3 py-2 text-xs text-ink focus:outline-none focus:border-lokale" />
          </div>
          <div>
            <button id="btnV1AdminLoginSubmit" type="submit" class="w-full py-2.5 px-4 rounded-full bg-lokale hover:bg-lokaledark text-white font-bold text-xs transition shadow-sm">Masuk Panel Admin v1</button>
          </div>
        </form>
        <div id="v1AdminLoginError" class="hidden p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-[11px] font-semibold"></div>
      </div>

      <!-- Panel Aktif Admin -->
      <div id="v1AdminWorkspace" class="hidden space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex flex-wrap gap-1.5 bg-lokalesoft p-1 rounded-full">
            <button type="button" data-v1-admin-tab="users" class="px-3.5 py-1.5 rounded-full text-xs font-bold bg-lokale text-white transition">Verifikasi Pendaftar &amp; HP</button>
            <button type="button" data-v1-admin-tab="contacts" class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale hover:bg-white/80 transition">Kontak &amp; Pesan Booking</button>
            <button type="button" data-v1-admin-tab="bookings" class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale hover:bg-white/80 transition">Konfirmasi Booking</button>
            <button type="button" data-v1-admin-tab="transport" class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale hover:bg-white/80 transition">Master Transport API</button>
          </div>
          <span id="v1AdminSummary" class="text-[11px] font-semibold text-muted">Memuat data...</span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <select id="v1AdminStatusFilter" class="bg-lokalesoft/50 border border-lokaleborder rounded-xl px-3 py-2 text-xs text-ink font-semibold">
            <option value="ALL">Semua Status</option>
          </select>
          <input id="v1AdminSearch" type="search" placeholder="Cari nama, kode booking, email, atau nomor HP..." class="flex-1 min-w-[220px] bg-lokalesoft/40 border border-lokaleborder focus:border-lokale rounded-xl px-3.5 py-2 text-xs text-ink focus:outline-none" />
          <button id="btnV1AdminRefresh" type="button" class="px-3.5 py-2 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white font-semibold text-xs transition">Muat Ulang</button>
        </div>

        <div id="v1AdminFeedback" class="hidden p-3 rounded-2xl text-xs font-medium"></div>
        <div id="v1AdminList" class="space-y-3"></div>
      </div>
    </section>
  </main>

<?php
  $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
  if ($scriptDir === '/') {
      $scriptDir = '';
  }
  $apiV1PathPrefix = $scriptDir . '/index.php/api/v1';
  $adminBootstrapPayload = [
      'config' => [
          'apiV1BaseUrl' => $apiV1PathPrefix,
          'siteUrl'      => $scriptDir !== '' ? $scriptDir : '/',
      ],
      'guides'      => $guides,
      'apiBaseUrl'  => rtrim(site_url('api/tour-guides'), '/'),
      'apiV1Guides' => rtrim(site_url('api/v1/guides'), '/'),
  ];
?>
  <script id="sumutgo-admin-data" type="application/json"><?= json_encode($adminBootstrapPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
  <script>
    const ADMIN_BOOTSTRAP = JSON.parse(document.getElementById('sumutgo-admin-data')?.textContent || '{}');
    window.SUMUTGO_CONFIG = ADMIN_BOOTSTRAP.config || {};
  </script>
  <script src="<?= esc($scriptDir . '/assets/js/api.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/api.js') ?>"></script>
  <script>
    let TOUR_GUIDES = Array.isArray(ADMIN_BOOTSTRAP.guides) ? ADMIN_BOOTSTRAP.guides : [];
    const API_BASE_URL = ADMIN_BOOTSTRAP.apiBaseUrl || '/api/tour-guides';
    const API_V1_GUIDES = ADMIN_BOOTSTRAP.apiV1Guides || '/api/v1/guides';
    const escapeHtml = v => String(v ?? "").replace(/[&<>"']/g, ch => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[ch]));

    async function refreshAdminGuidesFromApi() {
      try {
        const resp = await window.SumutGoApi.guides.list({ page: 1, per_page: 500 });
        if (Array.isArray(resp?.data)) {
          TOUR_GUIDES = resp.data;
          renderAdminLedger();
        }
      } catch (_) {}
    }

    function updateKpis() {
      document.getElementById("kpiTotal").textContent = String(TOUR_GUIDES.length);
      document.getElementById("kpiAvail").textContent = String(TOUR_GUIDES.filter(g => g.status === "Tersedia").length);
      document.getElementById("kpiDuty").textContent = String(TOUR_GUIDES.filter(g => g.status === "Sedang Bertugas").length);
      document.getElementById("kpiBooked").textContent = String(TOUR_GUIDES.filter(g => g.status === "Booked").length);
    }

    function renderAdminLedger() {
      updateKpis();
      const busy = TOUR_GUIDES.filter(g => g.status !== "Tersedia");
      const fb = document.getElementById("adminActionFeedback");
      document.getElementById("bookingLedgerList").innerHTML = busy.length
        ? busy.map(g => {
            const untilTxt = g.active_booking_end_date ? ` • s.d. ${g.active_booking_end_date}` : "";
            return `<div class="py-2.5 flex justify-between items-center gap-2">
              <div class="min-w-0">
                <strong class="text-ink block truncate">${escapeHtml(g.id)} • ${escapeHtml(g.name)}</strong>
                <div class="text-[11px] text-muted truncate">${escapeHtml(g.status)}${escapeHtml(untilTxt)} • ${escapeHtml(g.destName)}</div>
              </div>
              <button type="button" data-rel="${escapeHtml(g.id)}" class="shrink-0 px-3 py-1 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white font-semibold transition">Set Tersedia</button>
            </div>`;
          }).join("")
        : `<p class="py-6 text-center text-muted">Seluruh ${TOUR_GUIDES.length} pemandu saat ini berstatus Tersedia.</p>`;

      document.querySelectorAll("[data-rel]").forEach(b => b.addEventListener("click", async () => {
        const id = b.dataset.rel;
        const token = window.SumutGoApi?.getToken();
        if (!token) {
          fb.className = "p-2.5 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 text-[11px] font-medium";
          fb.textContent = "Otorisasi Admin diperlukan: silakan masuk pada panel Login Admin v1 di bawah terlebih dahulu.";
          fb.classList.remove("hidden");
          document.getElementById("v1AdminLoginBox")?.scrollIntoView({ behavior: "smooth", block: "center" });
          return;
        }
        b.disabled = true;
        b.textContent = "Menyimpan...";
        try {
          const resp = await fetch(`${API_BASE_URL}/${encodeURIComponent(id)}/status`, {
            method: "PUT",
            headers: {
              "Content-Type": "application/json",
              "Authorization": `Bearer ${token}`
            },
            body: JSON.stringify({ availability_status: "Tersedia", status_ketersediaan: "Tersedia" })
          });
          const data = await resp.json();
          if (!resp.ok) throw new Error(data.message || "Gagal memperbarui status.");
          const g = TOUR_GUIDES.find(x => x.id === id);
          if (g) {
            g.status = "Tersedia";
            g.active_booking_end_date = null;
          }
          fb.className = "p-2.5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 text-[11px] font-medium";
          fb.textContent = data.message || `Status ${id} diperbarui menjadi Tersedia.`;
          fb.classList.remove("hidden");
          renderAdminLedger();
        } catch (err) {
          b.disabled = false;
          b.textContent = "Set Tersedia";
          fb.className = "p-2.5 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 text-[11px] font-medium";
          fb.textContent = err.message || "Gagal terhubung ke server.";
          fb.classList.remove("hidden");
        }
      }));
    }

    async function fetchApiTest(mode) {
      document.querySelectorAll(".api-test-btn").forEach(b => {
        b.className = b.dataset.apiTest === mode
          ? "api-test-btn px-3 py-1.5 rounded-full bg-lokale text-white font-mono text-[11px] transition"
          : "api-test-btn px-3 py-1.5 rounded-full bg-lokalesoft text-lokale hover:bg-lokale/10 font-mono text-[11px] transition";
      });
      const url = mode === "stats"
        ? `${API_BASE_URL}/stats`
        : mode === "detail"
          ? `${API_BASE_URL}/TG-001`
          : mode === "v1"
            ? `${API_V1_GUIDES}?per_page=5`
            : mode === "transport"
              ? `${(window.SUMUTGO_CONFIG && window.SUMUTGO_CONFIG.apiV1BaseUrl) || '/api/v1'}/transport/status`
              : API_BASE_URL;
      const out = document.getElementById("apiSandboxOutput");
      out.textContent = "Memuat response API...";
      try {
        const r = await fetch(url);
        const d = await r.json();
        out.textContent = JSON.stringify(d, null, 2);
      } catch (e) {
        out.textContent = JSON.stringify({ error: "Gagal mengambil data API" }, null, 2);
      }
    }

    // =========================================================================
    // PANEL KONFIRMASI ADMIN API v1 (USERS, BOOKING CONTACTS & BOOKINGS)
    // =========================================================================
    const v1State = {
      user: null,
      tab: "users",
      statusFilter: "ALL",
      q: ""
    };

    const formatPhone = raw => {
      const d = String(raw || "").replace(/[^0-9]/g, "");
      if (!d) return "-";
      return "+" + (d.startsWith("0") ? "62" + d.slice(1) : d);
    };

    const formatIdr = v => "Rp " + Number(v || 0).toLocaleString("id-ID");

    function setV1Feedback(msg, isError = false) {
      const el = document.getElementById("v1AdminFeedback");
      if (!el) return;
      if (!msg) {
        el.classList.add("hidden");
        el.textContent = "";
        return;
      }
      el.className = isError
        ? "p-3 rounded-2xl border border-rose-200 bg-rose-50 text-rose-700 text-xs font-medium"
        : "p-3 rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-700 text-xs font-medium";
      el.textContent = msg;
      el.classList.remove("hidden");
    }

    function populateV1StatusFilter(tab) {
      const sel = document.getElementById("v1AdminStatusFilter");
      if (!sel) return;
      const options = tab === "users"
        ? [["ALL", "Semua Pendaftar"], ["false", "Belum Terkonfirmasi"], ["true", "Sudah Terkonfirmasi"]]
        : tab === "contacts"
          ? [["ALL", "Semua Kontak"], ["unverified", "Belum Diverifikasi"], ["contacted", "Sedang Dihubungi"], ["confirmed", "Terkonfirmasi"]]
          : tab === "transport"
            ? [["moda", "Moda Transportasi (/api/moda)"], ["lokasi", "Titik Lokasi (/api/lokasi)"], ["penyedia", "Penyedia (/api/penyedia)"], ["jadwal", "Jadwal (/api/jadwal)"]]
            : [["ALL", "Semua Booking"], ["pending", "Pending"], ["confirmed", "Confirmed"], ["ongoing", "Ongoing"], ["completed", "Completed"], ["cancelled", "Cancelled"]];
      sel.innerHTML = options.map(([val, lbl]) => `<option value="${val}">${lbl}</option>`).join("");
      v1State.statusFilter = tab === "transport" ? "moda" : "ALL";
    }

    function updateV1AuthUi() {
      const loginBox = document.getElementById("v1AdminLoginBox");
      const workspace = document.getElementById("v1AdminWorkspace");
      const badgeBox = document.getElementById("v1AdminSessionBadge");
      const isAdmin = v1State.user && String(v1State.user.role || "").toLowerCase() === "admin";

      loginBox.classList.toggle("hidden", Boolean(isAdmin));
      workspace.classList.toggle("hidden", !isAdmin);

      if (isAdmin) {
        badgeBox.innerHTML = `
          <span class="px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-[11px] font-bold">
            ✓ Admin: ${escapeHtml(v1State.user.full_name)} (${escapeHtml(formatPhone(v1State.user.phone_number))})
          </span>
          <button id="btnV1AdminLogout" type="button" class="px-3 py-1 rounded-full bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white border border-rose-200 text-[11px] font-semibold transition">Keluar Admin</button>`;
        badgeBox.classList.remove("hidden");
        badgeBox.classList.add("flex");
        document.getElementById("btnV1AdminLogout")?.addEventListener("click", async () => {
          try { await window.SumutGoApi.auth.logout(); } catch (_) {}
          window.SumutGoApi.clearSessionStorage();
          v1State.user = null;
          updateV1AuthUi();
        });
      } else {
        badgeBox.innerHTML = "";
        badgeBox.classList.add("hidden");
        badgeBox.classList.remove("flex");
      }
    }

    async function initV1AdminSession() {
      const token = window.SumutGoApi?.getToken();
      if (!token) {
        v1State.user = null;
        updateV1AuthUi();
        return;
      }
      try {
        const me = await window.SumutGoApi.auth.me();
        if (me?.data && String(me.data.role || "").toLowerCase() === "admin") {
          v1State.user = me.data;
          window.SumutGoApi.setStoredUser(me.data);
          updateV1AuthUi();
          populateV1StatusFilter(v1State.tab);
          await loadV1AdminData();
        } else {
          v1State.user = null;
          updateV1AuthUi();
        }
      } catch (_) {
        v1State.user = null;
        updateV1AuthUi();
      }
    }

    function switchV1AdminTab(tab) {
      v1State.tab = tab;
      document.querySelectorAll("[data-v1-admin-tab]").forEach(btn => {
        btn.className = btn.dataset.v1AdminTab === tab
          ? "px-3.5 py-1.5 rounded-full text-xs font-bold bg-lokale text-white transition"
          : "px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale hover:bg-white/80 transition";
      });
      populateV1StatusFilter(tab);
      setV1Feedback("");
      loadV1AdminData();
    }

    async function loadV1AdminData() {
      const listEl = document.getElementById("v1AdminList");
      const summaryEl = document.getElementById("v1AdminSummary");
      if (!listEl || !v1State.user) return;

      listEl.innerHTML = `<div class="py-8 text-center text-muted">Memuat data ${escapeHtml(v1State.tab)} dari PostgreSQL Supabase...</div>`;

      try {
        const q = (document.getElementById("v1AdminSearch")?.value || "").trim();
        const f = v1State.statusFilter;

        if (v1State.tab === "users") {
          const resp = await window.SumutGoApi.users.list({
            page: 1,
            per_page: 50,
            is_verified: f !== "ALL" ? f : "",
            q
          });
          const rows = Array.isArray(resp?.data) ? resp.data : [];
          if (summaryEl) summaryEl.textContent = `${rows.length} pengguna ditampilkan`;
          if (!rows.length) {
            listEl.innerHTML = `<div class="py-8 text-center bg-lokalesoft/30 rounded-2xl text-muted">Tidak ada pendaftar sesuai filter.</div>`;
            return;
          }
          listEl.innerHTML = rows.map(u => {
            const digits = String(u.phone_number || "").replace(/[^0-9]/g, "");
            const badge = u.is_verified
              ? `<span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">✓ Terkonfirmasi</span>`
              : `<span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-bold">⏳ Belum Verifikasi</span>`;
            const nextVal = u.is_verified ? "false" : "true";
            const btnLabel = u.is_verified ? "Batalkan Verifikasi" : "Konfirmasi Pendaftar & HP";
            const btnCls = u.is_verified
              ? "bg-slate-100 hover:bg-slate-200 text-ink"
              : "bg-emerald-600 hover:bg-emerald-700 text-white";
            return `<div class="border border-lokaleborder rounded-2xl p-4 space-y-2 bg-white">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                  <strong class="text-sm text-ink">${escapeHtml(u.full_name)}</strong>
                  <span class="px-2 py-0.5 rounded-full bg-lokalesoft text-lokale text-[10px] font-bold uppercase">${escapeHtml(u.role)}</span>
                  ${badge}
                </div>
                <span class="font-mono text-[11px] text-muted">ID #${escapeHtml(u.user_id)} • ${escapeHtml(u.booking_count || 0)} booking</span>
              </div>
              <div class="flex flex-wrap items-center justify-between gap-2 text-[11px]">
                <div>Email: <strong class="text-ink">${escapeHtml(u.email)}</strong> • HP: <strong class="font-mono text-lokale">${escapeHtml(formatPhone(digits))}</strong></div>
                <div class="flex flex-wrap items-center gap-1.5">
                  ${digits ? `<a href="https://wa.me/${digits}" target="_blank" rel="noopener noreferrer" class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold">Chat WA</a>` : ""}
                </div>
              </div>
              ${u.verification_note ? `<div class="text-[11px] text-muted bg-lokalesoft/50 rounded-xl px-3 py-1.5">Catatan: ${escapeHtml(u.verification_note)}</div>` : ""}
              <div class="flex flex-wrap items-center gap-2 pt-1">
                <input type="text" data-v1-user-note="${u.user_id}" value="${escapeHtml(u.verification_note || "")}" placeholder="Catatan verifikasi admin (opsional)..." class="flex-1 min-w-[200px] bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-1.5 text-xs" />
                <button type="button" data-v1-verify-user="${u.user_id}" data-next-verified="${nextVal}" class="px-3.5 py-1.5 rounded-full ${btnCls} text-[11px] font-semibold transition">${btnLabel}</button>
              </div>
            </div>`;
          }).join("");

          listEl.querySelectorAll("[data-v1-verify-user]").forEach(btn => btn.addEventListener("click", async () => {
            const userId = Number(btn.dataset.v1VerifyUser);
            const isVerified = btn.dataset.nextVerified === "true";
            const note = listEl.querySelector(`[data-v1-user-note="${userId}"]`)?.value || "";
            btn.disabled = true;
            try {
              const r = await window.SumutGoApi.users.verify(userId, { is_verified: isVerified, verification_note: note });
              setV1Feedback(r.message || "Status pendaftar diperbarui.", false);
              await loadV1AdminData();
            } catch (err) {
              setV1Feedback(err.message || "Gagal memperbarui status pendaftar.", true);
              btn.disabled = false;
            }
          }));
        } else if (v1State.tab === "contacts") {
          const resp = await window.SumutGoApi.bookingContacts.list({
            page: 1,
            per_page: 50,
            confirmation_status: f !== "ALL" ? f : "",
            q
          });
          const rows = Array.isArray(resp?.data) ? resp.data : [];
          if (summaryEl) summaryEl.textContent = `${rows.length} kontak booking ditampilkan`;
          if (!rows.length) {
            listEl.innerHTML = `<div class="py-8 text-center bg-lokalesoft/30 rounded-2xl text-muted">Belum ada pesan/kontak sesuai filter.</div>`;
            return;
          }
          listEl.innerHTML = rows.map(c => {
            const digits = String(c.contact_phone || "").replace(/[^0-9]/g, "");
            const statusCls = c.confirmation_status === "confirmed"
              ? "bg-emerald-50 text-emerald-700 border-emerald-200"
              : c.confirmation_status === "contacted"
                ? "bg-amber-50 text-amber-800 border-amber-200"
                : "bg-slate-100 text-slate-600 border-slate-200";
            return `<div class="border border-lokaleborder rounded-2xl p-4 space-y-2 bg-white">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-mono font-bold text-lokale bg-lokalesoft px-2.5 py-0.5 rounded-full">${escapeHtml(c.booking_code)}</span>
                  <span class="px-2 py-0.5 rounded-full border text-[10px] font-bold uppercase ${statusCls}">${escapeHtml(c.confirmation_status)}</span>
                  <span class="text-[10px] font-semibold uppercase text-muted">${escapeHtml(c.sender_role)} via ${escapeHtml(c.preferred_channel)}</span>
                </div>
                <span class="font-mono text-[11px] text-muted">Kontak #${escapeHtml(c.contact_id)}</span>
              </div>
              <div class="text-[11px] text-muted">Pemesan: <strong class="text-ink">${escapeHtml(c.tourist_name)}</strong> (${escapeHtml(formatPhone(c.tourist_phone))}) ↔ Pemandu: <strong class="text-ink">${escapeHtml(c.guide_name)}</strong> (${escapeHtml(formatPhone(c.guide_phone))})</div>
              <div class="p-2.5 rounded-xl bg-lokalesoft/40 border border-lokaleborder text-xs text-ink">${escapeHtml(c.inquiry_message)}</div>
              <div class="flex flex-wrap items-center gap-2 pt-1">
                ${digits ? `<a href="https://wa.me/${digits}" target="_blank" rel="noopener noreferrer" class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold text-[11px]">WA Pengirim</a>` : ""}
                <input type="text" data-v1-contact-note="${c.contact_id}" value="${escapeHtml(c.admin_note || "")}" placeholder="Catatan tindak lanjut admin..." class="flex-1 min-w-[180px] bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-1.5 text-xs" />
                <button type="button" data-v1-confirm-contact="${c.contact_id}" data-next-status="contacted" class="px-3 py-1.5 rounded-full bg-amber-50 hover:bg-amber-600 text-amber-800 hover:text-white border border-amber-200 text-[11px] font-semibold transition">Tandai Dihubungi</button>
                <button type="button" data-v1-confirm-contact="${c.contact_id}" data-next-status="confirmed" class="px-3.5 py-1.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-semibold transition">Konfirmasi</button>
              </div>
            </div>`;
          }).join("");

          listEl.querySelectorAll("[data-v1-confirm-contact]").forEach(btn => btn.addEventListener("click", async () => {
            const contactId = Number(btn.dataset.v1ConfirmContact);
            const nextStatus = btn.dataset.nextStatus;
            const note = listEl.querySelector(`[data-v1-contact-note="${contactId}"]`)?.value || "";
            btn.disabled = true;
            try {
              const r = await window.SumutGoApi.bookingContacts.confirm(contactId, { confirmation_status: nextStatus, admin_note: note });
              setV1Feedback(r.message || "Status kontak diperbarui.", false);
              await loadV1AdminData();
            } catch (err) {
              setV1Feedback(err.message || "Gagal memperbarui kontak.", true);
              btn.disabled = false;
            }
          }));
        } else if (v1State.tab === "transport") {
          const resource = ["moda", "lokasi", "penyedia", "jadwal"].includes(f) ? f : "moda";
          const resp = await window.SumutGoApi.transport.admin.list(resource, q ? { q } : {});
          const rawData = resp?.data;
          let rows = Array.isArray(rawData)
            ? rawData
            : Array.isArray(rawData?.[resource])
              ? rawData[resource]
              : Array.isArray(rawData?.items)
                ? rawData.items
                : [];
          if (q) {
            const ql = q.toLowerCase();
            rows = rows.filter(item => JSON.stringify(item).toLowerCase().includes(ql));
          }
          if (summaryEl) summaryEl.textContent = `${rows.length} data ${resource} (Transport API)`;
          const createFormHtml = `
            <form id="v1TransportCreateForm" class="border border-lokaleborder rounded-2xl p-4 bg-lokalesoft/35 space-y-2">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <strong class="text-xs text-lokale">Tambah Data ${escapeHtml(resource)} via Secret Key (POST /api/v1/transport/admin/${escapeHtml(resource)})</strong>
                <span class="text-[10px] font-mono text-muted">Role: Admin</span>
              </div>
              <textarea id="v1TransportCreateJson" rows="2" placeholder='Contoh JSON: {"jenis":"Bus Pariwisata"}' class="w-full bg-white border border-lokaleborder rounded-xl px-3 py-2 font-mono text-[11px] text-ink"></textarea>
              <div class="flex justify-end">
                <button type="submit" class="px-4 py-1.5 rounded-full bg-lokale hover:bg-lokaledark text-white text-[11px] font-semibold transition">Kirim Data ${escapeHtml(resource)}</button>
              </div>
            </form>`;

          const itemsHtml = rows.length
            ? rows.map(item => {
                const itemId = item.id ?? item[`${resource}_id`] ?? null;
                const title = item.nama_lokasi || item.jenis || item.nama_penyedia || item.nama || item.kode || `#${itemId ?? '-'}`;
                const metaPairs = Object.entries(item)
                  .slice(0, 6)
                  .map(([k, v]) => `<span class="px-2 py-0.5 rounded-full bg-lokalesoft text-ink text-[10px] font-mono">${escapeHtml(k)}: ${escapeHtml(typeof v === "object" ? JSON.stringify(v) : v)}</span>`)
                  .join(" ");
                return `<div class="border border-lokaleborder rounded-2xl p-4 space-y-2 bg-white">
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <strong class="text-sm text-ink">${escapeHtml(title)}</strong>
                    ${itemId !== null ? `<button type="button" data-v1-transport-del="${escapeHtml(itemId)}" data-resource="${escapeHtml(resource)}" class="px-3 py-1 rounded-full bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white border border-rose-200 text-[11px] font-semibold transition">Hapus #${escapeHtml(itemId)}</button>` : ""}
                  </div>
                  <div class="flex flex-wrap gap-1.5">${metaPairs}</div>
                </div>`;
              }).join("")
            : `<div class="py-8 text-center bg-lokalesoft/30 rounded-2xl text-muted">Data master ${escapeHtml(resource)} kosong.</div>`;

          listEl.innerHTML = createFormHtml + itemsHtml;

          document.getElementById("v1TransportCreateForm")?.addEventListener("submit", async (e) => {
            e.preventDefault();
            const rawJson = (document.getElementById("v1TransportCreateJson")?.value || "").trim();
            let parsed;
            try {
              parsed = JSON.parse(rawJson);
              if (!parsed || typeof parsed !== "object") throw new Error("Objek JSON tidak valid.");
            } catch (_) {
              setV1Feedback("Format JSON payload tidak valid.", true);
              return;
            }
            try {
              const r = await window.SumutGoApi.transport.admin.create(resource, parsed);
              setV1Feedback(r.message || `Data ${resource} berhasil ditambahkan.`, false);
              await loadV1AdminData();
            } catch (err) {
              setV1Feedback(err.message || `Gagal menambah data ${resource}.`, true);
            }
          });

          listEl.querySelectorAll("[data-v1-transport-del]").forEach(btn => btn.addEventListener("click", async () => {
            const delId = Number(btn.dataset.v1TransportDel);
            const resName = btn.dataset.resource;
            if (!window.confirm(`Hapus data ${resName} #${delId} di Transport API?`)) return;
            btn.disabled = true;
            try {
              const r = await window.SumutGoApi.transport.admin.remove(resName, delId);
              setV1Feedback(r.message || `Data ${resName} #${delId} dihapus.`, false);
              await loadV1AdminData();
            } catch (err) {
              setV1Feedback(err.message || "Gagal menghapus data transportasi.", true);
              btn.disabled = false;
            }
          }));
        } else {
          const resp = await window.SumutGoApi.bookings.list({
            page: 1,
            per_page: 50,
            status: f !== "ALL" ? f : ""
          });
          let rows = Array.isArray(resp?.data) ? resp.data : [];
          if (q) {
            const ql = q.toLowerCase();
            rows = rows.filter(b => `${b.booking_code} ${b.tourist_name} ${b.guide_name} ${b.tourist_phone || ""} ${b.guide_phone || ""}`.toLowerCase().includes(ql));
          }
          if (summaryEl) summaryEl.textContent = `${rows.length} booking ditampilkan`;
          if (!rows.length) {
            listEl.innerHTML = `<div class="py-8 text-center bg-lokalesoft/30 rounded-2xl text-muted">Tidak ada booking sesuai filter.</div>`;
            return;
          }
          listEl.innerHTML = rows.map(b => {
            let btns = "";
            if (b.status === "pending") {
              btns += `<button type="button" data-v1-booking-status="${b.booking_id}" data-version="${b.version}" data-next="confirmed" class="px-3 py-1 rounded-full bg-lokale hover:bg-lokaledark text-white font-semibold">Konfirmasi Booking</button>`;
              btns += `<button type="button" data-v1-booking-status="${b.booking_id}" data-version="${b.version}" data-next="cancelled" class="px-3 py-1 rounded-full bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white border border-rose-200 font-semibold">Batalkan</button>`;
            } else if (b.status === "confirmed") {
              btns += `<button type="button" data-v1-booking-status="${b.booking_id}" data-version="${b.version}" data-next="ongoing" class="px-3 py-1 rounded-full bg-indigo-600 text-white font-semibold">Mulai Tur</button>`;
              btns += `<button type="button" data-v1-booking-status="${b.booking_id}" data-version="${b.version}" data-next="cancelled" class="px-3 py-1 rounded-full bg-rose-50 text-rose-600 border border-rose-200 font-semibold">Batalkan</button>`;
            } else if (b.status === "ongoing") {
              btns += `<button type="button" data-v1-booking-status="${b.booking_id}" data-version="${b.version}" data-next="completed" class="px-3 py-1 rounded-full bg-emerald-600 text-white font-semibold">Selesaikan Tur</button>`;
            }
            return `<div class="border border-lokaleborder rounded-2xl p-4 space-y-2 bg-white">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                  <span class="font-mono font-bold text-lokale bg-lokalesoft px-2.5 py-0.5 rounded-full">${escapeHtml(b.booking_code)}</span>
                  <span class="px-2 py-0.5 rounded-full bg-slate-100 text-ink text-[10px] font-bold uppercase">${escapeHtml(b.status)}</span>
                  <span class="text-[10px] font-mono text-muted">v${escapeHtml(b.version)}</span>
                </div>
                <strong class="font-mono text-lokale">${formatIdr(b.total_price_idr)}</strong>
              </div>
              <div class="text-[11px] text-muted">Pemesan: <strong class="text-ink">${escapeHtml(b.tourist_name)}</strong> (${escapeHtml(formatPhone(b.tourist_phone))}) • Pemandu: <strong class="text-ink">${escapeHtml(b.guide_name)}</strong> (${escapeHtml(formatPhone(b.guide_phone))})</div>
              <div class="text-[11px] text-ink">${escapeHtml(b.start_date)} s.d. ${escapeHtml(b.end_date)} (${escapeHtml(b.duration_days)} Hari • ${escapeHtml(b.number_of_guests)} Tamu)</div>
              ${btns ? `<div class="flex flex-wrap justify-end gap-2 pt-1">${btns}</div>` : ""}
            </div>`;
          }).join("");

          listEl.querySelectorAll("[data-v1-booking-status]").forEach(btn => btn.addEventListener("click", async () => {
            const bookingId = Number(btn.dataset.v1BookingStatus);
            const version = Number(btn.dataset.version);
            const nextStatus = btn.dataset.next;
            let cancelReason = undefined;
            if (nextStatus === "cancelled") {
              const promptRes = window.prompt("Alasan pembatalan (opsional):", "Dibatalkan oleh Admin");
              if (promptRes === null) return;
              cancelReason = promptRes.trim() || "Dibatalkan oleh Admin";
            }
            btn.disabled = true;
            try {
              const payload = { status: nextStatus, version };
              if (cancelReason !== undefined) payload.cancel_reason = cancelReason;
              const r = await window.SumutGoApi.bookings.updateStatus(bookingId, payload);
              setV1Feedback(r.message || `Booking diperbarui menjadi ${nextStatus}.`, false);
              await Promise.all([loadV1AdminData(), refreshAdminGuidesFromApi()]);
            } catch (err) {
              setV1Feedback(err.message || "Gagal memperbarui booking.", true);
              btn.disabled = false;
            }
          }));
        }
      } catch (err) {
        listEl.innerHTML = `<div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700">${escapeHtml(err.message || "Gagal memuat data.")}</div>`;
      }
    }

    window.addEventListener("DOMContentLoaded", () => {
      renderAdminLedger();
      fetchApiTest("list");
      document.querySelectorAll(".api-test-btn").forEach(b => b.addEventListener("click", () => fetchApiTest(b.dataset.apiTest)));

      document.getElementById("v1AdminLoginForm")?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const email = (document.getElementById("v1AdminEmail")?.value || "").trim().toLowerCase();
        const password = document.getElementById("v1AdminPassword")?.value || "";
        const errEl = document.getElementById("v1AdminLoginError");
        const submitBtn = document.getElementById("btnV1AdminLoginSubmit");
        if (errEl) errEl.classList.add("hidden");
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = "Memverifikasi...";
        }
        try {
          const resp = await window.SumutGoApi.auth.login({ email, password });
          const user = resp?.data?.user;
          const token = resp?.data?.token;
          if (!token || !user || String(user.role || "").toLowerCase() !== "admin") {
            throw new Error("Akun ini bukan role admin atau gagal autentikasi.");
          }
          window.SumutGoApi.setToken(token);
          window.SumutGoApi.setStoredUser(user);
          v1State.user = user;
          updateV1AuthUi();
          populateV1StatusFilter(v1State.tab);
          await loadV1AdminData();
        } catch (err) {
          if (errEl) {
            errEl.textContent = err.message || "Gagal login sebagai Admin.";
            errEl.classList.remove("hidden");
          }
        } finally {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = "Masuk Panel Admin v1";
          }
        }
      });

      document.querySelectorAll("[data-v1-admin-tab]").forEach(b => {
        b.addEventListener("click", () => switchV1AdminTab(b.dataset.v1AdminTab));
      });
      document.getElementById("v1AdminStatusFilter")?.addEventListener("change", (e) => {
        v1State.statusFilter = e.target.value || "ALL";
        loadV1AdminData();
      });
      document.getElementById("btnV1AdminRefresh")?.addEventListener("click", () => loadV1AdminData());
      let v1SearchTimer = null;
      document.getElementById("v1AdminSearch")?.addEventListener("input", () => {
        if (v1SearchTimer) window.clearTimeout(v1SearchTimer);
        v1SearchTimer = window.setTimeout(() => loadV1AdminData(), 220);
      });

      initV1AdminSession();
    });
  </script>
</body>
</html>
