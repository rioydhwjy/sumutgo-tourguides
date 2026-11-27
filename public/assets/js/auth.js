/**
 * SumutGO Authentication & Session Controller
 * Mengelola Login, Daftar (Register), Logout, sesi JWT, tombol akun demo,
 * dan pembaruan reaktif UI (Navbar + Blur Gate) via /api/v1/auth/*.
 */
(function (global) {
  'use strict';

  const DEMO_ACCOUNTS = {
    wisatawan: {
      email: 'budi@wisatawan.id',
      password: 'Password123!',
      label: 'Wisatawan (Budi Santoso)',
    },
    guide: {
      email: 'horas@guide.id',
      password: 'Password123!',
      label: 'Pemandu (Horas TG-001)',
    },
    admin: {
      email: 'admin@sumutgo.id',
      password: 'Password123!',
      label: 'Admin SumutGO',
    },
  };

  const state = {
    user: null,
    mode: 'login',
    pendingAction: null,
    listeners: new Set(),
    toastTimer: null,
  };

  function normalizePhone(rawPhone) {
    let clean = String(rawPhone || '').replace(/[^0-9]/g, '');
    if (!clean) return '';
    if (clean.startsWith('0062')) {
      clean = clean.slice(2);
    } else if (clean.startsWith('0')) {
      clean = '62' + clean.slice(1);
    } else if (clean.startsWith('8')) {
      clean = '62' + clean;
    }
    return clean;
  }

  function isValidPhone(normalized) {
    return /^[1-9][0-9]{8,14}$/.test(String(normalized || ''));
  }

  function isAuthenticated() {
    return Boolean(state.user && global.SumutGoApi.getToken());
  }

  function getUser() {
    return state.user;
  }

  function subscribe(fn) {
    if (typeof fn === 'function') {
      state.listeners.add(fn);
    }
    return () => state.listeners.delete(fn);
  }

  function notifyChange(meta = {}) {
    syncBodyAuthClasses();
    updateNavbarAuth();
    state.listeners.forEach((fn) => {
      try {
        fn(state.user, isAuthenticated(), meta);
      } catch (err) {
        console.error('[SumutGoAuth] Listener error:', err);
      }
    });
  }

  function syncBodyAuthClasses() {
    const authed = isAuthenticated();
    document.body.classList.toggle('auth-authenticated', authed);
    document.body.classList.toggle('auth-guest', !authed);
  }

  function showToast(message, tone = 'success') {
    const toast = document.getElementById('sumutgoToast');
    const card = document.getElementById('sumutgoToastCard');
    const msgEl = document.getElementById('sumutgoToastMsg');
    const dotEl = document.getElementById('sumutgoToastDot');
    if (!toast || !msgEl) return;

    if (state.toastTimer) {
      clearTimeout(state.toastTimer);
    }

    msgEl.textContent = message;
    if (card) {
      if (tone === 'error') {
        card.className = 'rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-800 shadow-xl flex items-center gap-2.5 transition-all';
        if (dotEl) dotEl.className = 'w-2.5 h-2.5 rounded-full bg-rose-500 shrink-0';
      } else if (tone === 'info') {
        card.className = 'rounded-2xl border border-lokaleborder bg-white px-4 py-3 text-xs font-semibold text-ink shadow-xl flex items-center gap-2.5 transition-all';
        if (dotEl) dotEl.className = 'w-2.5 h-2.5 rounded-full bg-lokale shrink-0';
      } else {
        card.className = 'rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-900 shadow-xl flex items-center gap-2.5 transition-all';
        if (dotEl) dotEl.className = 'w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0';
      }
    }

    toast.classList.remove('hidden');
    state.toastTimer = setTimeout(() => {
      toast.classList.add('hidden');
    }, 4200);
  }

  function getRoleBadgeMeta(role) {
    const r = String(role || 'wisatawan').toLowerCase();
    if (r === 'admin') {
      return {
        label: 'admin',
        cls: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      };
    }
    if (r === 'guide') {
      return {
        label: 'guide',
        cls: 'bg-amber-50 text-amber-700 border-amber-200',
      };
    }
    return {
      label: 'wisatawan',
      cls: 'bg-lokalesoft text-lokale border-lokaleborder',
    };
  }

  function updateNavbarAuth() {
    const guestBox = document.getElementById('navAuthGuest');
    const userBox = document.getElementById('navAuthUser');
    const nameEl = document.getElementById('navUserName');
    const avatarEl = document.getElementById('navUserAvatar');
    const roleEl = document.getElementById('navUserRoleBadge');
    const myBookingsBtn = document.getElementById('btnOpenMyBookings');
    const myBookingsText = document.getElementById('btnOpenMyBookingsText');
    const adminConfirmBtn = document.getElementById('btnOpenAdminConfirm');
    const adminQuickLink = document.getElementById('navAdminQuickLink');

    const authed = isAuthenticated();
    if (guestBox) guestBox.classList.toggle('hidden', authed);
    if (userBox) userBox.classList.toggle('hidden', !authed);

    if (authed && state.user) {
      const fullName = String(state.user.full_name || state.user.email || 'Pengguna').trim();
      const initials = fullName
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('') || 'U';
      const roleMeta = getRoleBadgeMeta(state.user.role);
      const isAdmin = String(state.user.role || '').toLowerCase() === 'admin';

      if (nameEl) nameEl.textContent = fullName;
      if (avatarEl) avatarEl.textContent = initials;
      if (roleEl) {
        roleEl.textContent = roleMeta.label;
        roleEl.className = `px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide border ${roleMeta.cls}`;
      }
      if (myBookingsBtn && myBookingsText) {
        myBookingsBtn.classList.remove('hidden');
        myBookingsText.textContent = state.user.role === 'guide'
          ? 'Jadwal Tur'
          : isAdmin
            ? 'Semua Booking'
            : 'Booking Saya';
      }
      if (adminConfirmBtn) {
        adminConfirmBtn.classList.toggle('hidden', !isAdmin);
      }
      if (adminQuickLink) {
        adminQuickLink.classList.toggle('hidden', !isAdmin);
      }
    } else {
      if (adminConfirmBtn) adminConfirmBtn.classList.add('hidden');
      if (adminQuickLink) adminQuickLink.classList.add('hidden');
    }
  }

  function clearAuthFeedback() {
    ['loginFeedback', 'registerFeedback'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) {
        el.innerHTML = '';
        el.classList.add('hidden');
      }
    });
  }

  function renderAuthError(targetId, message, fieldErrors = null) {
    const el = document.getElementById(targetId);
    if (!el) return;

    const escape = (v) => String(v ?? '').replace(/[&<>"']/g, (ch) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[ch]));

    let detailsHtml = '';
    if (fieldErrors && typeof fieldErrors === 'object') {
      const entries = Object.values(fieldErrors).filter(Boolean);
      if (entries.length) {
        detailsHtml = `<ul class="mt-1.5 list-disc list-inside space-y-0.5 text-[11px] font-normal">${entries.map((item) => `<li>${escape(item)}</li>`).join('')}</ul>`;
      }
    }

    el.innerHTML = `<div>${escape(message)}</div>${detailsHtml}`;
    el.className = 'p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold';
    el.classList.remove('hidden');
  }

  function setAuthMode(mode) {
    state.mode = mode === 'register' ? 'register' : 'login';
    const loginPanel = document.getElementById('authLoginPanel');
    const registerPanel = document.getElementById('authRegisterPanel');
    const tabLogin = document.getElementById('btnAuthTabLogin');
    const tabRegister = document.getElementById('btnAuthTabRegister');
    const titleEl = document.getElementById('authModalTitle');
    const subtitleEl = document.getElementById('authModalSubtitle');

    const isReg = state.mode === 'register';
    if (loginPanel) loginPanel.classList.toggle('hidden', isReg);
    if (registerPanel) registerPanel.classList.toggle('hidden', !isReg);

    const activeTabCls = 'py-2 rounded-full text-xs font-bold transition bg-lokale text-white shadow-sm';
    const inactiveTabCls = 'py-2 rounded-full text-xs font-semibold transition text-lokale hover:text-lokaledark';
    if (tabLogin) tabLogin.className = isReg ? inactiveTabCls : activeTabCls;
    if (tabRegister) tabRegister.className = isReg ? activeTabCls : inactiveTabCls;

    if (titleEl) {
      titleEl.textContent = isReg ? 'Daftar Akun Wisatawan' : 'Masuk ke SumutGO';
    }
    if (subtitleEl) {
      subtitleEl.textContent = isReg
        ? 'Buat akun untuk membuka seluruh detail pemandu & reservasi tur.'
        : 'Gunakan email dan password akun SumutGO Anda.';
    }

    clearAuthFeedback();
  }

  function openAuthModal(mode = 'login', noticeMessage = '', pendingAction = null) {
    const modal = document.getElementById('authModal');
    const noticeBox = document.getElementById('authContextNotice');
    if (!modal) return;

    if (pendingAction !== undefined && pendingAction !== null) {
      state.pendingAction = pendingAction;
    }

    setAuthMode(mode);

    if (noticeBox) {
      if (noticeMessage) {
        noticeBox.textContent = noticeMessage;
        noticeBox.classList.remove('hidden');
      } else {
        noticeBox.textContent = '';
        noticeBox.classList.add('hidden');
      }
    }

    modal.classList.remove('hidden');

    setTimeout(() => {
      const targetInput = state.mode === 'register'
        ? document.getElementById('regFullName')
        : document.getElementById('loginEmail');
      if (targetInput) targetInput.focus();
    }, 60);
  }

  function closeAuthModal(clearPending = false) {
    const modal = document.getElementById('authModal');
    if (modal) modal.classList.add('hidden');
    if (clearPending) {
      state.pendingAction = null;
    }
  }

  function fillDemoCredentials(accountKey) {
    const demo = DEMO_ACCOUNTS[accountKey];
    if (!demo) return;
    setAuthMode('login');
    const emailEl = document.getElementById('loginEmail');
    const passEl = document.getElementById('loginPassword');
    if (emailEl) emailEl.value = demo.email;
    if (passEl) passEl.value = demo.password;
    clearAuthFeedback();
    showToast(`Kredensial demo ${demo.label} diisikan. Klik Masuk Sekarang.`, 'info');
  }

  function consumePendingAction() {
    const action = state.pendingAction;
    state.pendingAction = null;
    return action;
  }

  async function handleLoginSubmit(e) {
    e.preventDefault();
    clearAuthFeedback();

    const emailEl = document.getElementById('loginEmail');
    const passEl = document.getElementById('loginPassword');
    const submitBtn = document.getElementById('btnLoginSubmit');

    const email = (emailEl?.value || '').trim().toLowerCase();
    const password = passEl?.value || '';

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      renderAuthError('loginFeedback', 'Masukkan alamat email yang valid.');
      emailEl?.focus();
      return;
    }
    if (!password) {
      renderAuthError('loginFeedback', 'Password wajib diisi.');
      passEl?.focus();
      return;
    }

    const originalText = submitBtn ? submitBtn.textContent : 'Masuk Sekarang';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Memproses Masuk...';
    }

    try {
      const resp = await global.SumutGoApi.auth.login({ email, password });
      const token = resp?.data?.token || '';
      const user = resp?.data?.user || null;
      if (!token || !user) {
        throw new Error('Respons login dari server tidak lengkap.');
      }

      global.SumutGoApi.setToken(token);
      global.SumutGoApi.setStoredUser(user);
      state.user = user;

      // Sinkronisasi profil terbaru via /api/v1/auth/me
      try {
        const meResp = await global.SumutGoApi.auth.me();
        if (meResp?.data) {
          state.user = meResp.data;
          global.SumutGoApi.setStoredUser(meResp.data);
        }
      } catch (_) {
        // Fallback aman ke objek user dari login
      }

      const pending = consumePendingAction();
      closeAuthModal(false);
      document.getElementById('loginForm')?.reset();
      notifyChange({ reason: 'login', pendingAction: pending });
      showToast(`Selamat datang kembali, ${state.user.full_name || state.user.email}!`, 'success');
    } catch (err) {
      renderAuthError('loginFeedback', err.message || 'Gagal masuk. Periksa kembali email dan password Anda.', err.errors);
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      }
    }
  }

  async function handleRegisterSubmit(e) {
    e.preventDefault();
    clearAuthFeedback();

    const fullNameEl = document.getElementById('regFullName');
    const emailEl = document.getElementById('regEmail');
    const phoneEl = document.getElementById('regPhoneNumber');
    const passEl = document.getElementById('regPassword');
    const confirmEl = document.getElementById('regPasswordConfirm');
    const submitBtn = document.getElementById('btnRegisterSubmit');

    const full_name = (fullNameEl?.value || '').trim();
    const email = (emailEl?.value || '').trim().toLowerCase();
    const rawPhone = (phoneEl?.value || '').trim();
    const phone_number = normalizePhone(rawPhone);
    const password = passEl?.value || '';
    const password_confirmation = confirmEl?.value || '';

    if (full_name.length < 2) {
      renderAuthError('registerFeedback', 'Nama lengkap minimal 2 karakter.');
      fullNameEl?.focus();
      return;
    }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      renderAuthError('registerFeedback', 'Masukkan alamat email yang valid.');
      emailEl?.focus();
      return;
    }
    if (!isValidPhone(phone_number)) {
      renderAuthError('registerFeedback', 'Nomor HP tidak valid. Gunakan format Indonesia/internasional, contoh: 081234567890 atau +6281234567890.');
      phoneEl?.focus();
      return;
    }
    if (password.length < 8) {
      renderAuthError('registerFeedback', 'Password minimal 8 karakter.');
      passEl?.focus();
      return;
    }
    if (password !== password_confirmation) {
      renderAuthError('registerFeedback', 'Konfirmasi password tidak cocok dengan password.');
      confirmEl?.focus();
      return;
    }

    const originalText = submitBtn ? submitBtn.textContent : 'Daftar & Masuk';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Mendaftarkan Akun...';
    }

    try {
      const resp = await global.SumutGoApi.auth.register({
        full_name,
        email,
        phone_number,
        password,
        password_confirmation,
      });

      const token = resp?.data?.token || '';
      const user = resp?.data?.user || null;
      if (!token || !user) {
        throw new Error('Respons pendaftaran tidak lengkap.');
      }

      global.SumutGoApi.setToken(token);
      global.SumutGoApi.setStoredUser(user);
      state.user = user;

      const pending = consumePendingAction();
      closeAuthModal(false);
      document.getElementById('registerForm')?.reset();
      notifyChange({ reason: 'register', pendingAction: pending });
      showToast(`Pendaftaran berhasil! Selamat datang, ${user.full_name}!`, 'success');
    } catch (err) {
      renderAuthError('registerFeedback', err.message || 'Gagal mendaftarkan akun baru.', err.errors);
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      }
    }
  }

  async function logout() {
    const logoutBtn = document.getElementById('btnNavLogout');
    if (logoutBtn) logoutBtn.disabled = true;

    try {
      if (global.SumutGoApi.getToken()) {
        await global.SumutGoApi.auth.logout();
      }
    } catch (_) {
      // Tetap lanjutkan pembersihan sesi client meskipun token sudah kedaluwarsa
    } finally {
      global.SumutGoApi.clearSessionStorage();
      state.user = null;
      state.pendingAction = null;
      if (logoutBtn) logoutBtn.disabled = false;
      notifyChange({ reason: 'logout' });
      showToast('Anda berhasil keluar. Detail sensitif pemandu dikunci kembali.', 'info');
    }
  }

  function togglePasswordVisibility(inputId, btnEl) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const nextType = input.type === 'password' ? 'text' : 'password';
    input.type = nextType;
    if (btnEl) {
      btnEl.textContent = nextType === 'text' ? 'Sembunyikan' : 'Lihat';
    }
  }

  async function init() {
    const cachedToken = global.SumutGoApi.getToken();
    const cachedUser = global.SumutGoApi.getStoredUser();

    if (cachedToken && cachedUser) {
      state.user = cachedUser;
    } else {
      global.SumutGoApi.clearSessionStorage();
      state.user = null;
    }

    bindAuthDomEvents();
    syncBodyAuthClasses();
    updateNavbarAuth();

    if (cachedToken) {
      try {
        const meResp = await global.SumutGoApi.auth.me();
        if (meResp?.data) {
          state.user = meResp.data;
          global.SumutGoApi.setStoredUser(meResp.data);
          notifyChange({ reason: 'session-sync' });
        }
      } catch (err) {
        if (err && err.status === 401) {
          global.SumutGoApi.clearSessionStorage();
          state.user = null;
          notifyChange({ reason: 'session-expired' });
        }
      }
    }
  }

  function bindAuthDomEvents() {
    document.getElementById('btnNavLogin')?.addEventListener('click', () => openAuthModal('login', '', null));
    document.getElementById('btnNavRegister')?.addEventListener('click', () => openAuthModal('register', '', null));
    document.getElementById('btnNavLogout')?.addEventListener('click', logout);
    document.getElementById('btnCloseAuthModal')?.addEventListener('click', () => closeAuthModal(true));
    document.getElementById('btnAuthTabLogin')?.addEventListener('click', () => setAuthMode('login'));
    document.getElementById('btnAuthTabRegister')?.addEventListener('click', () => setAuthMode('register'));

    document.getElementById('loginForm')?.addEventListener('submit', handleLoginSubmit);
    document.getElementById('registerForm')?.addEventListener('submit', handleRegisterSubmit);

    document.querySelectorAll('[data-demo-account]').forEach((btn) => {
      btn.addEventListener('click', () => fillDemoCredentials(btn.dataset.demoAccount));
    });

    document.querySelectorAll('[data-toggle-pw]').forEach((btn) => {
      btn.addEventListener('click', () => togglePasswordVisibility(btn.dataset.togglePw, btn));
    });

    document.getElementById('authModal')?.addEventListener('click', (e) => {
      if (e.target && (e.target.id === 'authModal' || e.target.dataset?.modalBackdrop === 'authModal')) {
        closeAuthModal(true);
      }
    });

    global.addEventListener('sumutgo:unauthorized', () => {
      if (isAuthenticated()) {
        global.SumutGoApi.clearSessionStorage();
        state.user = null;
        notifyChange({ reason: 'unauthorized' });
        showToast('Sesi login Anda berakhir. Silakan masuk kembali.', 'error');
      }
    });
  }

  global.SumutGoAuth = {
    init,
    isAuthenticated,
    getUser,
    subscribe,
    openAuthModal,
    closeAuthModal,
    logout,
    showToast,
    getRoleBadgeMeta,
    normalizePhone,
    isValidPhone,
  };
})(window);
