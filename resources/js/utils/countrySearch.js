/**
 * Client-side country autocomplete for the market picker.
 * Prefix-first ranking, accent-insensitive, multi-term (name, code, aliases).
 */

export function normalizeSearchText(text) {
  return String(text || '')
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
    .toLowerCase()
    .trim();
}

function collectTerms(country) {
  const raw = [
    country.name,
    country.code,
    ...(country.searchTerms || []),
  ];

  return [...new Set(raw.map(normalizeSearchText).filter(Boolean))];
}

function scoreTerm(term, query) {
  if (!term || !query) return 0;

  if (term === query) {
    return 1000;
  }

  if (term.startsWith(query)) {
    return 920 - Math.min(term.length, 80);
  }

  const words = term.split(/[\s,.-]+/).filter(Boolean);
  for (const word of words) {
    if (word.startsWith(query)) {
      return 880 - Math.min(term.length, 80);
    }
  }

  const index = term.indexOf(query);
  if (index >= 0) {
    return 520 - index;
  }

  return 0;
}

function scoreCountry(country, query) {
  let best = 0;

  for (const term of collectTerms(country)) {
    best = Math.max(best, scoreTerm(term, query));
  }

  if (best > 0 && country.code) {
    const code = normalizeSearchText(country.code);
    if (code === query) {
      best += 40;
    } else if (code.startsWith(query)) {
      best += 20;
    }
  }

  return best;
}

/**
 * @param {Array<{code: string, name: string, searchTerms?: string[]}>} countries
 * @param {string} query
 * @param {{ limit?: number, popularCodes?: string[] }} [options]
 */
export function filterCountries(countries, query, options = {}) {
  const { limit = 12, popularCodes = [] } = options;
  const popular = new Set(popularCodes.map((code) => String(code).toUpperCase()));
  const normalizedQuery = normalizeSearchText(query);

  if (!normalizedQuery) {
    return [];
  }

  const ranked = countries
    .map((country) => {
      let score = scoreCountry(country, normalizedQuery);
      if (score > 0 && popular.has(String(country.code).toUpperCase())) {
        score += 60;
      }
      return { country, score };
    })
    .filter((entry) => entry.score > 0)
    .sort((a, b) => {
      if (b.score !== a.score) {
        return b.score - a.score;
      }
      return String(a.country.name).localeCompare(String(b.country.name), undefined, { sensitivity: 'base' });
    });

  return ranked.slice(0, limit).map((entry) => entry.country);
}

/**
 * Split display name into before / match / after for highlighted rendering.
 */
export function splitNameHighlight(name, query) {
  const display = String(name || '');
  const normalizedQuery = normalizeSearchText(query);

  if (!display || !normalizedQuery) {
    return { before: display, match: '', after: '' };
  }

  const normalizedName = normalizeSearchText(display);

  let startNorm = normalizedName.indexOf(normalizedQuery);
  if (startNorm < 0) {
    const words = normalizedName.split(/[\s,.-]+/);
    let offset = 0;
    for (const word of words) {
      if (word.startsWith(normalizedQuery)) {
        startNorm = offset;
        break;
      }
      offset += word.length + 1;
    }
  }

  if (startNorm < 0) {
    return { before: display, match: '', after: '' };
  }

  const endNorm = startNorm + normalizedQuery.length;
  let normPos = 0;
  let start = 0;
  let end = display.length;

  for (let i = 0; i <= display.length; i++) {
    if (normPos === startNorm) {
      start = i;
    }
    if (normPos === endNorm) {
      end = i;
      break;
    }
    if (i < display.length) {
      normPos += normalizeSearchText(display[i]).length;
    }
  }

  return {
    before: display.slice(0, start),
    match: display.slice(start, end),
    after: display.slice(end),
  };
}
