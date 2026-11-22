<?php $title = 'Am uitat parola'; ?>
<div class="stack" style="gap:6px">
  <h1>Ai uitat parola?</h1>
  <p class="muted small">Scrie emailul contului și îți trimitem un link ca să alegi o parolă nouă.</p>
</div>
<?php if ($sent): ?>
  <div class="alert ok" role="status">Dacă există un cont cu adresa <?= e($email) ?>, am trimis acum un email cu linkul. Verifică și dosarul Spam.</div>
  <a class="btn lg block" href="<?= e(url('login')) ?>">Înapoi la intrare</a>
<?php else: ?>
<form method="post" class="stack" style="gap:14px">
  <?= csrf_field() ?>
  <label class="field">Email
    <input type="email" name="email" autocomplete="email" required autofocus>
  </label>
  <button class="btn primary lg block" type="submit">Trimite linkul</button>
  <a class="small" href="<?= e(url('login')) ?>" style="text-align:center">Înapoi la intrare</a>
</form>
<?php endif; ?>
