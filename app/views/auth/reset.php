<?php $title = 'Parolă nouă'; ?>
<div class="stack" style="gap:6px">
  <h1>Alege o parolă nouă</h1>
  <p class="muted small">Cel puțin 8 caractere.</p>
</div>
<?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="stack" style="gap:14px">
  <?= csrf_field() ?>
  <label class="field">Parola nouă
    <input type="password" name="password" autocomplete="new-password" minlength="8" required autofocus>
  </label>
  <label class="field">Scrie parola încă o dată
    <input type="password" name="password2" autocomplete="new-password" minlength="8" required>
  </label>
  <button class="btn primary lg block" type="submit">Salvează parola</button>
</form>
