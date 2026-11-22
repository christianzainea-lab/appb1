<?php
$title = 'Roluri și poziții';
$act = e(url('setari/pozitii'));
$ico = [
    'up' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>',
    'down' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>',
    'del' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg>',
    'more' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>',
];
$hidden = fn(string $action, int $id) => csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $id . '">';
$Y = '<span class="c y">✓</span>'; $N = '<span class="c n">—</span>';
// [text, voluntar, pastor, editor] – adminul poate tot
$perms = [
    ['Vadă programul și cântările', 1, 1, 1], ['Asculte înregistrări și deschidă acorduri', 1, 1, 1], ['Își seteze zilele indisponibile', 1, 1, 1],
    ['Își aleagă singur pozițiile din profil', 1, 1, 1],
    ['Lucreze la ordinea serviciului (fără să aleagă cântări) și copieze mesajul WhatsApp', 0, 1, 1],
    ['Programeze oameni și publice serviciile', 0, 0, 1], ['Aleagă cântările și adauge sau editeze cântări', 0, 0, 1],
    ['Dea poziții și eticheta „Lider” voluntarilor', 0, 0, 1], ['Editeze șabloane, poziții și setări', 0, 0, 1],
    ['Schimbe rolul cuiva sau modifice editori și pastori', 0, 0, 0], ['Aprobe conturi noi', 0, 0, 0],
];
?>
<main class="page">
  <div class="page-head">
    <div class="grow">
      <h1>Roluri și poziții</h1>
      <span class="small muted">Aici se definesc pozițiile din echipă. Oamenii primesc rolul și pozițiile din <a href="<?= e(url('setari/persoane')) ?>">Persoane și invitații</a> sau din profilul lor.</span>
    </div>
  </div>

  <div class="cols">
    <section class="stack" style="flex:999 1 640px" aria-label="Poziții în echipă">
      <h2>Poziții în echipă</h2>
      <div class="pgroups">
        <?php foreach ($tree as $gi => $g): ?>
          <div class="pgroup">
            <div class="pgroup-head">
              <form method="post" action="<?= $act ?>" class="grow"><?= $hidden('group_rename', (int)$g['id']) ?>
                <label><span class="sr">Numele grupului</span><input type="text" name="name" value="<?= e($g['name']) ?>" onchange="this.form.submit()"></label>
              </form>
              <span class="xs muted" style="white-space:nowrap"><?= count($g['positions']) ?> poziții</span>
              <details class="menu">
                <summary class="icon-btn" aria-label="Opțiuni grup"><?= $ico['more'] ?></summary>
                <div class="menu-body">
                  <?php if ($gi > 0): ?><form method="post" action="<?= $act ?>"><?= $hidden('group_up', (int)$g['id']) ?><button class="btn ghost sm block" type="submit">Mută grupul mai sus</button></form><?php endif; ?>
                  <?php if ($gi < count($tree) - 1): ?><form method="post" action="<?= $act ?>"><?= $hidden('group_down', (int)$g['id']) ?><button class="btn ghost sm block" type="submit">Mută grupul mai jos</button></form><?php endif; ?>
                  <form method="post" action="<?= $act ?>"><?= $hidden('group_delete', (int)$g['id']) ?><button class="btn ghost danger sm block" type="submit" <?= $g['positions'] ? 'disabled title="Grupul are poziții"' : 'data-confirm="Ștergi grupul?"' ?>>Șterge grupul</button></form>
                </div>
              </details>
            </div>
            <?php foreach ($g['positions'] as $pi => $p): ?>
              <div class="pos">
                <div class="pos-line">
                  <span class="name"><?= e($p['name']) ?></span>
                  <span class="xs muted" style="margin-right:4px"><?= (int)$p['people'] ?> pers.</span>
                  <form method="post" action="<?= $act ?>"><?= $hidden('pos_up', (int)$p['id']) ?><button class="icon-btn" type="submit" aria-label="Mută <?= e($p['name']) ?> mai sus"<?= $pi === 0 ? ' disabled' : '' ?>><?= $ico['up'] ?></button></form>
                  <form method="post" action="<?= $act ?>"><?= $hidden('pos_down', (int)$p['id']) ?><button class="icon-btn" type="submit" aria-label="Mută <?= e($p['name']) ?> mai jos"<?= $pi === count($g['positions']) - 1 ? ' disabled' : '' ?>><?= $ico['down'] ?></button></form>
                  <details class="menu">
                    <summary class="icon-btn" aria-label="Opțiuni <?= e($p['name']) ?>"><?= $ico['more'] ?></summary>
                    <div class="menu-body">
                      <form method="post" action="<?= $act ?>" class="inline-form"><?= $hidden('pos_rename', (int)$p['id']) ?><label class="grow"><span class="sr">Nume nou</span><input type="text" name="name" value="<?= e($p['name']) ?>" required></label><button class="btn sm" type="submit">Redenumește</button></form>
                      <form method="post" action="<?= $act ?>" class="inline-form"><?= $hidden('part_add', (int)$p['id']) ?><label class="grow"><span class="sr">Partidă nouă</span><input type="text" name="name" placeholder="<?= mb_strtolower($p['name']) === 'voce' ? 'ex. Mezzo' : 'Variantă nouă' ?>" required></label><button class="btn sm" type="submit">+ <?= mb_strtolower($p['name']) === 'voce' ? 'Partidă' : 'Variantă' ?></button></form>
                      <?php if (count($tree) > 1): ?>
                        <form method="post" action="<?= $act ?>" class="inline-form"><?= $hidden('pos_move', (int)$p['id']) ?>
                          <label class="grow"><span class="sr">Mută în grupul</span><select name="group_id" style="min-height:34px;font-size:13px">
                            <?php foreach ($tree as $og): if ((int)$og['id'] === (int)$g['id']) continue; ?><option value="<?= (int)$og['id'] ?>"><?= e($og['name']) ?></option><?php endforeach; ?>
                          </select></label><button class="btn sm" type="submit">Mută</button></form>
                      <?php endif; ?>
                      <form method="post" action="<?= $act ?>"><?= $hidden('pos_delete', (int)$p['id']) ?><input type="hidden" name="confirm" value="1"><button class="btn ghost danger sm block" type="submit" data-confirm="Ștergi poziția „<?= e($p['name']) ?>”?<?= $p['people'] ? ' ' . (int)$p['people'] . ' persoane o au în profil și o vor pierde.' : '' ?> Dispare și din șabloane.">Șterge poziția</button></form>
                    </div>
                  </details>
                </div>
                <?php if ($p['parts']): ?>
                  <div class="parts">
                    <span class="xs muted"><?= mb_strtolower($p['name']) === 'voce' ? 'Partide' : 'Variante' ?>:</span>
                    <?php foreach ($p['parts'] as $pt): ?>
                      <form method="post" action="<?= $act ?>" class="part-tag"><?= $hidden('part_delete', (int)$pt['id']) ?><?= e($pt['name']) ?><button type="submit" aria-label="Șterge <?= e($pt['name']) ?>" data-confirm="Ștergi „<?= e($pt['name']) ?>”?">×</button></form>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <form method="post" action="<?= $act ?>" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="pos_add"><input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
              <label class="grow"><span class="sr">Poziție nouă în <?= e($g['name']) ?></span><input type="text" name="name" placeholder="Poziție nouă în acest grup" required></label>
              <button class="btn dashed sm" type="submit">+ Adaugă</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
      <form method="post" action="<?= $act ?>" class="inline-form" style="max-width:420px">
        <?= csrf_field() ?><input type="hidden" name="action" value="group_add">
        <label class="grow"><span class="sr">Grup nou</span><input type="text" name="name" placeholder="Grup nou (ex. Copii, Primire)" required></label>
        <button class="btn sm" type="submit">+ Grup nou</button>
      </form>
      <p class="xs muted">Pozițiile apar în profilul fiecăruia, în Persoane, în șabloane și la planificare. „Cameră” poate avea mai multe locuri pe același eveniment (Cameră 1, Cameră 2) – se setează din șablon.</p>
    </section>

    <aside class="card stack" style="flex:1 1 340px" aria-label="Rolurile din aplicație">
      <h2>Rolurile din aplicație</h2>
      <span class="xs muted">Administrator › Editor › Voluntar. Pastorul are ce are un voluntar, plus ordinea serviciului. Rolurile le schimbă doar un administrator.</span>
      <div class="perm perm4">
        <span class="h">Poate să…</span><span class="h c" style="color:var(--text-2)">Vol.</span><span class="h c" style="color:var(--text-2)">Past.</span><span class="h c" style="color:var(--accent)">Edit.</span><span class="h c" style="color:var(--accent)">Admin</span>
        <?php foreach ($perms as [$l, $v, $pa, $ed]): ?>
          <span><?= e($l) ?></span><?= $v ? $Y : $N ?><?= $pa ? $Y : $N ?><?= $ed ? $Y : $N ?><span class="c" style="color:var(--accent)">✓</span>
        <?php endforeach; ?>
      </div>
    </aside>
  </div>
</main>
