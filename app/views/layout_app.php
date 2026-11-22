<?php
$me = current_user();
$isEditor = can('editor');
$canPlan = can_plan();
$active = $me && $me['status'] === 'active';
$nav = $nav ?? '';
$sub = $sub ?? '';
$cur = fn(string $k) => $nav === $k ? ' aria-current="page"' : '';
$icons = [
    'program' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
    'cantari' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
    'indisponibil' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M9 14l6 5M15 14l-6 5"/></svg>',
    'editare' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
    'stare' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'profil' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>',
];
?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#FFFFFF">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($title ?? '') ? $title . ' · ' : '') ?><?= e(app_name()) ?></title>
<link rel="icon" href="<?= e(favicon_url()) ?>" media="(prefers-color-scheme: light)">
<link rel="icon" href="<?= e(favicon_url(true)) ?>" media="(prefers-color-scheme: dark)">
<link rel="apple-touch-icon" href="<?= e(logo_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
</head>
<body class="app">
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="<?= e(url('')) ?>"><img src="<?= e(logo_url()) ?>" alt=""><span><?= e(app_name()) ?></span></a>
    <nav class="mainnav" aria-label="Meniu">
      <?php if ($active): ?>
        <a href="<?= e(url('')) ?>"<?= $cur('program') ?>>Programul meu</a>
        <a href="<?= e(url('cantari')) ?>"<?= $cur('cantari') ?>>Cântări</a>
        <?php if ($canPlan): ?><a href="<?= e(url('planificare')) ?>"<?= $cur('planificare') ?>>Planificare</a><?php endif; ?>
        <a href="<?= e(url('indisponibil')) ?>"<?= $cur('indisponibil') ?>><?= $isEditor ? 'Indisponibil' : 'Zile indisponibile' ?></a>
        <a href="<?= e(url('profil')) ?>"<?= $cur('profil') ?>>Profil</a>
        <?php if ($isEditor): ?><a href="<?= e(url('setari')) ?>"<?= $cur('setari') ?>>Setări</a><?php endif; ?>
      <?php else: ?>
        <a href="<?= e(url('asteptare')) ?>">Starea contului</a>
        <a href="<?= e(url('profil')) ?>"<?= $cur('profil') ?>>Profil</a>
      <?php endif; ?>
    </nav>
    <?php if ($me): ?>
      <span class="me-role"><?= e(role_label($me['role'])) ?></span>
      <a class="me-avatar" href="<?= e(url('profil')) ?>" aria-label="Profilul meu"><?= avatar_html($me, 36, '#650B0A', '#FFFFFF') ?></a>
      <button class="menu-btn" type="button" aria-label="Meniu" aria-expanded="false" aria-controls="menu-pop" data-menu-toggle><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    <?php endif; ?>
  </div>
  <?php if ($me): ?>
  <div class="menu-pop" id="menu-pop" hidden>
    <div class="menu-pop-inner stack"><?php include APP_DIR . '/views/partials/menu_items.php'; ?></div>
  </div>
  <?php endif; ?>
</header>
<?php if ($nav === 'setari'): ?>
<div class="subnav">
  <nav class="subnav-inner" aria-label="Secțiuni setări">
    <span>Setări</span>
    <?php foreach (['whatsapp' => 'Mesaj WhatsApp', 'sabloane' => 'Șabloane', 'persoane' => 'Persoane și invitații', 'pozitii' => 'Roluri și poziții', 'etichete' => 'Etichete cântări', 'tonuri' => 'Tonuri', 'carti' => 'Cărți', 'biserica' => 'Biserică și logo'] as $k => $label): ?>
      <a href="<?= e(url('setari/' . $k)) ?>"<?= $sub === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</div>
<?php endif; ?>
<?php $flashes = flash(); if ($flashes): ?>
<div class="page" style="padding-bottom:0">
  <?php foreach ($flashes as [$type, $msg]): ?>
    <div class="alert <?= $type === 'error' ? 'error' : 'ok' ?>" role="status"><?= e($msg) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?= $content ?>
<nav class="bottomnav" aria-label="Meniu">
  <?php if ($active): ?>
    <a href="<?= e(url('')) ?>"<?= $cur('program') ?>><?= $icons['program'] ?>Program</a>
    <a href="<?= e(url('cantari')) ?>"<?= $cur('cantari') ?>><?= $icons['cantari'] ?>Cântări</a>
    <?php if ($canPlan): ?>
      <a href="<?= e(url('planificare')) ?>"<?= $nav === 'planificare' ? ' aria-current="page"' : '' ?>><?= $icons['editare'] ?>Planificare</a>
    <?php endif; ?>
    <a href="<?= e(url('indisponibil')) ?>"<?= $cur('indisponibil') ?>><?= $icons['indisponibil'] ?>Indisponibil</a>
  <?php else: ?>
    <a href="<?= e(url('asteptare')) ?>"><?= $icons['stare'] ?>Starea contului</a>
  <?php endif; ?>
  <a href="<?= e(url('profil')) ?>"<?= $cur('profil') ?>><?= $icons['profil'] ?>Profil</a>
</nav>
<?php if ($me && $me['status'] === 'active' && empty($me['guide_seen_at']) && ($nav ?? '') !== 'ghid'): ?>
<dialog id="guide-dlg" class="guide-dlg" data-autoshow aria-label="Bun venit">
  <div class="dlg stack" style="align-items:center;text-align:center;gap:12px">
    <img src="<?= e(logo_url()) ?>" alt="" style="width:72px;height:auto">
    <h2>Bun venit, <?= e(first_name($me['name'])) ?>!</h2>
    <p class="small" style="color:var(--text-2);margin:0">Vezi în un minut, în imagini, ce poți face în aplicație.</p>
    <a class="btn primary block" href="<?= e(url('ghid')) ?>">Arată-mi</a>
    <form method="post" action="<?= e(url('ghid')) ?>" data-guide-later style="width:100%"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e(trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/')) ?>"><button class="btn ghost block" type="submit">Mai târziu</button></form>
    <span class="xs muted">Îl găsești oricând în Profil și în meniul ☰.</span>
  </div>
</dialog>
<?php endif; ?>
<script src="<?= e(asset('app.js')) ?>"></script>
</body>
</html>
