<?php $title = 'Intră în cont'; ?>
<div class="stack" style="gap:6px">
  <h1>Bine ai venit</h1>
  <p class="muted small">Intră în contul tău din echipa de voluntari.</p>
</div>
<?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="stack" style="gap:14px" novalidate>
  <?= csrf_field() ?>
  <label class="field">Email
    <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
  </label>
  <label class="field">Parolă
    <input type="password" name="password" autocomplete="current-password" required>
  </label>
  <div class="row" style="justify-content:space-between">
    <label class="check"><input type="checkbox" name="remember" value="1" checked>Ține-mă minte</label>
    <a class="small" href="<?= e(url('parola-uitata')) ?>">Am uitat parola</a>
  </div>
  <button class="btn primary lg block" type="submit">Intră în cont</button>
</form>
<p class="small muted" style="text-align:center">Nu ai cont? Cere liderului de echipă un link de invitație.</p>
