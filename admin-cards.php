<?php
require_once __DIR__ . '/inc/Config.php';
require_once __DIR__ . '/inc/Database.php';
require_once __DIR__ . '/inc/Auth.php';

use RiftCollect\Config; use RiftCollect\Database; use RiftCollect\Auth;
Config::init(); Database::instance(); Auth::init();
if (!Auth::isAdmin()) { http_response_code(403); echo '<h2>Accès administrateur requis</h2>'; exit; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8" />
<title>Admin Cartes - RiftCollect</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="assets/css/style.css" />
<style>
body{background:#0f1113;color:#e2e8f0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;}
.page-wrap{max-width:1400px;margin:0 auto;padding:1.2rem 1rem;}
h1{font-size:1.4rem;font-weight:600;}
header{display:flex;gap:1rem;align-items:center;flex-wrap:wrap;}
input,select,button,textarea{padding:.4rem .6rem;font-size:.75rem;}
table.table-dark td,table.table-dark th{border-color:#2a3136;font-size:.65rem;vertical-align:middle;}
table.table-dark thead th{background:#222a30;}
.row-editing{background:#1e293b!important;}
.status{margin-left:auto;font-size:.7rem;}
.price-input{width:5rem;}
.rarity-select{min-width:7rem;}
.actions button{margin-right:.25rem;}
textarea{width:100%;background:#101418;color:#f1f5f9;border:1px solid #2a3136;}
textarea:focus,input:focus,select:focus{outline:2px solid #2563eb;}
footer{margin-top:2rem;font-size:.65rem;color:#94a3b8;}
.card-tools{background:#1b1f24;border:1px solid #2a3136;padding:.75rem;border-radius:6px;}
.search-inline input,.search-inline select{background:#101418;color:#f1f5f9;border:1px solid #2a3136;}
.search-inline button{background:#2563eb;color:#fff;border:none;border-radius:4px;}
.search-inline button:hover{background:#1d4ed8;}
.mini-img{width:38px;height:auto;border-radius:4px;border:1px solid #2a3136;background:#0f1113;}
.table-wrap{background:#1b1f24;border:1px solid #2a3136;border-radius:6px;overflow:hidden;}
</style>
</style>
</head>
<body>
<?php // Inject only the navbar from index.php (manual copy to avoid full HTML duplication) ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php#/"><img src="assets/img/logo.png" alt="RiftCollect" style="height:34px"/><span class="visually-hidden">RiftCollect</span></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="index.php#/cartes">Cartes</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#/collection">Ma collection</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#/stats">Statistiques</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#/actus">Actus</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#/compte">Compte</a></li>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="index.php#/"><small>Retour site</small></a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="page-wrap">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="m-0">Administration des cartes</h1>
    <div class="status" id="status"></div>
  </div>
  <div class="card-tools mb-3">
    <form id="searchForm" onsubmit="return false" class="search-inline d-flex flex-wrap gap-2 align-items-end">
      <div class="flex-grow-1 min-w-200">
        <label class="form-label mb-1">Recherche</label>
        <input type="text" id="q" class="form-control form-control-sm" placeholder="Nom ou ID (OGN-030)" />
      </div>
      <div>
        <label class="form-label mb-1">Rareté</label>
        <select id="rarity" class="form-select form-select-sm">
          <option value="">Toutes</option>
          <option>Common</option><option>Uncommon</option><option>Rare</option><option>Epic</option><option>Legendary</option><option>Overnumbered</option>
        </select>
      </div>
      <div>
        <label class="form-label mb-1">Set</label>
        <input type="text" id="set" class="form-control form-control-sm" placeholder="OGN" />
      </div>
      <div class="d-flex gap-2">
        <button type="button" id="btnSearch" class="btn btn-sm btn-primary">Chercher</button>
        <button type="button" id="btnReset" class="btn btn-sm btn-outline-secondary">Reset</button>
      </div>
    </form>
  </div>
  <div class="table-wrap">
    <table class="table table-dark table-hover mb-0" id="cardsTable">
      <thead>
        <tr><th>ID</th><th>Nom</th><th>Rareté</th><th>Prix</th><th>Set</th><th>Couleur</th><th>Type</th><th>Description</th><th>Image</th><th style="width:70px">Actions</th></tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
  <footer class="mt-3">Edition rapide des métadonnées (nom, rareté, prix, etc.).</footer>
</div>
<script>
const apiBase = 'api.php';
const statusEl = document.getElementById('status');
function setStatus(msg, ok=true){ statusEl.textContent = msg; statusEl.style.color = ok?'#22c55e':'#ef4444'; }
async function apiGet(params){
  const url = apiBase + '?' + new URLSearchParams(params).toString();
  const r = await fetch(url);
  return r.json();
}
async function apiPost(params){
  const fd = new FormData();
  Object.entries(params).forEach(([k,v])=>fd.append(k,v));
  const r = await fetch(apiBase, {method:'POST', body:fd});
  return r.json();
}
async function loadCards(page=1){
  setStatus('Chargement...');
  const q = document.getElementById('q').value.trim();
  const rarity = document.getElementById('rarity').value.trim();
  const set = document.getElementById('set').value.trim();
  try{
    const js = await apiGet({action:'cards.list', q, rarity, set, page, pageSize:50});
    if(!js.ok) throw new Error(js.error||'Erreur');
    renderCards(js.data.items||[]);
    setStatus((js.data.total||0)+' cartes');
  }catch(e){ setStatus(e.message,false); }
}
function renderCards(items){
  const tb = document.querySelector('#cardsTable tbody');
  tb.innerHTML='';
  items.forEach(c=>{
    const tr=document.createElement('tr');
    tr.innerHTML = `
      <td><code>${c.id||''}</code></td>
      <td><input type=\"text\" class=\"form-control form-control-sm bg-dark text-light\" value=\"${escapeHtml(c.name||'')}\" data-field=\"name\" /></td>
      <td><select class=\"form-select form-select-sm bg-dark text-light rarity-select\" data-field=\"rarity\">${rarityOptions(c.rarity)}</select></td>
      <td><input type=\"number\" step=\"0.01\" class=\"form-control form-control-sm bg-dark text-light price-input\" value=\"${c.price!=null?c.price:''}\" data-field=\"price\" /></td>
      <td><input type=\"text\" class=\"form-control form-control-sm bg-dark text-light\" value=\"${escapeHtml(c.set_code||'')}\" data-field=\"set_code\" /></td>
      <td><input type=\"text\" class=\"form-control form-control-sm bg-dark text-light\" value=\"${escapeHtml(c.color||'')}\" data-field=\"color\" /></td>
      <td><input type=\"text\" class=\"form-control form-control-sm bg-dark text-light\" value=\"${escapeHtml(c.card_type||'')}\" data-field=\"card_type\" /></td>
      <td><textarea rows=\"2\" class=\"form-control form-control-sm bg-dark text-light\" data-field=\"description\">${escapeHtml(c.description||'')}</textarea></td>
      <td>${c.image_url?`<img src=\"${c.image_url}\" alt=\"img\" class=\"mini-img\"/>`:''}</td>
      <td class=\"actions\"><button type=\"button\" class=\"btn btn-sm btn-outline-secondary\" data-action=\"save\">💾</button></td>`;
    tr.querySelector('[data-action=\"save\"]').addEventListener('click',()=>saveRow(tr,c.id));
    tb.appendChild(tr);
  });
}
function rarityOptions(sel){
  const list=['','Common','Uncommon','Rare','Epic','Legendary','Overnumbered'];
  return list.map(r=>`<option value=\"${r}\" ${r.toLowerCase()===(sel||'').toLowerCase()?'selected':''}>${r||'-'}</option>`).join('');
}
function escapeHtml(str){ return (str||'').replace(/[&<>\"']/g,s=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[s])); }
async function saveRow(tr,id){
  tr.classList.add('row-editing');
  const inputs=[...tr.querySelectorAll('[data-field]')];
  const payload={action:'admin.cards.update', id};
  inputs.forEach(inp=>{ payload[inp.getAttribute('data-field')] = inp.value; });
  try{
    const js = await apiPost(payload);
    if(!js.ok) throw new Error(js.error||'Echec');
    setStatus('Carte '+id+' mise à jour');
    tr.classList.remove('row-editing');
  }catch(e){ setStatus(e.message,false); tr.classList.remove('row-editing'); }
}
// Initial load
loadCards();
document.getElementById('btnSearch').addEventListener('click',()=>loadCards());
document.getElementById('btnReset').addEventListener('click',()=>{ document.getElementById('q').value=''; document.getElementById('rarity').value=''; document.getElementById('set').value=''; loadCards(); });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>