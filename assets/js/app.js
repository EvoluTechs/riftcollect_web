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
    // expose admin flag for UI if needed
    document.body.dataset.isAdmin = (user.is_admin ? '1' : '0');
  } else {
    loginLink.style.display = '';
    accountDropdown.style.display = 'none';
    navUserEmail.textContent = '';
    if (navCollectionItem) navCollectionItem.style.display = 'none';
    if (navStatsItem) navStatsItem.style.display = 'none';
    document.body.dataset.isAdmin = '0';
  }
}

async function bootstrapAuthUi() {
  try {
    const data = await api.me();
    setAccountUi(data.user);
  } catch (e) {
    setAccountUi(null);
  }
  // Wire logout
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.onclick = async (ev) => {
      ev.preventDefault();
      try { await api.logout(); } catch {}
      await bootstrapAuthUi();
      location.hash = '#/';
    };
  }
}

function el(tag, attrs = {}, ...children) {
  const e = document.createElement(tag);
  Object.entries(attrs).forEach(([k, v]) => {
    if (v == null) return;
    if (k === 'class') {
      e.className = v;
    } else if (k.startsWith('on') && typeof v === 'function') {
      e.addEventListener(k.substring(2).toLowerCase(), v);
    } else if (typeof v === 'boolean') {
      if (v) e.setAttribute(k, '');
      // if boolean false, skip adding the attribute entirely
    } else {
      e.setAttribute(k, v);
    }
  });
  children.forEach(c => {
    if (c == null) return;
    if (typeof c === 'string') e.appendChild(document.createTextNode(c)); else e.appendChild(c);
  });
  return e;
}

// Global: Admin CDN scan modal (accessible from multiple routes)
async function openCdnScanModal(defaultSetArg = 'OGN', onFinish) {
  const defaultSet = /^[A-Z]{3}$/.test(String(defaultSetArg||'')) ? String(defaultSetArg).toUpperCase() : 'OGN';
  const modalWrap = document.createElement('div');
  modalWrap.className = 'modal fade';
  modalWrap.setAttribute('tabindex', '-1');
  modalWrap.setAttribute('aria-hidden', 'true');
  modalWrap.innerHTML = `
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark text-light">
          <div class="modal-header">
            <h5 class="modal-title">Synchronisation des cartes</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="min-height:260px;">
            <div class="container-fluid">
              <div class="row g-2 mb-2">
                <div class="col-md-4">
                  <label class="form-label">Sets</label>
                  <input id="optSets" class="form-control form-control-sm" value="${defaultSet}" placeholder="ex: OGN, RB1" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Plage</label>
                  <input id="optRange" class="form-control form-control-sm" value="1-300" placeholder="ex: 1-300 ou 1,5,10-20" />
                </div>
                <div class="col-md-4">
                  <label class="form-label">Fichier image (ext)</label>
                  <input id="optExt" class="form-control form-control-sm" value="full-desktop.jpg" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Délai (ms)</label>
                  <input id="optDelay" type="number" min="0" class="form-control form-control-sm" value="150" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Max secondes</label>
                  <input id="optMaxSec" type="number" min="0" class="form-control form-control-sm" value="25" />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Limite trouvées</label>
                  <input id="optLimit" type="number" min="0" class="form-control form-control-sm" value="0" />
                </div>
                <div class="col-md-3 d-flex align-items-end gap-3">
                  <div class="form-check">
                    <input id="optAsync" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="optAsync">Asynchrone</label>
                  </div>
                </div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-md-3 d-flex align-items-end gap-3">
                  <div class="form-check">
                    <input id="optForce" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="optForce">Écraser images</label>
                  </div>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-3">
                  <div class="form-check">
                    <input id="optAltOnly" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="optAltOnly">Alternatives uniquement</label>
                  </div>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-3">
                  <div class="form-check">
                    <input id="optLlm" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="optLlm">IA (Vision)</label>
                  </div>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Timeout IA (s)</label>
                  <input id="optLlmTimeout" type="number" min="5" class="form-control form-control-sm" value="30" />
                </div>
                <div class="col-md-3 d-flex align-items-end gap-3">
                  <div class="form-check">
                    <input id="optSaveAi" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="optSaveAi">Sauver JSON IA</label>
                  </div>
                  <div class="form-check">
                    <input id="optPrintAi" class="form-check-input" type="checkbox" />
                    <label class="form-check-label" for="optPrintAi">Afficher JSON IA</label>
                  </div>
                </div>
                <div class="col-12">
                  <div class="d-flex flex-wrap gap-3 align-items-center">
                    <div class="form-check">
                      <input id="optLlmRetryOnly" class="form-check-input" type="checkbox" />
                      <label class="form-check-label" for="optLlmRetryOnly">IA: relancer uniquement les échecs</label>
                    </div>
                    <div class="form-check">
                      <input id="optLlmOverwrite" class="form-check-input" type="checkbox" />
                      <label class="form-check-label" for="optLlmOverwrite">IA: forcer la ré-analyse</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <pre id="syncLog" class="mb-0 mt-2 border rounded p-2" style="white-space:pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.85rem; max-height:48vh; overflow:auto;">Prêt. Cliquez sur Lancer.\n</pre>
          </div>
          <div class="modal-footer d-flex justify-content-between">
            <div class="small text-muted">Conseil: laissez l'IA décochée pour une synchro rapide.</div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
              <button type="button" class="btn btn-outline-danger" id="syncCancelBtn" disabled>Annuler</button>
              <button type="button" class="btn btn-primary" id="syncStartBtn">Lancer</button>
            </div>
          </div>
        </div>
      </div>`;
  document.body.appendChild(modalWrap);
  const ModalCtor = (window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal : null;
  const modal = ModalCtor ? new ModalCtor(modalWrap) : null;
  const logEl = modalWrap.querySelector('#syncLog');
  const cancelBtn = modalWrap.querySelector('#syncCancelBtn');
  const startBtn = modalWrap.querySelector('#syncStartBtn');
  const get = (id) => modalWrap.querySelector(id);
  const inputs = {
    sets: get('#optSets'),
    range: get('#optRange'),
    ext: get('#optExt'),
    delay: get('#optDelay'),
    maxSec: get('#optMaxSec'),
    limit: get('#optLimit'),
    async: get('#optAsync'),
    force: get('#optForce'),
  altOnly: get('#optAltOnly'),
    llm: get('#optLlm'),
    llmTimeout: get('#optLlmTimeout'),
    saveAI: get('#optSaveAi'),
    printAI: get('#optPrintAi'),
    llmRetryOnly: get('#optLlmRetryOnly'),
    llmOverwrite: get('#optLlmOverwrite'),
  };
  const setDisabled = (v) => {
    Object.values(inputs).forEach(el => el.disabled = v);
    startBtn.disabled = v;
    cancelBtn.disabled = !v;
  };
  if (modal) modal.show(); else modalWrap.style.display = 'block';

  let controller = null;
  async function run() {
    const params = new URLSearchParams({
      sets: (inputs.sets.value || 'OGN').trim(),
      range: (inputs.range.value || '1-300').trim(),
      ext: (inputs.ext.value || 'full-desktop.jpg').trim(),
      out: 'text',
      delay: String(Math.max(0, parseInt(inputs.delay.value||'0', 10) || 0)),
      llm: inputs.llm.checked ? '1' : '0',
      forceimg: inputs.force.checked ? '1' : '0',
  altOnly: inputs.altOnly.checked ? '1' : '0',
      maxSec: String(Math.max(0, parseInt(inputs.maxSec.value||'0', 10) || 0)),
      limit: String(Math.max(0, parseInt(inputs.limit.value||'0', 10) || 0)),
      async: inputs.async.checked ? '1' : '0',
      llmTimeout: String(Math.max(5, parseInt(inputs.llmTimeout.value||'30', 10) || 30)),
      saveAI: inputs.saveAI.checked ? '1' : '0',
      printAI: inputs.printAI.checked ? '1' : '0',
      llmRetryOnly: inputs.llmRetryOnly.checked ? '1' : '0',
      llmOverwrite: inputs.llmOverwrite.checked ? '1' : '0',
    });
    // Progress file for async follow-up (also works for sync)
    const progressId = 'scan-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8) + '.log';
    params.set('progressFile', progressId);
    const streamUrl = 'cron/scan_cdn_cards.php?' + params.toString();
    controller = new AbortController();
    const { signal } = controller;
    setDisabled(true);
    logEl.textContent += 'Lancement…\n';
    let finished = false;
    try {
      const res = await fetch(streamUrl, { signal });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      if (!inputs.async.checked) {
        // Stream inline (sync mode)
        const reader = res.body && res.body.getReader ? res.body.getReader() : null;
        if (!reader) {
          const text = await res.text();
          logEl.textContent += text;
        } else {
          const decoder = new TextDecoder();
          let buffer = '';
          while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            if (buffer.length > 1024 || buffer.includes('\n')) {
              logEl.textContent += buffer;
              buffer = '';
              logEl.scrollTop = logEl.scrollHeight;
            }
          }
          if (buffer) { logEl.textContent += buffer; logEl.scrollTop = logEl.scrollHeight; }
        }
        finished = true;
        if (typeof onFinish === 'function') { await onFinish(); }
      } else {
        // Async mode: the HTTP call returns immediately; start polling progress file
        let pos = 0;
        let idleCount = 0;
        finished = false;
        const poll = async () => {
          if (controller.signal.aborted) return;
          try {
            const u = new URL('api.php', location.href);
            u.searchParams.set('action', 'cron.progress');
            u.searchParams.set('file', progressId);
            u.searchParams.set('pos', String(pos));
            const r = await fetch(u.toString());
            const j = await r.json();
            if (j && j.ok && j.data) {
              const chunk = j.data.chunk || '';
              const size = j.data.size || 0;
              const nextPos = j.data.pos || size;
              if (chunk && chunk.length) {
                logEl.textContent += chunk;
                logEl.scrollTop = logEl.scrollHeight;
                pos = nextPos;
                idleCount = 0;
                if (/\nDone\. Tried:/i.test(chunk)) {
                  finished = true;
                  if (typeof onFinish === 'function') { await onFinish(); }
                  return; // stop; interval cleared below
                }
              } else {
                idleCount++;
              }
            } else {
              idleCount++;
            }
          } catch (_) {
            idleCount++;
          }
        };
        // Start poller
        const interval = setInterval(async () => {
          await poll();
          if (finished || idleCount >= 60) { // ~2 minutes without changes
            clearInterval(interval);
            setDisabled(false);
            cancelBtn.disabled = true;
            if (finished) {
              logEl.textContent += "\n— Terminé —\n";
            } else {
              logEl.textContent += "\n— Inactif: fin du suivi —\n";
            }
            logEl.scrollTop = logEl.scrollHeight;
          }
        }, 2000);
        // Initial nudge
        await poll();
      }
    } catch (e) {
      if (signal.aborted) {
        logEl.textContent += "\n— Opération annulée —\n";
      } else {
        logEl.textContent += "\n[Erreur] " + (e.message || e) + "\n";
      }
    } finally {
      if (!inputs.async.checked) {
        setDisabled(false);
        cancelBtn.disabled = true;
        if (finished) {
          logEl.textContent += "\n— Terminé —\n";
          logEl.scrollTop = logEl.scrollHeight;
        }
      }
    }
  }

  startBtn.addEventListener('click', () => run());
  cancelBtn.addEventListener('click', () => { if (controller) controller.abort(); });

  // Cleanup modal
  if (modal) {
    modalWrap.addEventListener('hidden.bs.modal', () => { modal.dispose(); modalWrap.remove(); }, { once: true });
  } else {
    const closeBtn = modalWrap.querySelector('[data-bs-dismiss="modal"]');
    if (closeBtn) closeBtn.addEventListener('click', () => modalWrap.remove(), { once: true });
  }
}

