import { Router } from './router.js';
import { api } from './api.js';

const root = document.getElementById('app-root');
const router = new Router(root);

function setAccountUi(user) {
  const loginLink = document.getElementById('loginLink');
  const accountDropdown = document.getElementById('accountDropdown');
  const navUserEmail = document.getElementById('navUserEmail');
  const navCollectionItem = document.getElementById('navCollectionItem');
  const navStatsItem = document.getElementById('navStatsItem');
  if (user) {
    loginLink.style.display = 'none';
    accountDropdown.style.display = '';
    navUserEmail.textContent = user.email;
    if (navCollectionItem) navCollectionItem.style.display = '';
    if (navStatsItem) navStatsItem.style.display = '';
  } else {
    loginLink.style.display = '';
    accountDropdown.style.display = 'none';
    navUserEmail.textContent = '';
    if (navCollectionItem) navCollectionItem.style.display = 'none';
    if (navStatsItem) navStatsItem.style.display = 'none';
  }
}

async function bootstrapAuthUi() {
  try {
    const data = await api.me();
    setAccountUi(data.user);
  } catch (e) {
    setAccountUi(null);
  }
}

function el(tag, attrs = {}, ...children) {
  const e = document.createElement(tag);
  Object.entries(attrs).forEach(([k, v]) => {
    if (k === 'class') e.className = v; else if (k.startsWith('on') && typeof v === 'function') e.addEventListener(k.substring(2).toLowerCase(), v); else e.setAttribute(k, v);
  });
  children.forEach(c => {
    if (c == null) return;
    if (typeof c === 'string') e.appendChild(document.createTextNode(c)); else e.appendChild(c);
  });
  return e;
}

// Views
router.register('#/', async (root) => {
  root.innerHTML = '';
  // Hero section with carousel if images available
  const hero = el('section', { class: 'hero position-relative overflow-hidden rounded-3 mb-4' });
  const heroInner = el('div', { class: 'hero-inner container position-relative py-5' },
    el('div', { class: 'row align-items-center' },
      el('div', { class: 'col-lg-7' },
          // Title intentionally removed per request
        el('p', { class: 'lead mb-4' }, "Parcourez les cartes Riftbound, gérez votre collection et suivez les nouvelles extensions. Projet fan, non affilié."),
        el('div', { class: 'd-flex gap-2' },
          el('a', { class: 'btn btn-primary btn-lg', href: '#/cartes' }, 'Parcourir les cartes'),
          el('a', { class: 'btn btn-outline-secondary btn-lg', href: '#/collection' }, 'Ma collection')
        )
      )
    )
  );
  hero.append(heroInner);

  root.append(hero);

  // Features
  const features = el('section', { class: 'py-4' },
    el('div', { class: 'container' },
      el('div', { class: 'row g-3' },
        el('div', { class: 'col-md-3' }, el('div', { class: 'feature card h-100 p-3' }, el('div', { class: 'h5' }, 'Cartothèque'), el('p', { class: 'mb-0 text-muted' }, 'Base officielle via API, recherche et filtres.'))),
        el('div', { class: 'col-md-3' }, el('div', { class: 'feature card h-100 p-3' }, el('div', { class: 'h5' }, 'Ma collection'), el('p', { class: 'mb-0 text-muted' }, 'Suivez vos cartes possédées et manquantes.'))),
        el('div', { class: 'col-md-3' }, el('div', { class: 'feature card h-100 p-3' }, el('div', { class: 'h5' }, 'Statistiques'), el('p', { class: 'mb-0 text-muted' }, 'Progression globale, raretés et sets.'))),
        el('div', { class: 'col-md-3' }, el('div', { class: 'feature card h-100 p-3' }, el('div', { class: 'h5' }, 'Actus'), el('p', { class: 'mb-0 text-muted' }, 'Soyez informé des extensions et événements.')))
      )
    )
  );
  root.append(features);

  // Latest expansions preview
  const expSec = el('section', { class: 'py-2' }, el('div', { class: 'container' }, el('h3', { class: 'mb-3' }, 'Dernières extensions'), el('div', { class: 'row g-3', id: 'exp-list' })));
  root.append(expSec);

  // Load expansions
  try {
    const data = await api.expansionsList();
    const list = expSec.querySelector('#exp-list');
    list.innerHTML = '';
    (data.expansions || []).slice(0, 3).forEach(e => {
      const date = e.released_at ? new Date(e.released_at * 1000).toLocaleDateString('fr-FR') : '—';
      list.append(
        el('div', { class: 'col-md-4' },
          el('div', { class: 'card h-100' },
            el('div', { class: 'card-body d-flex flex-column' },
              el('div', { class: 'h5' }, e.name),
              el('div', { class: 'text-muted mb-3' }, e.code + ' · ' + date),
              el('div', { class: 'mt-auto' }, el('a', { class: 'btn btn-sm btn-outline-secondary', href: '#/actus' }, 'Voir toutes les actus'))
            )
          )
        )
      );
    });
  } catch {}

  // No carousel and no image overlay: hero background is defined in CSS only
});

