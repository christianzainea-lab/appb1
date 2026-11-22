<?php $title = 'Înscriere'; $err = fn($k) => isset($errors[$k]) ? '<span class="err">' . e($errors[$k]) . '</span>' : ''; ?>
<div class="stack" style="gap:6px">
  <h1><?= $inv['kind'] === 'personal' && $inv['name'] ? 'Bine ai venit, ' . e(first_name($inv['name'])) . '!' : 'Alătură-te echipei' ?></h1>
  <span class="small muted"><?= $inv['kind'] === 'personal' ? 'Invitație personală' : 'Link de invitație' ?><?= $inv['expires_at'] ? ' · valabil până pe ' . e(ro_date($inv['expires_at'])) : '' ?></span>
</div>
<?php if ($errors): ?><div class="alert error" role="alert">Verifică câmpurile marcate mai jos.</div><?php endif; ?>
<form method="post" class="stack" style="gap:14px" novalidate>
  <?= csrf_field() ?>
  <label class="field<?= isset($errors['name']) ? ' has-err' : '' ?>">Nume complet
    <input type="text" name="name" value="<?= e($old['name']) ?>" autocomplete="name" required>
    <?= $err('name') ?>
  </label>
  <label class="field<?= isset($errors['email']) ? ' has-err' : '' ?>">Email
    <input type="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email" placeholder="nume@exemplu.ro" required>
    <?= $err('email') ?>
  </label>
  <label class="field<?= isset($errors['phone']) ? ' has-err' : '' ?>">Număr de WhatsApp <span class="hint">opțional</span>
    <input type="tel" name="phone" value="<?= e($old['phone']) ?>" autocomplete="tel" placeholder="+40 7xx xxx xxx">
    <?= $err('phone') ?>
  </label>
  <label class="field<?= isset($errors['password']) ? ' has-err' : '' ?>">Parolă <span class="hint">cel puțin 8 caractere</span>
    <input type="password" name="password" autocomplete="new-password" minlength="8" required>
    <?= $err('password') ?>
  </label>
  <fieldset>
    <legend>Unde slujești? <span class="muted" style="font-weight:500">(opțional)</span></legend>
    <span class="xs muted">Poți completa și mai târziu, din profil. Editorii pot seta pozițiile pentru tine.</span>
    <?php $selPos = $old['positions']; $selParts = $old['parts']; include APP_DIR . '/views/partials/positions.php'; ?>
  </fieldset>
  <button class="btn primary lg block" type="submit" style="margin-top:4px">Creează contul</button>
  <span class="xs muted" style="text-align:center">Ai deja cont? <a href="<?= e(url('login')) ?>">Intră în cont</a></span>
</form>
