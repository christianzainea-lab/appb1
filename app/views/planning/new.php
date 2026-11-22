<?php $title = 'Eveniment special'; $err = fn($k) => isset($errors[$k]) ? '<span class="err">' . e($errors[$k]) . '</span>' : ''; ?>
<main class="page narrow" style="max-width:640px">
  <a class="back-link" href="<?= e(url('planificare')) ?>">‹ Înapoi la planificare</a>
  <h1>Eveniment special</h1>
  <form method="post" class="card stack">
    <?= csrf_field() ?>
    <label class="field<?= isset($errors['name']) ? ' has-err' : '' ?>">Nume<input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="ex. Crăciun, Botez, Concert" required maxlength="120"><?= $err('name') ?></label>
    <div class="grid-3">
      <label class="field<?= isset($errors['date']) ? ' has-err' : '' ?>">Data<input type="date" name="date" value="<?= e($_POST['date'] ?? '') ?>" required><?= $err('date') ?></label>
      <label class="field<?= isset($errors['time']) ? ' has-err' : '' ?>">Ora<input type="time" name="time" value="<?= e($_POST['time'] ?? '10:00') ?>" required><?= $err('time') ?></label>
      <label class="field<?= isset($errors['rehearsal']) ? ' has-err' : '' ?>">Repetiție<input type="time" name="rehearsal" value="<?= e($_POST['rehearsal'] ?? '') ?>"><?= $err('rehearsal') ?></label>
    </div>
    <label class="field">Pornește de la șablonul <span class="hint">pozițiile se copiază; le poți schimba apoi</span>
      <select name="template_id"><option value="">Fără șablon (adaugi pozițiile pe eveniment)</option>
        <?php foreach ($tpls as $t): ?><option value="<?= (int)$t['id'] ?>"<?= (int)($_POST['template_id'] ?? 0) === (int)$t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
      </select></label>
    <div class="row" style="justify-content:flex-end"><a class="btn" href="<?= e(url('planificare')) ?>">Anulează</a><button class="btn primary" type="submit">Creează ciorna</button></div>
  </form>
</main>
