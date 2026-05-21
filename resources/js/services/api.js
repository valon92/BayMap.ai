const API_BASE = import.meta.env.VITE_API_URL || '/api';

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

export const api = {
  getGeo: () => request('/geo'),
  getTrending: () => request('/trending'),
  getExamples: () => request('/examples'),
  search: (query, filters = {}, locale = null) =>
    request('/search', {
      method: 'POST',
      body: JSON.stringify({ q: query, filters, locale }),
    }),
};

export default api;
