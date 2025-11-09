<?php
require_once __DIR__ . '/inc/Config.php';
require_once __DIR__ . '/inc/Database.php';
require_once __DIR__ . '/inc/Auth.php';

use RiftCollect\Config; use RiftCollect\Database; use RiftCollect\Auth;
Config::init(); Database::instance(); Auth::init();
if (!Auth::isAdmin()) { http_response_code(403); echo '<h2>Accès administrateur requis</h2>'; exit; }
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8" />
<title>Admin Articles - RiftCollect</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="assets/css/style.css" />
<style>
body{background:#0f1113;color:#e2e8f0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;}
.page-wrap{max-width:1400px;margin:0 auto;padding:1.25rem 1rem;}
h1{font-size:1.55rem;font-weight:600;}
#editorCard,#listCard{background:#1b1f24;border:1px solid #2a3136;box-shadow:0 1px 2px rgba(0,0,0,.4);}
#editorCard .card-header,#listCard .card-header{background:#222a30;border-bottom:1px solid #2a3136;}
label span.req{color:#fb7185;}
#status{font-size:.75rem;min-height:1rem;margin-bottom:.5rem;}
textarea#content{min-height:230px;resize:vertical;font-family:monospace;background:#101418;color:#f1f5f9;border:1px solid #2a3136;}
input[type=text]{background:#101418;color:#f1f5f9;border:1px solid #2a3136;}
input[type=text]:focus,textarea#content:focus{outline:2px solid #2563eb;}
table.table-dark thead th{background:#222a30;border-color:#2a3136;}
table.table-dark td,table.table-dark th{border-color:#2a3136;font-size:.72rem;vertical-align:middle;}
.btn-primary{background:#2563eb;border-color:#2563eb;}
.btn-outline-secondary{border-color:#4b5563;color:#d1d5db;}
.btn-outline-secondary:hover{background:#374151;color:#fff;}
.btn-outline-danger{border-color:#dc2626;color:#fca5a5;}
.btn-outline-danger:hover{background:#dc2626;color:#fff;}
#preview{background:#12171c;border:1px solid #2a3136;padding:.6rem .75rem;min-height:230px;overflow:auto;font-size:.78rem;border-radius:4px;}
#preview.empty:before{content:'Aperçu (markdown)';color:#475569;font-style:italic;}
.article-row.draft td{opacity:.55;}
.muted{color:#94a3b8!important;}
@media (max-width:1200px){.layout-cols{flex-direction:column;}}
/* CKEditor dark theme overrides */
.ck.ck-reset_all, .ck.ck-reset_all * { color: #e2e8f0; }
.ck.ck-editor__main>.ck-editor__editable { background:#101418; color:#e2e8f0; border-color:#2a3136; }
.ck.ck-toolbar { background:#1b1f24; border-color:#2a3136; }
.ck.ck-toolbar .ck-toolbar__separator { background:#2a3136; }
.ck.ck-button, .ck.ck-dropdown__button { color:#e2e8f0; }
.ck.ck-button:hover, .ck.ck-button.ck-on, .ck.ck-dropdown__button:hover { background:#222a30; }
.ck.ck-dropdown .ck-dropdown__panel { background:#1b1f24; border-color:#2a3136; }
.ck.ck-list__item .ck-button { color:#e2e8f0; }
.ck.ck-list__item .ck-button:hover { background:#222a30; }
.ck.ck-editor__editable:not(.ck-editor__nested-editable) { min-height: 300px; }
</style>
</head>
<body>
<?php // Top navbar copied from site for consistency on admin pages ?>
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
  <h1 class="mb-3">Administration des articles</h1>
  <div id="status" class="text-info"></div>
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="small muted">Gérez vos actualités. Cliquez sur ✏️ pour modifier, ou créez un nouvel article.</div>
    <button class="btn btn-sm btn-primary" id="newArticleBtn">Créer un article</button>
  </div>
  <div class="d-flex gap-3 layout-cols">
    <div class="flex-grow-1">
      <div id="listCard" class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div class="fw-semibold">Liste des articles</div>
          <div class="small muted" id="listMeta"></div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-dark table-hover mb-0" id="articlesTable">
              <thead class="small">
                <tr><th>ID</th><th>Titre</th><th>Rédacteur</th><th>Slug</th><th>Src</th><th>Img</th><th>Guide</th><th>Pub</th><th>Créé</th><th style="width:90px">Actions</th></tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Modal: Create/Edit Article -->
  <div class="modal fade" id="articleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content" style="background:#1b1f24;border:1px solid #2a3136;">
        <div class="modal-header" style="border-bottom:1px solid #2a3136;">
          <h5 class="modal-title" id="editorTitle">Nouvel article</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="editor" onsubmit="return false;" class="row g-3">
            <input type="hidden" id="articleId" />
            <div class="col-12 col-md-8">
              <label class="form-label">Titre <span class="req">*</span></label>
              <input type="text" id="title" required placeholder="Titre" class="form-control form-control-sm" />
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end">
              <div class="d-flex gap-4 align-items-center ms-md-3">
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="published">
                  <label class="form-check-label" for="published">Publié</label>
                </div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="is_guide">
                  <label class="form-check-label" for="is_guide">Guide</label>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-8">
              <label class="form-label">Sous-titre</label>
              <input type="text" id="subtitle" placeholder="Sous-titre" class="form-control form-control-sm" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Date de publication</label>
              <input type="datetime-local" id="created_at" class="form-control form-control-sm" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Rédacteur</label>
              <input type="text" id="redacteur" placeholder="Nom du rédacteur" class="form-control form-control-sm" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Source (URL)</label>
              <input type="url" id="source" placeholder="https://…" class="form-control form-control-sm" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Image (URL)</label>
              <input type="url" id="image_url" placeholder="https://…/image.jpg" class="form-control form-control-sm" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Aperçu image</label>
              <div class="border rounded p-2 text-center" style="background:#12171c;border-color:#2a3136;min-height:130px;">
                <img id="image_preview" src="" alt="" style="max-width:100%;max-height:120px;display:none;border-radius:4px;" />
                <div id="no_image" class="small muted">Aucune image</div>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Contenu</label>
              <textarea id="content" placeholder="Votre contenu..." class="form-control form-control-sm"></textarea>
            </div>
          </form>
        </div>
        <div class="modal-footer" style="border-top:1px solid #2a3136;">
          <div class="me-auto small muted" id="editorMeta"></div>
          <button type="button" id="deleteBtn" class="btn btn-sm btn-outline-danger d-none">Supprimer</button>
          <button type="button" id="resetBtn" class="btn btn-sm btn-outline-secondary">Réinitialiser</button>
          <button type="button" id="saveBtn" class="btn btn-sm btn-primary">Enregistrer</button>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<!-- CKEditor 5 Classic -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script>
const apiBase='api.php';
function setStatus(msg, ok=true){ const el=document.getElementById('status'); el.textContent=msg; el.style.color=ok?'#22c55e':'#ef4444'; }
async function apiGet(params){ const url=apiBase+'?'+new URLSearchParams(params); const r=await fetch(url); return r.json(); }
async function apiPost(params){ const fd=new FormData(); Object.entries(params).forEach(([k,v])=>fd.append(k,v)); const r=await fetch(apiBase,{method:'POST',body:fd}); return r.json(); }
function escapeHtml(str){ return (str||'').replace(/[&<>"']/g,s=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[s])); }
async function loadArticles(page=1){
  setStatus('Chargement des articles...');
  try{
    const js=await apiGet({action:'articles.list', page, pageSize:50, all:1});
    if(!js.ok) throw new Error(js.error||'Erreur liste');
    renderArticles(js.data.items||[]);
    setStatus((js.data.total||0)+' articles');
  }catch(e){ setStatus(e.message,false); }
}
function renderArticles(items){ const tb=document.querySelector('#articlesTable tbody'); tb.innerHTML=''; let pubCount=0; items.forEach(a=>{ if(a.published==1) pubCount++; const tr=document.createElement('tr'); if(a.published!=1) tr.classList.add('article-row','draft'); const created=a.created_at?new Date(a.created_at*1000).toLocaleDateString():''; const srcIcon=a.source?`<a href="${escapeHtml(a.source)}" target="_blank" rel="noopener" title="Source" class="text-info">🔗</a>`:'—'; const imgIcon=a.image_url?`<span title="Image">🖼️</span>`:'—'; const guideIcon=a.is_guide==1?'📘':'—'; tr.innerHTML=`<td>${a.id}</td><td>${escapeHtml(a.title||'')}</td><td>${escapeHtml(a.redacteur||'')}</td><td><code>${escapeHtml(a.slug||'')}</code></td><td class="text-center">${srcIcon}</td><td class="text-center">${imgIcon}</td><td class="text-center">${guideIcon}</td><td>${a.published==1?'✔️':'—'}</td><td>${created}</td><td class="d-flex gap-1"><button class="btn btn-sm btn-outline-secondary" data-act="edit" title="Éditer">✏️</button></td>`; tr.querySelector('[data-act="edit"]').addEventListener('click',()=>editArticle(a)); tb.appendChild(tr); }); const meta=document.getElementById('listMeta'); if(meta) meta.textContent=items.length+' articles ('+pubCount+' publiés)'; }

// CKEditor instance
let _editor = null;
function initEditor(){
  if (_editor) return Promise.resolve(_editor);
  return ClassicEditor.create(document.getElementById('content'), {
    toolbar: [ 'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'blockQuote', 'insertTable', 'undo', 'redo' ]
  }).then(ed => { _editor = ed; return ed; });
}

function tsToLocalInput(ts){ if(!ts) return ''; const d=new Date(ts*1000); const pad=n=>String(n).padStart(2,'0'); return d.getFullYear()+"-"+pad(d.getMonth()+1)+"-"+pad(d.getDate())+"T"+pad(d.getHours())+":"+pad(d.getMinutes()); }
function localInputToTs(val){ if(!val) return null; const ms=Date.parse(val); return isNaN(ms)?null:Math.floor(ms/1000); }

async function editArticle(a){ await initEditor(); document.getElementById('articleId').value=a.id; document.getElementById('title').value=a.title||''; _editor.setData(a.content||''); document.getElementById('published').checked=(a.published==1); document.getElementById('is_guide').checked=(a.is_guide==1); document.getElementById('redacteur').value=a.redacteur||''; document.getElementById('source').value=a.source||''; document.getElementById('image_url').value=a.image_url||''; document.getElementById('subtitle').value=a.subtitle||''; document.getElementById('created_at').value=tsToLocalInput(a.created_at||0); updateImagePreview(); const del=document.getElementById('deleteBtn'); del.classList.remove('d-none'); const t=document.getElementById('editorTitle'); if(t) t.textContent='Éditer article'; const m=document.getElementById('editorMeta'); if(m) m.textContent='ID '+a.id+(a.published==1?' • publié':' • brouillon'); openModal(); setStatus('Edition article '+a.id); }
async function resetEditor(){ await initEditor(); document.getElementById('articleId').value=''; document.getElementById('title').value=''; _editor.setData(''); document.getElementById('published').checked=false; document.getElementById('is_guide').checked=false; document.getElementById('redacteur').value=''; document.getElementById('source').value=''; document.getElementById('image_url').value=''; document.getElementById('subtitle').value=''; document.getElementById('created_at').value=''; updateImagePreview(); const del=document.getElementById('deleteBtn'); del.classList.add('d-none'); const t=document.getElementById('editorTitle'); if(t) t.textContent='Nouvel article'; const m=document.getElementById('editorMeta'); if(m) m.textContent=''; }
async function saveArticle(){ await initEditor(); const id=document.getElementById('articleId').value.trim(); const title=document.getElementById('title').value.trim(); const content=_editor.getData(); const published=document.getElementById('published').checked?1:0; const is_guide=document.getElementById('is_guide').checked?1:0; const redacteur=document.getElementById('redacteur').value.trim(); const source=document.getElementById('source').value.trim(); const image_url=document.getElementById('image_url').value.trim(); const subtitle=document.getElementById('subtitle').value.trim(); const created_at_ts=localInputToTs(document.getElementById('created_at').value); if(title===''){ setStatus('Titre requis',false); return; } setStatus('Sauvegarde...'); try{ let js; const base={title, content, subtitle, published, is_guide, redacteur, source, image_url, ...(created_at_ts?{created_at:created_at_ts}:{})}; if(id){ js=await apiPost({action:'articles.update', id, ...base}); } else { js=await apiPost({action:'articles.create', ...base}); } if(!js.ok) throw new Error(js.error||'Erreur save'); setStatus('Article sauvegardé'); resetEditor(); closeModal(); loadArticles(); }catch(e){ setStatus(e.message,false); } }
async function deleteArticle(){ const id=document.getElementById('articleId').value.trim(); if(!id){ return; } if(!confirm("Supprimer l'article "+id+" ?")) return; setStatus('Suppression...'); try{ const js=await apiPost({action:'articles.delete', id}); if(!js.ok) throw new Error(js.error||'Erreur suppression'); setStatus('Article supprimé'); resetEditor(); closeModal(); loadArticles(); }catch(e){ setStatus(e.message,false); } }
// Modal helpers
let _articleModal = null;
function openModal(){ if(!_articleModal){ _articleModal = new bootstrap.Modal(document.getElementById('articleModal')); } _articleModal.show(); }
function closeModal(){ if(_articleModal){ _articleModal.hide(); } }
document.getElementById('newArticleBtn').addEventListener('click', ()=>{ resetEditor(); openModal(); setStatus('Nouveau article'); });
// Image preview helper
function updateImagePreview(){ const url=document.getElementById('image_url').value.trim(); const img=document.getElementById('image_preview'); const no=document.getElementById('no_image'); if(url){ img.src=url; img.style.display='inline-block'; no.style.display='none'; } else { img.src=''; img.style.display='none'; no.style.display='block'; } }
document.getElementById('image_url').addEventListener('input', updateImagePreview);
// Events
 document.getElementById('saveBtn').addEventListener('click',saveArticle); document.getElementById('resetBtn').addEventListener('click',resetEditor); document.getElementById('deleteBtn').addEventListener('click',deleteArticle);
// Init
initEditor().then(()=>{ resetEditor(); loadArticles(); });
</script>
</body>
</html>