// Global: Camera Scan modal (open from buttons/links)
async function openScanModal() {
  const wrap = document.createElement('div');
  wrap.className = 'modal fade';
  wrap.setAttribute('tabindex', '-1');
  wrap.setAttribute('aria-hidden', 'true');
  wrap.innerHTML = `
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content bg-dark text-light">
        <div class="modal-header">
          <h5 class="modal-title">Ajouter via caméra</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-secondary mb-2">Cadrez la carte dans le cadre. L'analyse détectera l'identifiant (ex: OGN-310).</div>
          <div class="scan-cam-wrap position-relative">
            <video id="scanVideo" playsinline autoplay class="w-100 rounded border" style="max-height:60vh;background:#000"></video>
            <div class="scan-overlay" style="pointer-events:none;">
              <div class="frame"></div>
              <div class="wm wm-top">RiftCollect • RiftCollect • RiftCollect • RiftCollect • </div>
              <div class="wm wm-bottom">RiftCollect • RiftCollect • RiftCollect • RiftCollect • </div>
              <div class="wm wm-left">RiftCollect • RiftCollect • RiftCollect • </div>
              <div class="wm wm-right">RiftCollect • RiftCollect • RiftCollect • </div>
              <div class="scan-ocr-badge" id="scanOcrBadge" style="pointer-events:auto; display:none;"><span class="label">Détecté:</span></div>
              </div>
            </div>
          </div>
          <canvas id="scanCanvas" class="d-none"></canvas>
          <div class="d-flex flex-wrap gap-2 my-3">
            <button type="button" class="btn btn-outline-primary" id="scanAnalyzeBtn" disabled>Analyser</button>
            <label class="btn btn-outline-secondary d-flex align-items-center gap-2 mb-0">
              <input type="checkbox" class="form-check-input" id="scanAutoToggle"> Analyse auto
            </label>
            <button type="button" class="btn btn-outline-secondary" id="scanStopBtn">Stop</button>
            <button type="button" class="btn btn-outline-secondary d-none" id="scanTorchBtn">Lampe</button>
            <button type="button" class="btn btn-warning" id="scanAiBtn" title="Analyser via IA (cloud)">IA</button>
          </div>
          <div id="scanStatus" class="text-muted small"></div>
          <div id="scanResults" class="mt-2"></div>
        </div>
      </div>
    </div>`;
  document.body.appendChild(wrap);
  const ModalCtor = (window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal : null;
  const modal = ModalCtor ? new ModalCtor(wrap) : null;
  if (modal) modal.show(); else wrap.style.display = 'block';

  // Grab refs within modal
  const video = wrap.querySelector('#scanVideo');
  const canvas = wrap.querySelector('#scanCanvas');
  const overlay = wrap.querySelector('.scan-overlay');
  const frameEl = overlay.querySelector('.frame');
  const ocrBadge = wrap.querySelector('#scanOcrBadge');
  const analyzeBtn = wrap.querySelector('#scanAnalyzeBtn');
  const stopBtn = wrap.querySelector('#scanStopBtn');
  const torchBtn = wrap.querySelector('#scanTorchBtn');
  const aiBtn = wrap.querySelector('#scanAiBtn');
  const autoToggle = wrap.querySelector('#scanAutoToggle');
  // no upload input anymore (user requested)
  // Enable auto analysis by default
  autoToggle.checked = true;
  const results = wrap.querySelector('#scanResults');
  const statusEl = wrap.querySelector('#scanStatus');
  // Size the dashed frame to always fit within the video area
  const cleanupFit = installFrameFitter(video, overlay, 0.9);

  let stream = null;
  let autoTimer = null;
  let ocrBusy = false;
  let collectionMap = {};
  let torchOn = false;
  let confirmOpen = false;
  let aiBusy = false;
  const urlParams = new URLSearchParams(location.search);
  const scanDebug = urlParams.get('scanDebug') === '1' || localStorage.getItem('scanDebug') === '1';
  function logStatus(msg, isError = false) {
    if (statusEl) {
      statusEl.textContent = msg || '';
      statusEl.classList.toggle('text-danger', !!isError);
      statusEl.classList.toggle('text-muted', !isError);
    }
    if (scanDebug && msg) console.log('[scan]', msg);
  }
  try {
    const data = await api.collectionGet();
    collectionMap = Object.fromEntries((data.items||[]).map(it => [it.card_id, it.qty||0]));
  } catch {}

  async function startCamera() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
      video.srcObject = stream;
      analyzeBtn.disabled = false;
      // Torch capability
      try {
        const track = stream.getVideoTracks && stream.getVideoTracks()[0];
        const caps = track && track.getCapabilities ? track.getCapabilities() : null;
        if (caps && 'torch' in caps) { torchBtn.classList.remove('d-none'); }
      } catch {}
      // auto-start loop if checkbox enabled
      if (autoToggle.checked) {
        if (autoTimer) clearInterval(autoTimer);
        autoTimer = setInterval(runAiOnce, 2000);
        // Kick a first quick pass
        setTimeout(runAiOnce, 400);
      }
      logStatus('Caméra OK — IA auto active');
    } catch (e) {
      toast('Caméra indisponible: ' + (e.message||''), true);
      logStatus('Caméra indisponible. Activez les permissions caméra et utilisez HTTPS sur mobile.', true);
    }
  }
  function stopCamera() {
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    analyzeBtn.disabled = true;
    if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
    torchOn = false;
    logStatus('Caméra arrêtée');
  }

  function pauseAuto() {
    if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
  }
  function resumeAuto(immediate = false) {
    if (autoToggle.checked && stream) {
      if (autoTimer) clearInterval(autoTimer);
      autoTimer = setInterval(runAiOnce, 2000);
      if (immediate) setTimeout(runAiOnce, 400);
    }
  }

  async function showConfirmCard(id) {
    confirmOpen = true;
    pauseAuto();
    // Fetch card details for nicer modal
    let card = null;
    try { card = await api.cardDetail(id, 'fr-FR'); } catch {}
    const wrap = document.createElement('div');
    wrap.className = 'modal fade';
    wrap.setAttribute('tabindex','-1');
    wrap.setAttribute('aria-hidden','true');
    const title = (card && (card.name || card.id)) || id;
    const img = (card && card.image_url) || 'assets/img/card-placeholder.svg';
    wrap.innerHTML = `
      <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
          <div class="modal-header">
            <h5 class="modal-title">Confirmer la carte</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body d-flex gap-3 align-items-start">
            <img src="${img}" alt="${title}" style="width:120px; height:auto; border-radius:.25rem" loading="lazy" decoding="async" />
            <div class="flex-grow-1">
              <div class="h5 mb-1">${title}</div>
              <div class="text-muted">#${id}</div>
              <div class="small text-muted mt-2">Valider pour ajouter +1 à votre collection.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="refuseBtn">Refuser</button>
            <button type="button" class="btn btn-success" id="acceptBtn">Valider (+1)</button>
          </div>
        </div>
      </div>`;
    document.body.appendChild(wrap);
    const ModalCtor = (window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal : null;
    const modal = ModalCtor ? new ModalCtor(wrap) : null;
    if (modal) modal.show(); else { wrap.style.display='block'; }
    const acceptBtn = wrap.querySelector('#acceptBtn');
    const refuseBtn = wrap.querySelector('#refuseBtn');
    acceptBtn.onclick = async () => {
      try {
        acceptBtn.disabled = true; refuseBtn.disabled = true;
        const cur = collectionMap[id] || 0; const next = cur + 1;
        await api.collectionSet(id, next);
        collectionMap[id] = next;
        toast('Ajouté: ' + id + ' (total ' + next + ')');
      } catch (e) {
        toast('Erreur: ' + (e.message||''), true);
      } finally {
        if (modal) modal.hide(); else wrap.remove();
      }
    };
    wrap.addEventListener('hidden.bs.modal', () => {
      confirmOpen = false; wrap.remove(); resumeAuto(true);
    }, { once: true });
    // Fallback if no bootstrap
    if (!modal) {
      refuseBtn.onclick = () => { wrap.remove(); confirmOpen=false; resumeAuto(true); };
    }
  }

  async function toggleTorch() {
    try {
      if (!stream) return;
      const track = stream.getVideoTracks && stream.getVideoTracks()[0];
      if (!track || !track.applyConstraints) return;
      torchOn = !torchOn;
      await track.applyConstraints({ advanced: [{ torch: torchOn }] });
      torchBtn.classList.toggle('btn-outline-secondary', !torchOn);
      torchBtn.classList.toggle('btn-warning', torchOn);
    } catch {}
  }

  async function loadTesseract() {
    if (window.Tesseract) return window.Tesseract;
    await new Promise((resolve, reject) => { const s = document.createElement('script'); s.src='https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js'; s.onload=resolve; s.onerror=() => reject(new Error('Tesseract.js load failed')); document.head.appendChild(s); });
    return window.Tesseract;
  }
  async function loadJsQR() {
    if (window.jsQR) return window.jsQR;
    await new Promise((resolve, reject) => { const s = document.createElement('script'); s.src='https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js'; s.onload=resolve; s.onerror=() => reject(new Error('jsQR load failed')); document.head.appendChild(s); });
    return window.jsQR;
  }
  function getFrameRectInCanvas() {
    const w = video.videoWidth || 1280; const h = video.videoHeight || 720;
    const videoRect = video.getBoundingClientRect();
    const frameEl = overlay.querySelector('.frame');
    const frameRect = frameEl.getBoundingClientRect();
    const relX = (frameRect.left - videoRect.left) / videoRect.width;
    const relY = (frameRect.top - videoRect.top) / videoRect.height;
    const relW = frameRect.width / videoRect.width; const relH = frameRect.height / videoRect.height;
    return { x: relX * w, y: relY * h, w: relW * w, h: relH * h, cw: w, ch: h };
  }
  function preprocessToDataUrl(drawFn, scale = 2.2, threshold = 0) {
    const tmp = document.createElement('canvas');
    const tctx = tmp.getContext('2d');
    drawFn(tctx, tmp);
    const iw = tmp.width; const ih = tmp.height;
    const ow = Math.min(1200, Math.max(300, Math.floor(iw * scale)));
    const oh = Math.floor(ih * (ow / iw));
    canvas.width = ow; canvas.height = oh;
    const ctx = canvas.getContext('2d');
    ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(tmp, 0, 0, iw, ih, 0, 0, ow, oh);
    const img = ctx.getImageData(0, 0, ow, oh); const d = img.data;
    for (let i = 0; i < d.length; i += 4) {
      const r = d[i], g = d[i+1], b = d[i+2];
      let v = Math.round(0.299*r + 0.587*g + 0.114*b);
      v = Math.min(255, Math.max(0, Math.floor((v - 128) * 1.25 + 128)));
      if (threshold > 0) v = v > threshold ? 255 : 0;
      d[i] = d[i+1] = d[i+2] = v;
    }
    ctx.putImageData(img, 0, 0);
    return canvas.toDataURL('image/png');
  }
  function captureIdVariants() {
    // Capture multiple small rectangles where ID is likely printed
    const w = video.videoWidth || 1280; const h = video.videoHeight || 720;
    const { x, y, w: fw, h: fh } = getFrameRectInCanvas();
    // Align bottom-left sample exactly with the mini rectangle drawn in CSS (::after left:0; bottom:0; width:22%; height:6%)
    const ID_LEFT = 0.00, ID_RIGHT = 0.05, ID_BOTTOM = 0.00, ID_TOP = 0.04, ID_W = 0.22, ID_H = 0.06;
    const zones = [];
    // bottom-left
    zones.push({ sx: Math.floor(x + fw * ID_LEFT), sy: Math.floor(y + fh * (1 - ID_BOTTOM - ID_H)) });
    // bottom-right
    zones.push({ sx: Math.floor(x + fw * (1 - ID_RIGHT - ID_W)), sy: Math.floor(y + fh * (1 - ID_BOTTOM - ID_H)) });
    // top-left
    zones.push({ sx: Math.floor(x + fw * ID_LEFT), sy: Math.floor(y + fh * ID_TOP) });
    const out = [];
    for (const z of zones) {
      const sx = Math.max(0, z.sx);
      const sy = Math.max(0, z.sy);
      const sw = Math.min(w - sx, Math.floor(fw * ID_W));
      const sh = Math.min(h - sy, Math.floor(fh * ID_H));
      if (sw <= 0 || sh <= 0) continue;
      const draw = (ctx, cv) => { cv.width = sw; cv.height = sh; ctx.drawImage(video, sx, sy, sw, sh, 0, 0, sw, sh); };
      out.push(preprocessToDataUrl(draw, 2.2, 0));
      out.push(preprocessToDataUrl(draw, 2.2, 170));
      out.push(preprocessToDataUrl(draw, 2.2, 200));
    }
    return out;
  }
  function parseIdsFromText(text) {
    const out = new Set();
    let m; let re = /\[([A-Z]{2,5})\]\s*[-–—]\s*\[(\d{1,4})\]/g;
    while ((m = re.exec(text)) !== null) out.add((m[1]||'').toUpperCase() + '-' + (m[2]||''));
    let m2; let re2 = /\b([A-Z]{2,5})\s*[-–—·•:/|\\\s]\s*(\d{1,4})\b/g;
    while ((m2 = re2.exec(text)) !== null) out.add((m2[1]||'').toUpperCase() + '-' + (m2[2]||''));
    return Array.from(out);
  }
  // Render small clickable chips into the overlay badge (top of the frame)
  function renderOverlayIds(ids) {
    if (!ocrBadge) return;
    ocrBadge.innerHTML = '';
    const label = document.createElement('span'); label.className='label'; label.textContent='Détecté:'; ocrBadge.appendChild(label);
    (ids||[]).slice(0,3).forEach(id => {
      const chip = document.createElement('span'); chip.className='scan-ocr-chip'; chip.textContent=id;
      chip.onclick = async (ev) => { ev.stopPropagation(); try { const cur = collectionMap[id]||0; const next=cur+1; await api.collectionSet(id,next); collectionMap[id]=next; toast('Ajouté: '+id+' (total '+next+')'); } catch(e){ toast('Erreur: '+(e.message||''), true);} };
      ocrBadge.appendChild(chip);
    });
    ocrBadge.style.display = (ids && ids.length) ? '' : 'none';
  }
  async function validateExistingIds(ids) {
    const ok = [];
    for (const id of ids) { try { await api.cardDetail(id); ok.push(id); } catch(_){} if (ok.length>=5) break; }
    return ok;
  }
  async function runOnce(variantsArg) {
    if (ocrBusy || confirmOpen) return; ocrBusy = true;
    const previews = (src) => { const p = document.createElement('img'); p.src = src; p.style.maxWidth = '220px'; p.style.border = '1px solid rgba(255,255,255,.2)'; p.style.borderRadius = '.25rem'; return p; };
    try {
      // Turn on scanning animation
      if (frameEl) frameEl.classList.add('scanning');
      // First try QR detection on full frame (fast path)
      try {
        const jsQR = await loadJsQR();
        const w = video.videoWidth || 1280; const h = video.videoHeight || 720;
        canvas.width = w; canvas.height = h; const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, w, h);
        const img = ctx.getImageData(0, 0, w, h);
        const code = jsQR(img.data, w, h, { inversionAttempts: 'attemptBoth' });
        if (code && code.data) {
          const txt = String(code.data).toUpperCase();
          const idsFromQR = parseIdsFromText(txt);
          if (idsFromQR.length) {
            const valid = await validateExistingIds(idsFromQR);
            if (valid && valid.length) {
              await showConfirmCard(valid[0]);
              return;
            }
          }
        }
  } catch (err) { if (scanDebug) console.warn('jsQR failed', err); }

      const variants = Array.isArray(variantsArg) ? variantsArg : captureIdVariants();
      if (!variants.length) return;
      // No noisy text; rely on frame animation to indicate progress
      let T = null;
      try { T = await loadTesseract(); } catch (err) { if (scanDebug) console.warn('Tesseract load failed', err); }
      let found = [];
      if (T) {
        for (const v of variants) {
          try {
            const { data } = await T.recognize(v, 'eng', { tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789[]-—–·•:/| ', preserve_interword_spaces:'1' });
            const text = (data && data.text) ? data.text.toUpperCase() : '';
            if (scanDebug) console.log('OCR text:', text);
            const ids = parseIdsFromText(text);
            if (ids.length) { const valid = await validateExistingIds(ids); if (valid.length) { found = valid; break; } }
          } catch (err) { if (scanDebug) console.warn('OCR pass failed', err); }
        }
      }
  if (!found.length) {
        // Fallback: full-frame perceptual matching on the server
        // Rate-limit fallback to avoid spamming server when it returns 500
        const now = Date.now();
        window.__lastMatchTry = window.__lastMatchTry || 0;
        window.__matchCooldownMs = 3500;
        if (now - window.__lastMatchTry < window.__matchCooldownMs) {
          // skip this round
        } else try {
          window.__lastMatchTry = now;
          const { x, y, w: fw, h: fh } = getFrameRectInCanvas();
          const w = video.videoWidth || 1280; const h = video.videoHeight || 720;
          // Extract just the frame region to reduce noise
          const tmp = document.createElement('canvas');
          const tw = Math.min(512, Math.max(256, Math.floor(fw)));
          const th = Math.floor(fh * (tw / fw));
          tmp.width = tw; tmp.height = th;
          const tctx = tmp.getContext('2d');
          tctx.imageSmoothingEnabled = true; tctx.imageSmoothingQuality = 'high';
          tctx.drawImage(video, x, y, fw, fh, 0, 0, tw, th);
          const dataUrl = tmp.toDataURL('image/jpeg', 0.85);
          const match = await api.cardsMatchImage(dataUrl, 5);
          const items = (match && match.matches) || [];
          if (items.length) {
            // Take the best match and confirm
            await showConfirmCard(items[0].id);
            logStatus('Image global: correspondance trouvée');
            return;
          }
        } catch (e) {
          // surface server error in status if available, and run a quick health check
          const msg = (e && e.message) ? e.message : 'inconnu';
          logStatus('Appariement image en erreur: ' + msg, true);
          try {
            const h = await api.cardsMatchImageHealth();
            const hints = [];
            if (!h.gd) hints.push('GD désactivé');
            if (!h.imagehash_file) hints.push('ImageHash.php absent');
            if (!h.assets_cards_dir) hints.push('Dossier assets/img/cards absent');
            if (h.cards_in_db === 0) hints.push('0 carte en base');
            if (!h.hash_cache_file) hints.push('Cache de hash absent');
            if (h.hash_cache_file && h.hash_cache_count === 0) hints.push('Cache de hash vide');
            if (hints.length) {
              logStatus('Diagnostic: ' + hints.join(' · '), true);
            }
          } catch {}
        }
        renderOverlayIds([]);
        results.innerHTML = '<div class="alert alert-warning">Aucun identifiant détecté.</div>';
        logStatus('Aucune correspondance trouvée. Essayez d’améliorer la lumière ou de recadrer.', true);
        return;
      }
      // OCR produced candidates - confirm best one immediately
      await showConfirmCard(found[0]);
    } finally { ocrBusy = false; if (frameEl) frameEl.classList.remove('scanning'); }
  }

  // Wire controls
  analyzeBtn.onclick = runAiOnce;
  stopBtn.onclick = () => stopCamera();
  torchBtn.onclick = () => toggleTorch();
  autoToggle.onchange = () => { if (autoToggle.checked && stream) { if (autoTimer) clearInterval(autoTimer); autoTimer=setInterval(runAiOnce,2000); } else { if (autoTimer) { clearInterval(autoTimer); autoTimer=null; } } };
  // upload flow removed

  async function runAiOnce() {
    if (confirmOpen || aiBusy) return; aiBusy = true;
    try {
      if (!video.srcObject) { toast('Caméra non démarrée', true); return; }
      logStatus('Analyse IA en cours…');
      const { x, y, w: fw, h: fh } = getFrameRectInCanvas();
      const tmp = document.createElement('canvas');
      const tw = Math.min(768, Math.max(320, Math.floor(fw)));
      const th = Math.floor(fh * (tw / fw));
      tmp.width = tw; tmp.height = th;
      const tctx = tmp.getContext('2d');
      tctx.imageSmoothingEnabled = true; tctx.imageSmoothingQuality = 'high';
      tctx.drawImage(video, x, y, fw, fh, 0, 0, tw, th);
      const dataUrl = tmp.toDataURL('image/jpeg', 0.9);
      const res = await api.cardsMatchAi(dataUrl);
      const items = (res && res.candidates) || [];
      if (!items.length) { logStatus('IA: aucun candidat'); results.innerHTML='<div class="alert alert-warning">IA: aucun candidat</div>'; return; }
      await showConfirmCard(items[0].id);
      logStatus('IA: correspondance trouvée');
    } catch (e) {
      logStatus('IA en erreur: ' + (e && e.message ? e.message : 'inconnu'), true);
      results.innerHTML = '<div class="alert alert-danger">IA en erreur: ' + (e && e.message ? e.message : 'inconnu') + '</div>';
    } finally { aiBusy = false; }
  }

  aiBtn.onclick = runAiOnce;

  // Auto-start camera on modal show and check server flags
  if (modal) {
    wrap.addEventListener('shown.bs.modal', async () => {
      try { const flags = await api.configFlags(); if (!flags.llmEnabled && aiBtn) aiBtn.classList.add('d-none'); } catch {}
      startCamera();
    }, { once: true });
    wrap.addEventListener('hidden.bs.modal', () => { stopCamera(); cleanupFit && cleanupFit(); modal.dispose(); wrap.remove(); }, { once: true });
  } else {
    try { const flags = await api.configFlags(); if (!flags.llmEnabled && aiBtn) aiBtn.classList.add('d-none'); } catch {}
    startCamera();
    const closeBtn = wrap.querySelector('[data-bs-dismiss="modal"]');
    if (closeBtn) closeBtn.addEventListener('click', () => { stopCamera(); cleanupFit && cleanupFit(); wrap.remove(); }, { once: true });
  }
}

// Icon helpers for color/type
function normalizeColorName(name) {
  if (!name) return null;
  const s = String(name).trim().toLowerCase();
  const map = {
    body: 'body',
    calm: 'calm',
    chaos: 'chaos',
    colorless: 'colorless', neutral: 'colorless', none: 'colorless',
    fury: 'fury',
    mind: 'mind',
    order: 'order',
  };
  return map[s] || null;
}
function normalizeTypeName(name) {
  if (!name) return null;
  const s = String(name).trim().toLowerCase();
  const map = {
    unit: 'unit',
    spell: 'spell',
    champion: 'champion',
    battlefield: 'battlefield',
    gear: 'gear',
    legend: 'legend',
    rune: 'rune',
    token: 'token',
  };
  return map[s] || null;
}
function iconImg(src, alt, size='sm') {
  const cls = size === 'md' ? 'icon icon-md' : 'icon icon-sm';
  return el('img', { src, alt: alt || '', class: cls, loading: 'lazy', decoding: 'async' });
}
function colorIcon(color, size='sm') {
  const key = normalizeColorName(color);
  if (!key) return null;
  return iconImg('assets/img/color/' + key + '.webp', String(color), size);
}
function typeIcon(type, size='sm') {
  const key = normalizeTypeName(type);
  if (!key) return null;
  return iconImg('assets/img/type/' + key + '.svg', String(type), size);
}

// Utility: fit the scan frame within its overlay/video area keeping 2.5/3.5 ratio
function installFrameFitter(videoEl, overlayEl, fraction = 0.9) {
  const frameEl = overlayEl.querySelector('.frame');
  if (!frameEl) return () => {};
  const R = 2.5 / 3.5; // width / height ratio
  const fit = () => {
    const rect = overlayEl.getBoundingClientRect();
    let targetW = rect.width * fraction;
    let targetH = targetW / R;
    if (targetH > rect.height * fraction) {
      targetH = rect.height * fraction;
      targetW = targetH * R;
    }
    frameEl.style.width = Math.floor(targetW) + 'px';
    frameEl.style.height = Math.floor(targetH) + 'px';
  };
  const onMeta = () => fit();
  window.addEventListener('resize', fit);
  videoEl && videoEl.addEventListener && videoEl.addEventListener('loadedmetadata', onMeta);
  // Initial fit
  fit();
  // Return cleanup
  return () => {
    window.removeEventListener('resize', fit);
    videoEl && videoEl.removeEventListener && videoEl.removeEventListener('loadedmetadata', onMeta);
  };
}

// Open card modal helper
async function openCardModal(cardOrId) {
  try {
    const modalEl = document.getElementById('cardModal');
    const titleEl = document.getElementById('cardModalTitle');
    const nameEl = document.getElementById('cardModalName');
    const descEl = document.getElementById('cardModalDesc');
    const detailsEl = document.getElementById('cardModalDetails');
    const rawEl = document.getElementById('cardModalRaw');
    const toggleJsonBtn = document.getElementById('cardModalToggleJson');
    const badgesEl = document.getElementById('cardModalBadges');
    const statsEl = document.getElementById('cardModalStats');
    const imgEl = document.getElementById('cardModalImage');
    const langSel = document.getElementById('cardModalLang');

    const id = (typeof cardOrId === 'string') ? cardOrId : (cardOrId && (cardOrId.id || cardOrId.card_id)) || '';
    if (!id) throw new Error('id manquant');

    // Set optimistic UI with provided object while loading
    const seed = (typeof cardOrId === 'object' && cardOrId) ? cardOrId : null;
    if (seed) {
      titleEl.textContent = seed.name || seed.id || 'Carte';
      nameEl.textContent = seed.name || seed.id || '';
      descEl.textContent = seed.description || '';
      imgEl.src = seed.image_url || 'assets/img/card-placeholder.svg';
      badgesEl.innerHTML = '';
      statsEl.innerHTML = '';
      detailsEl.innerHTML = '';
      toggleJsonBtn.style.display = 'none';
      rawEl.classList.add('d-none');
    }

    let seq = 0;
    async function loadAndRender(locale) {
      const cur = ++seq;
      let data;
      try {
        data = await api.cardDetail(id, locale);
      } catch (e) {
        // Keep optimistic UI; do not break modal if fetch fails
        console && console.warn && console.warn('cardDetail failed', e);
        return;
      }
      if (cur !== seq) return; // outdated
      titleEl.textContent = data.name || data.id || 'Carte';
      nameEl.textContent = data.name || data.id || '';
      descEl.textContent = data.description || '';
      imgEl.src = data.image_url || 'assets/img/card-placeholder.svg';

  // Render structured details from data_json (AI output)
      detailsEl.innerHTML = '';
      rawEl.classList.add('d-none');
      toggleJsonBtn.style.display = 'none';
      let ai = null;
      try { if (data.data_json) ai = JSON.parse(data.data_json); } catch {}
      // Resolve card object depending on language-aware payload
      const currentLocale = (locale || 'fr-FR');
      const langKey = currentLocale.toLowerCase().startsWith('fr') ? 'fr' : (currentLocale.toLowerCase().startsWith('en') ? 'en' : 'en');
      let cardObj = null;
      if (ai && typeof ai === 'object') {
        if (ai.lang && typeof ai.lang === 'object') {
          cardObj = ai.lang[langKey] || ai.lang.fr || ai.lang.en || null;
        }
        if (!cardObj) cardObj = ai.card || ai;
      }
      const addRow = (label, value) => {
        if (value == null) return;
        const str = Array.isArray(value) ? value.join(', ') : String(value);
        if (str === '' || str === 'undefined') return;
        const dt = document.createElement('dt'); dt.className = 'col-4 text-light'; dt.textContent = label;
        const dd = document.createElement('dd'); dd.className = 'col-8'; dd.textContent = str;
        detailsEl.append(dt, dd);
      };
      if (cardObj) {
        // Build pretty badges
        badgesEl.innerHTML = '';
        const mkBadge = (text, color='gray') => { const s = document.createElement('span'); s.className = 'badge rounded-pill me-1 mb-1 ' + (color==='green'?'badge-soft-green':color==='blue'?'badge-soft-blue':color==='violet'?'badge-soft':'badge-soft-gray'); s.textContent = text; return s; };
        // Icon badges for type/color
        const iconRow = el('div', { class: 'icon-row mb-1' });
        const cIcon = colorIcon(cardObj.color || data.color, 'md');
        if (cIcon) iconRow.appendChild(el('span', { class: 'icon-badge' }, cIcon, el('span', { class: 'label' }, String(cardObj.color || data.color))));
        const tIcon = typeIcon(cardObj.card_type || data.card_type, 'md');
        if (tIcon) iconRow.appendChild(el('span', { class: 'icon-badge' }, tIcon, el('span', { class: 'label' }, String(cardObj.card_type || data.card_type))));
        if (iconRow.childNodes.length) badgesEl.appendChild(iconRow);
        const setCode = data.set_code || cardObj.set || '';
        if (setCode) badgesEl.appendChild(mkBadge(setCode.toUpperCase(), 'blue'));
        if (data.id) badgesEl.appendChild(mkBadge((data.id||'').toUpperCase(), 'gray'));
        // keep text badges as secondary if icon missing
        if ((cardObj.card_type || data.card_type) && !tIcon) badgesEl.appendChild(mkBadge(cardObj.card_type || data.card_type, 'violet'));
        if ((cardObj.color || data.color) && !cIcon) badgesEl.appendChild(mkBadge(cardObj.color || data.color, 'green'));
        if (cardObj.rarity || data.rarity) badgesEl.appendChild(mkBadge(cardObj.rarity || data.rarity, 'gray'));
        (Array.isArray(cardObj.region)?cardObj.region:[]).forEach(r => badgesEl.appendChild(mkBadge(r, 'blue')));

        // Stat chips
        statsEl.innerHTML = '';
        const addChip = (val, cls) => { const d = document.createElement('div'); d.className = 'stat-chip ' + cls; d.textContent = val; statsEl.appendChild(d); };
        if (typeof cardObj.cost === 'number') addChip(cardObj.cost, 'cost');
        if (typeof cardObj.might === 'number') addChip(cardObj.might, 'might');

        addRow('ID', data.id || '');
  addRow('Type', cardObj.card_type || data.card_type || '');
  addRow('Couleur', cardObj.color || data.color || '');
        if (cardObj.cost != null) addRow('Coût', cardObj.cost);
        if (cardObj.might != null) addRow('Puissance', cardObj.might);
        addRow('Rareté', cardObj.rarity || data.rarity || '');
        addRow('Région', cardObj.region || []);
        addRow('Artiste', cardObj.artist || '');
        if (cardObj.year != null) addRow('Année', cardObj.year);
        // Collectible block
        const ci = cardObj.collectible_info || {};
        addRow('Taille du set', ci.set_size || '');
        addRow('# dans le set', ci.card_number_in_set || cardObj.card_number || '');
        addRow('Éditeur', ci.publisher || '');
        // Effect and flavor
  if (!data.description && cardObj.effect) descEl.textContent = cardObj.effect;
        if (cardObj.flavor_text) addRow('Texte d’ambiance', cardObj.flavor_text);

        // Raw JSON toggle
        toggleJsonBtn.style.display = '';
        toggleJsonBtn.textContent = 'Afficher JSON';
        toggleJsonBtn.onclick = () => {
          const show = rawEl.classList.contains('d-none');
          if (show) {
            rawEl.textContent = JSON.stringify(ai, null, 2);
            rawEl.classList.remove('d-none');
            toggleJsonBtn.textContent = 'Masquer JSON';
          } else {
            rawEl.classList.add('d-none');
            toggleJsonBtn.textContent = 'Afficher JSON';
          }
        };
      } else {
        // Fallback minimal info
        badgesEl.innerHTML = '';
        statsEl.innerHTML = '';
        detailsEl.innerHTML = '';
      }
    }

    // Show modal
    const ModalCtor = (window.bootstrap && window.bootstrap.Modal) ? window.bootstrap.Modal : null;
    if (ModalCtor) {
      const modal = new ModalCtor(modalEl);
      modal.show();
    } else {
      modalEl.classList.add('show');
      modalEl.style.display = 'block';
      modalEl.removeAttribute('aria-hidden');
    }

    // Init language selector and first render
    if (langSel) langSel.value = 'fr-FR';
    await loadAndRender(langSel ? langSel.value : 'fr-FR');
    if (langSel) {
      langSel.onchange = async () => {
        await loadAndRender(langSel.value || 'fr-FR');
      };
    }
  } catch (e) {
    toast('Impossible de charger la carte', true);
  }
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
  // Rarity dropdown with icons
  const set = el('select', { class: 'form-select', title: 'Set' },
    el('option', { value: '' }, 'Tous les sets'),
    el('option', { value: 'OGN' }, 'OGN - Origins'),
    el('option', { value: 'OGS' }, 'OGS - Origins Proving Grounds'),
    el('option', { value: 'ARC' }, 'ARC - Arcane'),
    el('option', { value: 'SPF' }, 'SPF - Spiritforged')
  );
  const allowedPageSizes = [12, 24, 30, 60, 96];
  let savedPs = parseInt(localStorage.getItem('cards.pageSize') || '30', 10);
  if (!allowedPageSizes.includes(savedPs)) savedPs = 30;
  const pageSizeSel = el('select', { class: 'form-select form-select-sm', title: 'Résultats par page', 'aria-label': 'Résultats par page', style: 'width: 90px' });
  allowedPageSizes.forEach(n => {
    const opt = el('option', { value: String(n) }, String(n));
    if (n === savedPs) opt.setAttribute('selected', '');
    pageSizeSel.appendChild(opt);
  });
  pageSizeSel.addEventListener('change', () => {
    const v = parseInt(pageSizeSel.value || '30', 10) || 30;
    localStorage.setItem('cards.pageSize', String(v));
    page = 1;
    load();
  });
  const results = el('div', { class: 'row g-3 mt-3' });
  const actions = el('div', { class: 'd-flex justify-content-between align-items-center mt-3' });
  // Rarity icons helper (dynamic from backend)
  function rarityLabelFromKey(k) {
    const m = {
      common: 'Commune', uncommon: 'Peu commune', rare: 'Rare', epic: 'Épique', legendary: 'Légendaire'
    };
    return m[(k||'').toLowerCase()] || (k ? (k.charAt(0).toUpperCase()+k.slice(1)) : 'Toutes raretés');
  }
  let rarityIconMap = {}; // key -> url
  function rarityIcon(key, size='sm') {
    const k = (key||'').toLowerCase();
    const url = rarityIconMap[k] || (k ? ('assets/img/rarity/' + k + '.webp') : null);
    return url ? iconImg(url, k, size) : null;
  }
  let selectedRarity = '';
  const rarityDd = makeIconDropdown('Rareté', [{ key: '', label: 'Toutes raretés' }], rarityIcon, (key) => { selectedRarity = key; page = 1; load(); });
  rarityDd.setSelected('');
  // Load rarity icons from backend
  try {
    const data = await api.rarityIcons();
    const icons = (data.icons||[]);
    rarityIconMap = Object.fromEntries(icons.map(it => [String(it.key).toLowerCase(), it.url]));
    // rebuild dropdown menu with available icons
    const opts = [{ key: '', label: 'Toutes raretés' }].concat(icons.map(it => ({ key: it.key, label: rarityLabelFromKey(it.key) })));
    const rebuilt = makeIconDropdown('Rareté', opts, rarityIcon, (key) => { selectedRarity = key; page = 1; load(); });
    // Replace the old root in DOM
    rarityDd.root.replaceWith(rebuilt.root);
    rebuilt.setSelected('');
    // Update reference for layout assembly below
    rarityDd.root = rebuilt.root;
  } catch {}
  // Modal moved to global scope: openCdnScanModal()
  const pager = el('div', { class: 'd-flex align-items-center gap-2' });
  let page = 1;
  // Custom dropdowns for Color and Type with icons
  const colors = [
    { key: '', label: 'Toutes couleurs' },
    { key: 'body', label: 'Body' },
    { key: 'calm', label: 'Calm' },
    { key: 'chaos', label: 'Chaos' },
    { key: 'colorless', label: 'Incolore' },
    { key: 'fury', label: 'Fury' },
    { key: 'mind', label: 'Mind' },
    { key: 'order', label: 'Order' },
  ];
  const types = [
    { key: '', label: 'Tous types' },
    { key: 'unit', label: 'Unit' },
    { key: 'spell', label: 'Spell' },
    { key: 'champion', label: 'Champion' },
    { key: 'battlefield', label: 'Battlefield' },
    { key: 'gear', label: 'Gear' },
    { key: 'legend', label: 'Legend' },
    { key: 'rune', label: 'Rune' },
    { key: 'token', label: 'Token' },
  ];
  let selectedColor = '';
  let selectedType = '';
  function makeIconDropdown(title, options, getIconFn, onChange) {
    const wrap = el('div', { class: 'dropdown w-100' });
    const btn = el('button', { class: 'btn btn-outline-secondary w-100 dropdown-toggle', type: 'button', 'data-bs-toggle': 'dropdown', 'aria-expanded': 'false' }, title);
    const menu = el('ul', { class: 'dropdown-menu dropdown-menu-dark w-100' });
    function renderBtnLabel(opt) {
      btn.innerHTML = '';
      if (opt && opt.key) {
        const icon = getIconFn(opt.key, 'sm');
        if (icon) btn.appendChild(icon);
        btn.appendChild(document.createTextNode(' ' + opt.label));
      } else {
        btn.textContent = title;
      }
    }
    options.forEach(opt => {
      const li = document.createElement('li');
      const a = el('a', { class: 'dropdown-item d-flex align-items-center gap-2', href: '#', onclick: (e) => { e.preventDefault(); onChange(opt.key); renderBtnLabel(opt); } });
      if (opt.key) {
        const icon = getIconFn(opt.key, 'sm');
        if (icon) a.appendChild(icon);
      }
      a.appendChild(document.createTextNode(opt.label));
      li.appendChild(a);
      menu.appendChild(li);
    });
    wrap.append(btn, menu);
    return { root: wrap, setSelected: (key) => { const opt = options.find(o => o.key === key) || options[0]; renderBtnLabel(opt); } };
  }
  const colorDd = makeIconDropdown('Couleur', colors, colorIcon, (key) => { selectedColor = key; page = 1; load(); });
  const typeDd = makeIconDropdown('Type', types, typeIcon, (key) => { selectedType = key; page = 1; load(); });
  colorDd.setSelected('');
  typeDd.setSelected('');
  // Debounce helper for live search
  const debounce = (fn, wait = 300) => {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
  };
  // Live search bindings
  q.addEventListener('input', debounce(() => { page = 1; load(); }, 300));
  set.addEventListener('change', () => { page = 1; load(); });
  // Nothing to bind for dropdowns, handled inside onChange
  // If logged in, preload collection quantities to mark owned cards and set initial qty
  let owned = new Set();
  let qtyMap = {};
  let isLogged = false;
  try { const me = await api.me(); isLogged = !!me; } catch {}
  if (isLogged) {
    try {
      const cg = await api.collectionGet();
      owned = new Set((cg.items||[]).filter(it => (it.qty||0) > 0).map(it => it.card_id));
      qtyMap = Object.fromEntries((cg.items||[]).map(it => [it.card_id, it.qty||0]));
    } catch {}
  }
  let loadSeq = 0;
  async function load() {
    const seq = ++loadSeq;
    results.innerHTML = 'Chargement…';
    try {
  const ps = parseInt(pageSizeSel.value||'30', 10) || 30;
  const data = await api.cardsList(q.value.trim(), selectedRarity, set.value.trim(), page, ps, selectedColor, selectedType);
      if (seq !== loadSeq) return; // stale response, ignore
      results.innerHTML = '';
      data.items.forEach(card => {
        const qty = qtyMap[card.id] || 0;
        const imgEl = el('img', { src: card.image_url || 'assets/img/card-placeholder.svg', alt: card.name, loading: 'lazy', decoding: 'async' });
        const imgWrap = el('div', { class: 'card-img-wrapper', onclick: (ev) => { ev.stopPropagation(); openCardModal(card); } }, imgEl);
        // Icons row ABOVE the image
        const topRow = el('div', { class: 'top-icon-row' });
        const oci = colorIcon(card.color, 'sm');
        if (oci) topRow.appendChild(el('div', { class: 'icon-pill' }, oci));
        const oti = typeIcon(card.card_type, 'sm');
        if (oti) topRow.appendChild(el('div', { class: 'icon-pill' }, oti));
        // Footer left: ID only (icons moved to top row)
        const idBadge = el('div', { class: 'card-id-badge' }, '#' + (card.id || '').toUpperCase());
        let qtyInput;
        const qtyCtrl = isLogged ? el('div', { class: 'qty-ctrl' },
          el('button', { class: 'btn btn-sm btn-outline-secondary', onclick: async (ev) => { ev.stopPropagation(); const n = Math.max(0, (qtyMap[card.id]||0) - 1); await api.collectionSet(card.id, n); qtyMap[card.id] = n; qtyInput.value = n; if (n>0) owned.add(card.id); else owned.delete(card.id); } }, '−'),
          (qtyInput = el('input', { type: 'number', min: '0', value: String(qty), onchange: async (ev) => { ev.stopPropagation(); let n = parseInt(qtyInput.value||'0',10); if (isNaN(n)||n<0) n=0; await api.collectionSet(card.id, n); qtyMap[card.id] = n; if (n>0) owned.add(card.id); else owned.delete(card.id); } })),
          el('button', { class: 'btn btn-sm btn-outline-secondary', onclick: async (ev) => { ev.stopPropagation(); const n = (qtyMap[card.id]||0) + 1; await api.collectionSet(card.id, n); qtyMap[card.id] = n; qtyInput.value = n; owned.add(card.id); } }, '+')
        ) : el('div', { class: 'text-muted small' }, '');
        const footer = el('div', { class: 'card-footer-bar' }, idBadge, qtyCtrl);
        const tileChildren = [];
        if (topRow.childNodes.length) tileChildren.push(topRow);
        tileChildren.push(imgWrap, footer);
        const tile = el('div', {
          class: 'card h-100 card-tile clickable',
          onclick: () => openCardModal(card),
          'data-card-key': card.id || '',
          'data-card-name': card.name || '',
          'data-card-set': card.set_code || '',
          'data-card-rarity': (card.rarity || '').toLowerCase()
        }, ...tileChildren);
  results.append(el('div', { class: 'col-12 col-sm-6 col-md-4 col-lg-5ths' }, tile));
      });
  const total = Number(data.total) || 0;
  const pageSize = Number(data.pageSize) || ps;
  const totalPages = Math.max(1, Math.ceil(total / pageSize));
      pager.innerHTML = '';
      pager.append(
        el('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', disabled: page <= 1, onclick: () => { page = Math.max(1, page - 1); load(); } }, 'Précédent'),
        el('div', { class: 'small text-light', style: 'white-space:nowrap' }, `Page ${page} / ${totalPages}`),
        el('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', disabled: page >= totalPages, onclick: () => { page = Math.min(totalPages, page + 1); load(); } }, 'Suivant')
      );
    } catch (e) {
      results.innerHTML = '<div class="alert alert-danger">' + (e.message || 'Erreur') + '</div>';
    }
  }
  const searchBar = el('div', { class: 'row g-2' },
    el('div', { class: 'col-12 col-md-4 col-lg-4' }, q),
  el('div', { class: 'col-6 col-md-2 col-lg-2' }, rarityDd.root),
    el('div', { class: 'col-6 col-md-2 col-lg-2' }, set),
    el('div', { class: 'col-6 col-md-2 col-lg-2' }, colorDd.root),
    el('div', { class: 'col-6 col-md-2 col-lg-2' }, typeDd.root)
  );
  const rightActions = el('div', { class: 'd-flex align-items-center gap-2' }, pager, pageSizeSel);
  // Sync button moved to Compte; only show right-side controls here
  actions.append(rightActions);
  root.append(el('h2', {}, 'Cartes'), searchBar, results, actions);
  load();
});