router.register('#/connexion', (root) => {
  root.innerHTML = '';
  const email = el('input', { type: 'email', class: 'form-control', placeholder: 'email@exemple.com', required: true });
  const pass = el('input', { type: 'password', class: 'form-control', placeholder: 'Mot de passe (min 8)', required: true, minlength: 8 });
  const alert = el('div', { class: 'alert alert-danger d-none', role: 'alert' });
  const submitLogin = el('button', { class: 'btn btn-primary' }, 'Se connecter');
  const submitRegister = el('button', { class: 'btn btn-outline-secondary' }, "S'inscrire");
  submitLogin.addEventListener('click', async () => {
    alert.classList.add('d-none');
    try {
      await api.login(email.value.trim(), pass.value);
      location.hash = '#/';
      await bootstrapAuthUi();
    } catch (e) {
      alert.textContent = e.message || 'Erreur';
      alert.classList.remove('d-none');
    }
  });
  submitRegister.addEventListener('click', async () => {
    alert.classList.add('d-none');
    try {
      await api.register(email.value.trim(), pass.value);
      location.hash = '#/';
      await bootstrapAuthUi();
    } catch (e) {
      alert.textContent = e.message || 'Erreur';
      alert.classList.remove('d-none');
    }
  });
  root.append(
    el('h2', {}, 'Connexion / Inscription'),
    alert,
    el('div', { class: 'vstack gap-3', style: 'max-width:420px' },
      el('div', {}, el('label', { class: 'form-label' }, 'Email'), email),
      el('div', {}, el('label', { class: 'form-label' }, 'Mot de passe'), pass),
      el('div', { class: 'd-flex gap-2' }, submitLogin, submitRegister)
    )
  );
});

