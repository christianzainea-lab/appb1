<?php
$title = 'Cărți de cântări';
$act = e(url('setari/carti'));
$hidden = fn(string $a, int $id) => csrf_field() . '<input type="hidden" name="action" value="' . $a . '"><input type="hidden" name="id" value="' . $id . '">';
?>
<main class="page">
  <div class="page-head"><div class="grow"><h1>Cărți de cântări</h1><span class="small muted">Cărțile din care alegi la fiecare cântare (ex. CR) și din filtrul de pe pagina Cântări, în ordinea de aici.</span></div></div>
  <div class="pgroups">
    <section class="pgroup">
      <div class="pgroup-head"><span class="group-label grow" style="padding:0">Cărți</span><span class="xs muted"><?= count($books) ?> cărți</span></div>
      <?php if (!$books): ?><p class="small muted" style="margin:4px 0 8px">Nu ai adăugat încă nicio carte.</p><?php endif; ?>
      <?php foreach ($books as $i => $b): ?>
        <div class="pos">
          <div class="pos-line">
            <form method="post" action="<?= $act ?>" class="grow"><?= $hidden('rename', (int)$b['id']) ?><label><span class="sr">Nume</span><input type="text" name="name" value="<?= e($b['name']) ?>" maxlength="<?= SONG_BOOK_MAX ?>" required onchange="this.form.submit()" style="min-height:34px;border-color:transparent;font-weight:600;font-size:14px;padding:0 4px"></label></form>
            <span class="xs muted" style="margin-right:4px;white-space:nowrap"><?= (int)$b['songs'] ?> cântări</span>
            <form method="post" action="<?= $act ?>"><?= $hidden('up', (int)$b['id']) ?><button class="icon-btn" type="submit" aria-label="Mai sus"<?= $i === 0 ? ' disabled' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg></button></form>
            <form method="post" action="<?= $act ?>"><?= $hidden('down', (int)$b['id']) ?><button class="icon-btn" type="submit" aria-label="Mai jos"<?= $i === count($books) - 1 ? ' disabled' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button></form>
            <form method="post" action="<?= $act ?>"><?= $hidden('delete', (int)$b['id']) ?><button class="icon-btn danger" type="submit" aria-label="Șterge <?= e($b['name']) ?>" data-confirm="Ștergi cartea „<?= e($b['name']) ?>”?<?= $b['songs'] ? ' ' . (int)$b['songs'] . ' cântări vor rămâne fără carte (numărul rămâne).' : '' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg></button></form>
          </div>
        </div>
      <?php endforeach; ?>
      <form method="post" action="<?= $act ?>" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <label class="grow"><span class="sr">Carte nouă</span><input type="text" name="name" placeholder="Carte nouă, ex. CR" required maxlength="<?= SONG_BOOK_MAX ?>"></label>
        <button class="btn dashed sm" type="submit">+ Adaugă</button>
      </form>
      <span class="xs muted">Numele scurt apare lângă număr (ex. „CR 245”). La import, cărțile noi din fișier se adaugă singure aici.</span>
    </section>
    <?php if ($other): ?>
    <section class="pgroup">
      <div class="pgroup-head"><span class="group-label grow" style="padding:0">Cărți scrise la cântări, dar care nu sunt în listă</span></div>
      <?php foreach ($other as $o): ?>
        <div class="pos"><div class="pos-line">
          <strong class="grow small"><?= e($o['source']) ?></strong><span class="xs muted" style="white-space:nowrap"><?= (int)$o['n'] ?> cântări</span>
          <?php if (mb_strlen($o['source']) <= SONG_BOOK_MAX): ?><form method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="name" value="<?= e($o['source']) ?>"><button class="btn sm" type="submit">Adaugă în listă</button></form><?php endif; ?>
        </div></div>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>
  </div>
</main>
