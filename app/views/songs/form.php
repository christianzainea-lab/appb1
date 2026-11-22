<?php
$isNew = !$s;
$title = $isNew ? 'Cântare nouă' : 'Editează · ' . $s['title'];
$v = fn(string $k) => e($_POST[$k] ?? ($s[$k] ?? ''));
$err = fn($k) => isset($errors[$k]) ? '<span class="err">' . e($errors[$k]) . '</span>' : '';
$curKey = $_POST['song_key'] ?? ($s['song_key'] ?? '');
$byKind = [];
foreach ($labels as $l) $byKind[$l['kind']][] = $l;
$audio = array_values(array_filter($media, fn($m) => $m['kind'] === 'audio'));
$yt = array_values(array_filter($media, fn($m) => $m['kind'] === 'youtube'));
$mainId = 0;
foreach ($media as $m) if ($m['is_main']) $mainId = (int)$m['id'];
$back = $isNew ? url('cantari') : url('cantare/' . (int)$s['id']);
?>
<main class="page">
  <form method="post" enctype="multipart/form-data" class="stack" style="gap:16px" id="song-form" novalidate>
    <?= csrf_field() ?>
    <?php if ($s): ?><input type="hidden" name="loaded_at" value="<?= e($_POST['loaded_at'] ?? $s['updated_at']) ?>"><?php endif; ?>
    <div class="page-head">
      <div class="grow">
        <a class="back-link" href="<?= e($back) ?>">‹ <?= $isNew ? 'Înapoi la cântări' : 'Înapoi la cântare' ?></a>
        <h1><?= $isNew ? 'Cântare nouă' : 'Editează cântarea' ?></h1>
      </div>
      <a class="btn" href="<?= e($back) ?>">Anulează</a>
      <button class="btn primary" type="submit">Salvează cântarea</button>
    </div>
    <?php if ($errors): ?><div class="alert error" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <?php if (!empty($conflictBy)): ?>
      <label class="check alert info" style="align-items:center"><input type="checkbox" name="force_overwrite" value="1">Salvează oricum peste modificările lui <?= e($conflictBy) ?> (se notează în istoric)</label>
    <?php endif; ?>

    <div class="cols">
      <section class="card stack" aria-label="Detalii">
        <h2>Detalii</h2>
        <label class="field<?= isset($errors['title']) ? ' has-err' : '' ?>">Titlu <span class="hint">obligatoriu</span><input type="text" name="title" value="<?= $v('title') ?>" required maxlength="190"><?= $err('title') ?></label>
        <div class="grid-3">
          <?php $curSrc = (string)($_POST['source'] ?? ($s['source'] ?? '')); ?>
          <label class="field<?= isset($errors['source']) ? ' has-err' : '' ?>">Carte<select name="source">
            <option value="">Fără carte</option>
            <?php if ($curSrc !== '' && !in_array($curSrc, $sources, true)): ?><option value="<?= e($curSrc) ?>" selected><?= e($curSrc) ?> (nu mai e în listă)</option><?php endif; ?>
            <?php foreach ($sources as $src): ?><option value="<?= e($src) ?>"<?= $src === $curSrc ? ' selected' : '' ?>><?= e($src) ?></option><?php endforeach; ?>
          </select><?= $err('source') ?>
          <?php if (!$sources && can('editor')): ?><span class="xs muted">Cărțile se adaugă în <a href="<?= e(url('setari/carti')) ?>">Setări → Cărți</a>.</span><?php endif; ?></label>
          <label class="field">Număr<input type="text" name="source_no" value="<?= $v('source_no') ?>" maxlength="20"></label>
          <label class="field">Autor / artist<input type="text" name="author" value="<?= $v('author') ?>" maxlength="120"></label>
        </div>
        <div class="grid-3">
          <label class="field<?= isset($errors['song_key']) ? ' has-err' : '' ?>">Ton
            <select name="song_key">
              <option value="">—</option>
              <optgroup label="Major"><?php foreach (SONG_KEYS as $k): ?><option<?= $curKey === $k ? ' selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?></optgroup>
              <optgroup label="Minor"><?php foreach (SONG_KEYS as $k): ?><option value="<?= e($k) ?> m"<?= $curKey === $k . ' m' ? ' selected' : '' ?>><?= e($k) ?> minor</option><?php endforeach; ?></optgroup>
              <?php $extra = custom_keys(); $known = $curKey === '' || is_known_key($curKey); ?>
              <?php if ($extra || !$known): ?><optgroup label="Alte tonuri"><?php foreach ($extra as $c): ?><option value="<?= e($c['name']) ?>"<?= $curKey === $c['name'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?><?php if (!$known): ?><option value="<?= e($curKey) ?>" selected><?= e($curKey) ?></option><?php endif; ?></optgroup><?php endif; ?>
            </select><?= $err('song_key') ?></label>
          <label class="field<?= isset($errors['bpm']) ? ' has-err' : '' ?>">BPM<input type="number" name="bpm" value="<?= $v('bpm') ?>" min="30" max="250" inputmode="numeric"><?= $err('bpm') ?></label>
          <span></span>
        </div>
        <fieldset class="plain">
          <legend>Etichete</legend>
          <?php foreach (['ocazie', 'tema'] as $kind): if (empty($byKind[$kind])) continue; ?>
            <span class="xs" style="font-weight:600;color:var(--text-2)"><?= label_kind_name($kind) ?></span>
            <div class="chips">
              <?php foreach ($byKind[$kind] as $l): ?>
                <label class="chip sm"><input type="checkbox" name="labels[]" value="<?= (int)$l['id'] ?>"<?= in_array((int)$l['id'], $songLabels, true) ? ' checked' : '' ?>><?= e($l['name']) ?></label>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
          <div class="inline-form" style="max-width:420px">
            <label class="grow"><span class="sr">Etichetă nouă</span><input type="text" name="new_label" placeholder="+ Etichetă nouă" maxlength="60"></label>
            <label><span class="sr">Tip</span><select name="new_label_kind" style="min-height:34px;font-size:13px;width:auto"><option value="ocazie">Ocazie</option><option value="tema">Temă</option></select></label>
          </div>
        </fieldset>
        <label class="field">Versuri <span class="hint">text simplu · lasă un rând gol între strofe</span><textarea name="lyrics" rows="10" placeholder="Strofa 1&#10;…&#10;&#10;Refren&#10;…"><?= e($_POST['lyrics'] ?? ($s['lyrics'] ?? '')) ?></textarea></label>
        <label class="field">Notițe pentru echipă <span class="hint">opțional</span><textarea name="notes" rows="3" placeholder="ex. Bridge-ul se repetă de 3 ori."><?= $v('notes') ?></textarea></label>
      </section>

      <div class="stack" style="gap:16px">
        <section class="card stack" aria-label="Înregistrări">
          <div class="stack" style="gap:2px"><h2>Înregistrări</h2><span class="xs muted">Variantele pe YouTube și fișierele MP3 proprii. Bifează „Principală” la cea care pornește din listă.</span></div>
          <?php foreach (array_merge($audio, $yt) as $m): ?>
            <div class="media-item edit">
              <label class="main-pick" title="Principală"><input type="radio" name="main_media" value="<?= (int)$m['id'] ?>"<?= (int)$m['id'] === $mainId ? ' checked' : '' ?>><span class="sr">Principală</span></label>
              <div class="grow stack" style="gap:4px;min-width:0">
                <label class="sr" for="mt<?= (int)$m['id'] ?>">Nume</label>
                <input id="mt<?= (int)$m['id'] ?>" type="text" name="media_title[<?= (int)$m['id'] ?>]" value="<?= e($m['title'] ?? '') ?>" placeholder="<?= $m['kind'] === 'audio' ? 'ex. Mix complet, ghid alto' : 'ex. BBSO, varianta în engleză' ?>" style="min-height:36px;font-size:14px">
                <span class="xs muted" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $m['kind'] === 'audio' ? 'MP3' . ($m['duration'] ? ' · ' . fmt_duration((int)$m['duration']) : '') . ' · ' . fmt_size((int)$m['size']) : 'YouTube · ' . e($m['youtube_id']) ?></span>
              </div>
              <button class="play-btn" type="button" <?= play_attrs($m, $s['title'] ?? '') ?> aria-label="Ascultă"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></button>
              <label class="check xs" style="color:var(--danger)"><input type="checkbox" name="media_remove[]" value="<?= (int)$m['id'] ?>">Șterge</label>
            </div>
          <?php endforeach; ?>
          <span class="group-label">Adaugă din YouTube</span>
          <div class="stack" style="gap:8px" data-yt-rows>
            <div class="yt-row"><input type="text" name="yt_title[]" placeholder="Nume (opțional)" maxlength="120"><input type="url" name="yt_url[]" placeholder="https://youtu.be/…"></div>
          </div>
          <button class="btn ghost sm" type="button" data-add-yt style="align-self:flex-start;color:var(--accent)">+ Încă un link</button>
          <span class="group-label">Încarcă MP3</span>
          <label class="drop">
            <input type="file" name="audio[]" accept="audio/mpeg,audio/mp4,audio/x-m4a,.mp3,.m4a" multiple data-audio-input>
            <span style="font-weight:600;color:var(--accent)">+ Alege fișiere audio</span>
            <span class="xs muted">MP3 sau M4A · maximum <?= (int)$limit ?> MB în total la o salvare</span>
          </label>
          <div data-audio-list class="xs muted"></div>
          <?= $err('files') ?>
        </section>

        <section class="card stack" aria-label="Acorduri">
          <div class="stack" style="gap:2px"><h2>Acorduri / partitură</h2><span class="xs muted">PDF sau imagine. Se deschide și de pe telefon.</span></div>
          <?php if ($s && $s['chords_file']): ?>
            <div class="row"><a class="small" href="<?= e(url('acorduri/' . (int)$s['id'])) ?>" target="_blank" rel="noopener" style="font-weight:600"><?= e($s['chords_name'] ?: 'Acorduri') ?></a><span class="spacer"></span><label class="check xs" style="color:var(--danger)"><input type="checkbox" name="chords_remove" value="1">Șterge</label></div>
          <?php endif; ?>
          <label class="drop">
            <input type="file" name="chords" accept="application/pdf,image/jpeg,image/png,.pdf">
            <span style="font-weight:600;color:var(--accent)"><?= ($s && $s['chords_file']) ? 'Înlocuiește fișierul' : '+ Încarcă PDF' ?></span>
            <span class="xs muted" data-file-name>PDF, JPG sau PNG</span>
          </label>
        </section>
      </div>
    </div>
    <div class="row" style="justify-content:flex-end"><a class="btn" href="<?= e($back) ?>">Anulează</a><button class="btn primary" type="submit">Salvează cântarea</button></div>
  </form>
  <?php if ($s): ?>
    <button class="btn ghost danger sm" type="button" data-open="dlg-del" style="align-self:flex-start">Șterge cântarea</button>
    <dialog id="dlg-del">
      <form method="post" class="dlg">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
        <h2>Ștergi „<?= e($s['title']) ?>”?</h2>
        <p class="small" style="color:var(--text-2)">Se șterg definitiv cântarea, înregistrările MP3 încărcate, acordurile și tot istoricul ei (<?= (int)val('SELECT COUNT(*) FROM song_uses WHERE song_id = ?', [$s['id']]) ?> date în care s-a cântat). Nu se poate anula.</p>
        <label class="check"><input type="checkbox" name="confirm_delete" value="1" required data-enables="del-btn">Înțeleg că cântarea se pierde definitiv</label>
        <div class="row" style="justify-content:flex-end"><button class="btn ghost" type="button" data-close>Renunță</button><button class="btn primary" id="del-btn" type="submit" style="background:var(--danger);border-color:var(--danger)" disabled>Șterge definitiv</button></div>
      </form>
    </dialog>
  <?php endif; ?>
</main>
<?php include APP_DIR . '/views/partials/player.php'; ?>
