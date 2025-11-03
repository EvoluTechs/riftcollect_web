<?php
// Emits an SVG banner with embedded logo as a data URI so it renders as a CSS background in all browsers.
header('Content-Type: image/svg+xml; charset=utf-8');
$w = 1920; $h = 600;
$logoPath = __DIR__ . '/logo.png';
$hasLogo = is_file($logoPath);
$logoData = $hasLogo ? base64_encode(file_get_contents($logoPath)) : '';
// Logo placement (aligned right with some margin)
$logoTranslateX = 1080; // move further right by increasing
$logoTranslateY = -80;
$logoWidth = 800; $logoHeight = 800;
$gold = '#e7d18a';
$bg1 = '#0f1216'; $bg2 = '#111625'; $bg3 = '#0b0d14';
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?= $w ?>" height="<?= $h ?>" viewBox="0 0 <?= $w ?> <?= $h ?>" preserveAspectRatio="xMidYMid slice">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="<?= $bg1 ?>"/>
      <stop offset="50%" stop-color="<?= $bg2 ?>"/>
      <stop offset="100%" stop-color="<?= $bg3 ?>"/>
    </linearGradient>
    <radialGradient id="glow" cx="50%" cy="50%" r="60%">
      <stop offset="0%" stop-color="#3b4a6a" stop-opacity="0.15"/>
      <stop offset="100%" stop-color="#3b4a6a" stop-opacity="0"/>
    </radialGradient>
    <filter id="shadow" x="-50%" y="-50%" width="200%" height="200%">
      <feDropShadow dx="0" dy="8" stdDeviation="12" flood-color="#000" flood-opacity="0.6"/>
    </filter>
  </defs>

  <rect width="<?= $w ?>" height="<?= $h ?>" fill="url(#bg)"/>
  <rect width="<?= $w ?>" height="<?= $h ?>" fill="url(#glow)"/>
  
  <!-- Decorative gold lines (top/bottom) -->
  <g stroke="<?= $gold ?>" stroke-opacity="0.6" stroke-width="2">
    <path d="M0 520 H<?= $w ?>"/>
    <path d="M0 80 H<?= $w ?>"/>
  </g>

  <?php if ($hasLogo): ?>
  <image x="0" y="0" width="<?= $logoWidth ?>" height="<?= $logoHeight ?>" preserveAspectRatio="xMidYMid meet"
    transform="translate(<?= $logoTranslateX ?>, <?= $logoTranslateY ?>)"
    filter="url(#shadow)"
    xlink:href="data:image/png;base64,<?= $logoData ?>" xmlns:xlink="http://www.w3.org/1999/xlink" />
  <?php endif; ?>
</svg>
