<?php
$title = 'Fișiere la servicii';
$today = date('Y-m-d');
$past = array_filter($files, fn($f) => (string)$f['event_date'] < $today);
?>
<main class="page">
  <div class="page-head">
    <div class="grow">
      <h1>Fișiere la servicii</h1>
      <span class="small muted">Prezentările și PDF-urile atașate în ordinea serviciului. Se șterg doar manual, de aici sau de pe pagina serviciului.</span>
    </div>
  </div>
  <div class="row" style="gap:10px">
    <span class="badge grey"><?= count($files) ?> <?= count($files) === 1 ? 'fișier' : 'fișiere' ?></span>
    <span class="badge grey">Spațiu ocupat: <?= e(fmt_size((int)$total)) ?></span>
    <?php if ($past): ?><span class="badge grey"><?= count($past) ?> la servicii trecute · <?= e(fmt_size(array_sum(array_map(fn($f) => (int)$f['size'], $past)))) ?></span><?php endif; ?>
  </div>
  <?php if (!$files): ?>
    <section class="card"><p class="small muted" style="margin:0">Nu e niciun fișier atașat încă.</p></section>
  <?php else: ?>
  <form method="post" class="card stack" data-files-form>
    <?= csrf_field() ?><input type="hidden" name="action" value="delete_many">
    <div class="row" style="gap:10px">
      <label class="check grow"><input type="checkbox" data-check-all><span class="small">Selectează tot</span></label>
      <?php if ($past): ?><button class="btn sm" type="button" data-check-past>Selectează serviciile trecute</button><?php endif; ?>
      <button class="btn sm danger" type="submit" data-confirm="Ștergi definitiv fișierele selectate? Nu pot fi recuperate.">Șterge selectate</button>
    </div>
    <div class="file-table">
      <?php foreach ($files as $f): $old = (string)$f['event_date'] < $today; ?>
        <div class="file-row<?= $old ? ' past' : '' ?>">
          <label class="check"><input type="checkbox" name="ids[]" value="<?= (int)$f['id'] ?>"<?= $old ? ' data-past' : '' ?> aria-label="Selectează <?= e($f['name']) ?>"></label>
          <span class="ftype"><?= e(file_icon_label($f['name'])) ?></span>
          <span class="grow" style="min-width:0">
            <a href="<?= e(url('fisier/' . (int)$f['id'])) ?>" class="fname"><?= e($f['name']) ?></a>
            <span class="xs muted"><?= $f['ev_name'] ? '<a href="' . e(url('planificare/eveniment/' . (int)$f['event_id'])) . '">' . e($f['ev_name'] . ' · ' . ro_date((string)$f['event_date'])) . '</a>' : 'Serviciu șters' ?><?= $f['item_title'] ? ' · ' . e($f['item_title']) : '' ?> · <?= e(fmt_size((int)$f['size'])) ?><?= $f['by_name'] ? ' · ' . e($f['by_name']) : '' ?></span>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  </form>
  <?php endif; ?>
</main>
