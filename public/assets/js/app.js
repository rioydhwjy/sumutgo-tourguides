/**
 * SumutGO Tour Guides - Main Frontend Application
 * Mempertahankan 100% desain & 4 tab eksisting, terintegrasi penuh dengan:
 * 1. RESTful API v1 (/api/v1/...)
 * 2. Auth Session + Instant Blur Gate pada detail pemandu wisata
 * 3. Alur Konfirmasi Booking Multi-Hari (Tanggal + Durasi <-> End Date -> Konfirmasi -> Bukti Resmi)
 * 4. Riwayat & Manajemen Booking Saya + Ulasan (Review)
 */
(function (global) {
  'use strict';

  const cfg = global.SUMUTGO_CONFIG || {};
  let TOUR_GUIDES = Array.isArray(cfg.initialGuides) ? cfg.initialGuides.slice() : [];
  let MASTER_ECOSYSTEM = cfg.masterEcosystem || {
    destinations: [],
    culinaries: [],
    hotels: [],
    transports: [],
  };
  const DEFAULT_MAX_PRICE = Number(cfg.defaultMaxPrice || 850000);

  function getTodayYmd() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  function addDaysYmd(ymd, deltaDays) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(ymd || ''));
    if (!m) return getTodayYmd();
    const dt = new Date(Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3])));
    dt.setUTCDate(dt.getUTCDate() + Number(deltaDays || 0));
    const yy = dt.getUTCFullYear();
    const mm = String(dt.getUTCMonth() + 1).padStart(2, '0');
    const dd = String(dt.getUTCDate()).padStart(2, '0');
    return `${yy}-${mm}-${dd}`;
  }

  function diffDaysYmd(startYmd, endYmd) {
    const ms = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(startYmd || ''));
    const me = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(endYmd || ''));
    if (!ms || !me) return NaN;
    const t1 = Date.UTC(Number(ms[1]), Number(ms[2]) - 1, Number(ms[3]));
    const t2 = Date.UTC(Number(me[1]), Number(me[2]) - 1, Number(me[3]));
    return Math.round((t2 - t1) / 86400000);
  }

  function formatDateId(ymd) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(ymd || ''));
    if (!m) return String(ymd || '-');
    const dt = new Date(Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3])));
    return dt.toLocaleDateString('id-ID', {
      weekday: 'short',
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      timeZone: 'UTC',
    });
  }

  const DEFAULT_BOOK_START = cfg.defaultBookDate || addDaysYmd(getTodayYmd(), 1);

  // =========================================================================
  // COMPARE LIST - PER-USER STORAGE (localStorage key terikat user_id aktif)
  // =========================================================================
  function getCompareStorageKey() {
    const user = global.SumutGoAuth && global.SumutGoAuth.getUser ? global.SumutGoAuth.getUser() : null;
    if (!user) return null;
    const uid = user.user_id ?? user.id ?? user.sub;
    if (uid === undefined || uid === null || uid === '') return null;
    return `sumutgo_compare_u${uid}`;
  }

  function saveCompareList() {
    const key = getCompareStorageKey();
    if (!key) return;
    try {
      localStorage.setItem(key, JSON.stringify(state.compareList));
    } catch (_) {}
  }

  function loadCompareList() {
    const key = getCompareStorageKey();
    if (!key) {
      state.compareList = [];
      return;
    }
    try {
      const raw = localStorage.getItem(key);
      const parsed = raw ? JSON.parse(raw) : [];
      const seen = new Set();
      state.compareList = Array.isArray(parsed)
        ? parsed.filter((id) => typeof id === 'string' && !seen.has(id) && seen.add(id)).slice(0, 3)
        : [];
    } catch (_) {
      state.compareList = [];
    }
  }

  const state = {
    activeTab: 'directory',
    viewMode: 'cards',
    search: '',
    spec: 'ALL',
    status: 'ALL',
    lang: 'ALL',
    district: 'ALL',
    sort: 'id_asc',
    maxPrice: DEFAULT_MAX_PRICE,
    onlyFavorites: false,
    favorites: new Set(['TG-001', 'TG-013']),
    compareList: [],
    activeGuideId: 'TG-001',
    activeGuideDetail: null,
    refLat: 3.5952,
    refLng: 98.6722,
    refLabel: 'Pusat Kota Medan',
    userLat: null,
    userLng: null,
    selectedMapGuideId: null,
    ecoTab: 'destinations',
    booking: {
      step: 'form', // 'form' | 'confirm' | 'success'
      startDate: DEFAULT_BOOK_START,
      days: 2,
      endDate: addDaysYmd(DEFAULT_BOOK_START, 1),
      guests: 2,
      destinationId: '',
      transportId: '',
      meetingPoint: '',
      notes: '',
      confirmedReceipt: null,
    },
    myBookingsFilter: 'ALL',
    myBookingsList: [],
    transport: {
      loaded: false,
      loading: false,
      error: null,
      modes: [],
      locations: [],
      applied: null,
      routeSyncedOnce: false,
    },
  };

  let leafletMap = null;
  let leafletMarkersLayer = null;
  let transportMarkersLayer = null;
  let guideMarkersById = {};
  let userLocationMarker = null;
  const chartInstances = {
    specs: null,
    langs: null,
    statuses: null,
  };

  const formatIdr = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
  const escapeHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }[ch]));

  const getLangNames = (g) => {
    if (Array.isArray(g?.language_names) && g.language_names.length) return g.language_names;
    if (Array.isArray(g?.languages)) {
      return g.languages.map((l) => (typeof l === 'string' ? l : (l?.language_name || ''))).filter(Boolean);
    }
    if (typeof g?.spoken_languages === 'string' && g.spoken_languages.trim()) {
      return g.spoken_languages.split(',').map((s) => s.trim()).filter(Boolean);
    }
    return [];
  };

  const formatDistrict = (d) => {
    const val = String(d || 'Kota Medan').trim();
    return val.startsWith('Medan ') ? `Kec. ${val}` : val;
  };

  const haversineKm = (a1, o1, a2, o2) => {
    const R = 6371;
    const dA = (a2 - a1) * Math.PI / 180;
    const dO = (o2 - o1) * Math.PI / 180;
    const a = Math.sin(dA / 2) ** 2 + Math.cos(a1 * Math.PI / 180) * Math.cos(a2 * Math.PI / 180) * Math.sin(dO / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  };

  const statusBadgeClass = (s) => (
    s === 'Tersedia'
      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
      : s === 'Sedang Bertugas'
        ? 'bg-amber-50 text-amber-700 border-amber-200'
        : 'bg-rose-50 text-rose-700 border-rose-200'
  );

  function getGuideDutyMeta(g) {
    const status = String(g?.status || '');
    const isBusyStatus = status === 'Sedang Bertugas' || status === 'Booked';
    const rawEnd = g?.active_booking_end_date ? String(g.active_booking_end_date).trim() : '';
    const hasDate = /^\d{4}-\d{2}-\d{2}/.test(rawEnd);
    const dateText = hasDate ? formatDateId(rawEnd.slice(0, 10)) : '';
    if (!hasDate && !isBusyStatus) return null;
    const prefix = status === 'Sedang Bertugas' ? 'Bertugas s.d.' : isBusyStatus ? 'Booked s.d.' : 'Jadwal s.d.';
    const missingDateText = status === 'Booked'
      ? 'Status Booked (tgl selesai belum tercatat)'
      : 'Sedang bertugas (tgl selesai belum tercatat)';
    return {
      isBusyStatus,
      hasDate,
      dateText,
      shortText: hasDate ? `s.d. ${dateText}` : '',
      fullText: hasDate ? `${prefix} ${dateText}` : missingDateText,
    };
  }

  function updateModalGuideStatus(g) {
    const idEl = document.getElementById('modalGuideId');
    if (idEl) idEl.textContent = `${g.id} • ${g.status}`;
    const dutyMeta = getGuideDutyMeta(g);
    const dutyEl = document.getElementById('modalGuideDutyUntil');
    if (!dutyEl) return;
    if (dutyMeta) {
      dutyEl.className = dutyMeta.hasDate
        ? 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-amber-700 text-[10px] font-semibold'
        : 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-muted text-[10px] font-medium';
      dutyEl.textContent = dutyMeta.fullText;
      dutyEl.classList.remove('hidden');
    } else {
      dutyEl.textContent = '';
      dutyEl.classList.add('hidden');
    }
  }

  const bookingStatusBadgeClass = (s) => {
    switch (String(s || '').toLowerCase()) {
      case 'pending': return 'bg-amber-50 text-amber-700 border-amber-200';
      case 'confirmed': return 'bg-sky-50 text-sky-700 border-sky-200';
      case 'ongoing': return 'bg-indigo-50 text-indigo-700 border-indigo-200';
      case 'completed': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
      case 'cancelled': return 'bg-rose-50 text-rose-700 border-rose-200';
      default: return 'bg-slate-100 text-slate-700 border-slate-200';
    }
  };

  function isLoggedIn() {
    return Boolean(global.SumutGoAuth && global.SumutGoAuth.isAuthenticated());
  }

  function findGuide(idOrCode) {
    return TOUR_GUIDES.find((x) => x.id === idOrCode || String(x.guide_id) === String(idOrCode)) || null;
  }

  function updateGuideInCache(freshGuide) {
    if (!freshGuide || !freshGuide.id) return;
    const idx = TOUR_GUIDES.findIndex((x) => x.id === freshGuide.id || x.guide_id === freshGuide.guide_id);
    if (idx >= 0) {
      TOUR_GUIDES[idx] = Object.assign({}, TOUR_GUIDES[idx], freshGuide);
    }
  }

  function updateTopKpis() {
    const heroPill = document.getElementById('heroAvailPill');
    const statTotal = document.getElementById('statTotalGuides');
    const compBadge = document.getElementById('compareCountBadge');
    const favBadge = document.getElementById('favCountBadge');
    const favBtn = document.getElementById('btnFilterFavorites');

    if (heroPill) heroPill.textContent = `${TOUR_GUIDES.filter((g) => g.status === 'Tersedia').length} Tersedia`;
    if (statTotal) statTotal.textContent = String(TOUR_GUIDES.length);
    if (compBadge) compBadge.textContent = String(state.compareList.length);
    if (favBadge) favBadge.textContent = String(state.favorites.size);

    if (favBtn) {
      favBtn.className = state.onlyFavorites
        ? 'px-3 py-2 rounded-full bg-rose-500 text-white border border-rose-500 text-xs font-semibold flex items-center gap-1 transition shadow-sm'
        : 'px-3 py-2 rounded-full bg-white hover:bg-rose-50 border border-slate-200 text-xs font-semibold text-ink flex items-center gap-1 transition';
      const icon = favBtn.querySelector('span');
      if (icon) icon.className = state.onlyFavorites ? 'text-white' : 'text-rose-500';
    }
  }

  function updateViewModeButtons() {
    const btnCards = document.getElementById('btnViewCards');
    const btnTable = document.getElementById('btnViewTable');
    if (!btnCards || !btnTable) return;
    const activeCls = 'px-4 py-1.5 rounded-full text-xs font-semibold bg-lokale text-white transition';
    const inactiveCls = 'px-4 py-1.5 rounded-full text-xs font-semibold text-lokale hover:text-lokaledark transition';
    btnCards.className = state.viewMode === 'cards' ? activeCls : inactiveCls;
    btnTable.className = state.viewMode === 'table' ? activeCls : inactiveCls;
  }

  function getFilteredGuides() {
    const q = state.search.trim().toLowerCase();
    return TOUR_GUIDES.filter((g) => {
      const langs = getLangNames(g);
      if (state.onlyFavorites && !state.favorites.has(g.id)) return false;
      if (state.spec !== 'ALL' && g.specialization !== state.spec) return false;
      if (state.status !== 'ALL' && g.status !== state.status) return false;
      if (state.lang !== 'ALL' && !langs.includes(state.lang)) return false;
      if (state.district !== 'ALL' && g.district !== state.district) return false;
      if (g.price > state.maxPrice) return false;
      if (q && ![
        g.id, g.name, g.agency, g.district, g.address, g.specialization,
        g.destId, g.destName, g.culinaryId, g.culinaryName,
        g.hotelId, g.hotelName, g.transportId, g.transportName, langs.join(' '),
      ].join(' ').toLowerCase().includes(q)) return false;
      return true;
    }).sort((a, b) => (
      state.sort === 'rating_desc'
        ? b.rating - a.rating
        : state.sort === 'reviews_desc'
          ? b.reviews - a.reviews
          : state.sort === 'price_asc'
            ? a.price - b.price
            : state.sort === 'price_desc'
              ? b.price - a.price
              : a.id.localeCompare(b.id)
    ));
  }

  function renderDirectory() {
    updateTopKpis();
    updateViewModeButtons();
    const list = getFilteredGuides();
    const authed = isLoggedIn();

    const resultCountEl = document.getElementById('resultCount');
    const avgRatingEl = document.getElementById('avgRatingReadout');
    if (resultCountEl) resultCountEl.textContent = String(list.length);
    if (avgRatingEl) {
      avgRatingEl.textContent = (list.length ? (list.reduce((a, b) => a + b.rating, 0) / list.length).toFixed(2) : '0.00') + '★';
    }

    const grid = document.getElementById('guidesGrid');
    const wrap = document.getElementById('guidesTableWrapper');
    const tbody = document.getElementById('guidesTableBody');
    const empty = document.getElementById('emptyState');
    if (!grid || !wrap || !tbody || !empty) return;

    const guestNoticeStrip = document.getElementById('directoryGuestGateNotice');
    if (guestNoticeStrip) {
      guestNoticeStrip.classList.toggle('hidden', authed);
    }

    if (!list.length) {
      grid.classList.add('hidden');
      wrap.classList.add('hidden');
      empty.classList.remove('hidden');
      return;
    }

    empty.classList.add('hidden');

    if (state.viewMode === 'cards') {
      grid.classList.remove('hidden');
      wrap.classList.add('hidden');
      grid.innerHTML = list.map((g) => {
        const langs = getLangNames(g).join(', ');
        const distLabel = formatDistrict(g.district);
        const phoneMasked = g.phone ? `+${g.phone}` : '-';
        const dutyMeta = getGuideDutyMeta(g);

        return `<article class="lokale-card rounded-3xl p-5 flex flex-col justify-between">
          <div>
            <div class="flex items-start justify-between gap-2 mb-3">
              <div class="flex items-center gap-1.5 min-w-0">
                <span class="font-mono text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-lokalesoft text-lokale shrink-0">${escapeHtml(g.id)}</span>
                <span class="text-[11px] text-muted truncate">${escapeHtml(g.specialization)}</span>
              </div>
              <div class="flex items-start gap-1.5 shrink-0">
                <button type="button" data-fav-btn="${escapeHtml(g.id)}" aria-label="Favorit ${escapeHtml(g.name)}" class="w-7 h-7 rounded-full border text-xs transition ${state.favorites.has(g.id) ? 'bg-rose-50 border-rose-200 text-rose-500' : 'bg-white border-slate-200 text-slate-400 hover:text-rose-500'}">♥</button>
                <div class="flex flex-col items-end">
                  <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border ${statusBadgeClass(g.status)}">${escapeHtml(g.status)}</span>
                  ${dutyMeta && dutyMeta.hasDate
                    ? `<span class="text-[10px] text-amber-600 font-semibold mt-0.5">${escapeHtml(dutyMeta.shortText)}</span>`
                    : ''}
                </div>
              </div>
            </div>
            <h3 class="font-display font-bold text-base text-ink">${escapeHtml(g.name)}</h3>
            <p class="text-xs text-muted mt-0.5">${escapeHtml(g.agency)} • ${escapeHtml(distLabel)}</p>
            <div class="flex flex-wrap items-center gap-2 mt-2.5 text-xs">
              <span class="text-amber-500 font-bold">★ ${Number(g.rating).toFixed(1)}</span>
              <span class="text-muted">(${Number(g.reviews).toLocaleString('id-ID')})</span>
              <span class="text-[11px] font-medium text-lokale">${escapeHtml(langs || 'Indonesia')}</span>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 text-[11px]">
              <div class="truncate"><span class="font-semibold text-lokale">Wisata:</span> ${escapeHtml(g.destName)}</div>
            </div>

            <!-- BLUR GATE DETAIL PEMANDU PADA KARTU -->
            <div class="relative mt-2 rounded-2xl border ${authed ? 'border-slate-100 bg-lokalesoft/25 p-2.5' : 'border-lokaleborder bg-lokalesoft/40 p-2.5 overflow-hidden'}">
              <div class="space-y-1 text-[11px] transition-all duration-200 ${authed ? '' : 'blur-locked'}" ${authed ? '' : 'aria-hidden="true" inert'}>
                <div class="truncate text-ink"><span class="font-semibold text-ink/80">Kuliner:</span> ${escapeHtml(g.culinaryName || '-')}</div>
                <div class="truncate text-muted"><span class="font-semibold text-ink/80">Mitra:</span> ${escapeHtml(g.hotelName || '-')} • ${escapeHtml(g.transportName || '-')}</div>
                <div class="truncate text-muted"><span class="font-semibold text-ink/80">Kontak:</span> ${escapeHtml(phoneMasked)} • ${escapeHtml(g.address || '-')}</div>
              </div>
              ${authed ? '' : `
                <div class="blur-gate-overlay absolute inset-0 flex flex-col items-center justify-center text-center px-2.5 py-1.5 bg-white/60 backdrop-blur-[2px]">
                  <span class="inline-flex items-center gap-1 text-[10px] font-bold text-lokaledeep">
                    <svg class="w-3 h-3 text-lokale" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    Login untuk lihat detail &amp; kontak
                  </span>
                  <div class="mt-1 flex items-center gap-1.5">
                    <button type="button" data-gate-auth="login" class="px-2.5 py-0.5 rounded-full bg-lokale hover:bg-lokaledark text-white text-[10px] font-bold transition shadow-sm">Masuk</button>
                    <button type="button" data-gate-auth="register" class="px-2.5 py-0.5 rounded-full bg-white hover:bg-lokalesoft text-lokale border border-lokaleborder text-[10px] font-bold transition">Daftar</button>
                  </div>
                </div>
              `}
            </div>
          </div>

          <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
            <div><span class="text-[10px] text-muted block uppercase">Tarif/Hari</span><strong class="text-sm font-mono text-lokale">${formatIdr(g.price)}</strong></div>
            <div class="flex items-center gap-1.5">
              <button type="button" data-comp-btn="${escapeHtml(g.id)}" class="px-2.5 py-1.5 rounded-full text-[11px] font-semibold border transition ${state.compareList.includes(g.id) ? 'bg-lokalesoft text-lokale border-lokale' : 'bg-white text-muted border-slate-200 hover:border-lokale'}">${state.compareList.includes(g.id) ? '✓' : '+'} Banding</button>
              <button type="button" data-open-guide="${escapeHtml(g.id)}" class="px-3 py-1.5 rounded-full bg-lokalesoft hover:bg-lokale hover:text-white text-lokale border border-lokaleborder text-[11px] font-semibold transition">Detail</button>
              <button type="button" data-book-guide="${escapeHtml(g.id)}" class="px-3.5 py-1.5 rounded-full bg-lokale hover:bg-lokaledark text-white text-[11px] font-semibold transition shadow-sm">Booking</button>
            </div>
          </div>
        </article>`;
      }).join('');
    } else {
      grid.classList.add('hidden');
      wrap.classList.remove('hidden');
      tbody.innerHTML = list.map((g) => {
        const langs = getLangNames(g).join(', ');
        const dutyMeta = getGuideDutyMeta(g);
        return `<tr class="hover:bg-lokalesoft/40">
          <td class="py-3 px-3 font-mono font-bold text-lokale whitespace-nowrap">${escapeHtml(g.id)}</td>
          <td class="py-3 px-3"><div class="font-bold">${escapeHtml(g.name)}</div><div class="text-[11px] text-muted">${escapeHtml(g.agency)}</div></td>
          <td class="py-3 px-3">${escapeHtml(g.specialization)}</td>
          <td class="py-3 px-3 text-amber-500 font-bold whitespace-nowrap">★ ${Number(g.rating).toFixed(1)}</td>
          <td class="py-3 px-3 text-lokale">${escapeHtml(langs || 'Indonesia')}</td>
          <td class="py-3 px-3">${escapeHtml(g.destName)}</td>
          <td class="py-3 px-3 text-muted">
            ${authed
              ? `<span>${escapeHtml(g.hotelName)} • ${escapeHtml(g.transportName)}</span>`
              : `<div class="inline-flex items-center gap-1.5">
                   <span class="blur-locked text-[11px]" aria-hidden="true">${escapeHtml(g.hotelName)} • ${escapeHtml(g.transportName)}</span>
                   <button type="button" data-gate-auth="login" class="px-2 py-0.5 rounded-full bg-lokalesoft border border-lokaleborder text-lokale text-[10px] font-bold hover:bg-lokale hover:text-white transition">🔒 Masuk</button>
                 </div>`
            }
          </td>
          <td class="py-3 px-3 font-mono font-bold text-lokale whitespace-nowrap">${formatIdr(g.price)}</td>
          <td class="py-3 px-3 whitespace-nowrap"><span class="text-[10px] font-bold px-2 py-0.5 rounded-full border ${statusBadgeClass(g.status)}">${escapeHtml(g.status)}</span>${(dutyMeta && dutyMeta.hasDate) ? `<span class="block text-[10px] text-amber-600 font-semibold mt-0.5">${escapeHtml(dutyMeta.shortText)}</span>` : ''}</td>
          <td class="py-3 px-3 text-right whitespace-nowrap">
            <div class="inline-flex gap-1">
              <button type="button" data-open-guide="${escapeHtml(g.id)}" class="px-3 py-1 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white border border-lokaleborder text-[11px] font-semibold transition">Detail</button>
              <button type="button" data-book-guide="${escapeHtml(g.id)}" class="px-3 py-1 rounded-full bg-lokale hover:bg-lokaledark text-white text-[11px] font-semibold transition">Booking</button>
            </div>
          </td>
        </tr>`;
      }).join('');
    }

    document.querySelectorAll('[data-open-guide]').forEach((b) => {
      b.addEventListener('click', () => openGuideModal(b.dataset.openGuide, 'detail'));
    });
    document.querySelectorAll('[data-book-guide]').forEach((b) => {
      b.addEventListener('click', () => handleStartBooking(b.dataset.bookGuide));
    });
    document.querySelectorAll('[data-gate-auth]').forEach((b) => {
      b.addEventListener('click', (e) => {
        e.stopPropagation();
        const mode = b.dataset.gateAuth === 'register' ? 'register' : 'login';
        global.SumutGoAuth.openAuthModal(
          mode,
          'Masuk atau daftar akun wisatawan untuk membuka detail lengkap pemandu wisata.'
        );
      });
    });
    document.querySelectorAll('[data-fav-btn]').forEach((b) => {
      b.addEventListener('click', () => {
        state.favorites.has(b.dataset.favBtn)
          ? state.favorites.delete(b.dataset.favBtn)
          : state.favorites.add(b.dataset.favBtn);
        renderDirectory();
      });
    });
    document.querySelectorAll('[data-comp-btn]').forEach((b) => {
      b.addEventListener('click', () => {
        const id = b.dataset.compBtn;
        const idx = state.compareList.indexOf(id);
        if (idx >= 0) {
          // Sudah ada di daftar pembanding → hapus
          state.compareList.splice(idx, 1);
          saveCompareList();
          renderDirectory();
        } else {
          // Belum login → arahkan ke login
          if (!isLoggedIn()) {
            global.SumutGoAuth.openAuthModal(
              'login',
              'Masuk untuk menggunakan fitur pembanding pemandu wisata.'
            );
            return;
          }
          // Sudah ada 3 → blokir, tampilkan notifikasi & buka modal pembanding
          if (state.compareList.length >= 3) {
            global.SumutGoAuth.showToast(
              'Daftar pembanding penuh (maks. 3 pemandu). Hapus salah satu terlebih dahulu.',
              'error'
            );
            openCompareModal();
            return;
          }
          // Tambah ke daftar
          state.compareList.push(id);
          saveCompareList();
          renderDirectory();
        }
      });
    });
  }

  function resetAllFilters() {
    state.search = '';
    state.spec = 'ALL';
    state.status = 'ALL';
    state.lang = 'ALL';
    state.district = 'ALL';
    state.sort = 'id_asc';
    state.maxPrice = DEFAULT_MAX_PRICE;
    state.onlyFavorites = false;

    const mapSet = {
      filterSearch: '',
      filterSpec: 'ALL',
      filterStatus: 'ALL',
      filterLang: 'ALL',
      filterDistrict: 'ALL',
      filterSort: 'id_asc',
      filterMaxPrice: String(DEFAULT_MAX_PRICE),
    };
    Object.entries(mapSet).forEach(([id, val]) => {
      const el = document.getElementById(id);
      if (el) el.value = val;
    });
    const readout = document.getElementById('priceReadout');
    if (readout) readout.textContent = formatIdr(DEFAULT_MAX_PRICE);
    renderDirectory();
  }

  // =========================================================================
  // PETA GIS BASECAMP (LEAFLET)
  // =========================================================================
  function setMapLocationStatus(msg, tone = 'info') {
    const el = document.getElementById('mapLocationStatus');
    if (!el) return;
    if (!msg) {
      el.textContent = '';
      el.className = 'hidden font-medium';
      return;
    }
    const toneCls = tone === 'error' ? 'text-rose-600' : tone === 'success' ? 'text-emerald-700' : 'text-lokale';
    el.textContent = msg;
    el.className = `font-medium ${toneCls}`;
  }

  function buildGuidePopupHtml(g) {
    const authed = isLoggedIn();
    const langs = getLangNames(g).join(', ') || 'Indonesia';
    const distText = Number.isFinite(g.d) ? `${g.d.toFixed(2)} km dari titik jemput` : formatDistrict(g.district);
    const dutyMeta = getGuideDutyMeta(g);
    return `<div class="space-y-2 text-xs text-ink">
      <div class="flex items-start justify-between gap-2">
        <span class="font-mono text-[10px] font-bold px-2 py-0.5 rounded-full bg-lokalesoft text-lokale">${escapeHtml(g.id)}</span>
        <div class="flex flex-col items-end">
          <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border ${statusBadgeClass(g.status)}">${escapeHtml(g.status)}</span>
          ${dutyMeta && dutyMeta.hasDate ? `<span class="text-[10px] text-amber-600 font-semibold mt-0.5">${escapeHtml(dutyMeta.shortText)}</span>` : ''}
        </div>
      </div>
      <div>
        <div class="font-display font-bold text-sm text-ink leading-snug">${escapeHtml(g.name)}</div>
        <div class="text-[11px] text-muted">${escapeHtml(g.agency)}</div>
      </div>
      <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px]">
        <span class="text-amber-500 font-bold">★ ${Number(g.rating).toFixed(1)}</span>
        <span class="text-muted">(${Number(g.reviews).toLocaleString('id-ID')})</span>
        <span class="text-lokale font-semibold">• ${escapeHtml(distText)}</span>
      </div>
      <div class="pt-1.5 border-t border-slate-100 space-y-1 text-[11px] text-muted">
        <div><span class="font-semibold text-ink">Spesialisasi:</span> ${escapeHtml(g.specialization)}</div>
        <div><span class="font-semibold text-ink">Bahasa:</span> ${escapeHtml(langs)}</div>
        <div><span class="font-semibold text-ink">Destinasi:</span> ${escapeHtml(g.destName || '-')}</div>
        <div class="leading-snug">
          <span class="font-semibold text-ink">Basecamp:</span>
          ${authed
            ? escapeHtml(g.address)
            : `<span class="blur-locked">${escapeHtml(g.address)}</span> <span class="text-[10px] font-semibold text-lokale">(Masuk untuk lihat)</span>`}
        </div>
      </div>
      <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
        <div>
          <span class="text-[10px] text-muted block">Tarif/Hari</span>
          <strong class="font-mono text-xs text-lokale">${formatIdr(g.price)}</strong>
        </div>
        <button type="button" data-map-detail="${escapeHtml(g.id)}" class="px-3.5 py-1.5 rounded-full bg-lokale hover:bg-lokaledark text-white text-[11px] font-semibold shadow-sm transition cursor-pointer">Detail</button>
      </div>
    </div>`;
  }

  const LOCATE_BTN_IDLE_HTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" d="M12 2v3m0 14v3M2 12h3m14 0h3"/><circle cx="12" cy="12" r="7" stroke-opacity="0.35"/></svg><span>Lokasi Saya</span>`;
  const LOCATE_BTN_LOADING_HTML = `<svg class="w-3.5 h-3.5 animate-spin text-lokale" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path></svg><span>Mencari...</span>`;

  function setLocateButtonLoading(isLoading) {
    const btn = document.getElementById('btnMapMyLocation');
    if (!btn) return;
    btn.disabled = isLoading;
    btn.innerHTML = isLoading ? LOCATE_BTN_LOADING_HTML : LOCATE_BTN_IDLE_HTML;
  }

  function locateUserOnMap() {
    if (!navigator.geolocation) {
      setMapLocationStatus('Browser Anda tidak mendukung fitur geolokasi.', 'error');
      return;
    }
    setLocateButtonLoading(true);
    setMapLocationStatus('Mencari lokasi Anda...', 'info');

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const lat = Number(pos.coords.latitude.toFixed(6));
        const lng = Number(pos.coords.longitude.toFixed(6));
        state.userLat = lat;
        state.userLng = lng;
        state.refLat = lat;
        state.refLng = lng;
        state.refLabel = 'Lokasi Saya';
        state.selectedMapGuideId = null;

        setLocateButtonLoading(false);
        setMapLocationStatus('Peta difokuskan ke lokasi Anda saat ini.', 'success');
        renderMap();

        if (leafletMap) {
          let opened = false;
          const openUserPopup = () => {
            if (opened) return;
            opened = true;
            if (userLocationMarker) userLocationMarker.openPopup();
          };
          leafletMap.once('moveend', openUserPopup);
          setTimeout(openUserPopup, 700);
          leafletMap.flyTo([lat, lng], 14, { duration: 0.65 });
        }
      },
      (err) => {
        setLocateButtonLoading(false);
        const msg = err && err.code === 1
          ? 'Izin akses lokasi ditolak. Aktifkan izin lokasi di browser Anda.'
          : err && err.code === 3
            ? 'Waktu pencarian lokasi habis. Silakan coba lagi.'
            : 'Lokasi tidak dapat dideteksi saat ini.';
        setMapLocationStatus(msg, 'error');
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
    );
  }

  function focusGuideOnMap(guideId, opts = {}) {
    const g = findGuide(guideId);
    if (!g) return;
    state.selectedMapGuideId = g.id;
    renderMap({ preserveListScroll: true });

    if (!opts.skipFly && leafletMap) {
      const targetZoom = Math.max(leafletMap.getZoom(), 15);
      const targetLatLng = L.latLng(g.lat, g.lng);
      const needsMove = leafletMap.getCenter().distanceTo(targetLatLng) > 10 || leafletMap.getZoom() < targetZoom;
      let opened = false;
      const openSelectedPopup = () => {
        if (opened) return;
        opened = true;
        if (state.selectedMapGuideId === g.id && guideMarkersById[g.id]) {
          guideMarkersById[g.id].openPopup();
        }
      };
      if (needsMove) {
        leafletMap.once('moveend', openSelectedPopup);
        setTimeout(openSelectedPopup, 650);
        leafletMap.flyTo(targetLatLng, targetZoom, { duration: 0.55 });
      } else {
        openSelectedPopup();
      }
    }
  }

  function initOrUpdateMap() {
    if (!global.L) return;
    if (!leafletMap) {
      leafletMap = L.map('medanLeafletMap').setView([state.refLat, state.refLng], 12);
      L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', { maxZoom: 18 }).addTo(leafletMap);
      leafletMarkersLayer = L.layerGroup().addTo(leafletMap);
      transportMarkersLayer = L.layerGroup().addTo(leafletMap);

      const LocateControl = L.Control.extend({
        options: { position: 'topright' },
        onAdd: () => {
          const container = L.DomUtil.create('div', 'leaflet-control leaflet-control-locate');
          const btn = L.DomUtil.create('button', 'leaflet-control-locate-btn', container);
          btn.type = 'button';
          btn.id = 'btnMapMyLocation';
          btn.title = 'Fokus ke Lokasi Saya';
          btn.setAttribute('aria-label', 'Fokus ke Lokasi Saya');
          btn.innerHTML = LOCATE_BTN_IDLE_HTML;
          L.DomEvent.disableClickPropagation(container);
          L.DomEvent.disableScrollPropagation(container);
          btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            locateUserOnMap();
          });
          return container;
        },
      });
      new LocateControl().addTo(leafletMap);

      document.getElementById('medanLeafletMap')?.addEventListener('click', (e) => {
        const detailBtn = e.target.closest('[data-map-detail]');
        if (detailBtn) {
          e.preventDefault();
          e.stopPropagation();
          openGuideModal(detailBtn.dataset.mapDetail, 'detail');
          return;
        }
        const hubRefBtn = e.target.closest('[data-transport-node-ref]');
        if (hubRefBtn) {
          e.preventDefault();
          e.stopPropagation();
          const lat = parseFloat(hubRefBtn.dataset.lat);
          const lng = parseFloat(hubRefBtn.dataset.lng);
          const nama = hubRefBtn.dataset.nama || 'Simpul Transportasi';
          if (Number.isFinite(lat) && Number.isFinite(lng)) {
            state.refLat = lat;
            state.refLng = lng;
            state.refLabel = nama;
            state.selectedMapGuideId = null;
            setMapLocationStatus(`Titik jemput dipindahkan ke ${nama}.`, 'success');
            if (leafletMap) leafletMap.flyTo([lat, lng], 14, { duration: 0.45 });
            renderMap();
          }
          return;
        }
        const hubTarifBtn = e.target.closest('[data-transport-node-tarif]');
        if (hubTarifBtn) {
          e.preventDefault();
          e.stopPropagation();
          const nodeId = hubTarifBtn.dataset.transportNodeTarif || '';
          const nodeNama = hubTarifBtn.dataset.nama || '';
          switchTab('planner');
          ensureTransportCatalogLoaded().then(() => {
            const asalSel = document.getElementById('tarifAsalSelect');
            const asalTxt = document.getElementById('tarifAsalText');
            if (asalSel && nodeId && Array.from(asalSel.options).some((o) => o.value === String(nodeId))) {
              asalSel.value = String(nodeId);
            } else if (asalTxt && nodeNama) {
              asalTxt.value = nodeNama;
            }
            document.getElementById('transportTarifForm')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
          });
        }
      });

      leafletMap.on('click', (e) => {
        state.refLat = Number(e.latlng.lat.toFixed(4));
        state.refLng = Number(e.latlng.lng.toFixed(4));
        state.refLabel = `Titik Peta (${state.refLat}, ${state.refLng})`;
        setMapLocationStatus('');
        renderMap();
      });
    }
    setTimeout(() => {
      leafletMap.invalidateSize();
      renderMap();
    }, 150);
  }

  function renderMap(opts = {}) {
    if (!leafletMap) return;
    const listEl = document.getElementById('nearestGuidesList');
    const prevScroll = opts.preserveListScroll && listEl ? listEl.scrollTop : 0;

    leafletMarkersLayer.clearLayers();
    guideMarkersById = {};
    userLocationMarker = null;
    const activeRefEl = document.getElementById('activeRefLabel');
    if (activeRefEl) activeRefEl.textContent = state.refLabel;

    const locBtn = document.getElementById('btnMapMyLocation');
    if (locBtn && !locBtn.disabled) {
      locBtn.classList.toggle('is-active', state.refLabel === 'Lokasi Saya' && state.userLat !== null);
    }

    const onlyAvail = Boolean(document.getElementById('chkMapOnlyAvailable')?.checked);

    if (state.userLat !== null && state.userLng !== null) {
      L.circleMarker([state.userLat, state.userLng], {
        radius: 16,
        fillColor: '#2563eb',
        color: '#2563eb',
        weight: 1,
        opacity: 0.35,
        fillOpacity: 0.16,
        interactive: false,
      }).addTo(leafletMarkersLayer);

      userLocationMarker = L.circleMarker([state.userLat, state.userLng], {
        radius: 8,
        fillColor: '#2563eb',
        color: '#ffffff',
        weight: 3,
        fillOpacity: 1,
      }).bindPopup(`<strong>Lokasi Saya Saat Ini</strong><br/><span class="text-[11px] text-muted">${state.userLat}, ${state.userLng}</span>`).addTo(leafletMarkersLayer);
    }

    if (!(state.refLabel === 'Lokasi Saya' && state.userLat !== null && state.userLng !== null)) {
      L.circleMarker([state.refLat, state.refLng], {
        radius: 9,
        fillColor: '#f59e0b',
        color: '#14242b',
        weight: 2.5,
        fillOpacity: 1,
      }).bindPopup(`<strong>Titik Jemput Aktif</strong><br/>${escapeHtml(state.refLabel)}`).addTo(leafletMarkersLayer);
    }

    const ranked = TOUR_GUIDES
      .filter((g) => !onlyAvail || g.status === 'Tersedia')
      .map((g) => ({ ...g, d: haversineKm(state.refLat, state.refLng, g.lat, g.lng) }))
      .sort((a, b) => a.d - b.d);

    ranked.forEach((g) => {
      const isSelected = state.selectedMapGuideId === g.id;
      if (isSelected) {
        L.circleMarker([g.lat, g.lng], {
          radius: 16,
          fillColor: '#07779d',
          color: '#07779d',
          weight: 1.5,
          opacity: 0.5,
          fillOpacity: 0.18,
          interactive: false,
        }).addTo(leafletMarkersLayer);
      }

      const marker = L.circleMarker([g.lat, g.lng], {
        radius: isSelected ? 10 : 7,
        fillColor: g.status === 'Tersedia' ? '#07779d' : '#e11d48',
        color: isSelected ? '#14242b' : '#ffffff',
        weight: isSelected ? 3 : 2,
        fillOpacity: isSelected ? 1 : 0.9,
      }).bindPopup(buildGuidePopupHtml(g), { maxWidth: 290, autoPan: true }).addTo(leafletMarkersLayer);

      marker.on('click', () => {
        if (state.selectedMapGuideId !== g.id) {
          state.selectedMapGuideId = g.id;
          renderMap({ preserveListScroll: true });
          const focusedMarker = guideMarkersById[g.id];
          if (focusedMarker) focusedMarker.openPopup();
          const cardEl = listEl?.querySelector(`[data-map-guide="${CSS.escape(g.id)}"]`);
          if (cardEl) cardEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
      });

      if (isSelected) marker.bringToFront();
      guideMarkersById[g.id] = marker;
    });

    if (listEl) {
      listEl.innerHTML = ranked.length
        ? ranked.slice(0, 12).map((g, i) => {
          const isSelected = state.selectedMapGuideId === g.id;
          return `<div role="button" tabindex="0" data-map-guide="${escapeHtml(g.id)}" class="py-2.5 px-2.5 my-1 rounded-xl flex items-center justify-between gap-2.5 text-xs cursor-pointer transition border ${isSelected ? 'bg-white border-lokale shadow-sm ring-1 ring-lokale/30' : 'border-transparent hover:bg-white/80 hover:border-lokaleborder'}">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5">
                <strong class="text-ink truncate">#${i + 1} ${escapeHtml(g.name)}</strong>
              </div>
              <div class="text-[11px] text-muted truncate mt-0.5">${escapeHtml(formatDistrict(g.district))} • ${escapeHtml(g.status)}</div>
              <div class="text-[11px] text-lokale font-medium truncate mt-0.5">★ ${Number(g.rating).toFixed(1)} • ${formatIdr(g.price)}/hr</div>
            </div>
            <div class="flex flex-col items-end gap-1.5 shrink-0">
              <span class="font-mono font-bold text-lokale text-[11px]">${g.d.toFixed(2)} km</span>
              <button type="button" data-map-select="${escapeHtml(g.id)}" class="px-3.5 py-1.5 min-h-[30px] rounded-full bg-lokale hover:bg-lokaledark active:scale-95 text-white text-[11px] font-semibold shadow-sm transition cursor-pointer">Pilih</button>
            </div>
          </div>`;
        }).join('')
        : '<p class="text-xs text-muted py-6 text-center">Tidak ada pemandu yang sesuai filter.</p>';

      if (opts.preserveListScroll) {
        listEl.scrollTop = prevScroll;
      }

      listEl.querySelectorAll('[data-map-guide]').forEach((item) => {
        item.addEventListener('click', () => focusGuideOnMap(item.dataset.mapGuide));
        item.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            focusGuideOnMap(item.dataset.mapGuide);
          }
        });
      });

      listEl.querySelectorAll('[data-map-select]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const id = btn.dataset.mapSelect;
          focusGuideOnMap(id, { skipFly: true });
          openGuideModal(id, 'detail');
        });
      });
    }
  }

  // =========================================================================
  // KALKULATOR TRIP & INTEGRASI TRANSPORT APPLICATION API
  // =========================================================================
  function extractTransportArray(rawData, preferredKeys = []) {
    if (Array.isArray(rawData)) return rawData;
    if (!rawData || typeof rawData !== 'object') return [];
    const candidates = preferredKeys.concat(['items', 'results', 'opsi', 'tarif', 'rute', 'routes', 'lokasi', 'locations', 'transports', 'moda', 'data']);
    for (let i = 0; i < candidates.length; i += 1) {
      const val = rawData[candidates[i]];
      if (Array.isArray(val)) return val;
    }
    return [];
  }

  function formatTransportTypeLabel(t) {
    const raw = String(t || '').trim();
    return raw ? raw.charAt(0).toUpperCase() + raw.slice(1) : 'Simpul';
  }

  function inferTransportNodeType(hub) {
    const rawType = String(hub?.tipe_lokasi || '').trim().toLowerCase();
    const name = String(hub?.nama_lokasi || '').toLowerCase();
    if (/\bbandara\b|\bairport\b/.test(name)) return 'bandara';
    if (/\bstasiun\b|\bstation\b/.test(name)) return 'stasiun';
    if (/\bpelabuhan\b|\bdermaga\b|\bport\b/.test(name)) return 'pelabuhan';
    if (/\bhalte\b/.test(name)) return 'halte';
    if (/\bterminal\b/.test(name)) return 'terminal';
    return rawType || 'terminal';
  }

  function matchesTransportNodeType(hub, filter) {
    const f = String(filter || 'all').toLowerCase();
    if (f === 'all' || !f) return true;
    const rawType = String(hub?.tipe_lokasi || '').trim().toLowerCase();
    const inferred = inferTransportNodeType(hub);
    if (f === 'terminal') {
      return inferred === 'terminal' || (rawType === 'terminal' && !['bandara', 'stasiun', 'pelabuhan', 'halte'].includes(inferred));
    }
    return inferred === f || rawType === f;
  }

  function setTransportPanelFeedback(elId, msg, tone = 'info', retryCallback = null) {
    const el = document.getElementById(elId);
    if (!el) return;
    if (!msg) {
      el.innerHTML = '';
      el.classList.add('hidden');
      return;
    }
    const cls = tone === 'error'
      ? 'p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex flex-wrap items-center justify-between gap-2'
      : tone === 'warning'
        ? 'p-3 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex flex-wrap items-center justify-between gap-2'
        : 'p-3 rounded-2xl bg-lokalesoft border border-lokaleborder text-lokaledeep text-xs flex flex-wrap items-center justify-between gap-2';
    el.className = cls;
    el.innerHTML = `<span>${escapeHtml(msg)}</span>${retryCallback ? '<button type="button" data-transport-retry="1" class="px-3 py-1 rounded-full bg-white hover:bg-lokale text-lokale hover:text-white border border-lokaleborder text-[10px] font-bold transition">Coba Lagi</button>' : ''}`;
    el.classList.remove('hidden');
    if (retryCallback) {
      el.querySelector('[data-transport-retry]')?.addEventListener('click', retryCallback);
    }
  }

  function renderTransportLocationDropdowns() {
    const typeFilter = (document.getElementById('tarifNodeTypeFilter')?.value || 'ALL').toLowerCase();
    const asalSel = document.getElementById('tarifAsalSelect');
    const tujuanSel = document.getElementById('tarifTujuanSelect');
    if (!asalSel || !tujuanSel) return;

    const prevAsal = asalSel.value;
    const prevTujuan = tujuanSel.value;
    const locs = (state.transport.locations || []).filter((l) => matchesTransportNodeType(l, typeFilter));

    const optsHtml = '<option value="">Pilih dari daftar simpul...</option>' + locs.map((l) => {
      const lbl = `${l.nama_lokasi || 'Simpul'} (${formatTransportTypeLabel(inferTransportNodeType(l))})`;
      return `<option value="${escapeHtml(l.id)}">${escapeHtml(lbl)}</option>`;
    }).join('');

    asalSel.innerHTML = optsHtml;
    tujuanSel.innerHTML = optsHtml;
    if (prevAsal && Array.from(asalSel.options).some((o) => o.value === prevAsal)) asalSel.value = prevAsal;
    if (prevTujuan && Array.from(tujuanSel.options).some((o) => o.value === prevTujuan)) tujuanSel.value = prevTujuan;
  }

  function renderTransportModesHeader() {
    const badgesEl = document.getElementById('transportModesBadges');
    const healthEl = document.getElementById('transportApiHealthBadge');

    if (state.transport.loading) {
      if (healthEl) {
        healthEl.className = 'px-3 py-1 rounded-full bg-lokalesoft text-lokale border border-lokaleborder text-[11px] font-semibold';
        healthEl.textContent = 'Memuat API...';
      }
      return;
    }

    if (state.transport.error) {
      if (healthEl) {
        healthEl.className = 'px-3 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-semibold';
        healthEl.textContent = 'Mode Fallback (API Offline/Belum Aktif)';
      }
      if (badgesEl) {
        badgesEl.innerHTML = `<span class="text-amber-700 font-medium">${escapeHtml(state.transport.error)}</span>`;
      }
      return;
    }

    if (healthEl) {
      healthEl.className = 'px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-semibold';
      healthEl.textContent = `Terhubung (${state.transport.locations.length} Simpul)`;
    }

    if (badgesEl) {
      const modes = state.transport.modes || [];
      badgesEl.innerHTML = modes.length
        ? modes.map((m) => `<span class="px-2.5 py-0.5 rounded-full bg-lokalesoft border border-lokaleborder text-lokale font-semibold text-[11px]">${escapeHtml(m.jenis || m.nama || 'Moda')}</span>`).join('')
        : '<span class="text-muted">Belum ada daftar moda transportasi terdaftar.</span>';
    }
  }

  async function renderTransportNodesOnMap() {
    if (!leafletMap || !transportMarkersLayer) return;
    transportMarkersLayer.clearLayers();

    const chk = document.getElementById('chkShowTransportNodes');
    const statusEl = document.getElementById('transportNodesStatus');
    const retryBtn = document.getElementById('btnRetryTransportNodes');
    if (retryBtn) retryBtn.classList.add('hidden');

    if (!chk || !chk.checked) {
      if (statusEl) {
        statusEl.className = 'text-[11px] text-muted';
        statusEl.textContent = 'Centang untuk memuat simpul Bandara, Stasiun & Terminal';
      }
      return;
    }

    if (!state.transport.loaded && !state.transport.loading) {
      if (statusEl) statusEl.textContent = 'Memuat titik simpul Transport API...';
      await ensureTransportCatalogLoaded();
    }

    if (state.transport.error) {
      if (statusEl) {
        statusEl.className = 'text-[11px] text-rose-600 font-medium';
        statusEl.textContent = state.transport.error;
      }
      if (retryBtn) retryBtn.classList.remove('hidden');
      return;
    }

    const typeFilter = (document.getElementById('filterTransportNodeType')?.value || 'ALL').toLowerCase();
    const hubs = (state.transport.locations || []).filter((l) => {
      const lat = Number(l.latitude);
      const lng = Number(l.longitude);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return false;
      return matchesTransportNodeType(l, typeFilter);
    });

    hubs.forEach((hub) => {
      const lat = Number(hub.latitude);
      const lng = Number(hub.longitude);
      const tipe = formatTransportTypeLabel(inferTransportNodeType(hub));
      const nama = hub.nama_lokasi || 'Simpul Transportasi';
      const popupHtml = `<div class="space-y-2 text-xs text-ink">
        <div class="flex items-center justify-between gap-2">
          <span class="px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-bold uppercase">${escapeHtml(tipe)}</span>
          <span class="font-mono text-[10px] text-muted">#${escapeHtml(hub.id)}</span>
        </div>
        <div class="font-display font-bold text-sm text-ink leading-snug">${escapeHtml(nama)}</div>
        <div class="font-mono text-[10px] text-muted">${lat.toFixed(4)}, ${lng.toFixed(4)}</div>
        <div class="pt-1.5 border-t border-slate-100 flex flex-wrap gap-1.5">
          <button type="button" data-transport-node-ref="1" data-lat="${lat}" data-lng="${lng}" data-nama="${escapeHtml(nama)}" class="px-2.5 py-1 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white border border-lokaleborder text-[10px] font-semibold transition cursor-pointer">Titik Jemput</button>
          <button type="button" data-transport-node-tarif="${escapeHtml(hub.id)}" data-nama="${escapeHtml(nama)}" class="px-2.5 py-1 rounded-full bg-lokale hover:bg-lokaledark text-white text-[10px] font-semibold transition cursor-pointer">Cek Tarif</button>
        </div>
      </div>`;

      L.circleMarker([lat, lng], {
        radius: 7,
        fillColor: '#059669',
        color: '#ffffff',
        weight: 2.5,
        fillOpacity: 0.95,
      }).bindPopup(popupHtml, { maxWidth: 260, autoPan: true }).addTo(transportMarkersLayer);
    });

    if (statusEl) {
      statusEl.className = 'text-[11px] text-emerald-700 font-semibold';
      statusEl.textContent = hubs.length
        ? `Menampilkan ${hubs.length} simpul transportasi`
        : 'Tidak ada simpul transportasi untuk filter tipe ini.';
    }
  }

  async function ensureTransportCatalogLoaded(force = false) {
    if (!force && (state.transport.loaded || state.transport.loading)) return;
    if (!global.SumutGoApi?.transport) return;

    state.transport.loading = true;
    renderTransportModesHeader();

    try {
      const [modesRes, locsRes] = await Promise.allSettled([
        global.SumutGoApi.transport.transports(),
        global.SumutGoApi.transport.lokasi(),
      ]);

      let modes = [];
      let locs = [];
      let errMsg = null;

      if (modesRes.status === 'fulfilled') {
        modes = extractTransportArray(modesRes.value?.data, ['transports', 'moda']);
      } else {
        errMsg = modesRes.reason?.message || 'Gagal memuat daftar moda Transport API.';
      }

      if (locsRes.status === 'fulfilled') {
        locs = extractTransportArray(locsRes.value?.data, ['lokasi', 'locations']);
      } else if (!errMsg) {
        errMsg = locsRes.reason?.message || 'Gagal memuat daftar lokasi simpul Transport API.';
      }

      if (modesRes.status === 'rejected' && locsRes.status === 'rejected') {
        state.transport.error = errMsg;
        state.transport.loaded = false;
      } else {
        state.transport.error = errMsg;
        state.transport.modes = modes;
        state.transport.locations = locs;
        state.transport.loaded = true;
      }
    } catch (err) {
      state.transport.error = err?.message || 'Layanan Transport API sedang tidak tersedia.';
      state.transport.loaded = false;
    } finally {
      state.transport.loading = false;
      renderTransportModesHeader();
      renderTransportLocationDropdowns();
      if (document.getElementById('chkShowTransportNodes')?.checked) {
        renderTransportNodesOnMap();
      }
    }
  }

  function applyExternalTransportEstimate(estimate) {
    state.transport.applied = estimate;
    const modeSel = document.getElementById('planTransportMode');
    if (modeSel) modeSel.value = 'public_api';
    updatePlanner();
    global.SumutGoAuth?.showToast(`Estimasi "${estimate.label}" diterapkan ke Kalkulator Trip!`, 'success');
  }

  function syncRouteFormWithGuide(g) {
    const guide = g || findGuide(document.getElementById('planGuideSelect')?.value) || TOUR_GUIDES[0];
    if (!guide) return;
    const nameEl = document.getElementById('routeWisataName');
    const latEl = document.getElementById('routeWisataLat');
    const lngEl = document.getElementById('routeWisataLng');
    if (nameEl) nameEl.value = guide.destName || guide.name || '';
    if (latEl && guide.lat) latEl.value = Number(guide.lat).toFixed(6);
    if (lngEl && guide.lng) lngEl.value = Number(guide.lng).toFixed(6);
  }

  async function handleTarifFormSubmit(e) {
    if (e) e.preventDefault();
    const idAsal = document.getElementById('tarifAsalSelect')?.value || '';
    const idTujuan = document.getElementById('tarifTujuanSelect')?.value || '';
    const asalTxt = (document.getElementById('tarifAsalText')?.value || '').trim();
    const tujuanTxt = (document.getElementById('tarifTujuanText')?.value || '').trim();
    const jam = (document.getElementById('tarifJamInput')?.value || '08:00').trim();
    const btn = document.getElementById('btnSubmitTarif');
    const resEl = document.getElementById('transportTarifResults');

    setTransportPanelFeedback('transportTarifFeedback', '');
    if ((!idAsal && !asalTxt) || (!idTujuan && !tujuanTxt)) {
      setTransportPanelFeedback('transportTarifFeedback', 'Pilih simpul asal & tujuan (atau ketik nama lokasinya) terlebih dahulu.', 'warning');
      return;
    }

    const params = { jam };
    if (idAsal) params.id_asal = idAsal;
    if (asalTxt) params.asal = asalTxt;
    if (idTujuan) params.id_tujuan = idTujuan;
    if (tujuanTxt) params.tujuan = tujuanTxt;

    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Menghitung...';
    }
    if (resEl) resEl.innerHTML = '<div class="py-5 text-center text-muted">Menghitung jarak Haversine &amp; tarif dari Transport API...</div>';

    try {
      const resp = await global.SumutGoApi.transport.tarif(params);
      const data = resp?.data || {};
      const ruteInfo = (data.rute && !Array.isArray(data.rute) && typeof data.rute === 'object') ? data.rute : null;
      const items = extractTransportArray(data, ['pilihan_transportasi', 'opsi', 'tarif', 'routes', 'hasil', 'results']);
      const fallbackDistance = ruteInfo?.jarak_km ?? data.jarak_km ?? data.distance_km ?? data.jarak ?? null;
      const jamDepart = ruteInfo?.jam_berangkat || jam;

      if (!items.length && (data.tarif !== undefined || data.estimasi_tarif !== undefined || data.estimasi_biaya !== undefined || data.harga !== undefined)) {
        items.push(data);
      }

      const asalLabel = ruteInfo?.asal?.nama_lokasi || asalTxt || document.getElementById('tarifAsalSelect')?.selectedOptions?.[0]?.textContent || 'Asal';
      const tujuanLabel = ruteInfo?.tujuan?.nama_lokasi || tujuanTxt || document.getElementById('tarifTujuanSelect')?.selectedOptions?.[0]?.textContent || 'Tujuan';
      const distanceHeaderHtml = fallbackDistance !== null && fallbackDistance !== undefined
        ? `<div class="px-3 py-2 rounded-2xl bg-lokalesoft/50 border border-lokaleborder flex flex-wrap items-center justify-between gap-2 text-[11px]">
            <span class="font-semibold text-ink">${escapeHtml(asalLabel)} &rarr; ${escapeHtml(tujuanLabel)}</span>
            <span class="font-mono font-bold text-lokale">Jarak Haversine: ${Number(fallbackDistance).toFixed(2)} km • Jam ${escapeHtml(jamDepart)}</span>
          </div>`
        : '';

      if (!items.length) {
        if (resEl) {
          resEl.innerHTML = `${distanceHeaderHtml}<div class="py-4 px-3 text-center bg-lokalesoft/30 rounded-2xl border border-lokaleborder text-muted">Belum ada jadwal armada langsung terjadwal pada jam ${escapeHtml(jamDepart)} untuk rute ini. Coba rute terminal/bandara utama atau gunakan fitur Rekomendasi Rute Wisata di kanan.</div>`;
        }
        return;
      }

      resEl.innerHTML = distanceHeaderHtml + items.map((item, idx) => {
        const moda = item.jenis_transport || item.jenis || item.moda || item.nama_moda || item.transport || 'Transportasi Publik';
        const penyedia = item.penyedia || item.nama_penyedia || item.operator || '';
        const jarak = item.jarak_km ?? item.distance_km ?? item.jarak ?? fallbackDistance;
        const cost = Number(item.estimasi_tarif ?? item.tarif ?? item.estimasi_biaya ?? item.harga ?? item.biaya ?? item.price ?? 0);
        const jamInfo = item.jam_berlaku || item.jam || item.jadwal || jamDepart;
        const tarifPerKm = item.tarif_per_km !== undefined && item.tarif_per_km !== null ? ` • ${formatIdr(item.tarif_per_km)}/km` : '';
        const jarakText = jarak !== null && jarak !== undefined ? `${Number(jarak).toFixed(2)} km` : '';
        const label = `${moda}${penyedia ? ` (${penyedia})` : ''}`;
        const note = `[Transport API - Tarif] ${asalLabel} -> ${tujuanLabel} naik ${label} (${jamInfo})${jarakText ? ` (${jarakText})` : ''}, est. ${formatIdr(cost)}.`;

        return `<div class="p-3 rounded-2xl border ${idx === 0 ? 'border-lokale bg-lokalesoft/50' : 'border-lokaleborder bg-white'} space-y-1.5">
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="flex flex-wrap items-center gap-1.5">
                <strong class="text-ink">${escapeHtml(moda)}</strong>
                ${idx === 0 ? '<span class="px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-[9px] font-bold">Termurah</span>' : ''}
              </div>
              <div class="text-[11px] text-muted">${penyedia ? `${escapeHtml(penyedia)} • ` : ''}${jarakText ? `${escapeHtml(jarakText)} • ` : ''}Jam ${escapeHtml(jamInfo)}${escapeHtml(tarifPerKm)}</div>
            </div>
            <strong class="font-mono text-sm text-lokale shrink-0">${escapeHtml(item.estimasi_tarif_format || formatIdr(cost))}</strong>
          </div>
          <div class="flex justify-end">
            <button type="button" data-apply-transport="1" data-label="${escapeHtml(label)}" data-cost="${cost}" data-note="${escapeHtml(note)}" data-pickup="${escapeHtml(asalLabel)}" class="px-3 py-1 rounded-full bg-lokale hover:bg-lokaledark text-white text-[10px] font-bold transition">Terapkan ke Kalkulator</button>
          </div>
        </div>`;
      }).join('');

      resEl.querySelectorAll('[data-apply-transport]').forEach((b) => {
        b.addEventListener('click', () => {
          applyExternalTransportEstimate({
            source: 'tarif',
            label: b.dataset.label,
            cost: Number(b.dataset.cost || 0),
            note: b.dataset.note,
            pickup: b.dataset.pickup,
          });
        });
      });
    } catch (err) {
      setTransportPanelFeedback('transportTarifFeedback', err.message || 'Gagal menghubungi endpoint tarif Transport API.', 'error', () => handleTarifFormSubmit());
      if (resEl) {
        resEl.innerHTML = '<div class="py-4 text-center text-muted bg-lokalesoft/20 rounded-2xl">Data tarif tidak dapat dimuat saat ini.</div>';
      }
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.textContent = 'Hitung Tarif & Jarak';
      }
    }
  }

  function renderRouteSegmentsList(segments) {
    if (!Array.isArray(segments) || !segments.length) return '';
    return `<div class="space-y-1.5">` + segments.map((seg, i) => {
      const armadaList = Array.isArray(seg.opsi_armada) ? seg.opsi_armada : [];
      const bestArmada = armadaList[0] || null;
      const moda = seg.jenis_segmen || bestArmada?.jenis_transport || seg.moda || seg.jenis || seg.nama_moda || `Segmen ${i + 1}`;
      const penyedia = bestArmada?.penyedia || seg.penyedia || seg.nama_penyedia || '';
      const jam = bestArmada?.jam_berlaku || seg.jam_berlaku || '';
      const asal = seg.titik_berangkat?.nama_lokasi || seg.asal || seg.nama_asal || seg.dari || '';
      const tujuan = seg.titik_tiba?.nama_lokasi || seg.tujuan || seg.nama_tujuan || seg.ke || '';
      const cost = Number(seg.tarif_termurah ?? bestArmada?.estimasi_tarif ?? seg.tarif ?? seg.biaya ?? seg.estimasi_biaya ?? seg.harga ?? 0);
      const costText = seg.tarif_termurah_format || bestArmada?.estimasi_tarif_format || formatIdr(cost);
      const jarak = seg.jarak_km ?? seg.distance_km ?? null;
      const extraArmadaCount = armadaList.length > 1 ? ` (+${armadaList.length - 1} opsi lain)` : '';
      return `<div class="px-2.5 py-2 rounded-xl bg-white border border-lokaleborder/70 flex flex-wrap items-start justify-between gap-2 text-[11px]">
        <div class="min-w-0 flex-1 space-y-0.5">
          <div class="font-semibold text-ink">${seg.segmen || (i + 1)}. ${escapeHtml(moda)}${penyedia ? ` • ${escapeHtml(penyedia)}` : ''}${escapeHtml(extraArmadaCount)}</div>
          ${(asal || tujuan) ? `<div class="text-[10px] text-muted truncate">${escapeHtml(asal)} &rarr; ${escapeHtml(tujuan)}${jam ? ` • ${escapeHtml(jam)}` : ''}</div>` : ''}
          ${seg.deskripsi ? `<div class="text-[10px] text-slate-500 leading-snug">${escapeHtml(seg.deskripsi)}</div>` : ''}
        </div>
        <div class="text-right shrink-0">
          <strong class="font-mono text-lokale">${escapeHtml(costText)}</strong>
          ${jarak !== null && jarak !== undefined && Number(jarak) > 0 ? `<span class="block text-[10px] text-muted">${Number(jarak).toFixed(1)} km</span>` : ''}
        </div>
      </div>`;
    }).join('') + `</div>`;
  }

  async function handleRekomendasiFormSubmit(e) {
    if (e) e.preventDefault();
    const userLat = parseFloat(document.getElementById('routeUserLat')?.value);
    const userLng = parseFloat(document.getElementById('routeUserLng')?.value);
    const wisataLat = parseFloat(document.getElementById('routeWisataLat')?.value);
    const wisataLng = parseFloat(document.getElementById('routeWisataLng')?.value);
    const namaWisata = (document.getElementById('routeWisataName')?.value || 'Destinasi Wisata').trim();
    const tripType = document.getElementById('routeTripType')?.value === 'oneway' ? 'oneway' : 'roundtrip';
    const btn = document.getElementById('btnSubmitRekomendasi');
    const resEl = document.getElementById('transportRekomendasiResults');

    setTransportPanelFeedback('transportRekomendasiFeedback', '');
    if (!Number.isFinite(userLat) || !Number.isFinite(userLng) || !Number.isFinite(wisataLat) || !Number.isFinite(wisataLng)) {
      setTransportPanelFeedback('transportRekomendasiFeedback', 'Koordinat asal & tujuan wisata harus diisi angka valid.', 'warning');
      return;
    }

    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Mencari Rute...';
    }
    if (resEl) resEl.innerHTML = '<div class="py-5 text-center text-muted">Menyusun rekomendasi rute multi-moda dari Transport API...</div>';

    try {
      const resp = await global.SumutGoApi.transport.rekomendasiWisata({
        user_lat: userLat,
        user_lng: userLng,
        wisata_lat: wisataLat,
        wisata_lng: wisataLng,
        nama_wisata: namaWisata,
        trip_type: tripType,
      }, 'POST');
      const data = resp?.data || {};
      const hasStructuredRec = Boolean(data && typeof data === 'object' && (data.ringkasan_biaya || data.rute_perjalanan || data.metadata));
      const options = hasStructuredRec ? [] : extractTransportArray(data, ['rekomendasi', 'opsi', 'routes', 'rute', 'hasil']);
      const mainObj = hasStructuredRec ? data : (options.length ? options[0] : (typeof data === 'object' ? data : null));

      if (!mainObj || (Array.isArray(data) && !data.length)) {
        if (resEl) resEl.innerHTML = '<div class="py-5 text-center bg-lokalesoft/30 rounded-2xl border border-lokaleborder text-muted">Belum tersedia rekomendasi rute multi-moda untuk koordinat ini.</div>';
        return;
      }

      const listToRender = options.length ? options : [mainObj];
      resEl.innerHTML = listToRender.slice(0, 3).map((rec, idx) => {
        const meta = rec.metadata || {};
        const costSummary = rec.ringkasan_biaya || {};
        const routeGroups = rec.rute_perjalanan || {};
        const segPergi = Array.isArray(routeGroups.pergi) ? routeGroups.pergi : (rec.rute_pergi || rec.pergi || rec.segmen || rec.segments || []);
        const segPulang = Array.isArray(routeGroups.pulang) ? routeGroups.pulang : (rec.rute_pulang || rec.pulang || []);
        const totalCost = Number(costSummary.total_biaya_estimasi ?? rec.total_biaya ?? rec.estimasi_biaya ?? rec.total_tarif ?? rec.total_harga ?? rec.tarif ?? 0);
        const totalCostText = costSummary.total_biaya_format || formatIdr(totalCost);
        const computedDist = [...segPergi, ...segPulang].reduce((acc, s) => acc + (Number(s?.jarak_km) || 0), 0);
        const jarak = rec.total_jarak_km ?? rec.jarak_km ?? rec.distance_km ?? (computedDist > 0 ? computedDist : null);
        const nearestHub = meta.stasiun_terdekat_wisata;
        const tripLabel = meta.tipe_perjalanan || (tripType === 'roundtrip' ? 'Pulang-Pergi (PP)' : 'Sekali Jalan (One-Way)');
        const ringkas = rec.ringkasan || rec.summary || `Rute ${tripLabel} ke ${meta.nama_wisata || namaWisata}`;
        const label = `Multi-Moda ${tripType === 'roundtrip' ? 'PP' : 'Oneway'} (${meta.nama_wisata || namaWisata})`;
        const note = `[Transport API - Rute] ${ringkas}${jarak !== null ? ` (~${Number(jarak).toFixed(1)} km)` : ''}, est. ${totalCostText}.`;

        return `<div class="p-3.5 rounded-2xl border ${idx === 0 ? 'border-lokale bg-lokalesoft/50' : 'border-lokaleborder bg-white'} space-y-2">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <strong class="text-ink">${escapeHtml(ringkas)}</strong>
              <span class="block text-[10px] text-muted">${escapeHtml(tripLabel)}${jarak !== null ? ` • Total ~${Number(jarak).toFixed(1)} km` : ''}${nearestHub?.nama_lokasi ? ` • Hub terdekat: ${escapeHtml(nearestHub.nama_lokasi)} (${Number(nearestHub.jarak_ke_objek_wisata_km || 0).toFixed(1)} km ke objek)` : ''}</span>
            </div>
            <strong class="font-mono text-sm text-lokale">${escapeHtml(totalCostText)}</strong>
          </div>
          ${costSummary.keterangan ? `<p class="text-[10px] text-muted">${escapeHtml(costSummary.keterangan)}</p>` : ''}
          ${Array.isArray(segPergi) && segPergi.length ? `<div><span class="text-[10px] font-bold uppercase text-lokale block mb-1">Perjalanan Berangkat (${escapeHtml(costSummary.biaya_pergi_format || formatIdr(costSummary.biaya_pergi || 0))})</span>${renderRouteSegmentsList(segPergi)}</div>` : ''}
          ${Array.isArray(segPulang) && segPulang.length ? `<div><span class="text-[10px] font-bold uppercase text-amber-700 block mb-1">Perjalanan Pulang (${escapeHtml(costSummary.biaya_pulang_format || formatIdr(costSummary.biaya_pulang || 0))})</span>${renderRouteSegmentsList(segPulang)}</div>` : ''}
          <div class="flex justify-end pt-1">
            <button type="button" data-apply-rec="1" data-label="${escapeHtml(label)}" data-cost="${totalCost}" data-note="${escapeHtml(note)}" class="px-3.5 py-1 rounded-full bg-lokale hover:bg-lokaledark text-white text-[10px] font-bold transition">Pakai Estimasi Rute Ini</button>
          </div>
        </div>`;
      }).join('');

      resEl.querySelectorAll('[data-apply-rec]').forEach((b) => {
        b.addEventListener('click', () => {
          applyExternalTransportEstimate({
            source: 'rekomendasi',
            label: b.dataset.label,
            cost: Number(b.dataset.cost || 0),
            note: b.dataset.note,
            pickup: state.refLabel || '',
          });
        });
      });
    } catch (err) {
      setTransportPanelFeedback('transportRekomendasiFeedback', err.message || 'Gagal memuat rekomendasi rute wisata.', 'error', () => handleRekomendasiFormSubmit());
      if (resEl) resEl.innerHTML = '<div class="py-4 text-center text-muted bg-lokalesoft/20 rounded-2xl">Rekomendasi rute belum dapat dimuat.</div>';
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.textContent = 'Cari Rekomendasi Rute';
      }
    }
  }

  function updatePlanner() {
    const selEl = document.getElementById('planGuideSelect');
    const g = (selEl ? findGuide(selEl.value) : null) || TOUR_GUIDES[0];
    if (!g) return;
    const days = Math.max(1, Number(document.getElementById('planDays')?.value || 1));
    const pax = Math.max(1, Number(document.getElementById('planPax')?.value || 1));
    const hTier = document.getElementById('planHotelTier')?.value || 'partner';
    const tMode = document.getElementById('planTransportMode')?.value || 'partner';
    const appliedTrans = state.transport.applied;

    const gCost = g.price * days;
    let tCost = 0;
    let tLabel = g.transportName;
    if (tMode === 'public_api') {
      tCost = appliedTrans ? Math.max(0, Number(appliedTrans.cost || 0)) : 0;
      tLabel = appliedTrans ? appliedTrans.label : 'Pilih tarif/rute Transport API di bawah';
    } else {
      tCost = (tMode === 'hiace' ? 1250000 : tMode === 'bus' ? 2600000 : tMode === 'self' ? 0 : 650000) * days;
      if (tMode === 'self') tLabel = 'Kendaraan Pribadi';
    }

    const appliedBox = document.getElementById('planExternalTransportAppliedBox');
    const appliedSum = document.getElementById('planExternalTransportSummary');
    if (appliedBox && appliedSum) {
      const showApplied = Boolean(appliedTrans && tMode === 'public_api');
      appliedBox.classList.toggle('hidden', !showApplied);
      if (showApplied) {
        appliedSum.textContent = `${appliedTrans.label} (${formatIdr(appliedTrans.cost)}), akan disisipkan ke catatan saat klik Booking Paket Ini.`;
      }
    }

    const hCost = hTier === 'none' ? 0 : (hTier === 'luxury' ? 1450000 : hTier === 'budget' ? 420000 : 750000) * Math.ceil(pax / 2) * Math.max(1, days - 1);
    const cCost = pax * days * 75000;
    const total = gCost + tCost + hCost + cCost;

    document.getElementById('planSummaryTitle').textContent = `Paket ${g.specialization} (${days} Hari • ${pax} Peserta)`;
    document.getElementById('planCostPerPax').textContent = `${formatIdr(Math.round(total / pax))} / pax`;
    document.getElementById('planGuideLabel').textContent = g.name;
    document.getElementById('planGuideCost').textContent = formatIdr(gCost);
    document.getElementById('planTransLabel').textContent = tLabel;
    document.getElementById('planTransCost').textContent = formatIdr(tCost);
    document.getElementById('planHotelLabel').textContent = hTier === 'none' ? 'Tanpa Menginap' : g.hotelName;
    document.getElementById('planHotelCost').textContent = formatIdr(hCost);
    document.getElementById('planCulLabel').textContent = g.culinaryName;
    document.getElementById('planCulCost').textContent = formatIdr(cCost);
    document.getElementById('planGrandTotal').textContent = formatIdr(total);

    const dayTwoLabel = days > 1 ? `Hari 2–${days}:` : 'Sore – Malam:';
    const stayNote = hTier === 'none' ? 'tanpa menginap' : `menginap di ${escapeHtml(g.hotelName)}`;
    document.getElementById('planItineraryTimeline').innerHTML = `
      <div class="p-3.5 rounded-2xl bg-lokalesoft/50 border border-lokaleborder"><strong class="text-lokale">Hari 1:</strong> Penjemputan di ${escapeHtml(formatDistrict(g.district))} menuju <strong>${escapeHtml(g.destName)}</strong> &amp; kuliner <strong>${escapeHtml(g.culinaryName)}</strong>.</div>
      <div class="p-3.5 rounded-2xl bg-sand/70 border border-slate-200"><strong>${dayTwoLabel}</strong> Eksplorasi ${escapeHtml(g.specialization)} bersama ${escapeHtml(g.name)}, ${stayNote}.</div>`;
  }

  // =========================================================================
  // EKOSISTEM & GRAFIK
  // =========================================================================
  function updateAnalyticsCharts() {
    if (!global.Chart) return;
    const specsCanvas = document.getElementById('chartSpecializations');
    const langsCanvas = document.getElementById('chartLanguages');
    const statusesCanvas = document.getElementById('chartStatuses');
    if (!specsCanvas || !langsCanvas || !statusesCanvas) return;

    const sc = {};
    const ss = {};
    const lc = {};
    const st = { Tersedia: 0, 'Sedang Bertugas': 0, Booked: 0 };
    TOUR_GUIDES.forEach((g) => {
      sc[g.specialization] = (sc[g.specialization] || 0) + 1;
      ss[g.specialization] = (ss[g.specialization] || 0) + g.price;
      st[g.status] = (st[g.status] || 0) + 1;
      getLangNames(g).forEach((l) => {
        lc[l] = (lc[l] || 0) + 1;
      });
    });
    const sortedLangs = Object.entries(lc).sort((a, b) => b[1] - a[1]);

    if (chartInstances.specs) chartInstances.specs.destroy();
    if (chartInstances.langs) chartInstances.langs.destroy();
    if (chartInstances.statuses) chartInstances.statuses.destroy();

    chartInstances.specs = new Chart(specsCanvas, {
      type: 'bar',
      data: {
        labels: Object.keys(sc).map((s) => s.split('&')[0].trim()),
        datasets: [{
          label: 'Rata-rata (Ribu Rp)',
          data: Object.keys(sc).map((s) => Math.round(ss[s] / sc[s] / 1000)),
          backgroundColor: '#07779d',
          borderRadius: 8,
        }],
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
    });
    chartInstances.langs = new Chart(langsCanvas, {
      type: 'bar',
      data: {
        labels: sortedLangs.map(([l]) => l),
        datasets: [{
          label: 'Jumlah Pemandu',
          data: sortedLangs.map(([, c]) => c),
          backgroundColor: '#389ec0',
          borderRadius: 8,
        }],
      },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
    });
    chartInstances.statuses = new Chart(statusesCanvas, {
      type: 'doughnut',
      data: {
        labels: Object.keys(st),
        datasets: [{ data: Object.values(st), backgroundColor: ['#07779d', '#f59e0b', '#e11d48'] }],
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
    });
  }

  function renderAnalytics() {
    updateAnalyticsCharts();
    renderEco();
  }

  function renderEco() {
    document.querySelectorAll('.eco-tab-btn').forEach((b) => {
      b.className = b.dataset.ecoTab === state.ecoTab
        ? 'eco-tab-btn px-3.5 py-1.5 rounded-full text-xs font-semibold bg-lokale text-white transition'
        : 'eco-tab-btn px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale hover:text-lokaledark transition';
    });

    const counts = {};
    TOUR_GUIDES.forEach((g) => {
      const k = state.ecoTab === 'culinaries' ? g.culinaryId : state.ecoTab === 'hotels' ? g.hotelId : state.ecoTab === 'transports' ? g.transportId : g.destId;
      if (k) counts[k] = (counts[k] || 0) + 1;
    });

    const categoryLabel = state.ecoTab === 'culinaries' ? 'Kuliner Pilihan' : state.ecoTab === 'hotels' ? 'Hotel Mitra' : state.ecoTab === 'transports' ? 'Armada Transportasi' : 'Destinasi Wisata';
    const masterList = MASTER_ECOSYSTEM[state.ecoTab] || [];
    const items = masterList.map((row) => {
      const k = row.destination_code || row.culinary_code || row.hotel_code || row.transport_code || '';
      const n = row.destination_name || row.culinary_name || row.hotel_name || row.transport_name || '';
      return { k, n, c: counts[k] || 0 };
    });

    const gridEl = document.getElementById('ecosystemEntitiesGrid');
    if (!gridEl) return;

    gridEl.innerHTML = items.map((x) => `
      <div class="bg-lokalesoft/40 border border-lokaleborder rounded-2xl p-3.5 text-xs flex flex-col justify-between">
        <div>
          <div class="flex justify-between items-center gap-2"><span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-white border border-lokaleborder text-lokale">${categoryLabel}</span><span class="text-muted text-[11px]">${x.c} Pemandu</span></div>
          <div class="font-bold text-ink mt-2">${escapeHtml(x.n)}</div>
        </div>
        <button type="button" data-eco-q="${escapeHtml(x.k)}" class="mt-2.5 w-full py-1.5 rounded-full bg-white hover:bg-lokale hover:text-white text-lokale border border-lokaleborder font-semibold text-[11px] transition">Lihat Pemandu Terkait</button>
      </div>`).join('');

    document.querySelectorAll('[data-eco-q]').forEach((b) => {
      b.addEventListener('click', () => {
        resetAllFilters();
        state.search = b.dataset.ecoQ;
        const searchEl = document.getElementById('filterSearch');
        if (searchEl) searchEl.value = state.search;
        switchTab('directory');
        renderDirectory();
        document.getElementById('direktori-pemandu')?.scrollIntoView({ behavior: 'smooth' });
      });
    });
  }

  // =========================================================================
  // GUIDE DETAIL MODAL + BLUR GATE + MULTI-DAY BOOKING FLOW
  // =========================================================================
  function populateBookingMasterSelects(guide, preserveSelection = false) {
    const destSelect = document.getElementById('bookDestinationId');
    const transSelect = document.getElementById('bookTransportId');

    if (destSelect) {
      const prevDest = preserveSelection ? destSelect.value : '';
      const dests = MASTER_ECOSYSTEM.destinations || [];
      destSelect.innerHTML = `<option value="">Sesuai Destinasi Utama Pemandu</option>` +
        dests.map((d) => `<option value="${escapeHtml(d.destination_id)}">${escapeHtml(d.destination_code)} • ${escapeHtml(d.destination_name)}</option>`).join('');
      if (prevDest && Array.from(destSelect.options).some((o) => o.value === prevDest)) {
        destSelect.value = prevDest;
      } else if (guide?.primary_destination?.id) {
        destSelect.value = String(guide.primary_destination.id);
      }
    }

    if (transSelect) {
      const prevTrans = preserveSelection ? transSelect.value : '';
      const transports = MASTER_ECOSYSTEM.transports || [];
      transSelect.innerHTML = `<option value="">Sesuai Armada Default Pemandu</option>` +
        transports.map((t) => `<option value="${escapeHtml(t.transport_id)}">${escapeHtml(t.transport_code)} • ${escapeHtml(t.transport_name)}</option>`).join('');
      if (prevTrans && Array.from(transSelect.options).some((o) => o.value === prevTrans)) {
        transSelect.value = prevTrans;
      } else if (guide?.default_transport?.id) {
        transSelect.value = String(guide.default_transport.id);
      }
    }
  }

  function renderGuideRecentReviews(guide) {
    const box = document.getElementById('modalRecentReviews');
    if (!box) return;
    const revs = Array.isArray(guide?.recent_reviews) ? guide.recent_reviews : [];
    if (!revs.length) {
      box.innerHTML = '<p class="text-[11px] text-muted italic">Belum ada ulasan internal terbaru untuk pemandu ini.</p>';
      return;
    }
    box.innerHTML = revs.slice(0, 3).map((r) => `
      <div class="p-2.5 rounded-xl bg-white border border-slate-100 space-y-1">
        <div class="flex items-center justify-between gap-2">
          <strong class="text-[11px] text-ink">${escapeHtml(r.reviewer_name || 'Wisatawan')}</strong>
          <span class="text-amber-500 font-bold text-[11px]">★ ${Number(r.rating || 5)}/5</span>
        </div>
        <p class="text-[11px] text-muted leading-relaxed">${escapeHtml(r.review_text || '')}</p>
      </div>
    `).join('');
  }

  function applyGuideModalBlurState() {
    const authed = isLoggedIn();
    const protectedContent = document.getElementById('guideModalProtectedContent');
    const blurOverlay = document.getElementById('guideModalBlurOverlay');
    const bookingAuthGate = document.getElementById('guideBookingAuthGate');
    const bookingFlow = document.getElementById('guideBookingFlow');
    const guideRoleWarning = document.getElementById('guideRoleBookingNote');
    const bookingForm = document.getElementById('bookingForm');

    if (protectedContent) {
      protectedContent.classList.toggle('blur-locked', !authed);
      if (authed) {
        protectedContent.removeAttribute('aria-hidden');
        protectedContent.removeAttribute('inert');
      } else {
        protectedContent.setAttribute('aria-hidden', 'true');
        protectedContent.setAttribute('inert', '');
      }
    }

    if (blurOverlay) {
      blurOverlay.classList.toggle('hidden', authed);
    }

    if (bookingAuthGate) {
      bookingAuthGate.classList.toggle('hidden', authed);
    }

    if (bookingFlow) {
      bookingFlow.classList.toggle('hidden', !authed);
    }

    if (authed) {
      const user = global.SumutGoAuth.getUser();
      const isGuideRole = user && String(user.role).toLowerCase() === 'guide';
      if (guideRoleWarning) guideRoleWarning.classList.toggle('hidden', !isGuideRole);
      if (bookingForm) bookingForm.classList.toggle('hidden', isGuideRole && state.booking.step === 'form');
    }
  }

  function setBookingStep(step) {
    state.booking.step = step;
    const formPanel = document.getElementById('bookingStepForm');
    const confirmPanel = document.getElementById('bookingStepConfirm');
    const successPanel = document.getElementById('bookingStepSuccess');

    if (formPanel) formPanel.classList.toggle('hidden', step !== 'form');
    if (confirmPanel) confirmPanel.classList.toggle('hidden', step !== 'confirm');
    if (successPanel) successPanel.classList.toggle('hidden', step !== 'success');

    const stepMap = [
      ['stepBadge1', step === 'form', step === 'confirm' || step === 'success'],
      ['stepBadge2', step === 'confirm', step === 'success'],
      ['stepBadge3', step === 'success', false],
    ];
    stepMap.forEach(([id, isActive, isDone]) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.className = isActive
        ? 'px-2.5 py-1 rounded-full text-[10px] font-bold bg-lokale text-white transition'
        : isDone
          ? 'px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 transition'
          : 'px-2.5 py-1 rounded-full text-[10px] font-semibold bg-slate-100 text-muted transition';
    });
  }

  function syncBookingDurationFromDays(newDays, opts = {}) {
    const days = Math.min(30, Math.max(1, parseInt(newDays, 10) || 1));
    state.booking.days = days;

    const startEl = document.getElementById('bookStartDate');
    const endEl = document.getElementById('bookEndDate');
    const daysEl = document.getElementById('bookDays');

    if (startEl && startEl.value) {
      state.booking.startDate = startEl.value;
    }
    state.booking.endDate = addDaysYmd(state.booking.startDate, days - 1);

    if (daysEl && !opts.keepInput) daysEl.value = String(days);
    if (endEl) {
      endEl.min = state.booking.startDate;
      endEl.value = state.booking.endDate;
    }

    updateBookingPricingAndPresets();
  }

  function syncBookingFromEndDate() {
    const startEl = document.getElementById('bookStartDate');
    const endEl = document.getElementById('bookEndDate');
    const daysEl = document.getElementById('bookDays');
    const errEl = document.getElementById('bookingFormError');

    const startVal = startEl?.value || state.booking.startDate;
    const endVal = endEl?.value || state.booking.endDate;
    state.booking.startDate = startVal;
    state.booking.endDate = endVal;

    const diff = diffDaysYmd(startVal, endVal);
    if (Number.isNaN(diff) || diff < 0) {
      if (errEl) {
        errEl.textContent = 'Tanggal selesai tur tidak boleh lebih awal dari tanggal mulai.';
        errEl.classList.remove('hidden');
      }
      return false;
    }

    if (errEl) errEl.classList.add('hidden');
    const days = Math.min(30, diff + 1);
    state.booking.days = days;
    if (daysEl) daysEl.value = String(days);
    updateBookingPricingAndPresets();
    return true;
  }

  function updateBookingPricingAndPresets() {
    const g = state.activeGuideDetail || findGuide(state.activeGuideId);
    if (!g) return;

    const days = Math.max(1, Number(state.booking.days || 1));
    const total = Number(g.price || 0) * days;

    const totalReadout = document.getElementById('bookTotalReadout');
    const breakdownReadout = document.getElementById('bookBreakdownReadout');
    const dateRangeReadout = document.getElementById('bookDateRangeReadout');

    if (totalReadout) totalReadout.textContent = formatIdr(total);
    if (breakdownReadout) breakdownReadout.textContent = `${formatIdr(g.price)} × ${days} hari`;
    if (dateRangeReadout) {
      dateRangeReadout.textContent = days === 1
        ? `${formatDateId(state.booking.startDate)} (1 Hari)`
        : `${formatDateId(state.booking.startDate)} – ${formatDateId(state.booking.endDate)} (${days} Hari)`;
    }

    document.querySelectorAll('[data-duration-preset]').forEach((btn) => {
      const preset = Number(btn.dataset.durationPreset);
      btn.className = preset === days
        ? 'px-2.5 py-1 rounded-full text-[11px] font-bold bg-lokale text-white transition shadow-sm'
        : 'px-2.5 py-1 rounded-full text-[11px] font-semibold bg-white hover:bg-lokalesoft text-lokale border border-lokaleborder transition';
    });
  }

  function populateGuideModalData(g, opts = {}) {
    state.activeGuideId = g.id;
    state.activeGuideDetail = g;

    updateModalGuideStatus(g);
    document.getElementById('modalGuideName').textContent = g.name;
    document.getElementById('modalAgency').textContent = g.agency;
    document.getElementById('modalAddress').textContent = g.address || '-';
    document.getElementById('modalCoords').textContent = (g.lat && g.lng) ? `${Number(g.lat).toFixed(4)}, ${Number(g.lng).toFixed(4)}` : '-';
    document.getElementById('modalSpec').textContent = g.specialization;
    document.getElementById('modalLangs').textContent = getLangNames(g).join(', ') || 'Indonesia';
    document.getElementById('modalPrice').textContent = `${formatIdr(g.price)} / hari`;
    document.getElementById('modalRating').textContent = `★ ${Number(g.rating).toFixed(1)} (${Number(g.reviews || 0).toLocaleString('id-ID')} ulasan)`;

    const phoneEl = document.getElementById('modalPhone');
    const waLink = document.getElementById('modalWhatsappLink');
    const callLink = document.getElementById('modalCallLink');
    const copyBtn = document.getElementById('btnModalCopyPhone');
    const phoneDigits = toPhoneDigits(g.phone);
    if (phoneEl) phoneEl.textContent = formatDisplayPhone(phoneDigits);
    if (waLink) {
      const msg = encodeURIComponent(`Halo ${g.name}, saya tertarik dengan layanan pemandu wisata SumutGO (${g.id}).`);
      waLink.href = phoneDigits ? `https://wa.me/${phoneDigits}?text=${msg}` : '#';
      waLink.classList.toggle('hidden', !phoneDigits);
    }
    if (callLink) {
      callLink.href = phoneDigits ? `tel:+${phoneDigits}` : '#';
      callLink.classList.toggle('hidden', !phoneDigits);
    }
    if (copyBtn) {
      copyBtn.dataset.copyPhone = phoneDigits ? `+${phoneDigits}` : '';
      copyBtn.classList.toggle('hidden', !phoneDigits);
    }

    document.getElementById('modalDestName').textContent = g.destName || '-';
    document.getElementById('modalCulName').textContent = g.culinaryName || '-';
    document.getElementById('modalHotelName').textContent = g.hotelName || '-';
    document.getElementById('modalTransName').textContent = g.transportName || '-';

    renderGuideRecentReviews(g);
    populateBookingMasterSelects(g, Boolean(opts.preserveSelection));

    const mpEl = document.getElementById('bookMeetingPoint');
    if (mpEl) {
      mpEl.placeholder = g.address || 'Contoh: Lobby Hotel / Bandara Kualanamu';
    }

    updateBookingPricingAndPresets();
  }

  async function openGuideModal(id, intent = 'detail', prefill = null) {
    const g = findGuide(id);
    if (!g) return;

    const today = getTodayYmd();
    const startDefault = (prefill && prefill.startDate) || addDaysYmd(today, 1);
    const daysDefault = (prefill && prefill.days) ? Math.min(30, Math.max(1, Number(prefill.days))) : 2;
    const guestsDefault = (prefill && prefill.guests) ? Math.min(45, Math.max(1, Number(prefill.guests))) : 2;

    state.booking.startDate = startDefault;
    state.booking.days = daysDefault;
    state.booking.endDate = addDaysYmd(startDefault, daysDefault - 1);
    state.booking.guests = guestsDefault;
    state.booking.meetingPoint = (prefill && prefill.meetingPoint) ? String(prefill.meetingPoint) : '';
    state.booking.notes = (prefill && prefill.notes) ? String(prefill.notes) : '';
    state.booking.confirmedReceipt = null;

    const startEl = document.getElementById('bookStartDate');
    const endEl = document.getElementById('bookEndDate');
    const daysEl = document.getElementById('bookDays');
    const guestsEl = document.getElementById('bookGuests');
    const mpEl = document.getElementById('bookMeetingPoint');
    const notesEl = document.getElementById('bookNotes');
    const modalRouteBox = document.getElementById('modalTransportRouteResult');

    if (startEl) {
      startEl.min = today;
      startEl.value = state.booking.startDate;
    }
    if (endEl) {
      endEl.min = state.booking.startDate;
      endEl.value = state.booking.endDate;
    }
    if (daysEl) daysEl.value = String(state.booking.days);
    if (guestsEl) guestsEl.value = String(state.booking.guests);
    if (mpEl) mpEl.value = state.booking.meetingPoint;
    if (notesEl) notesEl.value = state.booking.notes;
    if (modalRouteBox) {
      modalRouteBox.innerHTML = '';
      modalRouteBox.classList.add('hidden');
    }

    document.getElementById('bookingFormError')?.classList.add('hidden');
    document.getElementById('bookingConfirmError')?.classList.add('hidden');

    populateGuideModalData(g);
    setBookingStep('form');
    applyGuideModalBlurState();

    const modal = document.getElementById('guideModal');
    if (modal) modal.classList.remove('hidden');

    if (intent === 'book') {
      setTimeout(() => {
        const bookingTarget = isLoggedIn()
          ? document.getElementById('guideBookingFlow')
          : document.getElementById('guideBookingAuthGate');
        bookingTarget?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }, 120);
    }

    // Fetch fresh detail + recent_reviews dari REST API v1 (/api/v1/guides/{id})
    try {
      const resp = await global.SumutGoApi.guides.detail(g.id);
      if (resp?.data && state.activeGuideId === g.id) {
        updateGuideInCache(resp.data);
        populateGuideModalData(resp.data, { preserveSelection: true });
        applyGuideModalBlurState();
      }
    } catch (_) {
      // Gunakan data cache SSR jika request background gagal
    }
  }

  function handleStartBooking(guideId, prefill = null) {
    const g = findGuide(guideId);
    if (!g) return;

    if (!isLoggedIn()) {
      global.SumutGoAuth.openAuthModal(
        'login',
        `Masuk atau daftar akun terlebih dahulu untuk memesan pemandu ${g.name} (${g.id}). Setelah masuk, Anda akan langsung diarahkan ke formulir pemesanan.`,
        {
          type: 'guide-modal',
          guideId: g.id,
          intent: 'book',
          prefill,
        }
      );
      return;
    }

    openGuideModal(g.id, 'book', prefill);
  }

  function handleBookingStep1Submit(e) {
    e.preventDefault();
    const errEl = document.getElementById('bookingFormError');
    if (errEl) errEl.classList.add('hidden');

    const g = state.activeGuideDetail || findGuide(state.activeGuideId);
    if (!g) return;

    const startDate = document.getElementById('bookStartDate')?.value || '';
    const endDate = document.getElementById('bookEndDate')?.value || '';
    const guests = parseInt(document.getElementById('bookGuests')?.value || '1', 10);
    const destId = document.getElementById('bookDestinationId')?.value || '';
    const transId = document.getElementById('bookTransportId')?.value || '';
    const meetingPoint = (document.getElementById('bookMeetingPoint')?.value || '').trim();
    const notes = (document.getElementById('bookNotes')?.value || '').trim();

    if (!startDate || !endDate) {
      if (errEl) {
        errEl.textContent = 'Tanggal mulai dan tanggal selesai tur wajib diisi.';
        errEl.classList.remove('hidden');
      }
      return;
    }

    const today = getTodayYmd();
    if (startDate < today) {
      if (errEl) {
        errEl.textContent = 'Tanggal mulai tur tidak boleh lebih awal dari hari ini.';
        errEl.classList.remove('hidden');
      }
      return;
    }

    const diff = diffDaysYmd(startDate, endDate);
    if (Number.isNaN(diff) || diff < 0) {
      if (errEl) {
        errEl.textContent = 'Tanggal selesai tur harus sama atau lebih besar dari tanggal mulai.';
        errEl.classList.remove('hidden');
      }
      return;
    }

    if (!guests || guests < 1) {
      if (errEl) {
        errEl.textContent = 'Jumlah tamu minimal 1 orang.';
        errEl.classList.remove('hidden');
      }
      return;
    }

    const days = diff + 1;
    state.booking.startDate = startDate;
    state.booking.endDate = endDate;
    state.booking.days = days;
    state.booking.guests = guests;
    state.booking.destinationId = destId;
    state.booking.transportId = transId;
    state.booking.meetingPoint = meetingPoint;
    state.booking.notes = notes;

    // Isi tampilan ringkasan Tahap Konfirmasi (Step 2)
    const destSelect = document.getElementById('bookDestinationId');
    const transSelect = document.getElementById('bookTransportId');
    const destLabel = destId && destSelect ? destSelect.options[destSelect.selectedIndex]?.text : g.destName;
    const transLabel = transId && transSelect ? transSelect.options[transSelect.selectedIndex]?.text : g.transportName;

    document.getElementById('confGuideLabel').textContent = `${g.name} (${g.id})`;
    document.getElementById('confDateRange').textContent = `${formatDateId(startDate)} s.d. ${formatDateId(endDate)}`;
    document.getElementById('confDuration').textContent = `${days} Hari`;
    document.getElementById('confGuests').textContent = `${guests} Orang`;
    document.getElementById('confDestLabel').textContent = destLabel || '-';
    document.getElementById('confTransLabel').textContent = transLabel || '-';
    document.getElementById('confMeetingPoint').textContent = meetingPoint || `${g.address} (Default Basecamp)`;
    document.getElementById('confNotes').textContent = notes || 'Tidak ada catatan khusus';
    document.getElementById('confRateBreakdown').textContent = `${formatIdr(g.price)} × ${days} hari`;
    document.getElementById('confTotalEstimate').textContent = formatIdr(g.price * days);

    document.getElementById('bookingConfirmError')?.classList.add('hidden');
    setBookingStep('confirm');
  }

  async function handleConfirmSubmitBooking() {
    const g = state.activeGuideDetail || findGuide(state.activeGuideId);
    if (!g) return;

    const btnConfirm = document.getElementById('btnConfirmBookingSubmit');
    const btnBack = document.getElementById('btnBackToBookingForm');
    const errEl = document.getElementById('bookingConfirmError');
    if (errEl) errEl.classList.add('hidden');

    if (btnConfirm) {
      btnConfirm.disabled = true;
      btnConfirm.textContent = 'Mengirim ke Server...';
    }
    if (btnBack) btnBack.disabled = true;

    try {
      // Kirim payload ke POST /api/v1/bookings TANPA duration_days / total_price_idr (dihitung otomatis oleh PostgreSQL)
      const payload = {
        guide_id: Number(g.guide_id),
        start_date: state.booking.startDate,
        end_date: state.booking.endDate,
        number_of_guests: Number(state.booking.guests),
      };
      if (state.booking.destinationId) {
        payload.destination_id = Number(state.booking.destinationId);
      }
      if (state.booking.transportId) {
        payload.transport_id = Number(state.booking.transportId);
      }
      if (state.booking.meetingPoint) {
        payload.meeting_point = state.booking.meetingPoint;
      }
      if (state.booking.notes) {
        payload.notes = state.booking.notes;
      }

      const resp = await global.SumutGoApi.bookings.create(payload);
      const created = resp?.data || {};
      state.booking.confirmedReceipt = created;

      // Render Bukti Konfirmasi Resmi dari Backend (Step 3)
      document.getElementById('recBookingCode').textContent = created.booking_code || '-';
      document.getElementById('recStatusBadge').textContent = created.status || 'pending';
      document.getElementById('recGuideName').textContent = `${created.guide_name || g.name} (${created.guide_code || g.id})`;
      document.getElementById('recDateRange').textContent = `${formatDateId(created.start_date || state.booking.startDate)} s.d. ${formatDateId(created.end_date || state.booking.endDate)}`;
      document.getElementById('recDurationDays').textContent = `${created.duration_days || state.booking.days} Hari`;
      document.getElementById('recTotalPrice').textContent = formatIdr(created.total_price_idr || (g.price * state.booking.days));
      document.getElementById('recGuests').textContent = `${created.number_of_guests || state.booking.guests} Orang`;
      document.getElementById('recMeetingPoint').textContent = created.meeting_point || state.booking.meetingPoint || g.address;
      populateReceiptContactSection(created, g);

      setBookingStep('success');
      global.SumutGoAuth.showToast(`Booking ${created.booking_code || ''} berhasil diajukan!`, 'success');

      // Refresh status guide & seluruh UI terkait dari REST API v1
      try {
        const freshGuide = await global.SumutGoApi.guides.detail(g.id);
        if (freshGuide?.data) {
          state.activeGuideDetail = freshGuide.data;
          updateGuideInCache(freshGuide.data);
          updateModalGuideStatus(freshGuide.data);
          renderDirectory();
          if (state.activeTab === 'map') renderMap({ preserveListScroll: true });
          if (state.activeTab === 'analytics') updateAnalyticsCharts();
          updatePlanner();
          const compModal = document.getElementById('compareModal');
          if (compModal && !compModal.classList.contains('hidden')) openCompareModal();
        }
      } catch (_) {
        // Ignore refresh failures
      }
    } catch (err) {
      if (errEl) {
        let extraHint = '';
        if (err.status === 409) {
          extraHint = ' Klik "Ubah Detail" untuk memilih tanggal lain yang tersedia.';
        } else if (err.status === 401) {
          extraHint = ' Silakan masuk kembali untuk melanjutkan pemesanan.';
        }
        errEl.textContent = (err.message || 'Gagal mengajukan reservasi.') + extraHint;
        errEl.classList.remove('hidden');
      }
    } finally {
      if (btnConfirm) {
        btnConfirm.disabled = false;
        btnConfirm.textContent = 'Konfirmasi & Ajukan Booking';
      }
      if (btnBack) btnBack.disabled = false;
    }
  }

  function toPhoneDigits(raw) {
    let digits = String(raw || '').replace(/[^0-9]/g, '');
    if (!digits) return '';
    if (digits.startsWith('0')) {
      digits = `62${digits.slice(1)}`;
    }
    return digits;
  }

  function formatDisplayPhone(raw) {
    const digits = toPhoneDigits(raw);
    return digits ? `+${digits}` : '-';
  }

  async function copyPhoneText(raw, label = 'Nomor HP') {
    const formatted = formatDisplayPhone(raw);
    if (!formatted || formatted === '-') return;
    try {
      if (global.navigator?.clipboard?.writeText) {
        await global.navigator.clipboard.writeText(formatted);
      } else {
        const ta = document.createElement('textarea');
        ta.value = formatted;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document['execCommand']('copy');
        document.body.removeChild(ta);
      }
      global.SumutGoAuth?.showToast(`${label} disalin: ${formatted}`, 'success');
    } catch (_) {
      global.SumutGoAuth?.showToast(`Salin manual: ${formatted}`, 'info');
    }
  }

  function contactStatusBadgeMeta(status) {
    const s = String(status || 'unverified').toLowerCase();
    if (s === 'confirmed') {
      return { label: 'Terkonfirmasi Admin', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
    }
    if (s === 'contacted') {
      return { label: 'Sedang Dihubungi', cls: 'bg-amber-50 text-amber-800 border-amber-200' };
    }
    return { label: 'Menunggu Konfirmasi', cls: 'bg-slate-100 text-slate-600 border-slate-200' };
  }

  function populateReceiptContactSection(created, guide) {
    const g = guide || {};
    const guideDigits = toPhoneDigits(created.guide_phone || g.phone);
    const guideName = created.guide_name || g.name || 'Pemandu Wisata';
    const bookingCode = created.booking_code || '';
    const startDate = created.start_date || state.booking.startDate || '';

    const recPhoneEl = document.getElementById('recGuidePhone');
    const waEl = document.getElementById('btnReceiptWaGuide');
    const callEl = document.getElementById('btnReceiptCallGuide');
    const copyEl = document.getElementById('btnReceiptCopyPhone');

    if (recPhoneEl) recPhoneEl.textContent = formatDisplayPhone(guideDigits);
    if (waEl) {
      const msg = encodeURIComponent(`Halo ${guideName}, saya sudah mengajukan booking ${bookingCode} pada SumutGO untuk tanggal ${startDate}.`);
      waEl.href = guideDigits ? `https://wa.me/${guideDigits}?text=${msg}` : '#';
      waEl.classList.toggle('hidden', !guideDigits);
    }
    if (callEl) {
      callEl.href = guideDigits ? `tel:+${guideDigits}` : '#';
      callEl.classList.toggle('hidden', !guideDigits);
    }
    if (copyEl) {
      copyEl.dataset.copyPhone = guideDigits ? `+${guideDigits}` : '';
      copyEl.classList.toggle('hidden', !guideDigits);
    }

    const form = document.getElementById('receiptContactForm');
    const phoneInput = document.getElementById('recContactPhone');
    const msgInput = document.getElementById('recContactMessage');
    const fbEl = document.getElementById('recContactFeedback');
    const currentUser = global.SumutGoAuth?.getUser() || {};

    if (form) {
      form.dataset.bookingId = String(created.booking_id || '');
    }
    if (phoneInput) {
      const formatted = formatDisplayPhone(currentUser.phone_number || created.tourist_phone || '');
      phoneInput.value = formatted === '-' ? '' : formatted;
    }
    if (msgInput) {
      msgInput.value = '';
    }
    if (fbEl) {
      fbEl.textContent = '';
      fbEl.classList.add('hidden');
    }
  }

  // =========================================================================
  // MODAL BOOKING SAYA (DAFTAR RESERVASI, UPDATE STATUS & REVIEW)
  // =========================================================================
  async function openMyBookingsModal() {
    if (!isLoggedIn()) {
      global.SumutGoAuth.openAuthModal('login', 'Silakan masuk untuk melihat daftar reservasi Anda.');
      return;
    }

    const modal = document.getElementById('myBookingsModal');
    if (modal) modal.classList.remove('hidden');
    await loadMyBookings();
  }

  async function loadMyBookings() {
    const listEl = document.getElementById('myBookingsList');
    const summaryEl = document.getElementById('myBookingsCountInfo');
    const userSummaryEl = document.getElementById('myBookingsUserSummary');
    const adminShortcutBtn = document.getElementById('btnBookingsOpenAdminConfirm');
    if (!listEl) return;

    const user = global.SumutGoAuth.getUser() || {};
    const role = String(user.role || 'wisatawan').toLowerCase();

    if (adminShortcutBtn) {
      adminShortcutBtn.classList.toggle('hidden', role !== 'admin');
    }

    if (userSummaryEl) {
      const userPhone = formatDisplayPhone(user.phone_number);
      const isVerified = Boolean(user.is_verified);
      const verifiedBadge = isVerified
        ? '<span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">✓ Akun &amp; Nomor HP Terkonfirmasi Admin</span>'
        : '<span class="px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-bold">⏳ Menunggu Konfirmasi Admin</span>';

      userSummaryEl.innerHTML = `<div class="flex flex-wrap items-center justify-between gap-2">
        <div class="space-y-0.5">
          <div class="font-bold text-ink">${escapeHtml(user.full_name || user.email || 'Pengguna')} <span class="font-normal text-muted">(${escapeHtml(user.email || '-')})</span></div>
          <div class="text-[11px] text-muted">Nomor HP Terdaftar: <strong class="font-mono text-ink">${escapeHtml(userPhone)}</strong></div>
        </div>
        ${verifiedBadge}
      </div>`;
      userSummaryEl.classList.remove('hidden');
    }

    listEl.innerHTML = '<div class="py-10 text-center text-xs text-muted">Memuat data reservasi dari server...</div>';

    try {
      const params = { page: 1, per_page: 30 };
      if (state.myBookingsFilter !== 'ALL') {
        params.status = state.myBookingsFilter;
      }
      const resp = await global.SumutGoApi.bookings.list(params);
      const items = Array.isArray(resp?.data) ? resp.data : [];
      state.myBookingsList = items;

      if (summaryEl) {
        summaryEl.textContent = `${items.length} reservasi ditampilkan`;
      }

      if (!items.length) {
        listEl.innerHTML = `<div class="py-10 text-center bg-lokalesoft/40 border border-lokaleborder rounded-2xl p-6 space-y-2">
          <p class="text-xs font-semibold text-ink">Belum ada reservasi pada filter ini.</p>
          <p class="text-[11px] text-muted">Pilih pemandu di direktori untuk mulai memesan tur multi-hari.</p>
        </div>`;
        return;
      }

      const defaultSenderPhoneRaw = formatDisplayPhone(user.phone_number);
      const defaultSenderPhone = defaultSenderPhoneRaw === '-' ? '' : defaultSenderPhoneRaw;

      listEl.innerHTML = items.map((b) => {
        const status = String(b.status || '').toLowerCase();
        const canTouristCancel = role === 'wisatawan' && (status === 'pending' || status === 'confirmed');
        const canTouristReview = role === 'wisatawan' && status === 'completed' && !b.review_id;
        const canOperatorManage = role === 'guide' || role === 'admin';

        const guideDigits = toPhoneDigits(b.guide_phone);
        const touristDigits = toPhoneDigits(b.tourist_phone);
        const waGuideText = encodeURIComponent(`Halo ${b.guide_name || 'Pemandu'}, terkait booking ${b.booking_code} (${b.start_date}).`);
        const waTouristText = encodeURIComponent(`Halo ${b.tourist_name || 'Pemesan'}, konfirmasi booking SumutGO ${b.booking_code} (${b.start_date}).`);
        const contactCount = Number(b.contact_count || 0);
        const latestContactMeta = contactCount > 0 ? contactStatusBadgeMeta(b.latest_contact_status) : null;
        const touristVerifiedBadge = b.tourist_verified
          ? '<span class="px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[9px] font-bold">HP Terkonfirmasi</span>'
          : '<span class="px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 text-[9px] font-bold">Belum Verifikasi</span>';

        let actionButtons = `<button type="button" data-toggle-contacts="${b.booking_id}" class="px-3 py-1 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white border border-lokaleborder text-[11px] font-semibold transition">Pesan &amp; Kontak (<span data-contact-count="${b.booking_id}">${contactCount}</span>)</button>`;
        if (canTouristCancel) {
          actionButtons += `<button type="button" data-cancel-booking="${b.booking_id}" data-version="${b.version}" class="px-3 py-1 rounded-full bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white border border-rose-200 text-[11px] font-semibold transition">Batalkan</button>`;
        }
        if (canOperatorManage) {
          if (status === 'pending') {
            actionButtons += `<button type="button" data-status-booking="${b.booking_id}" data-next-status="confirmed" data-version="${b.version}" class="px-3 py-1 rounded-full bg-lokale hover:bg-lokaledark text-white text-[11px] font-semibold transition">Konfirmasi</button>`;
            actionButtons += `<button type="button" data-cancel-booking="${b.booking_id}" data-version="${b.version}" class="px-3 py-1 rounded-full bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white border border-rose-200 text-[11px] font-semibold transition">Tolak</button>`;
          } else if (status === 'confirmed') {
            actionButtons += `<button type="button" data-status-booking="${b.booking_id}" data-next-status="ongoing" data-version="${b.version}" class="px-3 py-1 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-semibold transition">Mulai Tur</button>`;
            actionButtons += `<button type="button" data-cancel-booking="${b.booking_id}" data-version="${b.version}" class="px-3 py-1 rounded-full bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white border border-rose-200 text-[11px] font-semibold transition">Batalkan</button>`;
          } else if (status === 'ongoing') {
            actionButtons += `<button type="button" data-status-booking="${b.booking_id}" data-next-status="completed" data-version="${b.version}" class="px-3 py-1 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-semibold transition">Selesaikan Tur</button>`;
          }
        }
        if (canTouristReview) {
          actionButtons += `<button type="button" data-toggle-review="${b.booking_id}" class="px-3 py-1 rounded-full bg-amber-50 hover:bg-amber-500 text-amber-700 hover:text-white border border-amber-200 text-[11px] font-semibold transition">★ Beri Ulasan</button>`;
        }

        const reviewBlock = b.review_id
          ? `<div class="mt-2.5 p-2.5 rounded-xl bg-amber-50/70 border border-amber-200 text-[11px]">
               <div class="font-bold text-amber-800">Ulasan Anda: ★ ${Number(b.review_rating || 5)}/5</div>
               <div class="text-ink/80 mt-0.5">${escapeHtml(b.review_text || '')}</div>
             </div>`
          : canTouristReview
            ? `<form data-review-form="${b.booking_id}" class="hidden mt-2.5 p-3 rounded-xl bg-lokalesoft/50 border border-lokaleborder space-y-2 text-[11px]">
                 <div class="flex items-center justify-between gap-2">
                   <label class="font-bold text-ink">Beri Rating &amp; Ulasan Pemandu</label>
                   <select name="rating" class="bg-white border border-lokaleborder rounded-lg px-2 py-1 font-semibold">
                     <option value="5">★★★★★ (5)</option>
                     <option value="4">★★★★ (4)</option>
                     <option value="3">★★★ (3)</option>
                     <option value="2">★★ (2)</option>
                     <option value="1">★ (1)</option>
                   </select>
                 </div>
                 <textarea name="review_text" rows="2" required minlength="5" placeholder="Ceritakan pengalaman perjalanan Anda (minimal 5 karakter)..." class="w-full bg-white border border-lokaleborder rounded-xl px-3 py-2 text-xs"></textarea>
                 <div class="flex justify-end gap-2">
                   <button type="submit" class="px-4 py-1.5 rounded-full bg-lokale hover:bg-lokaledark text-white font-semibold transition">Kirim Ulasan</button>
                 </div>
               </form>`
            : '';

        const contactsPanel = `<div data-contacts-panel="${b.booking_id}" class="hidden mt-2.5 p-3 rounded-xl bg-lokalesoft/40 border border-lokaleborder space-y-2.5 text-[11px]">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="font-bold text-ink">Riwayat Kontak &amp; Pesan Booking</span>
            ${latestContactMeta ? `<span class="px-2 py-0.5 rounded-full border text-[10px] font-bold ${latestContactMeta.cls}">${latestContactMeta.label}</span>` : ''}
          </div>
          <div data-contacts-thread="${b.booking_id}" class="space-y-2 text-[11px]">
            <div class="text-muted">Memuat riwayat pesan...</div>
          </div>
          <form data-contact-form="${b.booking_id}" class="pt-2 border-t border-lokaleborder/60 space-y-2">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <select name="preferred_channel" class="bg-white border border-lokaleborder rounded-xl px-2.5 py-1.5 text-xs text-ink">
                <option value="whatsapp">WhatsApp</option>
                <option value="phone">Telepon</option>
                <option value="email">Email</option>
              </select>
              <input name="contact_phone" type="tel" maxlength="25" value="${escapeHtml(defaultSenderPhone)}" placeholder="081234567890" class="bg-white border border-lokaleborder rounded-xl px-2.5 py-1.5 text-xs font-mono text-ink" />
            </div>
            <textarea name="inquiry_message" rows="2" required minlength="3" maxlength="1000" placeholder="Kirim pesan ke pemandu/pemboking atau catatan untuk konfirmasi admin..." class="w-full bg-white border border-lokaleborder rounded-xl px-3 py-2 text-xs text-ink"></textarea>
            <div class="flex justify-end">
              <button type="submit" class="px-4 py-1.5 rounded-full bg-lokale hover:bg-lokaledark text-white font-semibold transition">Kirim Pesan Kontak</button>
            </div>
          </form>
        </div>`;

        return `<div class="bg-white border border-lokaleborder rounded-2xl p-4 space-y-2 text-xs shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-mono font-bold text-lokale bg-lokalesoft px-2.5 py-0.5 rounded-full">${escapeHtml(b.booking_code)}</span>
              <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border uppercase ${bookingStatusBadgeClass(b.status)}">${escapeHtml(b.status)}</span>
              ${latestContactMeta ? `<span class="text-[10px] font-bold px-2 py-0.5 rounded-full border ${latestContactMeta.cls}">Kontak: ${latestContactMeta.label}</span>` : ''}
              <span class="text-[10px] font-mono text-muted">v${escapeHtml(b.version)}</span>
            </div>
            <strong class="font-mono text-sm text-lokale">${formatIdr(b.total_price_idr)}</strong>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
            <div>
              <div class="font-bold text-ink">${escapeHtml(b.guide_name)} (${escapeHtml(b.guide_code)})</div>
              <div class="text-muted">Pemesan: ${escapeHtml(b.tourist_name)} ${touristVerifiedBadge}</div>
              <div class="text-muted">Tujuan: ${escapeHtml(b.destination_name)} • ${escapeHtml(b.transport_name)}</div>
            </div>
            <div class="sm:text-right">
              <div class="font-semibold text-ink">${formatDateId(b.start_date)} – ${formatDateId(b.end_date)}</div>
              <div class="text-lokale font-bold">${escapeHtml(b.duration_days)} Hari • ${escapeHtml(b.number_of_guests)} Tamu</div>
              <div class="text-muted truncate">Kumpul: ${escapeHtml(b.meeting_point || '-')}</div>
            </div>
          </div>
          <div class="flex flex-wrap items-center justify-between gap-2 bg-sand/40 border border-lokaleborder/60 rounded-xl px-3 py-2 text-[11px]">
            <div class="space-y-0.5">
              <div><span class="text-muted">HP Pemandu:</span> <strong class="font-mono text-ink">${escapeHtml(formatDisplayPhone(guideDigits))}</strong></div>
              <div><span class="text-muted">HP Pemesan:</span> <strong class="font-mono text-ink">${escapeHtml(formatDisplayPhone(touristDigits))}</strong></div>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
              ${guideDigits ? `<a href="https://wa.me/${guideDigits}?text=${waGuideText}" target="_blank" rel="noopener noreferrer" class="px-2.5 py-1 rounded-full bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white border border-emerald-200 font-semibold transition">WA Pemandu</a>` : ''}
              ${guideDigits ? `<a href="tel:+${guideDigits}" class="px-2.5 py-1 rounded-full bg-white hover:bg-slate-100 text-ink border border-slate-200 font-semibold transition">Telp Pemandu</a>` : ''}
              ${guideDigits ? `<button type="button" data-copy-phone="+${guideDigits}" class="px-2.5 py-1 rounded-full bg-white hover:bg-slate-100 text-ink border border-slate-200 font-semibold transition">Salin HP Pemandu</button>` : ''}
              ${canOperatorManage && touristDigits ? `<a href="https://wa.me/${touristDigits}?text=${waTouristText}" target="_blank" rel="noopener noreferrer" class="px-2.5 py-1 rounded-full bg-sky-50 hover:bg-sky-600 text-sky-700 hover:text-white border border-sky-200 font-semibold transition">WA Pemesan</a>` : ''}
            </div>
          </div>
          ${b.notes ? `<div class="text-[11px] text-muted bg-sand/50 rounded-xl px-2.5 py-1.5">Catatan: ${escapeHtml(b.notes)}</div>` : ''}
          ${b.cancel_reason ? `<div class="text-[11px] text-rose-700 bg-rose-50 rounded-xl px-2.5 py-1.5">Alasan batal: ${escapeHtml(b.cancel_reason)}</div>` : ''}
          ${contactsPanel}
          ${reviewBlock}
          ${actionButtons ? `<div class="pt-2 border-t border-slate-100 flex flex-wrap justify-end gap-2">${actionButtons}</div>` : ''}
        </div>`;
      }).join('');

      bindMyBookingsActions(listEl);
    } catch (err) {
      listEl.innerHTML = `<div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">${escapeHtml(err.message || 'Gagal memuat daftar reservasi.')}</div>`;
    }
  }

  async function loadBookingContactsThread(bookingId, container) {
    const threadEl = container.querySelector(`[data-contacts-thread="${bookingId}"]`);
    if (!threadEl) return;
    threadEl.innerHTML = '<div class="text-muted">Memuat riwayat pesan kontak...</div>';

    const user = global.SumutGoAuth?.getUser() || {};
    const isAdmin = String(user.role || '').toLowerCase() === 'admin';

    try {
      const resp = await global.SumutGoApi.bookingContacts.list({ booking_id: bookingId, page: 1, per_page: 25 });
      const rows = Array.isArray(resp?.data) ? resp.data : [];
      const countBadge = container.querySelector(`[data-contact-count="${bookingId}"]`);
      if (countBadge) countBadge.textContent = String(rows.length);

      if (!rows.length) {
        threadEl.innerHTML = '<div class="text-muted italic">Belum ada pesan komunikasi untuk booking ini. Kirim pesan di bawah untuk menghubungi pemandu atau pemboking.</div>';
        return;
      }

      threadEl.innerHTML = rows.map((c) => {
        const badge = contactStatusBadgeMeta(c.confirmation_status);
        const phone = formatDisplayPhone(c.contact_phone);
        const confirmBtn = isAdmin && c.confirmation_status !== 'confirmed'
          ? `<button type="button" data-inline-confirm-contact="${c.contact_id}" data-booking-id="${bookingId}" class="px-2.5 py-0.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-semibold transition">Konfirmasi</button>`
          : '';
        return `<div class="bg-white border border-lokaleborder rounded-xl p-2.5 space-y-1">
          <div class="flex flex-wrap items-center justify-between gap-1.5">
            <div class="flex flex-wrap items-center gap-1.5">
              <span class="px-2 py-0.5 rounded-full bg-lokalesoft text-lokale font-bold uppercase text-[9px]">${escapeHtml(c.sender_role)}</span>
              <span class="px-2 py-0.5 rounded-full bg-slate-100 text-ink font-semibold uppercase text-[9px]">${escapeHtml(c.preferred_channel)}</span>
              <span class="font-mono text-[10px] text-muted">${escapeHtml(phone)}</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="px-2 py-0.5 rounded-full border text-[9px] font-bold ${badge.cls}">${badge.label}</span>
              ${confirmBtn}
            </div>
          </div>
          <div class="text-ink">${escapeHtml(c.inquiry_message)}</div>
          ${c.admin_note ? `<div class="text-[10px] text-emerald-800 bg-emerald-50/70 rounded-lg px-2 py-1">Catatan Admin: ${escapeHtml(c.admin_note)}</div>` : ''}
        </div>`;
      }).join('');

      threadEl.querySelectorAll('[data-inline-confirm-contact]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const contactId = Number(btn.dataset.inlineConfirmContact);
          const bId = Number(btn.dataset.bookingId);
          btn.disabled = true;
          try {
            await global.SumutGoApi.bookingContacts.confirm(contactId, {
              confirmation_status: 'confirmed',
              admin_note: 'Dikonfirmasi Admin melalui panel booking.',
            });
            global.SumutGoAuth?.showToast('Kontak booking berhasil dikonfirmasi.', 'success');
            await loadBookingContactsThread(bId, container);
          } catch (err) {
            global.SumutGoAuth?.showToast(err.message || 'Gagal mengonfirmasi kontak.', 'error');
            btn.disabled = false;
          }
        });
      });
    } catch (err) {
      threadEl.innerHTML = `<div class="text-rose-600">${escapeHtml(err.message || 'Gagal memuat riwayat kontak.')}</div>`;
    }
  }

  function bindMyBookingsActions(container) {
    container.querySelectorAll('[data-cancel-booking]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const bookingId = Number(btn.dataset.cancelBooking);
        const version = Number(btn.dataset.version);
        const reason = global.prompt('Masukkan alasan pembatalan (opsional):', 'Perubahan jadwal perjalanan');
        if (reason === null) return;

        btn.disabled = true;
        try {
          await global.SumutGoApi.bookings.updateStatus(bookingId, {
            status: 'cancelled',
            version,
            cancel_reason: reason.trim() || 'Dibatalkan oleh pengguna',
          });
          global.SumutGoAuth.showToast('Reservasi berhasil dibatalkan.', 'info');
          await Promise.all([loadMyBookings(), syncCatalogFromApiV1()]);
        } catch (err) {
          global.SumutGoAuth.showToast(err.message || 'Gagal membatalkan reservasi.', 'error');
          btn.disabled = false;
        }
      });
    });

    container.querySelectorAll('[data-status-booking]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const bookingId = Number(btn.dataset.statusBooking);
        const nextStatus = btn.dataset.nextStatus;
        const version = Number(btn.dataset.version);
        btn.disabled = true;
        try {
          await global.SumutGoApi.bookings.updateStatus(bookingId, {
            status: nextStatus,
            version,
          });
          global.SumutGoAuth.showToast(`Status booking diperbarui menjadi ${nextStatus}.`, 'success');
          await Promise.all([loadMyBookings(), syncCatalogFromApiV1()]);
        } catch (err) {
          global.SumutGoAuth.showToast(err.message || 'Gagal memperbarui status.', 'error');
          btn.disabled = false;
        }
      });
    });

    container.querySelectorAll('[data-toggle-contacts]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const bookingId = Number(btn.dataset.toggleContacts);
        const panel = container.querySelector(`[data-contacts-panel="${bookingId}"]`);
        if (!panel) return;
        const willOpen = panel.classList.contains('hidden');
        panel.classList.toggle('hidden');
        if (willOpen) {
          loadBookingContactsThread(bookingId, container);
        }
      });
    });

    container.querySelectorAll('[data-contact-form]').forEach((form) => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const bookingId = Number(form.dataset.contactForm);
        const channel = form.querySelector('[name="preferred_channel"]')?.value || 'whatsapp';
        const phone = (form.querySelector('[name="contact_phone"]')?.value || '').trim();
        const msgInput = form.querySelector('[name="inquiry_message"]');
        const msg = (msgInput?.value || '').trim();
        const submitBtn = form.querySelector('button[type="submit"]');

        if (submitBtn) submitBtn.disabled = true;
        try {
          await global.SumutGoApi.bookingContacts.create({
            booking_id: bookingId,
            preferred_channel: channel,
            contact_phone: phone,
            inquiry_message: msg,
          });
          if (msgInput) msgInput.value = '';
          global.SumutGoAuth.showToast('Pesan kontak berhasil dikirim.', 'success');
          await loadBookingContactsThread(bookingId, container);
        } catch (err) {
          global.SumutGoAuth.showToast(err.message || 'Gagal mengirim pesan kontak.', 'error');
        } finally {
          if (submitBtn) submitBtn.disabled = false;
        }
      });
    });

    container.querySelectorAll('[data-toggle-review]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const form = container.querySelector(`[data-review-form="${btn.dataset.toggleReview}"]`);
        if (form) form.classList.toggle('hidden');
      });
    });

    container.querySelectorAll('[data-review-form]').forEach((form) => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const bookingId = Number(form.dataset.reviewForm);
        const rating = Number(form.querySelector('[name="rating"]')?.value || 5);
        const reviewText = (form.querySelector('[name="review_text"]')?.value || '').trim();
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        try {
          await global.SumutGoApi.reviews.create({
            booking_id: bookingId,
            rating,
            review_text: reviewText,
          });
          global.SumutGoAuth.showToast('Terima kasih! Ulasan Anda berhasil disimpan.', 'success');
          await Promise.all([loadMyBookings(), syncCatalogFromApiV1()]);
        } catch (err) {
          global.SumutGoAuth.showToast(err.message || 'Gagal mengirim ulasan.', 'error');
          if (submitBtn) submitBtn.disabled = false;
        }
      });
    });
  }

  // =========================================================================
  // MODAL COMPARE
  // =========================================================================
  function openCompareModal() {
    const authed = isLoggedIn();
    const items = state.compareList.map((id) => findGuide(id)).filter(Boolean);
    const colClass = items.length === 1 ? 'md:grid-cols-1' : items.length === 2 ? 'md:grid-cols-2' : 'md:grid-cols-3';
    const fullNotice = items.length >= 3
      ? '<div class="mb-3 px-3.5 py-2 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-[11px] font-semibold">Slot pembanding penuh (maksimal 3/3 pemandu). Hapus salah satu pemandu terlebih dahulu sebelum menambahkan pemandu lain.</div>'
      : '';

    document.getElementById('compareModalBody').innerHTML = items.length
      ? `${fullNotice}<div class="grid grid-cols-1 ${colClass} gap-4">` + items.map((g) => {
          const dutyMeta = getGuideDutyMeta(g);
          const dutyUntilHtml = dutyMeta
            ? (dutyMeta.hasDate
              ? `<div class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-amber-700 text-[10px] font-semibold">
                  <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                  ${escapeHtml(dutyMeta.fullText)}
                </div>`
              : `<div class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-muted text-[10px] font-medium">
                  ${escapeHtml(dutyMeta.fullText)}
                </div>`)
            : '';
          return `
          <div class="bg-lokalesoft/50 border border-lokaleborder rounded-2xl p-4 space-y-1.5 flex flex-col justify-between">
            <div class="space-y-1.5">
              <div class="flex items-center justify-between gap-2">
                <strong class="text-lokale font-mono">${escapeHtml(g.id)}</strong>
                <div class="flex flex-col items-end gap-0.5">
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border ${statusBadgeClass(g.status)}">${escapeHtml(g.status)}</span>
                  ${dutyUntilHtml}
                </div>
              </div>
              <h4 class="font-bold text-base text-ink">${escapeHtml(g.name)}</h4>
              <div class="text-muted">${escapeHtml(g.specialization)}</div>
              <div>Rating: <strong class="text-amber-500">★ ${Number(g.rating).toFixed(1)}</strong> (${g.reviews})</div>
              <div>Tarif: <strong class="text-lokale font-mono">${formatIdr(g.price)}</strong>/hari</div>
              <div>Bahasa: ${escapeHtml(getLangNames(g).join(', '))}</div>
              <div>Wisata: ${escapeHtml(g.destName)}</div>
              <div class="relative pt-1.5 border-t border-lokaleborder/60">
                <div class="space-y-1 ${authed ? '' : 'blur-locked'}" ${authed ? '' : 'aria-hidden="true"'}>
                  <div>Kuliner: ${escapeHtml(g.culinaryName)}</div>
                  <div>Hotel: ${escapeHtml(g.hotelName)}</div>
                  <div>Armada: ${escapeHtml(g.transportName)}</div>
                  <div>Kontak: ${escapeHtml(g.phone ? `+${g.phone}` : '-')}</div>
                </div>
                ${authed ? '' : '<div class="mt-1 text-[10px] font-bold text-lokale">🔒 Masuk untuk membuka rincian mitra &amp; kontak</div>'}
              </div>
            </div>
            <div class="mt-3 flex flex-col gap-1.5">
              <button type="button" data-compare-detail="${escapeHtml(g.id)}" class="w-full py-1.5 rounded-full bg-lokale text-white font-semibold text-[11px]">Lihat Detail &amp; Booking</button>
              <button type="button" data-compare-remove="${escapeHtml(g.id)}" class="w-full py-1.5 rounded-full bg-white border border-lokaleborder text-rose-600 hover:bg-rose-50 font-semibold text-[11px] transition">✕ Hapus dari Pembanding</button>
            </div>
          </div>`;
        }).join('') + `</div>`
      : '<p class="text-center py-6 text-muted">Klik tombol + Banding pada kartu pemandu untuk membandingkan.</p>';

    document.querySelectorAll('[data-compare-detail]').forEach((b) => {
      b.addEventListener('click', () => {
        document.getElementById('compareModal')?.classList.add('hidden');
        openGuideModal(b.dataset.compareDetail, 'detail');
      });
    });

    document.querySelectorAll('[data-compare-remove]').forEach((b) => {
      b.addEventListener('click', () => {
        const idx = state.compareList.indexOf(b.dataset.compareRemove);
        if (idx >= 0) state.compareList.splice(idx, 1);
        saveCompareList();
        openCompareModal();
        renderDirectory();
      });
    });

    document.getElementById('compareModal')?.classList.remove('hidden');
  }

  // =========================================================================
  // NAVIGASI TAB & MODALS
  // =========================================================================
  function switchTab(t) {
    state.activeTab = t;
    ['directory', 'map', 'planner', 'analytics'].forEach((k) => {
      document.getElementById('view-' + k)?.classList.toggle('hidden', k !== t);
    });
    document.querySelectorAll('.main-tab-btn').forEach((b) => {
      b.className = b.dataset.mainTab === t
        ? 'main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition bg-lokale text-white shadow-sm'
        : 'main-tab-btn px-4 py-2 rounded-full text-xs font-semibold transition text-ink/80 hover:text-lokale';
    });
    document.querySelectorAll('.main-tab-mobile').forEach((b) => {
      b.className = b.dataset.mainTab === t
        ? 'main-tab-mobile px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-lokale text-white transition'
        : 'main-tab-mobile px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap bg-lokalesoft text-lokale transition';
    });
    if (t === 'map') initOrUpdateMap();
    if (t === 'planner') {
      updatePlanner();
      ensureTransportCatalogLoaded();
      if (!state.transport.routeSyncedOnce) {
        state.transport.routeSyncedOnce = true;
        syncRouteFormWithGuide();
      }
    }
    if (t === 'analytics') renderAnalytics();
  }

  // =========================================================================
  // MODAL KONFIRMASI ADMIN (USERS, KONTAK BOOKING & BOOKINGS)
  // =========================================================================
  const adminConfirmState = {
    tab: 'users',
    statusFilter: 'ALL',
  };

  function setAdminConfirmFeedback(msg, isError = false) {
    const el = document.getElementById('adminConfirmFeedback');
    if (!el) return;
    if (!msg) {
      el.textContent = '';
      el.classList.add('hidden');
      return;
    }
    el.className = isError
      ? 'p-3 rounded-2xl border border-rose-200 bg-rose-50 text-rose-700 text-xs font-medium'
      : 'p-3 rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-700 text-xs font-medium';
    el.textContent = msg;
    el.classList.remove('hidden');
  }

  function populateAdminConfirmFilter(tab) {
    const sel = document.getElementById('adminConfirmStatusFilter');
    if (!sel) return;
    const opts = tab === 'users'
      ? [['ALL', 'Semua Pendaftar'], ['false', 'Belum Terkonfirmasi'], ['true', 'Sudah Terkonfirmasi']]
      : tab === 'contacts'
        ? [['ALL', 'Semua Kontak'], ['unverified', 'Belum Diverifikasi'], ['contacted', 'Sedang Dihubungi'], ['confirmed', 'Terkonfirmasi']]
        : [['ALL', 'Semua Booking'], ['pending', 'Pending'], ['confirmed', 'Confirmed'], ['ongoing', 'Ongoing'], ['completed', 'Completed'], ['cancelled', 'Cancelled']];
    sel.innerHTML = opts.map(([val, lbl]) => `<option value="${val}">${lbl}</option>`).join('');
    adminConfirmState.statusFilter = 'ALL';
  }

  async function openAdminConfirmModal(defaultTab = 'users') {
    const user = global.SumutGoAuth?.getUser() || {};
    if (!isLoggedIn() || String(user.role || '').toLowerCase() !== 'admin') {
      global.SumutGoAuth?.showToast('Hanya Admin yang dapat mengakses panel konfirmasi ini.', 'error');
      return;
    }
    const modal = document.getElementById('adminConfirmModal');
    if (modal) modal.classList.remove('hidden');
    await switchAdminConfirmTab(defaultTab);
  }

  async function switchAdminConfirmTab(tab) {
    adminConfirmState.tab = ['users', 'contacts', 'bookings'].includes(tab) ? tab : 'users';
    document.querySelectorAll('[data-admin-tab]').forEach((btn) => {
      btn.className = btn.dataset.adminTab === adminConfirmState.tab
        ? 'px-3.5 py-1.5 rounded-full text-xs font-bold bg-lokale text-white transition'
        : 'px-3.5 py-1.5 rounded-full text-xs font-semibold text-lokale hover:bg-white/80 transition';
    });
    populateAdminConfirmFilter(adminConfirmState.tab);
    setAdminConfirmFeedback('');
    await loadAdminConfirmData();
  }

  async function loadAdminConfirmData() {
    const bodyEl = document.getElementById('adminConfirmBody');
    const summaryEl = document.getElementById('adminConfirmSummary');
    if (!bodyEl) return;

    bodyEl.innerHTML = '<div class="py-10 text-center text-muted">Memuat data dari Supabase via API v1...</div>';
    const q = (document.getElementById('adminConfirmSearch')?.value || '').trim();
    const filter = adminConfirmState.statusFilter;

    try {
      if (adminConfirmState.tab === 'users') {
        const resp = await global.SumutGoApi.users.list({
          page: 1,
          per_page: 50,
          is_verified: filter !== 'ALL' ? filter : '',
          q,
        });
        const rows = Array.isArray(resp?.data) ? resp.data : [];
        if (summaryEl) summaryEl.textContent = `${rows.length} pendaftar ditemukan`;
        if (!rows.length) {
          bodyEl.innerHTML = '<div class="py-8 text-center bg-lokalesoft/30 rounded-2xl text-muted">Tidak ada data pendaftar pada filter ini.</div>';
          return;
        }

        bodyEl.innerHTML = rows.map((u) => {
          const digits = toPhoneDigits(u.phone_number);
          const badge = u.is_verified
            ? '<span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">✓ Terkonfirmasi</span>'
            : '<span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-bold">⏳ Belum Verifikasi</span>';
          const nextVerified = u.is_verified ? 'false' : 'true';
          const actionLabel = u.is_verified ? 'Batalkan Verifikasi' : 'Konfirmasi Pendaftar & HP';
          const actionCls = u.is_verified
            ? 'bg-slate-100 hover:bg-slate-200 text-ink'
            : 'bg-emerald-600 hover:bg-emerald-700 text-white';

          return `<div class="bg-white border border-lokaleborder rounded-2xl p-4 space-y-2 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="flex flex-wrap items-center gap-2">
                <strong class="text-sm text-ink">${escapeHtml(u.full_name)}</strong>
                <span class="px-2 py-0.5 rounded-full bg-lokalesoft text-lokale text-[10px] font-bold uppercase">${escapeHtml(u.role)}</span>
                ${badge}
              </div>
              <span class="font-mono text-[11px] text-muted">ID #${escapeHtml(u.user_id)} • ${escapeHtml(u.booking_count || 0)} booking</span>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 text-[11px]">
              <div>Email: <strong class="text-ink">${escapeHtml(u.email)}</strong> • HP: <strong class="font-mono text-lokale">${escapeHtml(formatDisplayPhone(digits))}</strong></div>
              <div class="flex flex-wrap items-center gap-1.5">
                ${digits ? `<a href="https://wa.me/${digits}" target="_blank" rel="noopener noreferrer" class="px-2.5 py-1 rounded-full bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white border border-emerald-200 font-semibold transition">Chat WA</a>` : ''}
                ${digits ? `<button type="button" data-copy-phone="+${digits}" class="px-2.5 py-1 rounded-full bg-slate-100 hover:bg-slate-200 text-ink font-semibold transition">Salin HP</button>` : ''}
              </div>
            </div>
            ${u.verification_note ? `<div class="text-[11px] text-muted bg-lokalesoft/40 rounded-xl px-3 py-1.5">Catatan Admin: ${escapeHtml(u.verification_note)}</div>` : ''}
            <div class="flex flex-wrap items-center gap-2 pt-1">
              <input type="text" data-admin-user-note="${u.user_id}" value="${escapeHtml(u.verification_note || '')}" placeholder="Catatan verifikasi admin (opsional)..." class="flex-1 min-w-[200px] bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-1.5 text-xs" />
              <button type="button" data-admin-verify-user="${u.user_id}" data-next-verified="${nextVerified}" class="px-3.5 py-1.5 rounded-full ${actionCls} text-[11px] font-semibold transition">${actionLabel}</button>
            </div>
          </div>`;
        }).join('');

        bodyEl.querySelectorAll('[data-admin-verify-user]').forEach((btn) => {
          btn.addEventListener('click', async () => {
            const userId = Number(btn.dataset.adminVerifyUser);
            const isVerified = btn.dataset.nextVerified === 'true';
            const note = bodyEl.querySelector(`[data-admin-user-note="${userId}"]`)?.value || '';
            btn.disabled = true;
            try {
              const resp = await global.SumutGoApi.users.verify(userId, {
                is_verified: isVerified,
                verification_note: note,
              });
              setAdminConfirmFeedback(resp.message || 'Status verifikasi pengguna diperbarui.', false);
              global.SumutGoAuth?.showToast(resp.message || 'Status pendaftar diperbarui.', 'success');
              await loadAdminConfirmData();
            } catch (err) {
              setAdminConfirmFeedback(err.message || 'Gagal memverifikasi pengguna.', true);
              btn.disabled = false;
            }
          });
        });
      } else if (adminConfirmState.tab === 'contacts') {
        const resp = await global.SumutGoApi.bookingContacts.list({
          page: 1,
          per_page: 50,
          confirmation_status: filter !== 'ALL' ? filter : '',
          q,
        });
        const rows = Array.isArray(resp?.data) ? resp.data : [];
        if (summaryEl) summaryEl.textContent = `${rows.length} pesan kontak ditampilkan`;
        if (!rows.length) {
          bodyEl.innerHTML = '<div class="py-8 text-center bg-lokalesoft/30 rounded-2xl text-muted">Belum ada data kontak komunikasi booking pada filter ini.</div>';
          return;
        }

        bodyEl.innerHTML = rows.map((c) => {
          const digits = toPhoneDigits(c.contact_phone);
          const badge = contactStatusBadgeMeta(c.confirmation_status);
          return `<div class="bg-white border border-lokaleborder rounded-2xl p-4 space-y-2 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono font-bold text-lokale bg-lokalesoft px-2.5 py-0.5 rounded-full">${escapeHtml(c.booking_code)}</span>
                <span class="px-2 py-0.5 rounded-full border text-[10px] font-bold ${badge.cls}">${badge.label}</span>
                <span class="text-[10px] font-semibold uppercase text-muted">${escapeHtml(c.sender_role)} via ${escapeHtml(c.preferred_channel)}</span>
              </div>
              <span class="font-mono text-[11px] text-muted">Kontak #${escapeHtml(c.contact_id)}</span>
            </div>
            <div class="text-[11px] text-muted">
              Pemesan: <strong class="text-ink">${escapeHtml(c.tourist_name)}</strong> (${escapeHtml(formatDisplayPhone(c.tourist_phone))}) ↔
              Pemandu: <strong class="text-ink">${escapeHtml(c.guide_name)}</strong> (${escapeHtml(formatDisplayPhone(c.guide_phone))})
            </div>
            <div class="p-2.5 rounded-xl bg-lokalesoft/40 border border-lokaleborder text-xs text-ink">${escapeHtml(c.inquiry_message)}</div>
            <div class="flex flex-wrap items-center gap-2 pt-1">
              ${digits ? `<a href="https://wa.me/${digits}" target="_blank" rel="noopener noreferrer" class="px-2.5 py-1 rounded-full bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white border border-emerald-200 font-semibold text-[11px] transition">WA Pengirim</a>` : ''}
              ${digits ? `<button type="button" data-copy-phone="+${digits}" class="px-2.5 py-1 rounded-full bg-slate-100 hover:bg-slate-200 text-ink font-semibold text-[11px] transition">Salin HP</button>` : ''}
              <input type="text" data-admin-contact-note="${c.contact_id}" value="${escapeHtml(c.admin_note || '')}" placeholder="Catatan tindak lanjut admin..." class="flex-1 min-w-[180px] bg-lokalesoft/40 border border-lokaleborder rounded-xl px-3 py-1.5 text-xs" />
              <button type="button" data-admin-confirm-contact="${c.contact_id}" data-next-status="contacted" class="px-3 py-1.5 rounded-full bg-amber-50 hover:bg-amber-600 text-amber-800 hover:text-white border border-amber-200 text-[11px] font-semibold transition">Tandai Dihubungi</button>
              <button type="button" data-admin-confirm-contact="${c.contact_id}" data-next-status="confirmed" class="px-3.5 py-1.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-semibold transition">Konfirmasi</button>
            </div>
          </div>`;
        }).join('');

        bodyEl.querySelectorAll('[data-admin-confirm-contact]').forEach((btn) => {
          btn.addEventListener('click', async () => {
            const contactId = Number(btn.dataset.adminConfirmContact);
            const nextStatus = btn.dataset.nextStatus;
            const note = bodyEl.querySelector(`[data-admin-contact-note="${contactId}"]`)?.value || '';
            btn.disabled = true;
            try {
              const resp = await global.SumutGoApi.bookingContacts.confirm(contactId, {
                confirmation_status: nextStatus,
                admin_note: note,
              });
              setAdminConfirmFeedback(resp.message || 'Status kontak booking diperbarui.', false);
              global.SumutGoAuth?.showToast(resp.message || 'Status kontak diperbarui.', 'success');
              await loadAdminConfirmData();
            } catch (err) {
              setAdminConfirmFeedback(err.message || 'Gagal memperbarui status kontak.', true);
              btn.disabled = false;
            }
          });
        });
      } else {
        const resp = await global.SumutGoApi.bookings.list({
          page: 1,
          per_page: 50,
          status: filter !== 'ALL' ? filter : '',
        });
        let rows = Array.isArray(resp?.data) ? resp.data : [];
        if (q) {
          const ql = q.toLowerCase();
          rows = rows.filter((b) => `${b.booking_code} ${b.tourist_name} ${b.guide_name} ${b.tourist_phone || ''} ${b.guide_phone || ''}`.toLowerCase().includes(ql));
        }
        if (summaryEl) summaryEl.textContent = `${rows.length} booking ditampilkan`;
        if (!rows.length) {
          bodyEl.innerHTML = '<div class="py-8 text-center bg-lokalesoft/30 rounded-2xl text-muted">Tidak ada booking pada filter ini.</div>';
          return;
        }

        bodyEl.innerHTML = rows.map((b) => {
          let btns = '';
          if (b.status === 'pending') {
            btns += `<button type="button" data-admin-booking-status="${b.booking_id}" data-version="${b.version}" data-next="confirmed" class="px-3 py-1 rounded-full bg-lokale hover:bg-lokaledark text-white font-semibold">Konfirmasi Booking</button>`;
            btns += `<button type="button" data-admin-booking-status="${b.booking_id}" data-version="${b.version}" data-next="cancelled" class="px-3 py-1 rounded-full bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white border border-rose-200 font-semibold">Batalkan</button>`;
          } else if (b.status === 'confirmed') {
            btns += `<button type="button" data-admin-booking-status="${b.booking_id}" data-version="${b.version}" data-next="ongoing" class="px-3 py-1 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Mulai Tur</button>`;
            btns += `<button type="button" data-admin-booking-status="${b.booking_id}" data-version="${b.version}" data-next="cancelled" class="px-3 py-1 rounded-full bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white border border-rose-200 font-semibold">Batalkan</button>`;
          } else if (b.status === 'ongoing') {
            btns += `<button type="button" data-admin-booking-status="${b.booking_id}" data-version="${b.version}" data-next="completed" class="px-3 py-1 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">Selesaikan Tur</button>`;
          }
          return `<div class="bg-white border border-lokaleborder rounded-2xl p-4 space-y-2 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono font-bold text-lokale bg-lokalesoft px-2.5 py-0.5 rounded-full">${escapeHtml(b.booking_code)}</span>
                <span class="px-2 py-0.5 rounded-full border text-[10px] font-bold uppercase ${bookingStatusBadgeClass(b.status)}">${escapeHtml(b.status)}</span>
                <span class="text-[10px] font-mono text-muted">v${escapeHtml(b.version)}</span>
              </div>
              <strong class="font-mono text-lokale">${formatIdr(b.total_price_idr)}</strong>
            </div>
            <div class="text-[11px] text-muted">
              Pemesan: <strong class="text-ink">${escapeHtml(b.tourist_name)}</strong> (${escapeHtml(formatDisplayPhone(b.tourist_phone))}) •
              Pemandu: <strong class="text-ink">${escapeHtml(b.guide_name)}</strong> (${escapeHtml(formatDisplayPhone(b.guide_phone))})
            </div>
            <div class="text-[11px] text-ink">${formatDateId(b.start_date)} – ${formatDateId(b.end_date)} (${escapeHtml(b.duration_days)} Hari • ${escapeHtml(b.number_of_guests)} Tamu)</div>
            ${btns ? `<div class="flex flex-wrap justify-end gap-2 pt-1">${btns}</div>` : ''}
          </div>`;
        }).join('');

        bodyEl.querySelectorAll('[data-admin-booking-status]').forEach((btn) => {
          btn.addEventListener('click', async () => {
            const bookingId = Number(btn.dataset.adminBookingStatus);
            const version = Number(btn.dataset.version);
            const nextStatus = btn.dataset.next;
            let cancelReason;
            if (nextStatus === 'cancelled') {
              const pr = global.prompt('Alasan pembatalan (opsional):', 'Dibatalkan oleh Admin');
              if (pr === null) return;
              cancelReason = pr.trim() || 'Dibatalkan oleh Admin';
            }
            btn.disabled = true;
            try {
              const payload = { status: nextStatus, version };
              if (cancelReason !== undefined) payload.cancel_reason = cancelReason;
              const resp = await global.SumutGoApi.bookings.updateStatus(bookingId, payload);
              setAdminConfirmFeedback(resp.message || `Booking diperbarui menjadi ${nextStatus}.`, false);
              global.SumutGoAuth?.showToast(`Status booking diperbarui menjadi ${nextStatus}.`, 'success');
              await Promise.all([loadAdminConfirmData(), syncCatalogFromApiV1()]);
            } catch (err) {
              setAdminConfirmFeedback(err.message || 'Gagal memperbarui status booking.', true);
              btn.disabled = false;
            }
          });
        });
      }
    } catch (err) {
      bodyEl.innerHTML = `<div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700">${escapeHtml(err.message || 'Gagal memuat panel konfirmasi admin.')}</div>`;
    }
  }

  function closeAllModals() {
    document.getElementById('guideModal')?.classList.add('hidden');
    document.getElementById('compareModal')?.classList.add('hidden');
    document.getElementById('myBookingsModal')?.classList.add('hidden');
    document.getElementById('adminConfirmModal')?.classList.add('hidden');
    global.SumutGoAuth?.closeAuthModal(true);
  }

  function onAuthStateChanged(_user, authed, meta = {}) {
    // 0. Sinkronkan daftar pembanding dengan akun yang sedang login/logout
    if (authed) {
      loadCompareList(); // Muat daftar pembanding milik akun yang baru login
    } else {
      state.compareList = []; // Bersihkan saat logout (kunci di-generate dari user_id, tidak bocor ke akun lain)
    }

    // 1. Segera update Direktori (menghilangkan atau mengembalikan Blur Gate secara instan)
    renderDirectory();

    // 2. Update Map Popup & Compare Modal jika sedang aktif
    if (state.activeTab === 'map') {
      renderMap({ preserveListScroll: true });
    }
    const compareModal = document.getElementById('compareModal');
    if (compareModal && !compareModal.classList.contains('hidden')) {
      openCompareModal();
    }

    // 3. Jika Modal Detail Pemandu sedang terbuka, langsung hilangkan/terapkan blur!
    const guideModal = document.getElementById('guideModal');
    if (guideModal && !guideModal.classList.contains('hidden')) {
      applyGuideModalBlurState();
    }

    // 4. Jika logout atau bukan admin, tutup modal khusus user terautentikasi
    if (!authed) {
      document.getElementById('compareModal')?.classList.add('hidden');
      document.getElementById('myBookingsModal')?.classList.add('hidden');
      document.getElementById('adminConfirmModal')?.classList.add('hidden');
    } else if (String((_user && _user.role) || '').toLowerCase() !== 'admin') {
      document.getElementById('adminConfirmModal')?.classList.add('hidden');
    }

    // 5. Jalankan pending action setelah login/register (misal user tadi klik tombol Booking saat guest)
    const pending = meta && meta.pendingAction;
    if (authed && pending && pending.type === 'guide-modal' && pending.guideId) {
      openGuideModal(pending.guideId, pending.intent || 'book', pending.prefill || null);
    }
  }

  async function syncCatalogFromApiV1() {
    try {
      const [guidesResp, masterResp] = await Promise.all([
        global.SumutGoApi.guides.list({ page: 1, per_page: 500 }),
        global.SumutGoApi.master.all(),
      ]);
      if (masterResp?.data && typeof masterResp.data === 'object') {
        MASTER_ECOSYSTEM = masterResp.data;
      }
      if (Array.isArray(guidesResp?.data) && guidesResp.data.length) {
        TOUR_GUIDES = guidesResp.data;
        populateSelectOptionsFromGuides();
        renderDirectory();
        updatePlanner();
        if (state.activeTab === 'map') renderMap({ preserveListScroll: true });
        if (state.activeTab === 'analytics' || chartInstances.statuses) updateAnalyticsCharts();

        const guideModalEl = document.getElementById('guideModal');
        if (guideModalEl && !guideModalEl.classList.contains('hidden') && state.activeGuideId) {
          const freshActive = findGuide(state.activeGuideId);
          if (freshActive) {
            state.activeGuideDetail = Object.assign({}, state.activeGuideDetail || {}, freshActive);
            updateModalGuideStatus(state.activeGuideDetail);
          }
        }
        const compareModalEl = document.getElementById('compareModal');
        if (compareModalEl && !compareModalEl.classList.contains('hidden')) {
          openCompareModal();
        }
      }
    } catch (_) {
      // Tetap gunakan data SSR bila sinkronisasi background gagal
    }
  }

  function populateSelectOptionsFromGuides() {
    const dists = Array.from(new Set(TOUR_GUIDES.map((g) => g.district))).sort();
    const filterDistrictEl = document.getElementById('filterDistrict');
    if (filterDistrictEl) {
      const prev = filterDistrictEl.value;
      filterDistrictEl.innerHTML = `<option value="ALL">Semua Kecamatan (${dists.length})</option>` +
        dists.map((d) => `<option value="${escapeHtml(d)}">${escapeHtml(formatDistrict(d))}</option>`).join('');
      if (prev && Array.from(filterDistrictEl.options).some((o) => o.value === prev)) {
        filterDistrictEl.value = prev;
      }
    }

    const planSelEl = document.getElementById('planGuideSelect');
    if (planSelEl) {
      const prev = planSelEl.value;
      planSelEl.innerHTML = TOUR_GUIDES.map((g) => `<option value="${escapeHtml(g.id)}">${escapeHtml(g.id)} • ${escapeHtml(g.name)} (${formatIdr(g.price)})</option>`).join('');
      if (prev && Array.from(planSelEl.options).some((o) => o.value === prev)) {
        planSelEl.value = prev;
      }
    }
  }

  function bindEvents() {
    document.getElementById('brandHomeLink')?.addEventListener('click', () => switchTab('directory'));
    document.querySelectorAll('[data-main-tab]').forEach((b) => {
      b.addEventListener('click', () => switchTab(b.dataset.mainTab));
    });
    document.getElementById('btnHeroSwitchMap')?.addEventListener('click', () => switchTab('map'));
    document.getElementById('btnHeroSwitchPlanner')?.addEventListener('click', () => switchTab('planner'));
    document.getElementById('btnPartnershipOpenEco')?.addEventListener('click', () => switchTab('analytics'));
    document.getElementById('btnViewCards')?.addEventListener('click', () => {
      state.viewMode = 'cards';
      renderDirectory();
    });
    document.getElementById('btnViewTable')?.addEventListener('click', () => {
      state.viewMode = 'table';
      renderDirectory();
    });

    const filterBindings = [
      ['filterSearch', 'search', 'input'],
      ['filterSpec', 'spec', 'change'],
      ['filterStatus', 'status', 'change'],
      ['filterLang', 'lang', 'change'],
      ['filterDistrict', 'district', 'change'],
      ['filterSort', 'sort', 'change'],
    ];
    filterBindings.forEach(([elId, key, ev]) => {
      const el = document.getElementById(elId);
      if (!el) return;
      el.addEventListener(ev, (e) => {
        state[key] = e.target.value;
        renderDirectory();
      });
      if (ev === 'change') {
        el.addEventListener('input', (e) => {
          state[key] = e.target.value;
          renderDirectory();
        });
      }
    });

    document.getElementById('filterMaxPrice')?.addEventListener('input', (e) => {
      state.maxPrice = Number(e.target.value);
      const readout = document.getElementById('priceReadout');
      if (readout) readout.textContent = formatIdr(state.maxPrice);
      renderDirectory();
    });

    document.querySelectorAll('.quick-dest-card').forEach((c) => {
      c.addEventListener('click', () => {
        resetAllFilters();
        state.search = c.dataset.quickDest;
        const searchEl = document.getElementById('filterSearch');
        if (searchEl) searchEl.value = state.search;
        renderDirectory();
        document.getElementById('direktori-pemandu')?.scrollIntoView({ behavior: 'smooth' });
      });
    });

    document.getElementById('btnResetFilters')?.addEventListener('click', resetAllFilters);
    document.getElementById('btnEmptyReset')?.addEventListener('click', resetAllFilters);
    document.getElementById('btnFilterFavorites')?.addEventListener('click', () => {
      state.onlyFavorites = !state.onlyFavorites;
      switchTab('directory');
      renderDirectory();
    });

    document.getElementById('chkMapOnlyAvailable')?.addEventListener('change', () => renderMap());
    document.getElementById('chkShowTransportNodes')?.addEventListener('change', () => renderTransportNodesOnMap());
    document.getElementById('filterTransportNodeType')?.addEventListener('change', () => renderTransportNodesOnMap());
    document.getElementById('btnRetryTransportNodes')?.addEventListener('click', async () => {
      await ensureTransportCatalogLoaded(true);
      renderTransportNodesOnMap();
    });

    ['planGuideSelect', 'planDays', 'planPax', 'planHotelTier', 'planTransportMode'].forEach((id) => {
      document.getElementById(id)?.addEventListener('change', updatePlanner);
    });

    document.getElementById('btnClearAppliedTransport')?.addEventListener('click', () => {
      state.transport.applied = null;
      const modeSel = document.getElementById('planTransportMode');
      if (modeSel && modeSel.value === 'public_api') modeSel.value = 'partner';
      updatePlanner();
    });
    document.getElementById('btnRefreshTransportInit')?.addEventListener('click', () => ensureTransportCatalogLoaded(true));
    document.getElementById('tarifNodeTypeFilter')?.addEventListener('change', renderTransportLocationDropdowns);
    document.getElementById('btnSwapTarifNodes')?.addEventListener('click', () => {
      const aSel = document.getElementById('tarifAsalSelect');
      const tSel = document.getElementById('tarifTujuanSelect');
      const aTxt = document.getElementById('tarifAsalText');
      const tTxt = document.getElementById('tarifTujuanText');
      if (aSel && tSel) {
        const tmp = aSel.value;
        aSel.value = tSel.value;
        tSel.value = tmp;
      }
      if (aTxt && tTxt) {
        const tmp = aTxt.value;
        aTxt.value = tTxt.value;
        tTxt.value = tmp;
      }
    });
    document.getElementById('transportTarifForm')?.addEventListener('submit', handleTarifFormSubmit);
    document.getElementById('btnSyncRouteWithGuide')?.addEventListener('click', () => syncRouteFormWithGuide());
    document.getElementById('btnRouteUseMapRef')?.addEventListener('click', () => {
      const latEl = document.getElementById('routeUserLat');
      const lngEl = document.getElementById('routeUserLng');
      if (latEl) latEl.value = Number(state.refLat).toFixed(6);
      if (lngEl) lngEl.value = Number(state.refLng).toFixed(6);
      global.SumutGoAuth?.showToast(`Koordinat awal disinkronkan dengan ${state.refLabel}.`, 'info');
    });
    document.getElementById('btnRouteUseGps')?.addEventListener('click', () => {
      if (!navigator.geolocation) {
        setTransportPanelFeedback('transportRekomendasiFeedback', 'Browser tidak mendukung geolokasi.', 'error');
        return;
      }
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          const latEl = document.getElementById('routeUserLat');
          const lngEl = document.getElementById('routeUserLng');
          if (latEl) latEl.value = Number(pos.coords.latitude).toFixed(6);
          if (lngEl) lngEl.value = Number(pos.coords.longitude).toFixed(6);
          global.SumutGoAuth?.showToast('Koordinat GPS Anda berhasil diisi.', 'success');
        },
        () => {
          setTransportPanelFeedback('transportRekomendasiFeedback', 'Gagal membaca posisi GPS browser.', 'error');
        },
        { enableHighAccuracy: true, timeout: 10000 }
      );
    });
    document.getElementById('transportRekomendasiForm')?.addEventListener('submit', handleRekomendasiFormSubmit);

    document.getElementById('btnBookFromPlanner')?.addEventListener('click', () => {
      const guideId = document.getElementById('planGuideSelect')?.value;
      const days = Math.max(1, Number(document.getElementById('planDays')?.value || 2));
      const guests = Math.max(1, Number(document.getElementById('planPax')?.value || 2));
      const tMode = document.getElementById('planTransportMode')?.value || 'partner';
      const appliedTrans = state.transport.applied;
      const prefill = { days, guests };
      if (tMode === 'public_api' && appliedTrans) {
        if (appliedTrans.note) prefill.notes = appliedTrans.note;
        if (appliedTrans.pickup) prefill.meetingPoint = appliedTrans.pickup;
      }
      handleStartBooking(guideId, prefill);
    });

    document.getElementById('btnModalCheckTransportRoute')?.addEventListener('click', async () => {
      const g = state.activeGuideDetail || findGuide(state.activeGuideId);
      const box = document.getElementById('modalTransportRouteResult');
      const btn = document.getElementById('btnModalCheckTransportRoute');
      if (!g || !box) return;
      if (!g.lat || !g.lng) {
        box.className = 'p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800';
        box.textContent = 'Koordinat basecamp pemandu tidak tersedia.';
        box.classList.remove('hidden');
        return;
      }
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Memuat Rute...';
      }
      box.className = 'p-2.5 rounded-xl bg-white border border-lokaleborder text-muted';
      box.textContent = 'Memeriksa rekomendasi rute Transport API...';
      box.classList.remove('hidden');

      try {
        const resp = await global.SumutGoApi.transport.rekomendasiWisata({
          user_lat: Number(state.refLat),
          user_lng: Number(state.refLng),
          wisata_lat: Number(g.lat),
          wisata_lng: Number(g.lng),
          nama_wisata: g.destName || g.name,
          trip_type: 'roundtrip',
        }, 'POST');
        const data = resp?.data || {};
        const hasStructuredRec = Boolean(data && typeof data === 'object' && (data.ringkasan_biaya || data.rute_perjalanan || data.metadata));
        const list = hasStructuredRec ? [] : extractTransportArray(data, ['rekomendasi', 'opsi', 'routes', 'rute', 'hasil']);
        const top = hasStructuredRec ? data : (list.length ? list[0] : data);
        const costSummary = top?.ringkasan_biaya || {};
        const meta = top?.metadata || {};
        const routeGroups = top?.rute_perjalanan || {};
        const segPergi = Array.isArray(routeGroups.pergi) ? routeGroups.pergi : (top?.rute_pergi || top?.pergi || []);
        const segPulang = Array.isArray(routeGroups.pulang) ? routeGroups.pulang : (top?.rute_pulang || top?.pulang || []);
        const computedDist = [...segPergi, ...segPulang].reduce((acc, s) => acc + (Number(s?.jarak_km) || 0), 0);
        const cost = Number(costSummary.total_biaya_estimasi ?? top?.total_biaya ?? top?.estimasi_biaya ?? top?.total_tarif ?? top?.tarif ?? 0);
        const costText = costSummary.total_biaya_format || formatIdr(cost);
        const jarak = top?.total_jarak_km ?? top?.jarak_km ?? top?.distance_km ?? (computedDist > 0 ? computedDist : null);
        const nearestHub = meta.stasiun_terdekat_wisata;
        const summary = top?.ringkasan || top?.summary || `Rute PP dari ${state.refLabel} ke ${meta.nama_wisata || g.destName || g.name}`;
        const noteText = `[Transport API] ${summary}${jarak !== null ? ` (~${Number(jarak).toFixed(1)} km)` : ''}, est. ${costText}.`;

        box.className = 'p-2.5 rounded-xl bg-white border border-emerald-200 space-y-1.5';
        box.innerHTML = `<div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <strong class="text-ink block">${escapeHtml(summary)}</strong>
              ${nearestHub?.nama_lokasi ? `<span class="text-[10px] text-muted block">Hub terdekat: ${escapeHtml(nearestHub.nama_lokasi)} (${Number(nearestHub.jarak_ke_objek_wisata_km || 0).toFixed(1)} km dari basecamp)${jarak !== null ? ` • Total ~${Number(jarak).toFixed(1)} km` : ''}</span>` : ''}
            </div>
            <strong class="font-mono text-lokale">${escapeHtml(costText)}</strong>
          </div>
          ${Array.isArray(segPergi) && segPergi.length ? renderRouteSegmentsList(segPergi.slice(0, 2)) : ''}
          <div class="flex justify-end">
            <button type="button" id="btnCopyRouteToNotes" class="px-2.5 py-1 rounded-full bg-lokalesoft hover:bg-lokale text-lokale hover:text-white border border-lokaleborder text-[10px] font-semibold transition">Tempel ke Catatan Booking</button>
          </div>`;
        document.getElementById('btnCopyRouteToNotes')?.addEventListener('click', () => {
          const notesInput = document.getElementById('bookNotes');
          if (notesInput) {
            notesInput.value = notesInput.value ? `${notesInput.value} | ${noteText}` : noteText;
          }
          global.SumutGoAuth?.showToast('Rekomendasi transport disalin ke catatan booking.', 'success');
        });
      } catch (err) {
        box.className = 'p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700';
        box.textContent = err.message || 'Gagal memuat estimasi Transport API.';
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Cek Rute ke Lokasi Pemandu';
        }
      }
    });

    document.querySelectorAll('.eco-tab-btn').forEach((b) => {
      b.addEventListener('click', () => {
        state.ecoTab = b.dataset.ecoTab;
        renderEco();
      });
    });

    // Guide Modal & Gate buttons
    document.getElementById('btnCloseGuideModal')?.addEventListener('click', () => {
      document.getElementById('guideModal')?.classList.add('hidden');
    });

    document.getElementById('btnDirectoryGuestLogin')?.addEventListener('click', () => {
      global.SumutGoAuth.openAuthModal('login', 'Masuk untuk membuka seluruh detail kontak, basecamp, mitra wisata, dan fitur pemesanan.');
    });
    document.getElementById('btnDirectoryGuestRegister')?.addEventListener('click', () => {
      global.SumutGoAuth.openAuthModal('register', 'Daftar akun wisatawan gratis untuk melihat detail lengkap semua pemandu SumutGO.');
    });

    const openAuthForActiveGuide = (mode, intent) => {
      const g = state.activeGuideDetail || findGuide(state.activeGuideId);
      global.SumutGoAuth.openAuthModal(
        mode,
        g ? `Masuk atau daftar untuk membuka detail lengkap & memesan ${g.name} (${g.id}).` : 'Masuk atau daftar untuk melanjutkan.',
        g ? { type: 'guide-modal', guideId: g.id, intent } : null
      );
    };

    document.getElementById('btnModalGateLogin')?.addEventListener('click', () => openAuthForActiveGuide('login', 'detail'));
    document.getElementById('btnModalGateRegister')?.addEventListener('click', () => openAuthForActiveGuide('register', 'detail'));
    document.getElementById('btnBookingGateLogin')?.addEventListener('click', () => openAuthForActiveGuide('login', 'book'));
    document.getElementById('btnBookingGateRegister')?.addEventListener('click', () => openAuthForActiveGuide('register', 'book'));

    // Multi-Day Booking Two-Way Date & Duration Sync
    document.getElementById('bookStartDate')?.addEventListener('change', (e) => {
      state.booking.startDate = e.target.value || getTodayYmd();
      syncBookingDurationFromDays(state.booking.days);
    });
    document.getElementById('bookEndDate')?.addEventListener('change', syncBookingFromEndDate);
    document.getElementById('bookDays')?.addEventListener('input', (e) => {
      const val = parseInt(e.target.value, 10);
      if (!Number.isNaN(val) && val >= 1) {
        syncBookingDurationFromDays(val, { keepInput: true });
      }
    });
    document.getElementById('bookDays')?.addEventListener('change', (e) => {
      syncBookingDurationFromDays(e.target.value);
    });
    document.getElementById('btnDaysMinus')?.addEventListener('click', () => {
      syncBookingDurationFromDays(Math.max(1, state.booking.days - 1));
    });
    document.getElementById('btnDaysPlus')?.addEventListener('click', () => {
      syncBookingDurationFromDays(Math.min(30, state.booking.days + 1));
    });
    document.querySelectorAll('[data-duration-preset]').forEach((btn) => {
      btn.addEventListener('click', () => {
        syncBookingDurationFromDays(Number(btn.dataset.durationPreset));
      });
    });

    document.getElementById('bookingForm')?.addEventListener('submit', handleBookingStep1Submit);
    document.getElementById('btnBackToBookingForm')?.addEventListener('click', () => setBookingStep('form'));
    document.getElementById('btnConfirmBookingSubmit')?.addEventListener('click', handleConfirmSubmitBooking);
    document.getElementById('btnReceiptViewBookings')?.addEventListener('click', () => {
      document.getElementById('guideModal')?.classList.add('hidden');
      openMyBookingsModal();
    });
    document.getElementById('btnReceiptBookAgain')?.addEventListener('click', () => {
      setBookingStep('form');
    });
    document.getElementById('btnGuideRoleViewBookings')?.addEventListener('click', () => {
      document.getElementById('guideModal')?.classList.add('hidden');
      openMyBookingsModal();
    });

    // Receipt quick contact & message form (Step 3)
    document.getElementById('receiptContactForm')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.currentTarget;
      const bookingId = Number(form.dataset.bookingId || 0);
      const channel = document.getElementById('recContactChannel')?.value || 'whatsapp';
      const phone = (document.getElementById('recContactPhone')?.value || '').trim();
      const msgEl = document.getElementById('recContactMessage');
      const fbEl = document.getElementById('recContactFeedback');
      const btn = document.getElementById('btnReceiptSendContact');
      const message = (msgEl?.value || '').trim();

      if (!bookingId) {
        global.SumutGoAuth?.showToast('ID booking tidak ditemukan untuk pengiriman pesan.', 'error');
        return;
      }

      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Mengirim...';
      }
      try {
        const resp = await global.SumutGoApi.bookingContacts.create({
          booking_id: bookingId,
          preferred_channel: channel,
          contact_phone: phone,
          inquiry_message: message,
        });
        if (msgEl) msgEl.value = '';
        if (fbEl) {
          fbEl.className = 'p-2 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 text-[11px] font-medium';
          fbEl.textContent = resp.message || 'Pesan & kontak berhasil dikirim. Menunggu konfirmasi Admin/Pemandu.';
          fbEl.classList.remove('hidden');
        }
        global.SumutGoAuth?.showToast('Pesan kontak berhasil dikirim!', 'success');
      } catch (err) {
        if (fbEl) {
          fbEl.className = 'p-2 rounded-xl border border-rose-200 bg-rose-50 text-rose-700 text-[11px] font-medium';
          fbEl.textContent = err.message || 'Gagal mengirim pesan kontak.';
          fbEl.classList.remove('hidden');
        }
        global.SumutGoAuth?.showToast(err.message || 'Gagal mengirim pesan kontak.', 'error');
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Kirim Pesan Kontak';
        }
      }
    });

    // Global delegated copy phone button
    document.addEventListener('click', (e) => {
      const target = e.target && e.target.closest ? e.target.closest('[data-copy-phone]') : null;
      if (!target) return;
      const val = target.dataset.copyPhone || '';
      if (val) copyPhoneText(val, 'Nomor HP');
    });

    // Compare Modal (Wajib login & simpan per akun)
    document.getElementById('btnToggleCompareModal')?.addEventListener('click', () => {
      if (!isLoggedIn()) {
        global.SumutGoAuth?.openAuthModal(
          'login',
          'Masuk untuk mengakses fitur pembanding pemandu wisata sesuai akun Anda.'
        );
        return;
      }
      openCompareModal();
    });
    document.getElementById('btnCloseCompareModal')?.addEventListener('click', () => {
      document.getElementById('compareModal')?.classList.add('hidden');
    });
    document.getElementById('btnClearCompare')?.addEventListener('click', () => {
      state.compareList = [];
      saveCompareList();
      document.getElementById('compareModal')?.classList.add('hidden');
      renderDirectory();
    });

    // My Bookings Modal
    document.getElementById('btnOpenMyBookings')?.addEventListener('click', openMyBookingsModal);
    document.getElementById('btnCloseMyBookingsModal')?.addEventListener('click', () => {
      document.getElementById('myBookingsModal')?.classList.add('hidden');
    });
    document.getElementById('btnRefreshMyBookings')?.addEventListener('click', loadMyBookings);
    document.getElementById('btnBookingsOpenAdminConfirm')?.addEventListener('click', () => {
      document.getElementById('myBookingsModal')?.classList.add('hidden');
      openAdminConfirmModal('users');
    });
    document.querySelectorAll('[data-booking-filter]').forEach((btn) => {
      btn.addEventListener('click', () => {
        state.myBookingsFilter = btn.dataset.bookingFilter || 'ALL';
        document.querySelectorAll('[data-booking-filter]').forEach((b) => {
          b.className = b.dataset.bookingFilter === state.myBookingsFilter
            ? 'px-3 py-1 rounded-full text-[11px] font-bold bg-lokale text-white transition'
            : 'px-3 py-1 rounded-full text-[11px] font-semibold bg-lokalesoft text-lokale hover:bg-lokale/10 transition';
        });
        loadMyBookings();
      });
    });

    // Admin Confirm Modal
    document.getElementById('btnOpenAdminConfirm')?.addEventListener('click', () => openAdminConfirmModal('users'));
    document.getElementById('btnCloseAdminConfirmModal')?.addEventListener('click', () => {
      document.getElementById('adminConfirmModal')?.classList.add('hidden');
    });
    document.getElementById('btnRefreshAdminConfirm')?.addEventListener('click', loadAdminConfirmData);
    document.querySelectorAll('[data-admin-tab]').forEach((btn) => {
      btn.addEventListener('click', () => switchAdminConfirmTab(btn.dataset.adminTab));
    });
    document.getElementById('adminConfirmStatusFilter')?.addEventListener('change', (e) => {
      adminConfirmState.statusFilter = e.target.value || 'ALL';
      loadAdminConfirmData();
    });
    let adminSearchTimer = null;
    document.getElementById('adminConfirmSearch')?.addEventListener('input', () => {
      if (adminSearchTimer) global.clearTimeout(adminSearchTimer);
      adminSearchTimer = global.setTimeout(() => {
        loadAdminConfirmData();
      }, 220);
    });

    // Backdrop & ESC handling
    ['guideModal', 'compareModal', 'myBookingsModal', 'adminConfirmModal'].forEach((modalId) => {
      document.getElementById(modalId)?.addEventListener('click', (e) => {
        if (e.target && (e.target.id === modalId || e.target.dataset?.modalBackdrop === modalId)) {
          document.getElementById(modalId)?.classList.add('hidden');
        }
      });
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeAllModals();
    });
  }

  async function bootstrap() {
    populateSelectOptionsFromGuides();
    bindEvents();

    if (global.SumutGoAuth) {
      global.SumutGoAuth.subscribe(onAuthStateChanged);
      try {
        await global.SumutGoAuth.init();
      } catch (err) {
        console.error('[SumutGoApp] Auth init error:', err);
      }
      if (isLoggedIn()) {
        loadCompareList();
      }
    }

    renderDirectory();

    if (!TOUR_GUIDES.length || !(MASTER_ECOSYSTEM.destinations && MASTER_ECOSYSTEM.destinations.length)) {
      syncCatalogFromApiV1();
    }
  }

  if (document.readyState === 'loading') {
    global.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }

  global.SumutGoApp = {
    openGuideModal,
    handleStartBooking,
    openMyBookingsModal,
    openAdminConfirmModal,
    switchTab,
    renderDirectory,
  };
})(window);
