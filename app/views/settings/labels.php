<?php
$title = 'Etichete cântări';
$act = e(url('setari/etichete'));
$byKind = ['ocazie' => [], 'tema' => []];
foreach ($labels as $l) $byKind[$l['kind']][] = $l;
$hidden = fn(string $a, int $id) => csrf_field() . '<input type="hidden" name="action" value="' . $a . '"><input type="hidden" name="id" value="' . $id . '">';
?>
<main class="page">
  <div class="page-head"><div class="grow"><h1>Etichete cântări</h1><span class="small muted">Ocaziile (Cină, Crăciun, Paști…) și temele (Laudă, Mulțumire…) după care se filtrează biblioteca.</span></div></div>
  <div class="pgroups">
    <?php foreach ($byKind as $kind => $list): ?>
      <section class="pgroup">
        <div class="pgroup-head"><span class="group-label grow" style="padding:0"><?= label_kind_name($kind) ?></span><span class="xs muted"><?= count($list) ?> etichete</span></div>
        <?php foreach ($list as $i => $l): ?>
          <div class="pos">
            <div class="pos-line">
              <form method="post" action="<?= $act ?>" class="grow"><?= $hidden('rename', (int)$l['id']) ?><label><span class="sr">Nume</span><input type="text" name="name" value="<?= e($l['name']) ?>" onchange="this.form.submit()" style="min-height:34px;border-color:transparent;font-weight:600;font-size:14px;padding:0 4px"></label></form>
              <span class="xs muted" style="margin-right:4px;white-space:nowrap"><?= (int)$l['songs'] ?> cântări</span>
              <form method="post" action="<?= $act ?>"><?= $hidden('up', (int)$l['id']) ?><button class="icon-btn" type="submit" aria-label="Mai sus"<?= $i === 0 ? ' disabled' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg></button></form>
              <form method="post" action="<?= $act ?>"><?= $hidden('down', (int)$l['id']) ?><button class="icon-btn" type="submit" aria-label="Mai jos"<?= $i === count($list) - 1 ? ' disabled' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button></form>
              <form method="post" action="<?= $act ?>"><?= $hidden('delete', (int)$l['id']) ?><button class="icon-btn danger" type="submit" aria-label="Șterge <?= e($l['name']) ?>" data-confirm="Ștergi eticheta „<?= e($l['name']) ?>”?<?= $l['songs'] ? ' Dispare de pe ' . (int)$l['songs'] . ' cântări.' : '' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg></button></form>
            </div>
          </div>
        <?php endforeach; ?>
        <form method="post" action="<?= $act ?>" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="kind" value="<?= $kind ?>">
          <label class="grow"><span class="sr">Etichetă nouă</span><input type="text" name="name" placeholder="<?= $kind === 'tema' ? 'Temă nouă' : 'Ocazie nouă' ?>" required maxlength="60"></label>
          <button class="btn dashed sm" type="submit">+ Adaugă</button>
        </form>
      </section>
    <?php endforeach; ?>
  </div>
</main>
