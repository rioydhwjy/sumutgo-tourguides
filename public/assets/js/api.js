/**
 * SumutGO RESTful API v1 Client
 * Seluruh komunikasi data & autentikasi wajib melalui endpoint backend CodeIgniter 4 (/api/v1/...).
 */
(function (global) {
  'use strict';

  const TOKEN_KEY = 'sumutgo_jwt_token';
  const USER_KEY = 'sumutgo_user';

  function getBaseUrl() {
    const raw = (global.SUMUTGO_CONFIG && global.SUMUTGO_CONFIG.apiV1BaseUrl) || '/api/v1';
    return String(raw).replace(/\/+$/, '');
  }

  class ApiError extends Error {
    constructor(message, status = 500, errors = null, payload = null) {
      super(message);
      this.name = 'ApiError';
      this.status = status;
      this.errors = errors;
      this.payload = payload;
    }
  }

  function getToken() {
    try {
      return global.localStorage.getItem(TOKEN_KEY) || '';
    } catch (_) {
      return '';
    }
  }

  function setToken(token) {
    try {
      if (token) {
        global.localStorage.setItem(TOKEN_KEY, String(token));
      } else {
        global.localStorage.removeItem(TOKEN_KEY);
      }
    } catch (_) {
      // Ignore storage quota / privacy mode errors
    }
  }

  function getStoredUser() {
    try {
      const raw = global.localStorage.getItem(USER_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (_) {
      return null;
    }
  }

  function setStoredUser(user) {
    try {
      if (user) {
        global.localStorage.setItem(USER_KEY, JSON.stringify(user));
      } else {
        global.localStorage.removeItem(USER_KEY);
      }
    } catch (_) {
      // Ignore storage errors
    }
  }

  function clearSessionStorage() {
    setToken('');
    setStoredUser(null);
  }

  function buildQueryString(params) {
    if (!params || typeof params !== 'object') return '';
    const sp = new URLSearchParams();
    Object.entries(params).forEach(([key, val]) => {
      if (val !== undefined && val !== null && String(val).trim() !== '') {
        sp.set(key, String(val));
      }
    });
    const qs = sp.toString();
    return qs ? `?${qs}` : '';
  }

  async function request(path, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const headers = Object.assign({ Accept: 'application/json' }, options.headers || {});

    if (!options.skipAuth) {
      const token = getToken();
      if (token) {
        headers.Authorization = `Bearer ${token}`;
      }
    }

    let body = options.body;
    if (body !== undefined && body !== null && !(body instanceof FormData) && typeof body !== 'string') {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(body);
    }

    const url = `${getBaseUrl()}${path.startsWith('/') ? path : `/${path}`}`;
    let response;
    try {
      response = await global.fetch(url, {
        method,
        headers,
        body,
        credentials: 'same-origin',
      });
    } catch (networkErr) {
      throw new ApiError('Tidak dapat terhubung ke server API SumutGO. Periksa koneksi Anda.', 0, null, networkErr);
    }

    let payload = null;
    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
      try {
        payload = await response.json();
      } catch (_) {
        payload = null;
      }
    } else {
      const text = await response.text().catch(() => '');
      payload = text ? { message: text } : null;
    }

    if (!response.ok || (payload && payload.status === 'error')) {
      const msg = (payload && payload.message) || `Permintaan gagal (HTTP ${response.status}).`;
      const errors = (payload && payload.errors) || null;

      if (response.status === 401 && options.handleUnauthorized !== false) {
        global.dispatchEvent(new CustomEvent('sumutgo:unauthorized', {
          detail: { status: response.status, message: msg, path },
        }));
      }

      throw new ApiError(msg, response.status, errors, payload);
    }

    return payload || { status: 'success', message: 'OK', data: null };
  }

  const SumutGoApi = {
    ApiError,
    getToken,
    setToken,
    getStoredUser,
    setStoredUser,
    clearSessionStorage,
    request,

    auth: {
      register(payload) {
        return request('/auth/register', {
          method: 'POST',
          body: payload,
          skipAuth: true,
          handleUnauthorized: false,
        });
      },
      login(payload) {
        return request('/auth/login', {
          method: 'POST',
          body: payload,
          skipAuth: true,
          handleUnauthorized: false,
        });
      },
      me() {
        return request('/auth/me', {
          method: 'GET',
        });
      },
      logout() {
        return request('/auth/logout', {
          method: 'POST',
          handleUnauthorized: false,
        });
      },
    },

    guides: {
      list(params = {}) {
        return request(`/guides${buildQueryString(params)}`, { method: 'GET' });
      },
      detail(identifier) {
        return request(`/guides/${encodeURIComponent(identifier)}`, { method: 'GET' });
      },
    },

    master: {
      all() {
        return request('/master/all', { method: 'GET' });
      },
    },

    bookings: {
      list(params = {}) {
        return request(`/bookings${buildQueryString(params)}`, { method: 'GET' });
      },
      detail(id) {
        return request(`/bookings/${encodeURIComponent(id)}`, { method: 'GET' });
      },
      create(payload) {
        return request('/bookings', {
          method: 'POST',
          body: payload,
        });
      },
      updateStatus(id, payload) {
        return request(`/bookings/${encodeURIComponent(id)}/status`, {
          method: 'PATCH',
          body: payload,
        });
      },
    },

    reviews: {
      list(params = {}) {
        return request(`/reviews${buildQueryString(params)}`, { method: 'GET' });
      },
      create(payload) {
        return request('/reviews', {
          method: 'POST',
          body: payload,
        });
      },
    },

    stats() {
      return request('/stats', { method: 'GET' });
    },
  };

  global.SumutGoApi = SumutGoApi;
})(window);
