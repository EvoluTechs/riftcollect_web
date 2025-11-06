<?php
// RiftCollect - Frontend Shell
// Simple SPA using hash routing, Bootstrap, and vanilla JS
// Safety: if rewrite misroutes /riot.txt to this script, serve it as plain text
if (isset($_SERVER['REQUEST_URI']) && preg_match('#/riot\.txt$#', $_SERVER['REQUEST_URI'])) {
  $p = __DIR__ . '/riot.txt';
  if (is_file($p)) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    // Some verifiers are strict: ensure no extra spaces/newlines
    $content = file_get_contents($p);
    $content = trim($content); // remove any leading/trailing whitespace/CRLF
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
      echo $content; // send body only for non-HEAD
    }
    exit;
  }
}
?>
<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RiftCollect — TCG Riftbound</title>
    <meta name="description" content="RiftCollect — Parcourir les cartes Riftbound, gérer sa collection et suivre les extensions.">
    <?php
      // Favicon: prefer assets/img/favicon.png, fallback to assets/img/logo.png
      $fav = 'assets/img/favicon.png';
      $favAbs = __DIR__ . '/' . $fav;
      if (!is_file($favAbs)) { $fav = 'assets/img/logo.png'; $favAbs = __DIR__ . '/' . $fav; }
      $favUrl = htmlspecialchars($fav . (is_file($favAbs) ? ('?v=' . filemtime($favAbs)) : ''), ENT_QUOTES);
    ?>
    <link rel="icon" type="image/png" href="<?php echo $favUrl; ?>">
    <link rel="apple-touch-icon" href="<?php echo $favUrl; ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <?php
      // Cache-busting for static assets served by PHP (avoid stale JS/CSS in browsers)
      $cssPath = __DIR__ . '/assets/css/style.css';
      $cssUrl = 'assets/css/style.css' . (is_file($cssPath) ? ('?v=' . filemtime($cssPath)) : '');
    ?>
    <link href="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES); ?>" rel="stylesheet">
  </head>
  <body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
      <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#/">
          <img src="assets/img/logo.png" alt="RiftCollect" id="brandLogo">
          <span class="visually-hidden">RiftCollect</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link" href="#/cartes">Cartes</a></li>
            <li class="nav-item" id="navCollectionItem" style="display:none;"><a class="nav-link" href="#/collection">Ma collection</a></li>
            <li class="nav-item" id="navStatsItem" style="display:none;"><a class="nav-link" href="#/stats">Statistiques</a></li>
            <li class="nav-item"><a class="nav-link" href="#/actus">Actus</a></li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Guide</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#/guide">Tous les guides</a></li>
                <li><a class="dropdown-item" href="#/guide/drop">Taux de drop (Origins)</a></li>
              </ul>
            </li>
          </ul>
          <ul class="navbar-nav">
            <li class="nav-item dropdown" id="accountDropdown" style="display:none;">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span id="navUserEmail"></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#/compte">Compte</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" id="logoutBtn">Se déconnecter</a></li>
              </ul>
            </li>
            <li class="nav-item" id="loginLink"><a class="nav-link" href="#/connexion">Se connecter</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <main class="container py-4" id="app-root">
      <!-- Route content injected here -->
    </main>

    <footer class="border-top py-4 mt-5">
      <div class="container small text-muted">
        <div class="d-flex justify-content-between flex-wrap gap-2">
          <div>
            RiftCollect n&#39;est pas affilié à Riftbound. Données issues de l&#39;API officielle (respect des conditions d&#39;utilisation).
          </div>
          <div>
            © <?php echo date('Y'); ?> RiftCollect
          </div>
        </div>
      </div>
    </footer>

    <!-- Card detail modal -->
    <div class="modal fade" id="cardModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
          <div class="modal-header">
            <h5 class="modal-title" id="cardModalTitle">Carte</h5>
            <div class="ms-auto d-flex align-items-center gap-2">
              <label for="cardModalLang" class="small text-muted">Langue</label>
              <select id="cardModalLang" class="form-select form-select-sm bg-dark text-light" style="width:auto">
                <option value="fr-FR">FR</option>
                <option value="en-US">EN</option>
              </select>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="container-fluid">
              <div class="row g-3 align-items-start">
                <div class="col-md-6">
                  <img id="cardModalImage" class="modal-card-img w-100 rounded border" alt="Carte" />
                </div>
                <div class="col-md-6">
                  <div class="vstack gap-2">
                    <div class="h4 mb-1" id="cardModalName">&nbsp;</div>
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                      <div class="d-flex flex-wrap gap-1" id="cardModalBadges"></div>
                      <div class="stat-chips d-flex align-items-center gap-2" id="cardModalStats" aria-label="Stats"></div>
                    </div>
                    <div id="cardModalDesc" class="mt-2 card-desc">&nbsp;</div>
                    <div class="mt-2 details-panel">
                      <div class="d-flex justify-content-between align-items-center mb-2 section-title-wrap">
                        <div class="fw-semibold section-title">Détails</div>
                        <div class="d-flex gap-2">
                          <button type="button" class="btn btn-sm btn-outline-secondary" id="cardModalToggleJson" style="display:none;">Afficher JSON</button>
                        </div>
                      </div>
                      <dl class="row mb-0 small dl-compact" id="cardModalDetails"></dl>
                      <pre id="cardModalRaw" class="mt-2 small d-none" style="max-height:30vh; overflow:auto; white-space:pre-wrap;"></pre>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <?php
      $appJsPath = __DIR__ . '/assets/js/app.js';
      $appJsUrl = 'assets/js/app.js' . (is_file($appJsPath) ? ('?v=' . filemtime($appJsPath)) : '');
    ?>
    <script type="module" src="<?php echo htmlspecialchars($appJsUrl, ENT_QUOTES); ?>"></script>
  </body>
</html>
