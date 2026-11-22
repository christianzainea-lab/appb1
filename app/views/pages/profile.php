<?php
$title = 'Profil';
$err = fn($k) => isset($errors[$k]) ? '<span class="err">' . e($errors[$k]) . '</span>' : '';
$pending = $u['status'] !== 'active';
?>
<main class="page narrow">
  <?php if ($pending): ?>
    <div class="alert info">Contul tău așteaptă aprobarea unui administrator. Între timp îți poți completa profilul. <a href="<?= e(url('asteptare')) ?>">Vezi starea</a></div>
  <?php endif; ?>
  <div class="row" style="gap:16px">
    <?= avatar_html($u, 72) ?>
    <div class="grow stack" style="gap:4px">
      <h1><?= e($u['name']) ?></h1>
      <span class="small muted"><span class="badge"><?= e(role_label($u['role'])) ?></span> · în echipă din <?= e(ro_month_year($u['created_at'])) ?></span>
    </div>
    <button class="btn sm" type="button" data-open="dlg-photo">Schimbă poza</button>
    <button class="btn primary" type="submit" form="profile-form">Salvează</button>
  </div>

  <?php if ($u['status'] === 'active'): ?>
  <a class="guide-link" href="<?= e(url('ghid')) ?>">
    <span class="guide-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M10 9l5 2.5-5 2.5z" fill="currentColor"/><path d="M8 21h8"/></svg></span>
    <span class="grow"><strong>Cum funcționează aplicația</strong><br><span class="xs muted">Ghid în imagini · un minut</span></span>
    <span aria-hidden="true">›</span>
  </a>
  <?php endif; ?>

  <form id="profile-form" method="post" class="cols" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <div class="stack" style="gap:16px">
      <section class="card stack">
        <h2>Date personale</h2>
        <div class="grid-2">
          <label class="field<?= isset($errors['name']) ? ' has-err' : '' ?>">Nume complet
            <input type="text" name="name" value="<?= e($_POST['name'] ?? $u['name']) ?>" autocomplete="name" required><?= $err('name') ?>
          </label>
          <label class="field<?= isset($errors['phone']) ? ' has-err' : '' ?>">Număr de WhatsApp
            <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? $u['phone']) ?>" autocomplete="tel" placeholder="+40 7xx xxx xxx"><?= $err('phone') ?>
          </label>
        </div>
        <div class="stack" style="gap:3px">
          <span class="small" style="font-weight:600">Email</span>
          <span><?= e($u['email']) ?> <?= $u['email_verified_at'] ? '<span class="xs" style="font-weight:600;color:var(--ok)">· confirmat</span>' : '<span class="xs" style="font-weight:600;color:var(--warn)">· neconfirmat</span>' ?></span>
        </div>
      </section>
      <section class="card stack">
        <h2>Notificări</h2>
        <label class="check"><input type="checkbox" name="notify_push" value="1"<?= $u['notify_push'] ? ' checked' : '' ?>>Notificări pe telefon când sunt programat</label>
        <label class="check"><input type="checkbox" name="notify_reminder" value="1"<?= $u['notify_reminder'] ? ' checked' : '' ?>>Amintește-mi cu o zi înainte</label>
        <p class="xs muted" style="border-top:1px solid var(--line-2);padding-top:10px">Notificările pe telefon vin într-o versiune următoare. Până atunci, adaugă programul în calendar (mai jos).</p>
      </section>
    </div>
    <section class="card stack" style="gap:10px">
      <h2>Unde slujesc</h2>
      <span class="small muted">Poți fi programat doar pe pozițiile selectate aici.</span>
      <?php $selPos = $mine; $selParts = $myParts; include APP_DIR . '/views/partials/positions.php'; ?>
      <div class="row" style="justify-content:flex-end;padding-top:6px"><button class="btn primary" type="submit">Salvează</button></div>
    </section>
  </form>

  <?php if ($calUrl): $webcal = preg_replace('#^https?://#', 'webcal://', $calUrl); ?>
  <section class="card stack" id="calendar">
    <h2>Programul în calendarul tău</h2>
    <span class="small" style="color:var(--text-2)">Serviciile publicate la care ești programat apar singure în calendarul de pe telefon sau în Google Calendar și se actualizează când se schimbă ceva.</span>
    <div class="row" style="gap:8px">
      <a class="btn primary sm" href="https://calendar.google.com/calendar/r?cid=<?= e(rawurlencode($webcal)) ?>" target="_blank" rel="noopener">Adaugă în Google Calendar</a>
      <a class="btn sm" href="<?= e($webcal) ?>">Calendar iPhone / Mac</a>
      <button class="btn ghost sm" type="button" data-copy="#cal-url">Copiază linkul</button>
    </div>
    <input type="text" id="cal-url" value="<?= e($calUrl) ?>" readonly class="xs" style="font-family:ui-monospace,Menlo,monospace" aria-label="Linkul calendarului" onclick="this.select()">
    <p class="xs muted">Linkul e personal – nu-l da altcuiva. Google Calendar îl actualizează de câteva ori pe zi, nu imediat.</p>
    <form method="post" style="align-self:flex-start"><?= csrf_field() ?><input type="hidden" name="action" value="calendar_renew"><button class="btn ghost sm" type="submit" data-confirm="Faci un link nou? Cel vechi nu va mai funcționa și va trebui să adaugi calendarul din nou.">Fă un link nou</button></form>
  </section>
  <?php endif; ?>

  <section class="card row">
    <h2 style="flex-basis:100%">Cont</h2>
    <button class="btn sm" type="button" data-open="dlg-pass">Schimbă parola</button>
    <span class="spacer"></span>
    <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="btn ghost danger sm" type="submit">Ieși din cont</button></form>
  </section>
</main>

<dialog id="dlg-pass"<?= $showPassword ? ' data-autoshow' : '' ?>>
  <form method="post" class="dlg">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h2>Schimbă parola</h2>
    <?php if ($u['password_hash']): ?>
      <label class="field<?= isset($errors['current']) ? ' has-err' : '' ?>">Parola actuală<input type="password" name="current" autocomplete="current-password" required><?= $err('current') ?></label>
    <?php endif; ?>
    <label class="field<?= isset($errors['password']) ? ' has-err' : '' ?>">Parola nouă <span class="hint">cel puțin 8 caractere</span><input type="password" name="password" autocomplete="new-password" minlength="8" required><?= $err('password') ?></label>
    <label class="field<?= isset($errors['password2']) ? ' has-err' : '' ?>">Scrie parola nouă încă o dată<input type="password" name="password2" autocomplete="new-password" minlength="8" required><?= $err('password2') ?></label>
    <div class="row" style="justify-content:flex-end"><button class="btn ghost" type="button" data-close>Renunță</button><button class="btn primary" type="submit">Salvează parola</button></div>
  </form>
</dialog>

<dialog id="dlg-photo">
  <div class="dlg">
    <h2>Poza de profil</h2>
    <form method="post" enctype="multipart/form-data" class="stack">
      <?= csrf_field() ?><input type="hidden" name="action" value="avatar">
      <label class="field">Alege o poză <span class="hint">JPG, PNG sau WEBP · se decupează pătrat</span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>
      <div class="row" style="justify-content:flex-end">
        <button class="btn ghost" type="button" data-close>Renunță</button>
        <button class="btn primary" type="submit">Încarcă</button>
      </div>
    </form>
    <?php if ($u['avatar']): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="avatar_remove"><button class="btn ghost danger sm" type="submit">Scoate poza actuală</button></form>
    <?php endif; ?>
  </div>
</dialog>
