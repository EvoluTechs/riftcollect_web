const API_BASE = 'api.php';

async function request(params, body) {
  const url = new URL(API_BASE, location.href);
  Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
  const init = { method: body ? 'POST' : 'GET', headers: {} };
  if (body && typeof body === 'object' && !(body instanceof FormData)) {
    init.headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(body);
  } else if (body instanceof FormData) {
    init.body = body;
  }
  const res = await fetch(url.toString(), init);
  const json = await res.json();
  if (!json.ok) throw new Error(json.error || 'Erreur');
  return json.data;
}

export const api = {
  health: () => request({ action: 'health' }),
  register: (email, password) => request({ action: 'register' }, (() => { const fd = new FormData(); fd.append('email', email); fd.append('password', password); return fd; })()),
  login: (email, password) => request({ action: 'login' }, (() => { const fd = new FormData(); fd.append('email', email); fd.append('password', password); return fd; })()),
  logout: () => request({ action: 'logout' }),
  me: () => request({ action: 'me' }),
  cardsList: (q, rarity, set, page, pageSize) => request({ action: 'cards.list', q, rarity, set, page, pageSize }),
  cardsRefresh: () => request({ action: 'cards.refresh' }),
  cardDetail: (id) => request({ action: 'cards.detail', id }),
  collectionGet: () => request({ action: 'collection.get' }),
  collectionSet: (card_id, qty) => request({ action: 'collection.set' }, (() => { const fd = new FormData(); fd.append('card_id', card_id); fd.append('qty', qty); return fd; })()),
  collectionBulkSet: (items) => request({ action: 'collection.bulkSet' }, items),
  statsProgress: () => request({ action: 'stats.progress' }),
  expansionsList: () => request({ action: 'expansions.list' }),
  subscribe: (enabled) => request({ action: 'subscribe' }, (() => { const fd = new FormData(); fd.append('enabled', enabled ? '1' : '0'); return fd; })()),
};