// Compte (profil + outils admin)
router.register('#/compte', async (root) => {
  root.innerHTML = '';
  let me = null;
  try { const data = await api.me(); me = data.user; } catch {}
  if (!me) {
    root.append(el('div', { class: 'alert alert-info' }, 'Connectez-vous pour accéder à votre compte.'));
    return;
  }
  const header = el('div', { class: 'd-flex justify-content-between align-items-center mb-3' },
    el('h2', {}, 'Mon compte'),
    el('a', { href: '#/connexion', class: 'btn btn-outline-secondary' }, 'Changer de compte')
  );
  const info = el('div', { class: 'card mb-3' },
    el('div', { class: 'card-body' },
      el('div', { class: 'mb-1' }, 'Email: ', el('strong', {}, me.email || '')),
      el('div', { class: 'text-muted small' }, me.is_admin ? 'Rôle: Administrateur' : 'Rôle: Utilisateur')
    )
  );
  // Notifications toggle
  const subToggle = el('input', { type: 'checkbox', class: 'form-check-input' });
  subToggle.addEventListener('change', async () => {
    try { await api.subscribe(subToggle.checked); toast('Préférence sauvegardée'); } catch (e) { toast('Erreur', true); }
  });
  const notif = el('div', { class: 'card mb-3' },
    el('div', { class: 'card-body d-flex align-items-center gap-2' },
      el('div', { class: 'form-check form-switch m-0' }, subToggle, el('label', { class: 'form-check-label ms-2' }, 'Recevoir des notifications des nouvelles extensions'))
    )
  );
  root.append(header, info, notif);
  if (me.is_admin) {
    // Admin tools
    const admin = el('div', { class: 'card' },
      el('div', { class: 'card-body' },
        el('div', { class: 'h5 mb-3' }, 'Administration'),
        el('div', { class: 'd-flex flex-wrap gap-2' },
          (function(){ const b = el('button', { class: 'btn btn-primary' }, 'Synchroniser via API'); b.onclick = async () => {
            try { const r = await api.cardsRefresh(); toast('Synchronisé: ' + ((r && r.synced) || 0) + ' cartes'); } catch(e) { toast('Erreur: ' + (e.message||''), true); }
          }; return b; })(),
          (function(){ const b = el('button', { class: 'btn btn-outline-secondary' }, 'Scanner CDN (avancé)'); b.onclick = async () => {
            // Use current set from local filter if available
            const defaultSet = 'OGN';
            try { await openCdnScanModal(defaultSet); } catch {}
          }; return b; })()
        )
      )
    );
    root.append(admin);
  }
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
        const qtyInput = (() => { const inp = el('input', { type: 'number', min: '0', value: it.qty || 0, class: 'form-control text-center' }); inp.addEventListener('click', ev => ev.stopPropagation()); return inp; })();
        const imgWrap = el('div', { class: 'card-img-wrapper', onclick: (ev) => { ev.stopPropagation(); openCardModal(it); } },
          el('img', { src: it.image_url || 'assets/img/card-placeholder.svg', alt: it.name, loading: 'lazy', decoding: 'async' })
        );
  const idBadge = el('div', { class: 'card-id-badge' }, '#' + (it.card_id || '').toUpperCase());
  // Icons row ABOVE the image on collection card
  const topRow2 = el('div', { class: 'top-icon-row' });
  const oci2 = colorIcon(it.color, 'sm');
  if (oci2) topRow2.appendChild(el('div', { class: 'icon-pill' }, oci2));
  const oti2 = typeIcon(it.card_type, 'sm');
  if (oti2) topRow2.appendChild(el('div', { class: 'icon-pill' }, oti2));
        const qtyCtrl = el('div', { class: 'qty-ctrl' },
          el('button', { class: 'btn btn-sm btn-outline-secondary', onclick: async (ev) => { ev.stopPropagation(); const n = Math.max(0, (it.qty||0) - 1); await api.collectionSet(it.card_id, n); it.qty = n; qtyInput.value = n; if (n === 0) wrap.remove(); } }, '−'),
          qtyInput,
          el('button', { class: 'btn btn-sm btn-outline-secondary', onclick: async (ev) => { ev.stopPropagation(); const n = (it.qty||0) + 1; await api.collectionSet(it.card_id, n); it.qty = n; qtyInput.value = n; } }, '+')
        );
        const footer = el('div', { class: 'card-footer-bar', onclick: (ev) => { ev.stopPropagation(); } }, idBadge, qtyCtrl);
        const trash = el('button', { class: 'corner-trash', title: 'Retirer (Alt/Ctrl: tout retirer)', onclick: async (ev) => {
          ev.stopPropagation();
          try {
            const full = !!(ev.altKey || ev.ctrlKey || ev.shiftKey);
            const next = full ? 0 : Math.max(0, (it.qty||0) - 1);
            await api.collectionSet(it.card_id, next);
            it.qty = next;
            qtyInput.value = next;
            if (full) {
              toast('Carte retirée de la collection');
            } else {
              toast('Quantité mise à jour: ' + next);
            }
            if (next === 0) wrap.remove();
          } catch (e) { toast('Erreur: ' + (e.message||''), true); }
        } }, (function(){ const s = document.createElementNS('http://www.w3.org/2000/svg','svg'); s.setAttribute('viewBox','0 0 16 16'); s.innerHTML = '<path fill="currentColor" d="M6.5 1a1 1 0 0 0-1 1H3a.5.5 0 0 0 0 1h10a.5.5 0 0 0 0-1h-2.5a1 1 0 0 0-1-1h-3ZM4.118 5.5l.427 7.67A2 2 0 0 0 6.538 15h2.924a2 2 0 0 0 1.993-1.83l.427-7.67H4.118ZM5 5.5h6l-.42 7.545a1 1 0 0 1-.997.955H6.538a1 1 0 0 1-.997-.955L5 5.5Zm2 .5a.5.5 0 0 1 .5.5v5a.5.5 0 0 1-1 0v-5A.5.5 0 0 1 7 6Zm3 0a.5.5 0 0 1 .5.5v5a.5.5 0 0 1-1 0v-5a.5.5 0 0 1 .5-.5Z"/>'; return s; })());
        const cardChildren = [];
        if (topRow2.childNodes.length) cardChildren.push(topRow2);
        cardChildren.push(imgWrap, footer, trash);
        const card = el('div', { class: 'card h-100 clickable position-relative card-tile', onclick: () => openCardModal(it) }, ...cardChildren);
  const wrap = el('div', { class: 'col-12 col-sm-6 col-md-4 col-lg-5ths' }, card);
        list.append(wrap);
      });
    }
    const header = el('div', { class: 'd-flex justify-content-between align-items-center mb-2' },
      el('h2', {}, 'Ma collection'),
      (function(){ const b = el('button', { class: 'btn btn-outline-primary' }, 'Ajouter via caméra'); b.onclick = async (ev) => { ev.preventDefault(); try { await openScanModal(); } catch(_){} }; return b; })()
    );
    root.append(header, list);
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

