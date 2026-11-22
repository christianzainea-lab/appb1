<?php
$title = $s['title'];
$ref = song_ref($s);
$audio = array_values(array_filter($media, fn($m) => $m['kind'] === 'audio'));
$yt = array_values(array_filter($media, fn($m) => $m['kind'] === 'youtube'));
$last = $uses[0]['used_on'] ?? null;
$recent = $last && $last >= date('Y-m-d', strtotime('-' . RECENT_DAYS . ' days'));
$chordsExt = $s['chords_file'] ? strtolower(pathinfo($s['chords_file'], PATHINFO_EXTENSION)) : '';
?>
<main class="page narrow">
  <div class="page-head">
    <div class="grow">
      <?php if ($svc): $t0 = strtotime($svc['e']['event_date']); ?>
        <a class="back-link" href="<?= e(url('program/' . (int)$svc['e']['id'])) ?>">‹ <?= e(RO_DAYS[(int)date('w', $t0)] . ', ' . date('j', $t0) . ' ' . RO_MONTHS[(int)date('n', $t0) - 1]) ?> · cântarea <?= $svc['idx'] + 1 ?> din <?= count($svc['list']) ?></a>
      <?php else: ?>
        <a class="back-link" href="<?= e(url('cantari')) ?>">‹ Înapoi la cântări</a>
      <?php endif; ?>
      <?php if ($ref): ?><span class="xs muted"><?= e($ref) ?></span><?php endif; ?>
      <h1><?= e($s['title']) ?></h1>
      <?php if ($svc && !empty($svc['list'][$svc['idx']]['note'])): ?><span class="small" style="color:var(--text-2)"><?= e($svc['list'][$svc['idx']]['note']) ?></span><?php endif; ?>
      <div class="row" style="gap:8px">
        <?php if ($s['author']): ?><span class="small muted"><?= e($s['author']) ?></span><?php endif; ?>
        <?php $svcIt = $svc ? $svc['list'][$svc['idx']] : null; $svcKey = $svcIt ? item_key($svcIt) : ''; ?>
        <?php if ($svcKey !== ''): ?><span class="badge">Ton la acest serviciu: <?= e($svcKey) ?></span><?php if ($s['song_key'] && $s['song_key'] !== $svcKey): ?><span class="badge grey">de obicei: <?= e($s['song_key']) ?></span><?php endif; ?>
        <?php elseif ($s['song_key']): ?><span class="badge grey">Ton: <?= e($s['song_key']) ?></span><?php endif; ?>
        <?php if ($s['bpm']): ?><span class="badge grey"><?= (int)$s['bpm'] ?> BPM</span><?php endif; ?>
        <?php foreach ($labels as $l): ?><span class="lbl<?= $l['kind'] === 'tema' ? ' theme' : '' ?>"><?= e($l['name']) ?></span><?php endforeach; ?>
      </div>
    </div>
    <?php if ($isEditor): ?><a class="btn" href="<?= e(url('cantare/' . (int)$s['id'] . '/editeaza')) ?>">Editează</a><?php endif; ?>
  </div>

  <div class="cols">
    <div class="stack" style="gap:16px">
      <section class="card stack">
        <h2>Înregistrări</h2>
        <?php if (!$media): ?><p class="small muted">Încă nu există înregistrări pentru această cântare.</p><?php endif; ?>
        <?php $ytN = 0; foreach (array_merge($audio, $yt) as $m): if ($m['kind'] === 'youtube') $ytN++; ?>
          <div class="media-item">
            <button class="play-btn lg" type="button" <?= play_attrs($m, $s['title']) ?> aria-label="Ascultă <?= e($m['title'] ?: $s['title']) ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></button>
            <div class="grow stack" style="gap:2px">
              <strong class="small"><?= e($m['title'] ?: ($m['kind'] === 'audio' ? 'Înregistrare' : 'Varianta ' . $ytN)) ?></strong>
              <span class="xs muted"><?= $m['kind'] === 'audio' ? 'MP3' . ($m['duration'] ? ' · ' . fmt_duration((int)$m['duration']) : '') . ($m['size'] ? ' · ' . fmt_size((int)$m['size']) : '') : 'YouTube' ?></span>
            </div>
            <?php if ($m['is_main']): ?><span class="badge">Principală</span><?php endif; ?>
            <?php if ($m['kind'] === 'youtube'): ?>
              <a class="icon-btn" href="https://www.youtube.com/watch?v=<?= e($m['youtube_id']) ?>" target="_blank" rel="noopener" aria-label="Deschide pe YouTube"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg></a>
            <?php else: ?>
              <a class="icon-btn" href="<?= e(url('media/' . (int)$m['id'])) ?>" download aria-label="Descarcă"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 4v12M6 11l6 6 6-6M5 20h14"/></svg></a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </section>

      <section class="card stack">
        <h2>Acorduri / partitură</h2>
        <?php if ($s['chords_file']): ?>
          <?php if (in_array($chordsExt, ['jpg', 'jpeg', 'png'], true)): ?>
            <a href="<?= e(url('acorduri/' . (int)$s['id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('acorduri/' . (int)$s['id'])) ?>" alt="Acorduri <?= e($s['title']) ?>" style="border:1px solid var(--line);border-radius:10px"></a>
          <?php endif; ?>
          <a class="btn" href="<?= e(url('acorduri/' . (int)$s['id'])) ?>" target="_blank" rel="noopener">Deschide <?= $chordsExt === 'pdf' ? 'PDF-ul' : 'imaginea' ?></a>
        <?php else: ?><p class="small muted">Nu sunt încărcate acorduri.</p><?php endif; ?>
      </section>

      <?php if (!empty($s['lyrics'])): ?>
      <details class="card" open><summary><h2 style="display:inline">Versuri</h2></summary><div class="lyrics"><?= e($s['lyrics']) ?></div></details>
      <?php endif; ?>
      <?php if ($s['notes']): ?>
      <section class="card stack"><h2>Notițe pentru echipă</h2><p class="small" style="white-space:pre-line;color:var(--text-2)"><?= e($s['notes']) ?></p></section>
      <?php endif; ?>
    </div>

    <div class="stack" style="gap:16px">
    <details class="card">
      <summary><h2 style="display:inline">Când s-a cântat</h2> <span class="xs muted"><?= $uses ? count($uses) . ' ori · ultima: ' . e(ro_date($uses[0]['used_on'], true)) : 'niciodată' ?><?= $upcoming ? ' · programată ' . e(use_label($upcoming[0])) : '' ?></span></summary>
      <div class="stack" style="margin-top:10px">
      <?php foreach ($upcoming as $up): ?>
        <p class="small" style="margin:0"><span class="badge">Programată</span> <?= e(use_label($up)) ?> · <?= e($up['event_name']) ?></p>
      <?php endforeach; ?>
      <?php if (!$uses): ?>
        <p class="small muted">Nu s-a cântat încă.</p>
      <?php else: ?>
        <p class="small" style="color:var(--text-2)">De <strong><?= count($uses) ?></strong> ori · ultima dată <strong style="<?= $recent ? 'color:var(--warn-text)' : '' ?>"><?= e(use_label($uses[0])) ?></strong> (<?= e(ago_days($last)) ?>)</p>
        <?php if ($recent): ?><div class="alert info xs" style="padding:8px 12px">S-a cântat în ultimele <?= RECENT_DAYS ?> zile.</div><?php endif; ?>
        <ul class="uses">
          <?php foreach ($uses as $i => $u): if ($i === 12): ?></ul><details><summary class="small" style="cursor:pointer;color:var(--accent);font-weight:600;padding:6px 0">Arată toate (<?= count($uses) ?>)</summary><ul class="uses"><?php endif; ?>
            <li><span><?= e(use_label($u)) ?></span><?php if ($u['note']): ?><span title="<?= e(symbol_title($u['note'])) ?>"><?= e($u['note']) ?></span><?php endif; ?></li>
          <?php endforeach; ?>
        </ul><?php if (count($uses) > 12): ?></details><?php endif; ?>
      <?php endif; ?>
      </div>
    </details>
    <?php if ($isEditor): ?>
      <?php render_partial('partials/history', ['history' => $history, 'created' => $s['created_at'], 'createdLabel' => 'Adăugată']); ?>
    <?php endif; ?>
    </div>
  </div>
