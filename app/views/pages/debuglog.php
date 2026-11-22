<?php
$title = 'Jurnal modificări';
$qs = fn(array $x) => '?' . http_build_query(array_filter(['zona' => $area, 'q' => $search, 'de_la' => $from, 'pana_la' => $to] + $x, fn($v) => $v !== '' && $v !== null));
$link = function (array $h) use ($songs, $events): string {
    $label = e($h['object_label'] ?? '');
    if ($h['area'] === 'song' && isset($songs[(int)$h['object_id']])) return '<a href="' . e(url('cantare/' . (int)$h['object_id'])) . '">' . $label . '</a>';
    if (in_array($h['area'], ['event', 'response'], true) && isset($events[(int)$h['object_id']])) return '<a href="' . e(url('planificare/eveniment/' . (int)$h['object_id'])) . '">' . $label . '</a>';
    return $label;
};
?>
<main class="page">
  <div class="page-head">
    <div class="grow"><h1>Jurnal modificări</h1><span class="small muted">Toate modificările din aplicație · doar pentru administratori · <?= $total ?> înregistrări</span></div>
    <span class="xs muted">Versiunea <?= e($build['version']) ?> · <?= e($build['sha']) ?></span>
  </div>
  <form class="row" method="get" action="<?= e(url('debuglog')) ?>">
    <label><span class="sr">Zona</span><select name="zona" style="width:auto" data-autosubmit><option value="">Toate zonele</option>
      <?php foreach (ACTIVITY_AREAS as $k => $v): ?><option value="<?= $k ?>"<?= $area === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
    <label class="grow" style="flex:1 1 220px"><span class="sr">Caută</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Caută persoană, cântare, eveniment, text…"></label>
    <label class="row xs" style="gap:6px">de la<input type="date" name="de_la" value="<?= e($from) ?>" style="width:auto"></label>
    <label class="row xs" style="gap:6px">până la<input type="date" name="pana_la" value="<?= e($to) ?>" style="width:auto"></label>
    <button class="btn" type="submit">Filtrează</button>
    <?php if ($area || $search || $from || $to): ?><a class="btn ghost" href="<?= e(url('debuglog')) ?>">Toate</a><?php endif; ?>
  </form>
  <section class="log">
    <div class="log-row log-head"><span>Când</span><span>Cine</span><span>Zona</span><span>Ce</span><span>Detalii</span></div>
    <?php if (!$items): ?><div class="empty"><p class="small muted">Nicio modificare pentru filtrele alese.</p></div><?php endif; ?>
    <?php foreach ($items as $h): ?>
      <div class="log-row">
        <span class="xs muted"><?= e(activity_when($h['created_at'])) ?></span>
        <span class="small" style="font-weight:600"><?= e($h['user_name'] ?: 'Sistem') ?></span>
        <span><span class="badge grey"><?= e(ACTIVITY_AREAS[$h['area']] ?? $h['area']) ?></span></span>
        <span class="small"><?= $link($h) ?></span>
        <span class="small" style="color:var(--text-2)"><?= e($h['summary']) ?></span>
      </div>
    <?php endforeach; ?>
  </section>
  <?php if ($pages > 1): ?>
    <div class="row" style="justify-content:center">
      <?php if ($page > 1): ?><a class="btn sm" href="<?= e(url('debuglog') . $qs(['pagina' => $page - 1])) ?>">‹ Mai noi</a><?php endif; ?>
      <span class="small muted">Pagina <?= $page ?> din <?= $pages ?></span>
      <?php if ($page < $pages): ?><a class="btn sm" href="<?= e(url('debuglog') . $qs(['pagina' => $page + 1])) ?>">Mai vechi ›</a><?php endif; ?>
    </div>
  <?php endif; ?>
</main>