// Guides index
router.register('#/guide', async (root) => {
  root.innerHTML = '';
  const intro = el('div', { class: 'mb-3 text-muted' }, "Astuces, probabilités et explications pour mieux collectionner et ouvrir vos boosters.");
  const list = el('div', { class: 'row g-3' });
  const card = el('div', { class: 'col-md-6 col-lg-4' },
    el('div', { class: 'card h-100' },
      el('div', { class: 'card-body d-flex flex-column' },
        el('div', { class: 'h5' }, 'Taux de drop (Origins)'),
        el('div', { class: 'text-muted mb-3' }, 'Comprendre les raretés, compositions des boosters et probabilités.'),
        el('div', { class: 'mt-auto' }, el('a', { class: 'btn btn-sm btn-outline-secondary', href: '#/guide/drop' }, 'Lire le guide'))
      )
    )
  );
  list.append(card);
  root.append(el('h2', {}, 'Guide'), intro, list);
});

// Guide: Taux de drop
router.register('#/guide/drop', async (root) => {
  root.innerHTML = '';
  const wrap = el('article', { class: 'card p-3 p-md-4' });
  const tags = el('div', { class: 'mb-2 d-flex flex-wrap gap-2' },
    el('span', { class: 'badge badge-soft' }, 'GUIDE'),
    el('span', { class: 'badge badge-soft-blue' }, 'TCG'),
    el('span', { class: 'badge badge-soft-gray' }, 'RIOT'),
  );
  const title = el('h2', { class: 'mb-1' }, "Riftbound Origins : Tous les taux de drop, raretés et cartes exclusives à connaître avant d’ouvrir vos boosters !");
  const meta = el('div', { class: 'text-muted mb-3' }, 'Publié le 30/10/2025 à 21:35 par Léo Girardeau');
  const intro = el('p', {}, "Découvrez tous les taux de drop et les raretés du JCC Riftbound Origins : communes, rares, épiques, alt-art et cartes signées. Composition des boosters, pull rates et cartes spéciales expliqués.");

  const hr = el('hr');
  const p1 = el('p', {}, "Les fans de Riftbound: League of Legends Trading Card Game le savent : ouvrir un booster est une expérience unique. Entre l’adrénaline du moment, le plaisir de la collection et la stratégie du deckbuilding, chaque pack promet son lot de surprises.");
  const p2 = el('p', {}, "Mais que contient exactement un booster ? Quelles sont les différentes raretés, et surtout, quelles sont les chances de tomber sur ces fameuses cartes épiques, alternatives ou signées ? Voici le guide complet pour tout comprendre sur les raretés et taux de drop de Riftbound: Origins.");

  const links = el('ul', {},
    el('li', {}, el('a', { href: '#/actus' }, 'Riftbound : Les 10 cartes les plus puissantes du set Origins')),
    el('li', {}, el('a', { href: '#/actus' }, 'Riftbound : Kai’Sa et Master Yi dominent le tournoi national de Shanghai')),
    el('li', {}, el('a', { href: '#/actus' }, 'Guide du meilleur Deck Sett Midrange sur Riftbound Origins')),
    el('li', {}, el('a', { href: '#/actus' }, 'Riftbound Spiritforged : Tout ce qu’on sait sur le Set 2 du TCG de Riot Games')),
  );

  // Composition des boosters
  const h3comp = el('h3', {}, "Composition d’un booster Riftbound: Origins");
  const pcomp = el('p', {}, "Chaque booster Riftbound Origins contient 14 cartes au total, réparties comme suit :");
  const table = (() => {
    const t = el('table', { class: 'table table-dark table-striped table-bordered table-sm align-middle' });
    const thead = el('thead', {}, el('tr', {}, el('th', {}, 'Contenu'), el('th', {}, 'Quantité')));
    const tbody = el('tbody');
    const rows = [
      ['Cartes communes', '7'],
      ['Cartes peu communes', '3'],
      ['Cartes rares ou mieux', '2'],
      ['Carte foil (toutes raretés possibles)', '1'],
      ['Carte Rune ou Token', '1'],
      ['Insert d’information', '1'],
    ];
    rows.forEach(r => tbody.appendChild(el('tr', {}, el('td', {}, r[0]), el('td', {}, r[1]))));
    t.append(thead, tbody);
    return t;
  })();
  const pcomp2 = el('p', {}, "Chaque pack garantit donc au moins deux cartes rares ou supérieures, et la possibilité de tomber sur des versions foil, alt art, showcase ou signées.");

  // Raretés
  const h3rar = el('h3', {}, 'Les différentes raretés de Riftbound');
  const prar = el('p', {}, "Riftbound distingue quatre raretés fonctionnelles, identifiées par la couleur de leur cadre et la forme du gemme de rareté :");
  const rarTable = (() => {
    const t = el('table', { class: 'table table-dark table-striped table-bordered table-sm align-middle' });
    const thead = el('thead', {}, el('tr', {}, el('th', {}, 'Rareté'), el('th', {}, 'Couleur du cadre'), el('th', {}, 'Symbole'), el('th', {}, 'Détails')));
    const tbody = el('tbody');
    const rows = [
      ['Commune', 'Bronze', 'Rond', "Cartes simples, accessibles, essentielles pour la base d’un deck."],
      ['Peu commune', 'Argenté', 'Triangle', "Effets plus complexes ou synergiques. Inclut les cartes Battlefield."],
      ['Rare', 'Or avec effet foil', 'Carré', "Cartes puissantes ou emblématiques, dont toutes les Champion Legends."],
      ['Épique (Epic)', 'Or minimaliste avec finition foil', 'Pentagone', "Les cartes les plus recherchées pour le jeu compétitif."],
    ];
    rows.forEach(r => tbody.appendChild(el('tr', {}, el('td', {}, r[0]), el('td', {}, r[1]), el('td', {}, r[2]), el('td', {}, r[3]))));
    t.append(thead, tbody);
    return t;
  })();

  // Taux de drop
  const h3rate = el('h3', {}, 'Les taux de drop (Pull Rates)');
  const prate = el('p', {}, "Selon les données officielles de Magical Meta, voici les probabilités d’obtenir chaque rareté dans un pack :");
  const rateTable = (() => {
    const t = el('table', { class: 'table table-dark table-striped table-bordered table-sm align-middle' });
    const thead = el('thead', {}, el('tr', {}, el('th', {}, 'Rareté'), el('th', {}, 'Taux d’apparition'), el('th', {}, 'Moyenne par boîte (24 packs)'), el('th', {}, 'Spécificité')));
    const tbody = el('tbody');
    const rows = [
      ['Commune', '7 par pack', '~168 par boîte', '~2 copies spécifiques par boîte'],
      ['Peu commune', '3 par pack', '~72 par boîte', '~1 copie spécifique par boîte'],
      ['Rare', '2 par pack', '~48 par boîte', "~1 copie spécifique toutes les 50 cartes"],
      ['Épique', '1 toutes les 4 packs', '~6 par boîte', '~1 copie spécifique par case'],
      ['Alt Art', '1 toutes les 12 packs', '~2 par boîte', '~1 copie spécifique toutes les 2 cases'],
      ['Showcase', '1 toutes les 72 packs', '~1 toutes les 3 boîtes', '~1 spécifique toutes les 6 cases'],
      ['Signée', '1 toutes les 720 packs', '~1 toutes les 30 boîtes', 'Ultra rare : ~1 spécifique toutes les 60 cases'],
    ];
    rows.forEach(r => tbody.appendChild(el('tr', {}, el('td', {}, r[0]), el('td', {}, r[1]), el('td', {}, r[2]), el('td', {}, r[3]))));
    t.append(thead, tbody);
    return t;
  })();
  const resumen = el('p', {}, "En résumé, un joueur ouvrant une boîte complète (24 boosters) peut espérer :");
  const resumenList = el('ul', {},
    el('li', {}, '6 cartes épiques,'),
    el('li', {}, '2 alt-art,'),
    el('li', {}, 'Et, s’il est chanceux, une Showcase ou une carte signée.'),
  );

  // Cartes spéciales
  const h3spec = el('h3', {}, 'Les cartes spéciales');
  const h4alt = el('h4', {}, 'Alt-Art (art alternatif)');
  const palt = el('p', {}, "Les 12 Champions principaux de Riftbound: Origins disposent chacun d’une version Alt-Art inspirée d’un skin de League of Legends. Ces cartes présentent :");
  const listAlt = el('ul', {},
    el('li', {}, 'Un foil spécial,'),
    el('li', {}, 'Un symbole hexagonal,'),
    el('li', {}, 'Et une illustration exclusive.'),
  );
  const palt2 = el('p', {}, "Elles apparaissent environ 2 fois par boîte, assez rares pour être convoitées, mais assez fréquentes pour enrichir les collections.");

  const h4over = el('h4', {}, 'Overnumbered Cards');
  const pover = el('p', {}, "Ces cartes « hors série » (numérotées au-delà des 298 cartes de l’extension) reprennent les Champions principaux avec des illustrations signées par les artistes de Riot Games eux-mêmes.");
  const poverList = el('ul', {},
    el('li', {}, 'Environ 1 Overnumber toutes les 3 boîtes.'),
    el('li', {}, 'Environ 1 Signature Overnumber (avec signature foil) toutes les 10 Overnumbers.'),
  );
  const pover2 = el('p', {}, "Ces cartes sont non jouables en format compétitif, mais constituent des objets de collection uniques qui ne seront jamais réédités avec le même visuel.");

  const h4foil = el('h4', {}, 'Foil Slot et Runes spéciales');
  const pfoil = el('p', {}, "Chaque booster contient une carte foil garantie. La plupart du temps, il s’agit d’une Commune ou d’une Uncommon, mais le slot peut être remplacé par :");
  const pfoilList = el('ul', {},
    el('li', {}, 'Une Rare ou Épique foil,'),
    el('li', {}, 'Ou, très rarement, une Rune alternative foil.'),
  );
  const pfoil2 = el('p', {}, "Ces Runes peuvent même apparaître dans des versions alt-art, ultra rares et recherchées.");

  const h3philo = el('h3', {}, 'Une philosophie de collection équilibrée');
  const pphilo = el('p', {}, "L’équipe de Riftbound insiste sur un point : toutes les cartes, même les communes, sont pensées pour être utiles et jouables. Les Rares et Épiques apportent du fun et de la puissance, mais sans déséquilibrer la méta.");
  const pphilo2 = el('p', {}, "L’objectif : permettre à chaque joueur, même occasionnel, de monter un deck compétitif sans dépenser une fortune, tout en offrant des trésors de collection pour les passionnés.");

  wrap.append(tags, title, meta, intro, hr,
    p1, p2, links, hr,
    h3comp, pcomp, table, pcomp2, hr,
    h3rar, prar, rarTable, hr,
    h3rate, prate, rateTable, resumen, resumenList, hr,
    h3spec, h4alt, palt, listAlt, palt2, h4over, pover, poverList, pover2, h4foil, pfoil, pfoilList, pfoil2, hr,
    h3philo, pphilo, pphilo2
  );
  root.append(wrap);
});

