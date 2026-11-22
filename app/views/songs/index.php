<?php
$title = 'Cântări';
$recentLimit = date('Y-m-d', strtotime('-' . RECENT_DAYS . ' days'));
$byKind = [];
foreach ($labels as $l) if ($l['songs'] > 0 || $isEditor) $byKind[$l['kind']][] = $l;
$plain = function (string $s): string {
    $s = mb_strtolower($s);
    return strtr($s, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']);
};
?>
<main class="page">
  <div class="page-head">
    <div class="grow">
      <h1>Cântări</h1>
      <span class="small muted"><span data-count><?= $stats['all'] ?></span> cântări<?php if ($isEditor): ?> · <?= $stats['noRec'] ?> fără înregistrare · <?= $stats['noChords'] ?> fără acorduri<?php endif; ?></span>
    </div>
    <?php if ($isEditor): ?><a class="btn" href="<?= e(url('cantari/import')) ?>">Importă</a><a class="btn primary" href="<?= e(url('cantari/noua')) ?>">+ Cântare nouă</a><?php endif; ?>
  </div>

  <form class="row" method="get" action="<?= e(url('cantari')) ?>" data-song-filter>
    <label class="grow" style="flex:1 1 280px"><span class="sr">Caută cântări</span><input type="search" name="q" placeholder="Caută titlu, vers, autor sau număr (ex. CR 4)…" autocomplete="off" data-song-search></label>
    <label class="row small" style="gap:6px;color:var(--text-2)">Sortează
      <select name="sort" data-autosubmit style="width:auto;min-height:42px">
        <?php foreach (['az' => 'Alfabetic', 'vechi' => 'Cel mai demult cântate', 'recent' => 'Cântate recent', 'des' => 'Cel mai des cântate', 'noi' => 'Adăugate recent'] as $k => $v): ?>
          <option value="<?= $k ?>"<?= $sort === $k ? ' selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select></label>
    <?php if ($isEditor): ?>
    <label class="row small" style="gap:6px;color:var(--text-2)">Arată
      <select style="width:auto;min-height:42px" data-song-show>
        <option value="">Toate</option><option value="norec">Fără înregistrare</option><option value="nochords">Fără acorduri</option><option value="nolabel">Fără etichete</option>
      </select></label>
    <?php endif; ?>
  </form>

<?php
  $books = [];
  foreach ($songs as $s0) { $bk = book_group($s0['source'] ?? ''); $books[$bk] = ($books[$bk] ?? 0) + 1; }
  // ordinea din Setări → Cărți; cărțile scrise altfel vin după, iar „Fără carte” la sfârșit
  $order = array_flip(array_column(books_all(), 'name'));
  uksort($books, fn($a, $b) => ($a === '') <=> ($b === '') ?: (($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX)) ?: strcmp($a, $b));
?>
  <div class="label-filter" role="group" aria-label="Filtrează după carte și etichetă">
    <button type="button" class="chip sm is-on" data-filter-all>Toate</button>
    <?php if (count($books) > 1): ?>
      <span class="label-sep" aria-hidden="true"></span>
      <?php foreach ($books as $bk => $n): ?>
        <button type="button" class="chip sm book" data-book-filter="<?= e($bk === '' ? '-' : $bk) ?>" aria-pressed="false"><?= e($bk === '' ? 'Fără carte' : $bk) ?> <span class="cnt"><?= (int)$n ?></span></button>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php foreach (['ocazie', 'tema'] as $kind): if (empty($byKind[$kind])) continue; ?>
      <span class="label-sep" aria-hidden="true"></span>
      <?php foreach ($byKind[$kind] as $l): ?>
        <button type="button" class="chip sm<?= $kind === 'tema' ? ' theme' : '' ?>" data-label-filter="<?= (int)$l['id'] ?>" aria-pressed="false"><?= e($l['name']) ?></button>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if ($isEditor): ?><a class="xs" href="<?= e(url('setari/etichete')) ?>" style="font-weight:600;margin-left:4px;white-space:nowrap">Gestionează etichetele</a><?php endif; ?>
  </div>

  <section class="songs" aria-label="Lista de cântări">
    <div class="song-row song-head<?= $isEditor ? ' ed' : '' ?>"><span>Titlu</span><span>Etichete</span><span>Ton</span><span>BPM</span><span>Înregistrări</span><span>Acorduri</span><span>Ultima dată</span><span title="În ultimele 3 luni">3 luni</span><?php if ($isEditor): ?><span></span><?php endif; ?></div>
    <?php foreach ($songs as $s):
        $ref = song_ref($s);
        $main = main_media($s['media']);
        $recent = $s['last_used'] && $s['last_used'] >= $recentLimit;
        $search = $plain($s['title'] . ' ' . $s['author'] . ' ' . $ref . ' ' . ($s['source'] ?? '') . ($s['source_no'] ?? '') . ' ' . $s['song_key'] . ' ' . preg_replace('/\s+/u', ' ', (string)($s['lyrics'] ?? '')));
        $recParts = [];
        if ($s['audio']) $recParts[] = $s['audio'] . ' MP3';
        if ($s['yt']) $recParts[] = $s['yt'] . ' YouTube';
    ?>
      <div class="song-row<?= $isEditor ? ' ed' : '' ?>" data-book="<?= e(book_group($s['source'] ?? '') ?: '-') ?>" data-search="<?= e($search) ?>" data-labels=",<?= e(implode(',', array_column($s['labels'], 'id'))) ?>," data-rec="<?= $s['media'] ? 1 : 0 ?>" data-chords="<?= $s['chords_file'] ? 1 : 0 ?>">
        <a class="c-title" href="<?= e(url('cantare/' . (int)$s['id'])) ?>">
          <strong><?= e($s['title']) ?></strong>
          <span class="xs muted"><?= e(implode(' · ', array_filter([$ref, $s['author']]))) ?><span class="m-only"><?= $s['song_key'] ? ' · ' . e($s['song_key']) : '' ?><?= $s['last_used'] ? ' · ultima: ' . e(ro_date($s['last_used'], date('Y', strtotime($s['last_used'])) !== date('Y'))) : '' ?></span></span>
        </a>
        <span class="c-labels"><?php foreach ($s['labels'] as $l): ?><span class="lbl<?= $l['kind'] === 'tema' ? ' theme' : '' ?>"><?= e($l['name']) ?></span><?php endforeach; ?></span>
        <span class="c-key small"><?= e($s['song_key'] ?? '—') ?></span>
        <span class="c-bpm small muted"><?= $s['bpm'] ? (int)$s['bpm'] : '—' ?></span>
        <span class="c-rec">
          <?php if ($main): ?>
            <button class="play-btn" type="button" <?= play_attrs($main, $s['title']) ?> aria-label="Ascultă <?= e($s['title']) ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></button>
            <span class="xs d-only" style="color:var(--text-2)"><?= e(implode(' · ', $recParts)) ?></span>
          <?php else: ?><span class="xs d-only" style="font-weight:600;color:var(--warn-text)">Lipsește</span><?php endif; ?>
        </span>
        <span class="c-chords">
          <?php if ($s['chords_file']): ?><a class="icon-btn" href="<?= e(url('acorduri/' . (int)$s['id'])) ?>" target="_blank" rel="noopener" aria-label="Acorduri pentru <?= e($s['title']) ?>" style="color:var(--ok)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="width:18px;height:18px"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg></a>
          <?php else: ?><span class="xs d-only muted">—</span><?php endif; ?>
        </span>
        <span class="c-last d-only"><?php if ($s['last_used']): ?><span class="small" style="font-weight:600;<?= $recent ? 'color:var(--warn-text)' : '' ?>"><?= e(ro_date($s['last_used'], date('Y', strtotime($s['last_used'])) !== date('Y'))) ?></span><br><span class="xs muted"><?= e(ago_days($s['last_used'])) ?></span><?php else: ?><span class="xs muted">niciodată</span><?php endif; ?></span>
        <span class="c-times small d-only" style="color:var(--text-2)"><?= $s['recent'] ?>×</span>
        <?php if ($isEditor): ?><span class="c-edit d-only"><a class="btn sm" href="<?= e(url('cantare/' . (int)$s['id'] . '/editeaza')) ?>">Editează</a></span><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="empty hidden" data-song-empty><p class="muted small">Nicio cântare nu se potrivește căutării.</p></div>
  </section>
</main>
<?php include APP_DIR . '/views/partials/player.php'; ?>