</main>
<?php include APP_DIR . '/views/partials/player.php'; ?>

<?php if ($svc):
  $go = fn(int $k) => url('cantare/' . (int)$svc['list'][$k]['song_id'] . '?din=' . (int)$svc['e']['id'] . '&nr=' . ($k + 1));
  $prev = $svc['idx'] > 0 ? $svc['idx'] - 1 : null;
  $next = $svc['idx'] < count($svc['list']) - 1 ? $svc['idx'] + 1 : null; ?>
<nav class="svc-bar" aria-label="Cântările serviciului" data-svc-nav<?= $prev !== null ? ' data-prev="' . e($go($prev)) . '"' : '' ?><?= $next !== null ? ' data-next="' . e($go($next)) . '"' : '' ?>>
  <?php if ($prev !== null): ?><a class="svc-btn" href="<?= e($go($prev)) ?>" aria-label="Cântarea anterioară: <?= e($svc['list'][$prev]['song_title']) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg><span>Anterioara</span></a>
  <?php else: ?><span class="svc-btn off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg><span>Anterioara</span></span><?php endif; ?>
  <a class="svc-mid" href="<?= e(url('program/' . (int)$svc['e']['id'])) ?>"><strong><?= $svc['idx'] + 1 ?> / <?= count($svc['list']) ?></strong><span><?= $next !== null ? 'Urmează: ' . e($svc['list'][$next]['song_title']) : 'Ultima cântare' ?></span></a>
  <?php if ($next !== null): ?><a class="svc-btn" href="<?= e($go($next)) ?>" aria-label="Cântarea următoare: <?= e($svc['list'][$next]['song_title']) ?>"><span>Următoarea</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></a>
  <?php else: ?><span class="svc-btn off"><span>Următoarea</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></span><?php endif; ?>
</nav>
<?php endif; ?>
