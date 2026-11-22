<?php
/** Ordinea serviciului așa cum o văd voluntarii: blocuri, cu ascultare pe loc. */
$playIcon = '<svg class="i-play" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg><svg class="i-close" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
?>
<section class="card stack" aria-label="Ordinea serviciului">
  <h2>Ordinea serviciului</h2>
  <div class="olist view">
    <?php $files = $files ?? []; $nr = 0; foreach ($items as $it): $song = $it['kind'] === 'song'; $m = $it['media'] ?? null; $songUrl = ($song && $it['song_id']) ? url('cantare/' . (int)$it['song_id'] . '?din=' . (int)$eid . '&nr=' . (++$nr)) : ''; ?>
      <div class="oitem view<?= $song ? ' is-song' : '' ?><?= $song && !$it['song_id'] ? ' unpicked' : '' ?>">
        <span class="o-num" aria-hidden="true"></span>
        <div class="o-main">
          <?php if ($song && $it['song_id']): ?>
            <a class="o-title-link" href="<?= e($songUrl) ?>"><?= e($it['song_title']) ?></a>
            <span class="o-meta">
              <?php if ($it['source']): ?><span><?= e(trim($it['source'] . ' ' . $it['source_no'])) ?></span><?php endif; ?>
              <?php if (item_key($it) !== ''): ?><span class="key-pill" title="Tonalitate"><?= e(item_key($it)) ?></span><?php endif; ?>
              <?php if ($m): ?><span><?= $m['kind'] === 'youtube' ? 'YouTube' : 'MP3' ?><?= $m['kind'] === 'audio' && $m['duration'] ? ' ' . e(fmt_duration((int)$m['duration'])) : '' ?></span><?php else: ?><span>Fără înregistrare</span><?php endif; ?>
              <?php if ($it['more']): ?><a href="<?= e($songUrl) ?>">+<?= (int)$it['more'] ?> <?= $it['more'] === 1 ? 'variantă' : 'variante' ?></a><?php endif; ?>
              <?php if ($it['chords_file']): ?><a href="<?= e(url('acorduri/' . (int)$it['song_id'])) ?>" target="_blank" rel="noopener">Acorduri</a><?php endif; ?>
            </span>
            <?php if ($it['note']): ?><span class="o-note-text"><?= e($it['note']) ?></span><?php endif; ?>
            <div class="o-player" data-inline-player hidden></div>
          <?php elseif ($song): ?>
            <span class="o-item-title muted"><?= e($it['title']) ?> – încă nealeasă</span>
            <?php if ($it['note']): ?><span class="o-note-text"><?= e($it['note']) ?></span><?php endif; ?>
          <?php else: ?>
            <span class="o-item-title"><?= e($it['title']) ?></span>
            <?php if ($it['note']): ?><span class="o-note-text"><?= e($it['note']) ?></span><?php endif; ?>
            <?php if (!empty($it['uid']) && !empty($files[$it['uid']])): ?>
              <span class="o-files">
                <?php foreach ($files[$it['uid']] as $f): ?>
                  <a class="fchip" href="<?= e(url('fisier/' . (int)$f['id'])) ?>" target="_blank" rel="noopener"><span class="ftype"><?= e(file_icon_label($f['name'])) ?></span><span class="fname"><?= e($f['name']) ?></span><span class="xs muted"><?= e(fmt_size((int)$f['size'])) ?></span></a>
                <?php endforeach; ?>
              </span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <?php if ($song && $it['song_id'] && $m): ?>
          <button class="play-btn o-play" type="button" data-play-inline="<?= $m['kind'] === 'youtube' ? 'yt' : 'audio' ?>" data-src="<?= e($m['kind'] === 'youtube' ? $m['youtube_id'] : url('media/' . (int)$m['id'])) ?>" aria-label="Ascultă <?= e($it['song_title']) ?>"><?= $playIcon ?></button>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