router.register('#/cartes', async (root) => {
  root.innerHTML = '';
  const q = el('input', { class: 'form-control', placeholder: 'Recherche (nom)' });
  const rarity = el('select', { class: 'form-select' },
    el('option', { value: '' }, 'Toutes raretés'),
    el('option', { value: 'common' }, 'Commune'),
    el('option', { value: 'rare' }, 'Rare'),
    el('option', { value: 'epic' }, 'Épique'),
    el('option', { value: 'legendary' }, 'Légendaire'),
  );
  const set = el('input', { class: 'form-control', placeholder: "Set (ex: RB1)" });
  const results = el('div', { class: 'row g-3 mt-3' });
  const actions = el('div', { class: 'd-flex justify-content-between align-items-center mt-3' });
  const syncBtn = el('button', { class: 'btn btn-sm btn-outline-primary' }, 'Synchroniser');
  syncBtn.addEventListener('click', async () => { try { syncBtn.disabled = true; await api.cardsRefresh(); await load(); } catch(e){ toast('Sync échouée', true);} finally { syncBtn.disabled = false; } });
  const pager = el('div', { class: 'd-flex align-items-center gap-3' });
  let page = 1;
  async function load() {
    results.innerHTML = 'Chargement…';
    try {
      const data = await api.cardsList(q.value.trim(), rarity.value, set.value.trim(), page, 30);
      results.innerHTML = '';
      data.items.forEach(card => {
        results.append(
          el('div', { class: 'col-6 col-sm-4 col-md-3 col-lg-2' },
            el('div', { class: 'card h-100' },
              el('img', { src: card.image_url || 'assets/img/card-placeholder.svg', class: 'card-img-top', alt: card.name }),
              el('div', { class: 'card-body p-2' },
                el('div', { class: 'small fw-semibold' }, card.name || card.id),
                el('div', { class: 'small text-muted' }, (card.rarity || '—') + ' · ' + (card.set_code || '—')),
                el('div', { class: 'mt-2 d-flex gap-2' },
                  el('button', { class: 'btn btn-sm btn-outline-primary', onclick: async () => {
                    try {
                      await api.collectionSet(card.id, 1);
                      toast('Ajouté à la collection');
                    } catch (e) { toast('Erreur: ' + e.message, true); }
                  }}, '+1'),
                  el('a', { class: 'btn btn-sm btn-outline-secondary', href: '#/collection' }, 'Voir ma collection')
                )
              )
            )
          )
        );
      });
      const totalPages = Math.max(1, Math.ceil(data.total / data.pageSize));
      pager.innerHTML = '';
      pager.append(
        el('button', { class: 'btn btn-outline-secondary', disabled: page <= 1, onclick: () => { page = Math.max(1, page - 1); load(); } }, 'Précédent'),
        el('div', {}, `Page ${page} / ${totalPages}`),
        el('button', { class: 'btn btn-outline-secondary', disabled: page >= totalPages, onclick: () => { page = Math.min(totalPages, page + 1); load(); } }, 'Suivant')
      );
    } catch (e) {
      results.innerHTML = '<div class="alert alert-danger">' + (e.message || 'Erreur') + '</div>';
    }
  }
  const searchBar = el('div', { class: 'row g-2' },
    el('div', { class: 'col-sm-6 col-md-5 col-lg-6' }, q),
    el('div', { class: 'col-sm-3 col-md-3 col-lg-3' }, rarity),
    el('div', { class: 'col-sm-3 col-md-2 col-lg-2' }, set),
    el('div', { class: 'col-12 col-md-2 col-lg-1 d-grid' }, el('button', { class: 'btn btn-primary', onclick: () => { page = 1; load(); } }, 'Rechercher'))
  );
  actions.append(syncBtn, pager);
  root.append(el('h2', {}, 'Cartes'), searchBar, results, actions);
  load();
});

router.register('#/collection', async (root) => {
  root.innerHTML = '';
  const alert = el('div', { class: 'alert alert-info' }, "Connectez-vous pour gérer votre collection.");
  try {
    const me = await api.me();
    alert.remove();
    const list = el('div', { class: 'row g-3' });
    async function load() {
      list.innerHTML = 'Chargement…';
      const data = await api.collectionGet();
      list.innerHTML = '';
      data.items.forEach(it => {
        list.append(
          el('div', { class: 'col-6 col-sm-4 col-md-3 col-lg-2' },
            el('div', { class: 'card h-100' },
              el('img', { src: it.image_url || 'assets/img/card-placeholder.svg', class: 'card-img-top', alt: it.name }),
              el('div', { class: 'card-body p-2' },
                el('div', { class: 'small fw-semibold' }, it.name || it.card_id),
                el('div', { class: 'small text-muted' }, (it.rarity || '—') + ' · ' + (it.set_code || '—')),
                el('div', { class: 'input-group input-group-sm mt-2' },
                  el('button', { class: 'btn btn-outline-secondary', onclick: async () => { const n = Math.max(0, (it.qty||0) - 1); await api.collectionSet(it.card_id, n); it.qty = n; qty.value = n; } }, '−'),
                  (function(){ const inp = el('input', { type: 'number', min: '0', value: it.qty || 0, class: 'form-control text-center' }); var qty = inp; return inp; })(),
                  el('button', { class: 'btn btn-outline-secondary', onclick: async () => { const n = (it.qty||0) + 1; await api.collectionSet(it.card_id, n); it.qty = n; qty.value = n; } }, '+')
                )
              )
            )
          )
        );
      });
    }
    root.append(el('h2', {}, 'Ma collection'), list);
    load();
  } catch (e) {
    root.append(alert);
  }
});