// (Removed duplicate '#/compte' route; merged into the earlier route with admin tools)

router.register('#/404', (root) => { root.innerHTML = '<div class="alert alert-warning">Page introuvable.</div>'; });

// Camera scan view
router.register('#/scan', async (root) => {
  root.innerHTML = '';
  const me = await api.me().catch(() => null);
  if (!me) { root.append(el('div', { class: 'alert alert-info' }, 'Connectez-vous pour utiliser le scanner.')); return; }

  const info = el('div', { class: 'alert alert-secondary' }, 'Cadrez la carte. Appuyez sur Analyser pour détecter l\'identifiant (ex: OGN-310).');
  const video = el('video', { playsinline: true, autoplay: true, class: 'w-100 rounded border', style: 'max-height:60vh; background:#000' });
  const canvas = el('canvas', { class: 'd-none' });
  // Camera container with overlay watermark and framing
  const overlay = el('div', { class: 'scan-overlay', style: 'pointer-events:none;' },
    el('div', { class: 'frame' }),
    el('div', { class: 'wm wm-top' }, 'RiftCollect • RiftCollect • RiftCollect • RiftCollect • '),
    el('div', { class: 'wm wm-bottom' }, 'RiftCollect • RiftCollect • RiftCollect • RiftCollect • '),
    el('div', { class: 'wm wm-left' }, 'RiftCollect • RiftCollect • RiftCollect • '),
    el('div', { class: 'wm wm-right' }, 'RiftCollect • RiftCollect • RiftCollect • '),
    (function(){ const b = document.createElement('div'); b.className='scan-ocr-badge'; b.id='scanOcrBadgeRoute'; b.style.pointerEvents='auto'; b.style.display='none'; const lab=document.createElement('span'); lab.className='label'; lab.textContent='Détecté:'; b.appendChild(lab); return b; })()
  );
  const camWrap = el('div', { class: 'scan-cam-wrap position-relative my-2' }, video, overlay);
  const controls = el('div', { class: 'd-flex flex-wrap gap-2 my-3' });
  const startBtn = el('button', { class: 'btn btn-primary' }, 'Démarrer la caméra');
  const shotBtn = el('button', { class: 'btn btn-outline-primary', disabled: true }, 'Analyser');
  const stopBtn = el('button', { class: 'btn btn-outline-secondary', disabled: true }, 'Stop');
  let autoScanToggle; // declare before usage to avoid TDZ
  const autoToggleWrap = el('label', { class: 'btn btn-outline-secondary d-flex align-items-center gap-2' },
    (function(){ const i = document.createElement('input'); i.type = 'checkbox'; i.className = 'form-check-input'; i.style.marginRight = '.25rem'; i.checked = false; return (autoScanToggle = i); })(),
    document.createTextNode('Analyse auto')
  );
  const uploadInput = el('input', { type: 'file', accept: 'image/*', capture: 'environment', class: 'form-control', style: 'max-width:320px' });
  const results = el('div', { class: 'mt-3' });
  const ocrBadgeRoute = overlay.querySelector('#scanOcrBadgeRoute');
  // Ensure the dashed frame fits within the visible video area
  installFrameFitter(video, overlay, 0.9);

  let stream = null;
  let collectionMap = {};
  let autoScanTimer = null;
  let ocrBusy = false;

  // Preload collection quantities for increment behavior
  try {
    const data = await api.collectionGet();
    collectionMap = Object.fromEntries((data.items||[]).map(it => [it.card_id, it.qty||0]));
  } catch {}

  async function startCamera() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
      video.srcObject = stream;
      shotBtn.disabled = false;
      stopBtn.disabled = false;
      // If auto is enabled, start periodic OCR
      if (autoScanToggle && autoScanToggle.checked) {
        if (autoScanTimer) clearInterval(autoScanTimer);
        autoScanTimer = setInterval(async () => {
          if (ocrBusy) return;
          const variants = captureOcrRegionDataUrlVariants();
          if (variants.length) await ocrAndDetect(variants);
        }, 1500);
      }
    } catch (e) {
      toast('Caméra indisponible: ' + (e.message||''), true);
    }
  }
  function stopCamera() {
    if (stream) {
      for (const t of stream.getTracks()) t.stop();
      stream = null;
    }
    shotBtn.disabled = true;
    stopBtn.disabled = true;
    if (autoScanTimer) { clearInterval(autoScanTimer); autoScanTimer = null; }
  }

  async function toggleTorch() {
    try {
      if (!stream) return;
      const track = stream.getVideoTracks && stream.getVideoTracks()[0];
      if (!track || !track.applyConstraints) return;
      torchOn = !torchOn;
      await track.applyConstraints({ advanced: [{ torch: torchOn }] });
      torchBtn.classList.toggle('btn-outline-secondary', !torchOn);
      torchBtn.classList.toggle('btn-warning', torchOn);
    } catch {}
  }

  async function loadTesseract() {
    if (window.Tesseract) return window.Tesseract;
    await new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
      s.onload = resolve; s.onerror = () => reject(new Error('Tesseract.js load failed'));
      document.head.appendChild(s);
    });
    return window.Tesseract;
  }
  async function loadJsQR() {
    if (window.jsQR) return window.jsQR;
    await new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';
      s.onload = resolve; s.onerror = () => reject(new Error('jsQR load failed'));
      document.head.appendChild(s);
    });
    return window.jsQR;
  }
  function getFrameRectInCanvas() {
    const w = video.videoWidth || 1280; const h = video.videoHeight || 720;
    const videoRect = video.getBoundingClientRect();
    const frameEl = overlay.querySelector('.frame');
    const frameRect = frameEl.getBoundingClientRect();
    const relX = (frameRect.left - videoRect.left) / videoRect.width;
    const relY = (frameRect.top - videoRect.top) / videoRect.height;
    const relW = frameRect.width / videoRect.width; const relH = frameRect.height / videoRect.height;
    return { x: relX * w, y: relY * h, w: relW * w, h: relH * h, cw: w, ch: h };
  }
  function preprocessToDataUrl(drawFn, scale = 2.2, threshold = 0) {
    const tmp = document.createElement('canvas');
    const tctx = tmp.getContext('2d');
    drawFn(tctx, tmp);
    const iw = tmp.width; const ih = tmp.height;
    const ow = Math.min(1200, Math.max(300, Math.floor(iw * scale)));
    const oh = Math.floor(ih * (ow / iw));
    canvas.width = ow; canvas.height = oh;
    const ctx = canvas.getContext('2d');
    ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(tmp, 0, 0, iw, ih, 0, 0, ow, oh);
    // Grayscale + optional threshold to boost text
    const img = ctx.getImageData(0, 0, ow, oh);
    const d = img.data;
    for (let i = 0; i < d.length; i += 4) {
      const r = d[i], g = d[i+1], b = d[i+2];
      let v = Math.round(0.299*r + 0.587*g + 0.114*b);
      // Slight contrast boost
      v = Math.min(255, Math.max(0, Math.floor((v - 128) * 1.25 + 128)));
      if (threshold > 0) {
        v = v > threshold ? 255 : 0;
      }
      d[i] = d[i+1] = d[i+2] = v;
    }
    ctx.putImageData(img, 0, 0);
    return canvas.toDataURL('image/png');
  }
  function captureOcrRegionDataUrlVariants() {
    // Only analyze the mini ID rectangle inside the frame
    const w = video.videoWidth || 1280; const h = video.videoHeight || 720;
    const { x, y, w: fw, h: fh } = getFrameRectInCanvas();
  const ID_LEFT = 0.05, ID_BOTTOM = 0.012, ID_W = 0.22, ID_H = 0.06;
    const sx = Math.max(0, Math.floor(x + fw * ID_LEFT));
    const sy = Math.max(0, Math.floor(y + fh * (1 - ID_BOTTOM - ID_H)));
    const sw = Math.min(w - sx, Math.floor(fw * ID_W));
    const sh = Math.min(h - sy, Math.floor(fh * ID_H));
    if (sw <= 0 || sh <= 0) return [];
    const draw = (ctx, cv) => { cv.width = sw; cv.height = sh; ctx.drawImage(video, sx, sy, sw, sh, 0, 0, sw, sh); };
    return [
      preprocessToDataUrl(draw, 2.2, 0),
      preprocessToDataUrl(draw, 2.2, 170),
      preprocessToDataUrl(draw, 2.2, 200),
    ];
  }

  function keyFromEl(cardEl) {
    // Preference: backend key -> scryfall-id -> (name + set)
    if (cardEl.dataset.cardKey) return { type: 'backend', key: cardEl.dataset.cardKey };
    if (cardEl.dataset.scryfallId) return { type: 'scryfall-id', key: cardEl.dataset.scryfallId };
    if (cardEl.dataset.cardName) {
      return { type: 'scryfall-name', key: cardEl.dataset.cardName, set: cardEl.dataset.cardSet || '' };
    }
    return null;
  }

  // (live price manager moved to global scope)


});


