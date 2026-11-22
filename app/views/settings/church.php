<?php $title = 'Biserică și logo'; ?>
<main class="page narrow">
  <div class="cols">
    <form method="post" class="card stack">
      <?= csrf_field() ?>
      <h2>Biserica</h2>
      <label class="field">Numele aplicației<input type="text" name="app_name" value="<?= e(app_name()) ?>" maxlength="60"></label>
      <label class="field">Numele bisericii <span class="hint">apare în emailuri și pe pagina de intrare</span><input type="text" name="church_name" value="<?= e(setting('church_name', '')) ?>" maxlength="120"></label>
      <div class="row" style="justify-content:flex-end"><button class="btn primary" type="submit">Salvează</button></div>
    </form>
    <section class="card stack">
      <h2>Logo</h2>
      <div class="row"><img src="<?= e(logo_url()) ?>" alt="Logo" style="width:64px;height:64px;object-fit:contain"><span class="xs muted grow">Apare în bara de sus, pe pagina de intrare și ca iconiță în tab. PNG (ideal cu fundal transparent), JPG sau WebP, până la 5 MB.</span></div>
      <form method="post" enctype="multipart/form-data" class="row" style="flex-wrap:wrap;gap:8px"><?= csrf_field() ?><input type="hidden" name="action" value="logo">
        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required class="grow"><button class="btn" type="submit">Încarcă logoul</button></form>
      <?php if (brand_file('logo.png')): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="logo_reset"><button class="btn ghost sm" type="submit">Revino la logoul implicit</button></form>
      <?php endif; ?>
    </section>
    <section class="card stack">
      <h2>Email</h2>
      <p class="small muted">Emailurile (confirmare, parolă nouă, aprobare) pleacă de la <strong><?= e((string)cfg('mail_from', '')) ?></strong>. Trimite-ți un email de test ca să verifici că ajung.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test_mail"><button class="btn" type="submit">Trimite-mi un email de test</button></form>
      <p class="xs muted">Versiunea aplicației: <?= e(APP_VERSION) ?></p>
    </section>
  </div>
</main>
