const API_BASE = import.meta.env.VITE_API_URL || '/api';
const IMAGE_STORAGE_KEY = 'powerbook_search_image';
const SCOPE_STORAGE_KEY = 'powerbook_location_scope';
const MARKET_STORAGE_KEY = 'powerbook_market_selection';

const DEFAULT_MARKET = { mode: 'auto', countryCode: null, countryCodes: [], continentCode: null };

async function parseJsonResponse(res) {
  const text = await res.text();
  const trimmed = text.trim();

  if (trimmed.startsWith('<')) {
    throw new Error(
      'Server returned HTML instead of JSON. Ensure Laravel is running: php artisan serve'
    );
  }

  try {
    return JSON.parse(trimmed);
  } catch {
    throw new Error('Invalid JSON response from API');
  }
}

async function request(path, options = {}) {
  const res = await fetch(`${API_BASE}${path}`, {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...options.headers,
    },
    ...options,
  });

  const data = await parseJsonResponse(res);

  if (!res.ok) {
    throw new Error(data.message || `API error ${res.status}`);
  }

  return data;
}

function parseCountryCodes(value) {
  if (Array.isArray(value)) {
    return [...new Set(value.map((c) => String(c).toUpperCase()).filter((c) => /^[A-Z]{2}$/.test(c)))];
  }
  if (!value) return [];
  return [...new Set(
    String(value)
      .split(/[\s,;]+/)
      .map((c) => c.trim().toUpperCase())
      .filter((c) => /^[A-Z]{2}$/.test(c))
  )];
}

function normalizeMarketSelection(raw) {
  if (!raw || typeof raw !== 'object') {
    return { ...DEFAULT_MARKET, countryCodes: [] };
  }

  let countryCodes = parseCountryCodes(raw.countryCodes);
  if (countryCodes.length === 0 && raw.countryCode) {
    countryCodes = parseCountryCodes(raw.countryCode);
  }

  let mode = ['auto', 'global', 'continent', 'country', 'countries'].includes(raw.mode)
    ? raw.mode
    : 'auto';

  if (countryCodes.length > 1) {
    mode = 'countries';
  } else if (countryCodes.length === 1 && mode !== 'continent' && mode !== 'global' && mode !== 'auto') {
    mode = 'country';
  }

  return {
    mode,
    countryCode: countryCodes.length === 1 ? countryCodes[0] : null,
    countryCodes,
    continentCode: raw.continentCode ? String(raw.continentCode).toUpperCase() : null,
  };
}

function migrateLegacyScope() {
  const legacy = localStorage.getItem(SCOPE_STORAGE_KEY);
  if (legacy === 'world' || legacy === 'global' || legacy === 'universal') {
    return { mode: 'global', countryCode: null, countryCodes: [], continentCode: null };
  }
  return { ...DEFAULT_MARKET, countryCodes: [] };
}

function scopeFromMarket(market) {
  const sel = normalizeMarketSelection(market);
  if (sel.mode === 'global') return 'world';
  if (sel.mode === 'country' || sel.mode === 'countries') return 'country';
  if (sel.mode === 'continent') return 'continent';
  return 'auto';
}

function marketCodeFromSelection(market) {
  const sel = normalizeMarketSelection(market);
  if (sel.mode === 'country' && sel.countryCode) return sel.countryCode;
  if (sel.mode === 'countries' && sel.countryCodes.length) return sel.countryCodes.join(',');
  if (sel.mode === 'continent') return sel.continentCode;
  return null;
}

export const api = {
  getGeo: () => request('/geo'),

  getMarkets: (locale = 'en') =>
    request(`/markets?locale=${encodeURIComponent(locale || 'en')}`),

  getTrending: () => request('/trending'),
  getExamples: () => request('/examples'),

  search: (
    query,
    filters = {},
    locale = null,
    imageBase64 = null,
    marketSelection = null,
    page = 1,
    perPage = 12,
    locationScopeOverride = null
  ) => {
    const market = normalizeMarketSelection(marketSelection || api.getMarketSelection());

    return request('/search', {
      method: 'POST',
      body: JSON.stringify({
        q: query || '',
        filters,
        locale,
        image: imageBase64 || null,
        location_scope: locationScopeOverride || scopeFromMarket(market),
        market_mode: market.mode,
        market_code: marketCodeFromSelection(market),
        page,
        per_page: perPage,
      }),
    });
  },

  getMarketSelection: () => {
    try {
      const raw = localStorage.getItem(MARKET_STORAGE_KEY);
      if (raw) {
        return normalizeMarketSelection(JSON.parse(raw));
      }
    } catch {
      // fall through to legacy migration
    }

    return migrateLegacyScope();
  },

  setMarketSelection: (selection) => {
    const normalized = normalizeMarketSelection(selection);
    localStorage.setItem(MARKET_STORAGE_KEY, JSON.stringify(normalized));
    localStorage.setItem(SCOPE_STORAGE_KEY, scopeFromMarket(normalized));
  },

  getLocationScope: () => scopeFromMarket(api.getMarketSelection()),

  setLocationScope: (scope) => {
    const normalized = scope === 'world'
      ? { mode: 'global', countryCode: null, countryCodes: [], continentCode: null }
      : { ...DEFAULT_MARKET, countryCodes: [] };
    api.setMarketSelection(normalized);
  },

  marketQueryParams: (selection) => {
    const market = normalizeMarketSelection(selection);
    const params = {
      scope: scopeFromMarket(market),
      market_mode: market.mode,
    };
    const code = marketCodeFromSelection(market);
    if (code) {
      params.market_code = code;
    }
    return params;
  },

  parseMarketFromQuery: (query = {}) => {
    const mode = String(query.market_mode || '').toLowerCase();
    const codes = parseCountryCodes(query.market_code);

    if (mode === 'countries' || (mode === 'country' && codes.length > 1)) {
      return normalizeMarketSelection({ mode: 'countries', countryCodes: codes });
    }

    if (mode === 'country' && codes.length === 1) {
      return normalizeMarketSelection({ mode: 'country', countryCode: codes[0], countryCodes: codes });
    }

    if (['auto', 'global', 'continent'].includes(mode)) {
      return normalizeMarketSelection({
        mode,
        countryCodes: [],
        continentCode: mode === 'continent' ? (query.market_code || null) : null,
      });
    }

    const legacyScope = String(query.scope || '').toLowerCase();
    if (legacyScope === 'world') {
      return { mode: 'global', countryCode: null, countryCodes: [], continentCode: null };
    }

    return api.getMarketSelection();
  },

  saveSearchImage: (base64) => {
    if (base64) {
      sessionStorage.setItem(IMAGE_STORAGE_KEY, base64);
    } else {
      sessionStorage.removeItem(IMAGE_STORAGE_KEY);
    }
  },

  loadSearchImage: () => sessionStorage.getItem(IMAGE_STORAGE_KEY),

  clearSearchImage: () => sessionStorage.removeItem(IMAGE_STORAGE_KEY),
};

export default api;
