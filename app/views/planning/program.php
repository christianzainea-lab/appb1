<?php
$title = $e['name'];
$t = strtotime($e['event_date']);
$groups = [];
foreach ($slots as $s) if ($s['user_id'] && $s['response'] !== 'declined') $groups[$s['group_name']][] = $s;
?>
<main class="page narrow" style="max-width:760px">
  <?php if ($isEditor): ?>
    <div class="editor-bar">
      <span class="grow small"><strong>Așa vede echipa această pagină</strong><?= $e['status'] !== 'published' ? ' · <span class="badge grey">Ciornă – echipa nu vede încă</span>' : '' ?></span>
      <a class="btn sm primary" href="<?= e(url('planificare/eveniment/' . (int)$e['id'])) ?>">Editează</a>
    </div>
  <?php endif; ?>
  <a class="back-link" href="<?= e(url('')) ?>">‹ Programul meu</a>
  <div class="stack" style="gap:4px">
    <h1><?= e($e['name']) ?></h1>
    <span style="color:var(--text-2)"><?= e(RO_DAYS[(int)date('w', $t)] . ', ' . date('j', $t) . ' ' . RO_MONTHS[(int)date('n', $t) - 1] . ' ' . date('Y', $t)) ?> · ora <?= e($e['start_time']) ?><?= $e['rehearsal_time'] ? ' · repetiție ' . e($e['rehearsal_time']) : '' ?></span>
  </div>
  <?php foreach ($mine as $m): ?>
    <div class="card row req">
      <div class="grow"><span class="xs muted">Poziția ta</span><br><strong><?= e($m['label']) ?></strong> <?= response_badge($m, true) ?></div>
      <?php if ($m['response'] === null): ?>
        <form method="post" action="<?= e(url('raspuns')) ?>"><?= csrf_field() ?><input type="hidden" name="slot_id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="answer" value="accept"><input type="hidden" name="back" value="program/<?= (int)$e['id'] ?>"><button class="btn primary" type="submit">Accept</button></form>
      <?php endif; ?>
      <?php if ($m['response'] !== 'declined' && $e['event_date'] >= date('Y-m-d')): ?>
        <details class="decline" style="flex-basis:100%">
          <summary class="btn sm"><?= $m['response'] === 'accepted' ? 'Nu mai pot ajunge' : 'Nu pot ajunge' ?></summary>
          <form method="post" action="<?= e(url('raspuns')) ?>" class="stack" style="margin-top:10px">
            <?= csrf_field() ?><input type="hidden" name="slot_id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="answer" value="decline"><input type="hidden" name="back" value="program/<?= (int)$e['id'] ?>">
            <label class="field">Motiv <span class="hint">opțional</span><input type="text" name="note" maxlength="255"></label>
            <button class="btn danger" type="submit">Trimite: nu pot ajunge</button>
          </form>
        </details>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($items) render_partial('planning/_order_view', ['items' => $items, 'eid' => (int)$e['id'], 'files' => $files]); ?>
  <?php if ($e['notes']): ?><section class="card stack"><h2>Notițe</h2><p class="small" style="white-space:pre-line;color:var(--text-2)"><?= e($e['notes']) ?></p></section><?php endif; ?>
  <section class="card stack">
    <h2>Echipa</h2>
    <?php if (!$groups): ?><p class="small muted">Echipa nu este încă stabilită.</p><?php endif; ?>
    <?php foreach ($groups as $g => $list): ?>
      <span class="group-label"><?= e($g) ?></span>
      <ul class="team">
        <?php foreach ($list as $s): ?>
          <li><span class="muted small"><?= e($s['label']) ?></span><span class="row" style="gap:8px"><?= avatar_html(['name' => $s['user_name'], 'avatar' => $s['avatar']], 26) ?><strong class="small"><?= e($s['user_name']) ?><?= (int)$s['user_id'] === (int)$me['id'] ? ' (tu)' : '' ?></strong></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </section>
</main>
