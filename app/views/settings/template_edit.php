<?php
$isNew = !$t;
$title = $isNew ? 'Șablon nou' : $t['name'];
$err = fn($k) => isset($errors[$k]) ? '<span class="err">' . e($errors[$k]) . '</span>' : '';
$val = fn($k, $d = '') => e($_POST[$k] ?? ($t[$k] ?? $d));
$curWd = (string)($_POST['weekday'] ?? ($t['weekday'] ?? '0'));
$curRule = (string)($_POST['rule'] ?? ($t['rule'] ?? 'every'));
?>
<main class="page">
  <form method="post" class="stack" style="gap:16px">
    <?= csrf_field() ?>
    <a class="back-link" href="<?= e(url('setari/sabloane')) ?>">‹ Toate șabloanele</a>
    <div class="page-head">
      <div class="grow"><h1><?= $isNew ? 'Șablon nou' : 'Editează șablonul' ?></h1><span class="small muted">Schimbările se aplică evenimentelor create de acum înainte.</span></div>
      <button class="btn primary" type="submit">Salvează șablonul</button>
    </div>
    <div class="cols">
      <div class="stack" style="gap:16px">
        <section class="card stack">
          <h2>Când</h2>
          <label class="field<?= isset($errors['name']) ? ' has-err' : '' ?>">Nume<input type="text" name="name" value="<?= $val('name') ?>" maxlength="120" required><?= $err('name') ?></label>
          <div class="grid-2">
            <label class="field">Ziua
              <select name="weekday"><?php foreach ([4, 0, 1, 2, 3, 5, 6] as $wd): ?><option value="<?= $wd ?>"<?= $curWd === (string)$wd ? ' selected' : '' ?>><?= RO_DAYS[$wd] ?></option><?php endforeach; ?></select></label>
            <label class="field">Se repetă
              <select name="rule"><?php foreach (RULES as $k => $v): ?><option value="<?= $k ?>"<?= $curRule === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
          </div>
          <div class="grid-2">
            <label class="field<?= isset($errors['start_time']) ? ' has-err' : '' ?>">Ora<input type="time" name="start_time" value="<?= $val('start_time', '10:00') ?>" required><?= $err('start_time') ?></label>
            <label class="field<?= isset($errors['rehearsal_time']) ? ' has-err' : '' ?>">Repetiție <span class="hint">opțional</span><input type="time" name="rehearsal_time" value="<?= $val('rehearsal_time') ?>"><?= $err('rehearsal_time') ?></label>
            <label class="field">Lider implicit <span class="hint">opțional · îl poți schimba la fiecare serviciu</span>
              <select name="leader_id">
                <option value="">— fără —</option>
                <?php $curLeader = (int)($_POST['leader_id'] ?? ($t['leader_id'] ?? 0)); foreach ($people as $pp): ?>
                  <option value="<?= (int)$pp['id'] ?>"<?= $curLeader === (int)$pp['id'] ? ' selected' : '' ?>><?= e($pp['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <label class="check"><input type="checkbox" name="active" value="1"<?= ($isNew || $t['active']) ? ' checked' : '' ?>>Activ – creează automat evenimentele în planificare</label>
          <?php if ($drafts): ?><label class="check"><input type="checkbox" name="apply_drafts" value="1">Actualizează și cele <?= $drafts ?> ciorne viitoare create din acest șablon</label><?php endif; ?>
        </section>
        <section class="card stack">
          <h2>Ordinea implicită</h2>
          <span class="xs muted">Golurile pentru cântări se completează la fiecare eveniment. Folosește săgețile ca să schimbi ordinea.</span>
          <div class="stack" style="gap:6px" data-items>
            <?php foreach ($items as $it): ?>
              <div class="tpl-item edit" data-item>
                <select name="item_kind[]" aria-label="Tip" style="width:auto;min-height:36px;font-size:13px"><option value="item"<?= $it['kind'] === 'item' ? ' selected' : '' ?>>Element</option><option value="song"<?= $it['kind'] === 'song' ? ' selected' : '' ?>>Cântare</option></select>
                <input type="text" name="item_title[]" value="<?= e($it['title']) ?>" aria-label="Titlu" style="min-height:36px;font-size:14px" maxlength="120">
                <button class="icon-btn" type="button" data-move="-1" aria-label="Mai sus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg></button>
                <button class="icon-btn" type="button" data-move="1" aria-label="Mai jos"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button>
                <button class="icon-btn danger" type="button" data-remove-item aria-label="Scoate"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
              </div>
            <?php endforeach; ?>
          </div>
          <template id="item-tpl">
              <div class="tpl-item edit" data-item>
                <select name="item_kind[]" aria-label="Tip" style="width:auto;min-height:36px;font-size:13px"><option value="item">Element</option><option value="song">Cântare</option></select>
                <input type="text" name="item_title[]" value="" aria-label="Titlu" style="min-height:36px;font-size:14px" maxlength="120">
                <button class="icon-btn" type="button" data-move="-1" aria-label="Mai sus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg></button>
                <button class="icon-btn" type="button" data-move="1" aria-label="Mai jos"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button>
                <button class="icon-btn danger" type="button" data-remove-item aria-label="Scoate"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
              </div>
          </template>
          <div class="row" style="gap:6px">
            <button class="btn sm dashed" type="button" data-add-item="song">+ Loc pentru cântare</button>
            <?php foreach (['Rugăciune', 'Predică', 'Anunțuri', 'Citire biblică', 'Cina Domnului'] as $x): ?><button class="chip sm" type="button" data-add-item="item" data-title="<?= e($x) ?>">+ <?= e($x) ?></button><?php endforeach; ?>
            <button class="chip sm" type="button" data-add-item="item" data-title="">+ Alt element</button>
          </div>
        </section>
      </div>
      <section class="card stack">
        <h2>Poziții necesare</h2>
        <span class="xs muted">Câte locuri are fiecare poziție la eveniment (0 = nu e nevoie).</span>
        <?php foreach ($tree as $g): if (!$g['positions']) continue; ?>
          <span class="group-label"><?= e($g['name']) ?></span>
          <div class="slot-grid">
            <?php foreach ($g['positions'] as $p): $n = (int)($_POST['slots'][$p['id']] ?? ($slots[(int)$p['id']] ?? 0)); ?>
              <label class="slot-item<?= $n ? ' on' : '' ?>"><span class="grow"><?= e($p['name']) ?></span>
                <input type="number" name="slots[<?= (int)$p['id'] ?>]" value="<?= $n ?>" min="0" max="10" inputmode="numeric" aria-label="Locuri <?= e($p['name']) ?>" data-slot-input></label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </section>
    </div>
    <div class="row" style="justify-content:flex-end"><a class="btn" href="<?= e(url('setari/sabloane')) ?>">Anulează</a><button class="btn primary" type="submit">Salvează șablonul</button></div>
  </form>
  <?php if (!$isNew): ?>
    <form method="post" class="card stack" style="gap:8px;max-width:520px">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete">
      <strong class="small">Șterge șablonul</strong>
      <span class="xs muted">Evenimentele deja create din el rămân în planificare; nu se mai creează altele noi.</span>
      <label class="check xs"><input type="checkbox" name="confirm" value="1" required data-enables="tpl-del">Înțeleg că șablonul se șterge definitiv</label>
      <button class="btn ghost danger sm" id="tpl-del" type="submit" disabled style="align-self:flex-start">Șterge șablonul</button>
    </form>
  <?php endif; ?>
</main>