// Init
bootstrapAuthUi();
// --- Live price manager (adds "en live" monetary value to each card) ---
(function () {
  const DEFAULT_ENDPOINT = 'api.php?action=card.price'; // Backend endpoint to resolve a card id to a price
  const REFRESH_MS = 60_000; // 60s
  const CACHE_MS = 30_000; // 30s cache to avoid spamming
  const SELECTOR = '[data-card-key], [data-scryfall-id], [data-card-name]';
  const cache = new Map(); // key -> { t: timestamp, price, currency }

  function keyFromEl(cardEl) {
    if (!cardEl || !(cardEl instanceof HTMLElement)) return null;
    if (cardEl.dataset.cardKey) return { type: 'backend', key: cardEl.dataset.cardKey };
    if (cardEl.dataset.scryfallId) return { type: 'scryfall-id', key: cardEl.dataset.scryfallId };
    if (cardEl.dataset.cardName) return { type: 'scryfall-name', key: cardEl.dataset.cardName, set: cardEl.dataset.cardSet || '' };
    return null;
  }

  function formatCurrency(amount, currency = '€', locale = 'fr-FR') {
    try {
      if (/^[A-Z]{3}$/.test(currency)) {
        return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(amount);
      }
      return new Intl.NumberFormat(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + ' ' + currency;
    } catch {
      return `${amount} ${currency}`;
    }
  }

  function ensureBadge(cardEl) {
    let badge = cardEl.querySelector('.live-price-badge');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'live-price-badge';
      badge.textContent = '...';
      badge.style.cssText = `
        position: absolute; top: 8px; right: 8px; z-index: 2;
        background: rgba(0,0,0,0.7); color: #fff; padding: 2px 8px;
        border-radius: 12px; font-size: 12px; line-height: 18px;
      `;
      const computed = getComputedStyle(cardEl);
      if (computed.position === 'static') {
        cardEl.style.position = 'relative';
      }
      cardEl.appendChild(badge);
    }
    return badge;
  }

  function cacheGet(key) {
    const hit = cache.get(key);
    if (!hit) return null;
    if (Date.now() - hit.t > CACHE_MS) return null;
    return hit;
  }

  function cacheSet(key, price, currency) {
    cache.set(key, { t: Date.now(), price, currency });
  }

  function computeStubPriceFromEl(cardEl) {
    const rar = (cardEl && cardEl.dataset && (cardEl.dataset.cardRarity || '')).toLowerCase();
    const map = {
      'common': 0.10,
      'commune': 0.10,
      'uncommon': 0.25,
      'peu commune': 0.25,
      'rare': 1.00,
      'epic': 3.50,
      'epique': 3.50,
      'legendary': 8.00,
      'legendaire': 8.00,
      'overnumbered': 15.00,
    };
    let price = map[rar] ?? 0.00;
    try {
      // small bump if variant suffix in id (e.g., OGN-007a)
      const id = (cardEl && cardEl.dataset && cardEl.dataset.cardKey) || '';
      if (/-[0-9]{1,4}[a-z]$/i.test(id)) price = Math.max(price, price * 1.15);
    } catch {}
    return { price: Math.round(price * 100) / 100, currency: 'EUR' };
  }

  async function fetchFromBackend(key) {
    const url = `${DEFAULT_ENDPOINT}?key=${encodeURIComponent(key)}`;
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`Backend price error ${res.status}`);
    const json = await res.json();
    const payload = (json && typeof json === 'object' && 'data' in json) ? json.data : json;
    return { price: payload.price, currency: payload.currency || '€' };
  }

  async function fetchFromScryfallById(id) {
    const res = await fetch(`https://api.scryfall.com/cards/${encodeURIComponent(id)}`);
    if (!res.ok) throw new Error(`Scryfall id error ${res.status}`);
    const json = await res.json();
    return extractScryfallPrice(json);
  }

  async function fetchFromScryfallByName(name, set) {
    const params = new URLSearchParams({ exact: name });
    if (set) params.set('set', set);
    const res = await fetch(`https://api.scryfall.com/cards/named?${params.toString()}`);
    if (!res.ok) throw new Error(`Scryfall name error ${res.status}`);
    const json = await res.json();
    return extractScryfallPrice(json);
  }

  function extractScryfallPrice(cardJson) {
    const prices = cardJson.prices || {};
    if (prices.eur != null) return { price: parseFloat(prices.eur), currency: 'EUR' };
    if (prices.usd != null) return { price: parseFloat(prices.usd), currency: 'USD' };
    if (prices.tix != null) return { price: parseFloat(prices.tix), currency: 'TIX' };
    throw new Error('No price available');
  }

  async function resolvePrice(cardEl) {
    const info = keyFromEl(cardEl);
    if (!info) throw new Error('No key for card');

    const cacheKey = JSON.stringify(info);
    const hit = cacheGet(cacheKey);
    if (hit) return hit;

    let result;
    if (info.type === 'backend' && DEFAULT_ENDPOINT) {
      try {
        result = await fetchFromBackend(info.key);
      } catch (_) {
        // Fallback to stub if backend not available (e.g., 404)
        result = computeStubPriceFromEl(cardEl);
      }
    } else if (info.type === 'scryfall-id') {
      result = await fetchFromScryfallById(info.key);
    } else if (info.type === 'scryfall-name') {
      result = await fetchFromScryfallByName(info.key, info.set);
    } else {
      // Unknown key type: fallback stub
      result = computeStubPriceFromEl(cardEl);
    }

    cacheSet(cacheKey, result.price, result.currency);
    return result;
  }

  async function updateCardPrice(cardEl) {
    const badge = ensureBadge(cardEl);
    try {
      badge.textContent = '...';
      const { price, currency } = await resolvePrice(cardEl);
      badge.textContent = formatCurrency(price, currency);
      badge.title = 'Valeur en live';
    } catch (e) {
      badge.textContent = '—';
      badge.title = (e && e.message) || 'Prix indisponible';
    }
  }

  // Fetch only when visible, and only once per element (outside explicit refreshes)
  const loadedOnce = new WeakSet();
  let io = null;
  function ensureObserved(cardEl) {
    if (!cardEl || !(cardEl instanceof HTMLElement)) return;
    if (loadedOnce.has(cardEl)) return;
    if ('IntersectionObserver' in window) {
      if (!io) {
        io = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              const el = entry.target;
              if (!loadedOnce.has(el)) {
                loadedOnce.add(el);
                updateCardPrice(el);
              }
              io.unobserve(el);
            }
          });
        }, { root: null, rootMargin: '0px', threshold: 0.2 });
      }
      io.observe(cardEl);
    } else {
      // Fallback without IO: fetch immediately but only once
      loadedOnce.add(cardEl);
      updateCardPrice(cardEl);
    }
  }

  function scanAndObserve() {
    const initial = Array.from(document.querySelectorAll(SELECTOR));
    initial.forEach(el => ensureObserved(el));
    const mo = new MutationObserver((muts) => {
      muts.forEach(m => {
        m.addedNodes.forEach(n => {
          if (!(n instanceof HTMLElement)) return;
          if (n.matches && n.matches(SELECTOR)) ensureObserved(n);
          n.querySelectorAll && n.querySelectorAll(SELECTOR).forEach(el => ensureObserved(el));
        });
      });
    });
    mo.observe(document.body || document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scanAndObserve);
  } else {
    scanAndObserve();
  }
})();

router.start();

// Logout is wired in bootstrapAuthUi()
