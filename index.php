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
    <link href="assets/css/style.css" rel="stylesheet">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script type="module" src="assets/js/app.js"></script>
  </body>
</html>
