<?php
$title = $e['name'] . ' ' . ro_date($e['event_date']);
$base = url('planificare/eveniment/' . (int)$e['id']);
$pub = $e['status'] === 'published';
$st = $stats[(int)$e['id']] ?? ['total' => 0, 'filled' => 0, 'pending' => 0, 'declined' => 0, 'unnotified' => 0, 'accepted' => 0];
$groups = [];
foreach ($slots as $s) $groups[$s['group_name']][] = $s;
$hidden = fn(string $a, int $sid = 0) => csrf_field() . '<input type="hidden" name="action" value="' . $a . '">' . ($sid ? '<input type="hidden" name="slot_id" value="' . $sid . '">' : '');
$ymLabel = ro_month_year($e['event_date']);
// lista de cântări pentru căutare: „Titlu · CR 4”; la titluri identice se adaugă numărul
$songLabel = [];
$songKey = [];
$cnt = [];
foreach ($songChoices as $sc) { $l = $sc['title'] . ($sc['source'] ? ' · ' . $sc['source'] . ($sc['source_no'] ? ' ' . $sc['source_no'] : '') : ''); $cnt[$l] = ($cnt[$l] ?? 0) + 1; $songLabel[(int)$sc['id']] = $l; $songKey[(int)$sc['id']] = (string)($sc['song_key'] ?? ''); }
foreach ($songLabel as $id => $l) if ($cnt[$l] > 1) $songLabel[$id] = $l . ' #' . $id;
$songCount = count(array_filter($items, fn($i) => $i['kind'] === 'song' && $i['song_id']));
$songSlots = count(array_filter($items, fn($i) => $i['kind'] === 'song'));
$pastor = !empty($pastor);
$files = $files ?? [];
$oitem = function (array $it) use ($songLabel, $pastor, $files): string {
    $song = $it['kind'] === 'song';
    $sid = (int)($it['song_id'] ?? 0);
    ob_start(); ?>
      <div class="oitem<?= $song ? ' is-song' : '' ?>" data-oitem>
        <input type="hidden" name="it_kind[]" value="<?= $song ? 'song' : 'item' ?>">
        <input type="hidden" name="it_id[]" value="<?= (int)($it['id'] ?? 0) ?>">
        <span class="o-num" aria-hidden="true"></span>
        <div class="o-main">
          <?php if ($song && $pastor): ?>
            <input type="hidden" name="it_title[]" value="<?= e($it['title'] ?? 'Cântare') ?>"><input type="hidden" name="it_song[]" value="<?= $sid ?: '' ?>"><input type="hidden" name="it_key[]" value="<?= e($it['song_key'] ?? '') ?>"><input type="hidden" name="it_note[]" value="<?= e($it['note'] ?? '') ?>">
            <span class="o-label xs muted"><?= e($it['title'] ?? 'Cântare') ?></span>
            <strong class="small"><?= $sid ? e($it['song_title']) . (item_key($it) !== '' ? ' <span class="key-pill">' . e(item_key($it)) . '</span>' : '') : '<span class="muted">încă nealeasă</span>' ?></strong>
            <?php if (!empty($it['note'])): ?><span class="xs muted"><?= e($it['note']) ?></span><?php endif; ?>
          <?php elseif ($song): ?>
            <input type="hidden" name="it_title[]" value="<?= e($it['title'] ?? 'Cântare') ?>">
            <input type="hidden" name="it_song[]" value="<?= $sid ?: '' ?>" data-song-id>
            <span class="o-label xs muted"><?= e($it['title'] ?? 'Cântare') ?><?php if (!empty($it['media'])): $m = $it['media']; ?>
              <button class="play-btn" type="button" data-play-inline="<?= $m['kind'] === 'youtube' ? 'yt' : 'audio' ?>" data-src="<?= e($m['kind'] === 'youtube' ? $m['youtube_id'] : url('media/' . (int)$m['id'])) ?>" aria-label="Ascultă"><svg class="i-play" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg><svg class="i-close" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button><?php endif; ?></span>
            <input type="text" class="o-song" value="<?= e($sid ? ($songLabel[$sid] ?? '') : '') ?>" placeholder="Caută cântarea…" aria-label="Cântarea" autocomplete="off" data-song-input>
            <div class="o-player" data-inline-player hidden></div>
            <div class="o-extra">
              <input type="text" name="it_key[]" value="<?= e($it['song_key'] ?? '') ?>" placeholder="<?= e(($it['default_key'] ?? '') ?: 'Ton') ?>" aria-label="Tonalitate" class="o-key" maxlength="<?= SONG_KEY_MAX ?>" data-song-key>
              <input type="text" name="it_note[]" value="<?= e($it['note'] ?? '') ?>" placeholder="Cine conduce / notă" aria-label="Notă" class="o-note" maxlength="190">
            </div>
          <?php else: ?>
            <input type="hidden" name="it_song[]" value="">
            <input type="hidden" name="it_key[]" value="">
            <input type="text" name="it_title[]" value="<?= e($it['title'] ?? '') ?>" placeholder="Ex. Rugăciune" aria-label="Element" class="o-title" maxlength="120">
            <div class="o-extra"><input type="text" name="it_note[]" value="<?= e($it['note'] ?? '') ?>" placeholder="Cine / notă" aria-label="Notă" class="o-note" maxlength="190"></div>
            <?php if (!empty($it['uid'])): ?>
              <div class="o-files">
                <?php foreach ($files[$it['uid']] ?? [] as $f): ?>
                  <span class="fchip"><span class="ftype"><?= e(file_icon_label($f['name'])) ?></span><a href="<?= e(url('fisier/' . (int)$f['id'])) ?>" target="_blank" rel="noopener"><?= e($f['name']) ?></a><span class="xs muted"><?= e(fmt_size((int)$f['size'])) ?></span><button class="icon-btn" type="button" data-file-del="<?= (int)$f['id'] ?>" data-name="<?= e($f['name']) ?>" aria-label="Șterge fișierul"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></span>
                <?php endforeach; ?>
                <label class="btn sm ghost attach"><input type="file" accept=".ppt,.pptx,.pdf,.key,.odp" data-item-file="<?= e($it['uid']) ?>" data-max-mb="<?= event_file_max_mb() ?>" hidden><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M21 12.5l-8.5 8.5a6 6 0 01-8.5-8.5L13 3.5a4 4 0 015.7 5.7L10 18a2 2 0 01-2.8-2.8L15 7.5"/></svg><span>Atașează fișier</span></label>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <span class="o-tools">
          <button class="icon-btn" type="button" data-omove="-1" aria-label="Mută mai sus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg></button>
          <button class="icon-btn" type="button" data-omove="1" aria-label="Mută mai jos"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button>
          <?php if (!($song && $pastor)): ?><button class="icon-btn danger" type="button" data-oremove aria-label="Scoate"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button><?php endif; ?>
        </span>
      </div>
    <?php return ob_get_clean();
};
?>
<main class="page">
  <div class="row">
    <a class="back-link" href="<?= e(url('planificare')) ?>">‹ Planificare</a>
    <span class="spacer"></span>
    <?php if (!$pastor): ?><a class="btn ghost sm tab-up" href="<?= e(url('planificare/luna?l=' . substr($e['event_date'], 0, 7))) ?>">Tabelul lunii</a><?php endif; ?>
    <a class="btn sm" href="<?= e(url('program/' . (int)$e['id'])) ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>Cum vede echipa</a>
  </div>
  <nav class="date-chips" data-live="chips" aria-label="Evenimente din <?= e($ymLabel) ?>">
    <?php foreach ($monthEvents as $me2): $s2 = $stats[(int)$me2['id']]; $curE = (int)$me2['id'] === (int)$e['id']; ?>
      <a href="<?= e(url('planificare/eveniment/' . (int)$me2['id'])) ?>"<?= $curE ? ' aria-current="date"' : '' ?> class="<?= $me2['status'] === 'draft' ? 'draft' : '' ?>" title="<?= e($me2['name']) ?>">
        <strong><?= e(event_short_date($me2['event_date'])) ?></strong><span><?= $s2['filled'] ?>/<?= $s2['total'] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="page-head" data-live="head">
    <div class="grow">
      <h1><?= e(event_title($e)) ?></h1>
      <span class="small muted">
        <?= $pub ? '<span class="badge ok">Publicat</span>' : '<span class="badge grey">Ciornă · echipa nu vede încă</span>' ?>
        <?php if (!$pastor): ?><span class="xs muted">· modificările se salvează automat ·</span><?php endif; ?>
        <?= $st['filled'] ?> din <?= $st['total'] ?> locuri ocupate<?= $e['rehearsal_time'] ? ' · repetiție ' . e($e['rehearsal_time']) : '' ?><?= $tpl ? ' · șablon: ' . e($tpl['name']) : '' ?>
        <?php if ($pub && $st['pending']): ?> · <?= $st['pending'] ?> neconfirmați<?php endif; ?>
      </span>
    </div>
    <?php if ($pastor): ?>
    <?php elseif (!$pub): ?>
      <form method="post" class="row" style="gap:10px">
        <?= $hidden('publish') ?>
        <label class="check xs"><input type="checkbox" name="notify" value="1">Anunță pe email</label>
        <button class="btn primary" type="submit">Publică pentru echipă</button>
      </form>
    <?php else: ?>
      <?php if ($st['unnotified']): ?>
        <form method="post"><?= $hidden('notify') ?><button class="btn" type="submit">Anunță pe email (<?= $st['unnotified'] ?> noi)</button></form>
      <?php endif; ?>
      <form method="post"><?= $hidden('unpublish') ?><button class="btn ghost sm" type="submit" data-confirm="Muți evenimentul înapoi în ciornă? Echipa nu îl va mai vedea până îl publici din nou.">Mută în ciornă</button></form>
    <?php endif; ?>
  </div>

  <div class="ev-layout<?= $pick ? ' picking' : '' ?>">
    <div class="ev-main">
    <?php if ($pastor): ?>
    <section class="card stack" aria-label="Echipa">
      <h2>Echipa</h2>
      <?php foreach ($groups as $gname => $list): $list = array_filter($list, fn($x) => $x['user_id'] && $x['response'] !== 'declined'); if (!$list) continue; ?>
        <span class="group-label"><?= e($gname) ?></span>
        <ul class="team">
          <?php foreach ($list as $s): ?>
            <li><span class="muted small"><?= e($s['label']) ?></span><span class="row" style="gap:8px"><strong class="small"><?= e($s['user_name']) ?></strong><?= response_badge($s, $pub) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
      <?php if (!$st['filled']): ?><p class="small muted">Echipa nu este încă stabilită.</p><?php endif; ?>
    </section>
    <?php else: ?>
    <section class="roster" data-live="roster" data-ajax-forms aria-label="Poziții și echipă">
      <p class="m-hint xs muted">Apasă pe un rând ca să alegi omul · glisează spre dreapta ca să-l scoți.</p>
      <div class="roster-row roster-head"><span>Poziție</span><span>Persoană</span><span>Răspuns</span><span>Duminici în <?= e(RO_MONTHS[(int)substr($e['event_date'], 5, 2) - 1]) ?></span><span></span></div>
      <?php if (!$slots): ?><div class="empty"><p class="small muted">Evenimentul nu are încă poziții. Adaugă-le de mai jos.</p></div><?php endif; ?>
      <?php foreach ($groups as $gname => $list): ?>
        <div class="roster-group"><?= e($gname) ?></div>
        <?php foreach ($list as $s): $isPick = $pick && (int)$pick['id'] === (int)$s['id']; $uid = (int)$s['user_id']; $declined = $s['response'] === 'declined'; ?>
          <div class="roster-row clickable<?= $isPick ? ' picking' : '' ?><?= !$uid ? ' empty-slot' : '' ?><?= $declined ? ' declined' : '' ?>" id="slot<?= (int)$s['id'] ?>" data-pick="<?= e($base . '?alege=' . (int)$s['id']) ?>">
            <span class="r-pos"><?= e($s['label']) ?></span>
            <span class="r-person">
              <?php if ($uid): ?>
                <strong<?= $declined ? ' style="text-decoration:line-through;color:var(--muted)"' : '' ?>><?= e($s['user_name']) ?><?= $uid === (int)$me['id'] ? ' (tu)' : '' ?><?= $s['is_test'] ? ' <span class="badge test">TEST</span>' : '' ?></strong>
                <span class="xs muted"><?= $declined ? 'Nu poate' . ($s['response_note'] ? ': „' . e($s['response_note']) . '”' : '') : e(user_part_names($uid, (int)$s['position_id'])) ?><?= isset($blocked[$uid]) && !$declined ? ' · <span style="color:var(--warn-text)">și-a marcat ziua ca indisponibilă</span>' : '' ?></span>
              <?php else: ?>
                <span class="xs muted" style="font-style:italic">Loc liber</span>
              <?php endif; ?>
            </span>
            <span class="r-resp"><?= response_badge($s, $pub) ?></span>
            <span class="r-load"><?= ($uid && !$declined) ? load_bar($load[$uid] ?? 0) : '' ?></span>
            <?php if ($uid): ?>
              <span class="r-swipe" aria-hidden="true">
                <form method="post"><?= $hidden('unassign', (int)$s['id']) ?><button class="btn sm" type="submit" tabindex="-1">Scoate persoana</button></form>
                <form method="post"><?= $hidden('remove_slot', (int)$s['id']) ?><button class="btn sm danger" type="submit" tabindex="-1" data-confirm="Scoți poziția <?= e($s['label']) ?> din acest eveniment?">Scoate poziția</button></form>
                <button class="icon-btn" type="button" data-unswipe tabindex="-1" aria-label="Închide"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
              </span>
            <?php endif; ?>
            <span class="r-act">
              <?php if (!$uid || $declined): ?>
                <a class="btn sm<?= $isPick ? ' primary' : '' ?>" style="<?= $isPick ? '' : 'border-color:var(--accent);color:var(--accent)' ?>" href="<?= e($base . '?alege=' . (int)$s['id'] . '#alege') ?>" data-pick="<?= e($base . '?alege=' . (int)$s['id']) ?>">Alege</a>
              <?php else: ?>
                <a class="btn ghost sm" href="<?= e($base . '?alege=' . (int)$s['id'] . '#alege') ?>" data-pick="<?= e($base . '?alege=' . (int)$s['id']) ?>">Schimbă</a>
              <?php endif; ?>
              <details class="menu">
                <summary class="icon-btn" aria-label="Mai multe pentru <?= e($s['label']) ?>"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg></summary>
                <div class="menu-body">
                  <?php if ($uid && $pub && $s['response'] !== 'accepted'): ?><form method="post"><?= $hidden('mark_accepted', (int)$s['id']) ?><button class="btn ghost sm block" type="submit">Marchează ca „Confirmat”</button></form><?php endif; ?>
                  <?php if ($uid): ?><form method="post"><?= $hidden('unassign', (int)$s['id']) ?><button class="btn ghost sm block" type="submit">Scoate persoana (loc liber)</button></form><?php endif; ?>
                  <form method="post"><?= $hidden('remove_slot', (int)$s['id']) ?><button class="btn ghost danger sm block" type="submit"<?= $uid ? ' data-confirm="Scoți poziția ' . e($s['label']) . ' din acest eveniment? ' . e($s['user_name']) . ' nu mai e programat."' : '' ?>>Scoate poziția din eveniment</button></form>
                </div>
              </details>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <div class="add-pos">
        <span class="xs muted">Adaugă doar la acest eveniment:</span>
        <?php foreach ($allPos as $p): if (in_array((int)$p['id'], $usedPos, true)) continue; ?>
          <form method="post"><?= $hidden('add_slot') ?><input type="hidden" name="position_id" value="<?= (int)$p['id'] ?>"><button class="chip sm" type="submit">+ <?= e($p['name']) ?></button></form>
        <?php endforeach; ?>
        <?php if ($usedPos): ?>
          <form method="post" class="inline-form"><?= $hidden('add_slot') ?>
            <label><span class="sr">Încă un loc pentru</span><select name="position_id" style="min-height:34px;font-size:13px;width:auto">
              <?php foreach ($allPos as $p): if (!in_array((int)$p['id'], $usedPos, true) || !empty($p['is_leader'])) continue; ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
            </select></label><button class="btn sm" type="submit">+ Încă un loc</button></form>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="card stack" id="ordine" data-live="order" data-ajax-forms aria-label="Ordinea serviciului">
      <div class="row">
        <h2 class="grow">Ordinea serviciului</h2>
        <span class="xs muted"><?= $songSlots ? $songCount . ' din ' . $songSlots . ' cântări alese' : '' ?></span>
      </div>
      <form method="post" class="stack" data-order-form>
        <?= $hidden('items') ?>
        <?php if ($pastor): ?><p class="xs muted" style="margin:0">Cântările le alege echipa de laudă – tu le poți muta mai sus sau mai jos. Restul elementelor le poți adăuga, scrie și scoate.</p><?php endif; ?>
        <div class="olist" data-olist>
          <?php foreach ($items as $it) echo $oitem($it); ?>
        </div>
        <?php if (!$items): ?><p class="small muted" data-oempty>Nu e nimic în ordine. Adaugă elementele de mai jos<?= $tpl ? ' sau modifică șablonul „' . e($tpl['name']) . '”' : '' ?>.</p><?php endif; ?>
        <template data-otpl="song"><?= $oitem(['kind' => 'song', 'title' => 'Cântare']) ?></template>
        <template data-otpl="item"><?= $oitem(['kind' => 'item', 'title' => '']) ?></template>
        <div class="row" style="gap:6px">
          <?php if (!$pastor): ?><button class="btn sm dashed" type="button" data-oadd="song">+ Cântare</button><?php endif; ?>
          <?php foreach (['Rugăciune', 'Predică', 'Anunțuri', 'Citire biblică', 'Cina Domnului'] as $x): ?><button class="chip sm" type="button" data-oadd="item" data-title="<?= e($x) ?>">+ <?= e($x) ?></button><?php endforeach; ?>
          <button class="chip sm" type="button" data-oadd="item" data-title="">+ Alt element</button>
        </div>
        <div class="row" style="justify-content:flex-end;gap:10px">
          <span class="xs" style="color:var(--warn-text);font-weight:600" data-odirty hidden>Modificări nesalvate</span>
          <button class="btn primary sm" type="submit" data-keep>Salvează ordinea</button>
        </div>
      </form>
    </section>
    </div>

    <aside class="ev-side">
      <section class="card stack" data-live="wa">
        <div class="row"><h2 class="grow">Mesaj WhatsApp</h2><a class="xs" href="<?= e(url('setari/whatsapp')) ?>">Modifică șablonul</a></div>
        <?php if (!$pub): ?><span class="xs" style="color:var(--warn-text)">Evenimentul e încă ciornă – publică-l înainte să trimiți mesajul.</span><?php endif; ?>
        <textarea class="wa-preview" rows="9" readonly aria-label="Textul mesajului" data-wa-text><?= e($waText) ?></textarea>
        <div class="row" style="gap:8px">
          <button class="btn primary sm" type="button" data-copy="[data-wa-text]">Copiază pentru WhatsApp</button>
          <a class="btn ghost sm" href="https://wa.me/?text=<?= e(rawurlencode($waText)) ?>" target="_blank" rel="noopener">Deschide WhatsApp</a>
        </div>
      </section>
      <?php if ($pick) render_partial('planning/_picker', ['e' => $e, 'pick' => $pick, 'cands' => $cands, 'me' => $me, 'popup' => false]); ?>

      <?php if ($pastor): ?>
      <section class="card stack">
        <h2>Notițe pentru echipă</h2>
        <form method="post" class="stack">
          <?= $hidden('notes') ?>
          <label class="field"><span class="hint">le văd toți cei programați</span><textarea name="notes" rows="4"><?= e($e['notes'] ?? '') ?></textarea></label>
          <div class="row" style="justify-content:flex-end"><button class="btn primary sm" type="submit">Salvează notițele</button></div>
        </form>
      </section>
      <?php else: ?>
      <details class="card"<?= $pick ? '' : ' open' ?>>
        <summary style="cursor:pointer"><h2 style="display:inline">Detalii eveniment</h2></summary>
        <form method="post" class="stack" style="margin-top:12px">
          <?= $hidden('details') ?>
          <label class="field">Nume<input type="text" name="name" value="<?= e($e['name']) ?>" maxlength="120" required></label>
          <div class="grid-3">
            <label class="field">Data<input type="date" name="date" value="<?= e($e['event_date']) ?>" required></label>
            <label class="field">Ora<input type="time" name="time" value="<?= e($e['start_time']) ?>" required></label>
            <label class="field">Repetiție<input type="time" name="rehearsal" value="<?= e($e['rehearsal_time'] ?? '') ?>"></label>
          </div>
          <label class="field">Notițe pentru echipă <span class="hint">le văd toți cei programați</span><textarea name="notes" rows="3"><?= e($e['notes'] ?? '') ?></textarea></label>
          <div class="row" style="justify-content:flex-end"><button class="btn primary sm" type="submit">Salvează detaliile</button></div>
        </form>
        <form method="post" class="stack" style="border-top:1px solid var(--line-2);padding-top:12px;margin-top:12px;gap:8px">
          <?= $hidden('cancel') ?>
          <label class="check xs"><input type="checkbox" name="confirm" value="1" required data-enables="cancel-btn">Șterg evenimentul<?= $st['filled'] ? ' și scot cele ' . $st['filled'] . ' persoane programate' : '' ?></label>
          <button class="btn ghost danger sm" id="cancel-btn" type="submit" disabled style="align-self:flex-start">Șterge evenimentul</button>
        </form>
      </details>
      <?php render_partial('partials/history', ['history' => $history, 'created' => null, 'createdLabel' => '']); ?>
      <?php endif; ?>
    </aside>
  </div>
</main>
<script type="application/json" id="song-data"><?= json_encode(array_map(fn($sc) => [
  'id' => (int)$sc['id'], 'l' => $songLabel[(int)$sc['id']], 't' => $sc['title'], 'n' => trim(($sc['source'] ?? '') . ' ' . ($sc['source_no'] ?? '')), 'k' => (string)($sc['song_key'] ?? ''),
  'x' => mb_substr(trim(($sc['author'] ?? '') . ' ' . preg_replace('/\s+/u', ' ', (string)($sc['lyrics'] ?? ''))), 0, 400),
], $songChoices), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<dialog id="pick-dlg" class="pick-dlg" aria-label="Alege o persoană"><div class="dlg" data-pick-body></div></dialog>