router.register('#/stats', async (root) => {
  root.innerHTML = '';
  try {
    const me = await api.me();
    const data = await api.statsProgress();
    root.append(
      el('h2', {}, 'Statistiques'),
      el('div', { class: 'mb-3' }, `Progression globale: ${data.global.owned} / ${data.global.total} (${data.global.percent}%)`),
      el('div', { class: 'row' },
        el('div', { class: 'col-md-6' },
          el('h5', {}, 'Par rareté'),
          el('ul', { class: 'list-group' }, ...data.byRarity.map(r => el('li', { class: 'list-group-item d-flex justify-content-between align-items-center' }, `${r.rarity || '—'}`, el('span', { class: 'badge bg-primary rounded-pill' }, `${r.owned}/${r.total} (${r.percent}%)`))))
        ),
        el('div', { class: 'col-md-6' },
          el('h5', {}, 'Par set'),
          el('ul', { class: 'list-group' }, ...data.bySet.map(s => el('li', { class: 'list-group-item d-flex justify-content-between align-items-center' }, `${s.set || '—'}`, el('span', { class: 'badge bg-secondary rounded-pill' }, `${s.owned}/${s.total} (${s.percent}%)`))))
        )
      )
    );
  } catch (e) {
    root.append(el('div', { class: 'alert alert-info' }, 'Connectez-vous pour voir vos stats.'));
  }
});

router.register('#/actus', async (root) => {
  root.innerHTML = '';
  const list = el('div', { class: 'vstack gap-2' });
  root.append(el('h2', {}, 'Extensions & Événements'), list);
  try {
    const data = await api.expansionsList();
    list.innerHTML = '';
    data.expansions.forEach(e => {
      const date = e.released_at ? new Date(e.released_at * 1000).toLocaleDateString('fr-FR') : '—';
      list.append(el('div', { class: 'card' }, el('div', { class: 'card-body d-flex justify-content-between' }, el('div', {}, el('div', { class: 'h5' }, e.name), el('div', { class: 'text-muted' }, e.code)), el('div', { class: 'text-muted' }, date))));
    });
  } catch (e) {
    list.innerHTML = '<div class="alert alert-danger">Impossible de charger les extensions.</div>';
  }
});

router.register('#/compte', async (root) => {
  root.innerHTML = '';
  const me = await api.me().catch(() => null);
  if (!me) { root.append(el('div', { class: 'alert alert-info' }, 'Connectez-vous.')); return; }
  const subToggle = el('input', { type: 'checkbox', class: 'form-check-input' });
  subToggle.addEventListener('change', async () => {
    try { await api.subscribe(subToggle.checked); toast('Préférence sauvegardée'); } catch (e) { toast('Erreur', true); }
  });
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) logoutBtn.onclick = async () => { await api.logout(); await bootstrapAuthUi(); location.hash = '#/'; };
  root.append(
    el('h2', {}, 'Mon compte'),
    el('div', { class: 'form-check form-switch' }, subToggle, el('label', { class: 'form-check-label ms-2' }, 'Recevoir des notifications des nouvelles extensions'))
  );
});

router.register('#/404', (root) => { root.innerHTML = '<div class="alert alert-warning">Page introuvable.</div>'; });

// Small toast helper
window.toast = function(msg, error) {
  const d = document.createElement('div');
  d.className = 'toast align-items-center text-bg-' + (error ? 'danger' : 'success') + ' border-0 position-fixed bottom-0 end-0 m-3 show';
  d.setAttribute('role', 'alert');
  d.innerHTML = '<div class="d-flex">\n    <div class="toast-body">' + msg + '</div>\n    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>\n  </div>';
  document.body.appendChild(d);
  setTimeout(() => d.remove(), 3000);
};

// Init
bootstrapAuthUi();
router.start();

// Hook logout in navbar
const logoutBtn = document.getElementById('logoutBtn');
if (logoutBtn) logoutBtn.addEventListener('click', async (e) => {
  e.preventDefault();
  try { await api.logout(); setAccountUi(null); location.hash = '#/'; } catch {}
});
