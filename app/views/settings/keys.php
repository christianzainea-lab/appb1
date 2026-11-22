<?php
$title = 'Tonuri';
$act = e(url('setari/tonuri'));
$hidden = fn(string $a, int $id) => csrf_field() . '<input type="hidden" name="action" value="' . $a . '"><input type="hidden" name="id" value="' . $id . '">';
?>
<main class="page">
  <div class="page-head"><div class="grow"><h1>Tonuri</h1><span class="small muted">Tonurile din lista „Ton” a cântărilor. Cele standard (Do … Si, major și minor) sunt mereu acolo; aici adaugi altele.</span></div></div>
  <div class="pgroups">
    <section class="pgroup">
      <div class="pgroup-head"><span class="group-label grow" style="padding:0">Alte tonuri</span><span class="xs muted"><?= count($keys) ?> tonuri</span></div>
      <?php if (!$keys): ?><p class="small muted" style="margin:4px 0 8px">Nu ai adăugat încă niciun ton.</p><?php endif; ?>
      <?php foreach ($keys as $i => $k): ?>
        <div class="pos">
          <div class="pos-line">
            <form method="post" action="<?= $act ?>" class="grow"><?= $hidden('rename', (int)$k['id']) ?><label><span class="sr">Nume</span><input type="text" name="name" value="<?= e($k['name']) ?>" maxlength="<?= SONG_KEY_MAX ?>" required onchange="this.form.submit()" style="min-height:34px;border-color:transparent;font-weight:600;font-size:14px;padding:0 4px"></label></form>
            <span class="xs muted" style="margin-right:4px;white-space:nowrap"><?= (int)$k['songs'] ?> cântări</span>
            <form method="post" action="<?= $act ?>"><?= $hidden('up', (int)$k['id']) ?><button class="icon-btn" type="submit" aria-label="Mai sus"<?= $i === 0 ? ' disabled' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg></button></form>
            <form method="post" action="<?= $act ?>"><?= $hidden('down', (int)$k['id']) ?><button class="icon-btn" type="submit" aria-label="Mai jos"<?= $i === count($keys) - 1 ? ' disabled' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button></form>
            <form method="post" action="<?= $act ?>"><?= $hidden('delete', (int)$k['id']) ?><button class="icon-btn danger" type="submit" aria-label="Șterge <?= e($k['name']) ?>" data-confirm="Ștergi tonul „<?= e($k['name']) ?>”?<?= $k['songs'] ? ' ' . (int)$k['songs'] . ' cântări vor rămâne fără ton.' : '' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg></button></form>
          </div>
        </div>
      <?php endforeach; ?>
      <form method="post" action="<?= $act ?>" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <label class="grow"><span class="sr">Ton nou</span><input type="text" name="name" placeholder="Ton nou, ex. Mi/Sol♯" required maxlength="<?= SONG_KEY_MAX ?>"></label>
        <button class="btn dashed sm" type="submit">+ Adaugă</button>
      </form>
    </section>
  </div>
</main>
