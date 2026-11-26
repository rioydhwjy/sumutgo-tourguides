<?php
$totalGuides = count($guides ?? []);
$availableGuides = (int) ($stats['available_guides'] ?? count(array_filter($guides ?? [], static fn ($g) => ($g['status'] ?? '') === 'Tersedia')));
$bookedGuides = (int) ($stats['booked_guides'] ?? count(array_filter($guides ?? [], static fn ($g) => ($g['status'] ?? '') === 'Booked')));
$onDutyGuides = (int) ($stats['on_duty_guides'] ?? count(array_filter($guides ?? [], static fn ($g) => ($g['status'] ?? '') === 'Sedang Bertugas')));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= esc($title ?? 'SumutGO — Portal Admin, Live API & SQL') ?></title>
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-18 py-3 flex items-center justify-between gap-4">
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
  </main>

  <script>
    const TOUR_GUIDES = <?= json_encode($guides, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const API_BASE_URL = '<?= rtrim(site_url('api/tour-guides'), '/') ?>';
    const API_V1_GUIDES = '<?= rtrim(site_url('api/v1/guides'), '/') ?>';
    const escapeHtml = v => String(v ?? "").replace(/[&<>"']/g, ch => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[ch]));

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
        ? busy.map(g => `<div class="py-2.5 flex justify-between items-center gap-2">
            <div class="min-w-0">
              <strong class="text-ink block truncate">${escapeHtml(g.id)} • ${escapeHtml(g.name)}</strong>
              <div class="text-[11px] text-muted truncate">${escapeHtml(g.status)} • ${escapeHtml(g.destName)}</div>
            </div>
            <button type="button" data-rel="${escapeHtml(g.id)}" class="shrink-0 px-3 py-1 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white font-semibold transition">Set Tersedia</button>
          </div>`).join("")
        : `<p class="py-6 text-center text-muted">Seluruh ${TOUR_GUIDES.length} pemandu saat ini berstatus Tersedia.</p>`;

      document.querySelectorAll("[data-rel]").forEach(b => b.addEventListener("click", async () => {
        const id = b.dataset.rel;
        b.disabled = true;
        b.textContent = "Menyimpan...";
        try {
          const resp = await fetch(`${API_BASE_URL}/${encodeURIComponent(id)}/status`, {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ availability_status: "Tersedia", status_ketersediaan: "Tersedia" })
          });
          const data = await resp.json();
          if (!resp.ok) throw new Error(data.message || "Gagal memperbarui status.");
          const g = TOUR_GUIDES.find(x => x.id === id);
          if (g) g.status = "Tersedia";
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
      const url = mode === "stats" ? `${API_BASE_URL}/stats` : mode === "detail" ? `${API_BASE_URL}/TG-001` : mode === "v1" ? `${API_V1_GUIDES}?per_page=5` : API_BASE_URL;
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

    window.addEventListener("DOMContentLoaded", () => {
      renderAdminLedger();
      fetchApiTest("list");
      document.querySelectorAll(".api-test-btn").forEach(b => b.addEventListener("click", () => fetchApiTest(b.dataset.apiTest)));
    });
  </script>
</body>
</html>
