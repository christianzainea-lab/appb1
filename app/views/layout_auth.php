<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#650B0A">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($title ?? '') ? $title . ' · ' : '') ?><?= e(app_name()) ?></title>
<link rel="icon" href="<?= e(favicon_url()) ?>" media="(prefers-color-scheme: light)">
<link rel="icon" href="<?= e(favicon_url(true)) ?>" media="(prefers-color-scheme: dark)">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
</head>
<body>
<div class="auth">
  <main class="auth-inner">
    <div class="auth-top">
      <img src="<?= e(logo_url()) ?>" alt="<?= e(setting('church_name') ?: 'Logo biserică') ?>">
      <?php if (current_user()): ?>
        <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="btn ghost sm">Ieși din cont</button></form>
      <?php endif; ?>
    </div>
    <?php foreach (flash() as [$type, $msg]): ?>
      <div class="alert <?= $type === 'error' ? 'error' : 'ok' ?>" role="status"><?= e($msg) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
<script src="<?= e(asset('app.js')) ?>"></script>
</body>
</html>
