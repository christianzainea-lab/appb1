<?php
$title = 'Programul meu';
$hasPos = (bool)user_position_ids((int)$u['id']);
$pending = array_values(array_filter($mine, fn($m) => $m['response'] === null));
$upcoming = array_values(array_filter($mine, fn($m) => $m['response'] === 'accepted'));
$whenLine = function (array $m): string {
    $t = strtotime($m['event_date']);
    return RO_DAYS[(int)date('w', $t)] . ', ' . date('j', $t) . ' ' . RO_MONTHS[(int)date('n', $t) - 1] . ' · ' . $m['start_time'] . ($m['rehearsal_time'] ? ' · repetiție ' . $m['rehearsal_time'] : '');
};
?>
<main class="page narrow">
  <div class="page-head">
    <div class="grow">
      <h1>Bună, <?= e(first_name($u['name'])) ?>!</h1>
      <span class="small muted"><?= e(RO_DAYS[(int)date('w')]) ?>, <?= e(date('j') . ' ' . RO_MONTHS[(int)date('n') - 1]) ?></span>
    </div>
  </div>
  <?php if (!$hasPos): ?>
    <div class="alert info"><span>Spune-ne unde slujești, ca să poți fi programat pe pozițiile potrivite. <a href="<?= e(url('profil')) ?>"><strong>Alege pozițiile</strong></a></span></div>
  <?php endif; ?>
  <?php if ($drafts): ?>
    <a class="card row" href="<?= e(url('planificare')) ?>" style="text-decoration:none;color:inherit;padding:14px 16px">
      <span class="grow"><strong>Planificare</strong><br><span class="xs muted"><?= $drafts ?> <?= $drafts === 1 ? 'eveniment e ciornă' : 'evenimente sunt ciorne' ?> în următoarele 6 săptămâni</span></span><span aria-hidden="true">›</span>
    </a>
  <?php endif; ?>

  <?php if ($pending): ?>
    <section class="stack" aria-label="De confirmat">
      <h2>De confirmat <span class="badge warn"><?= count($pending) ?></span></h2>
      <?php foreach ($pending as $m): ?>
        <div class="card stack req">
          <a href="<?= e(url('program/' . (int)$m['id'])) ?>" style="text-decoration:none;color:inherit" class="stack">
            <strong><?= e($m['name']) ?></strong>
            <span class="small" style="color:var(--text-2)"><?= e($whenLine($m)) ?></span>
            <span class="small">Poziție: <strong><?= e($m['position']) ?></strong></span>
          </a>
          <div class="row">
            <form method="post" action="<?= e(url('raspuns')) ?>"><?= csrf_field() ?><input type="hidden" name="slot_id" value="<?= (int)$m['slot_id'] ?>"><input type="hidden" name="answer" value="accept"><button class="btn primary" type="submit">Accept</button></form>
            <details class="decline">
              <summary class="btn">Nu pot ajunge</summary>
              <form method="post" action="<?= e(url('raspuns')) ?>" class="stack" style="margin-top:10px">
                <?= csrf_field() ?><input type="hidden" name="slot_id" value="<?= (int)$m['slot_id'] ?>"><input type="hidden" name="answer" value="decline">
                <label class="field">Motiv <span class="hint">opțional, îl văd editorii</span><input type="text" name="note" maxlength="255" placeholder="ex. sunt plecat din oraș"></label>
                <button class="btn danger" type="submit">Trimite: nu pot ajunge</button>
              </form>
            </details>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <section class="stack" aria-label="Următoarele servicii">
    <h2>Următoarele servicii</h2>
    <?php if (!$upcoming): ?>
      <section class="card empty">
        <span class="big-icon" aria-hidden="true"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg></span>
        <h2><?= $pending ? 'Confirmă serviciile de mai sus' : 'Încă nu ești programat' ?></h2>
        <p class="muted small" style="max-width:420px">Când un editor publică programul pentru joi sau duminică, serviciile tale apar aici, cu echipa și orele.</p>
      </section>
    <?php endif; ?>
    <?php foreach ($upcoming as $m): $t = strtotime($m['event_date']); ?>
      <a class="ev-card" href="<?= e(url('program/' . (int)$m['id'])) ?>">
        <span class="ev-date"><span><?= e(mb_substr(RO_DAYS[(int)date('w', $t)], 0, 3)) ?></span><strong><?= date('j', $t) ?></strong></span>
        <span class="grow stack" style="gap:2px;min-width:0"><strong><?= e($m['name']) ?></strong><span class="xs muted"><?= e($m['start_time']) ?><?= $m['rehearsal_time'] ? ' · repetiție ' . e($m['rehearsal_time']) : '' ?> · <?= e($m['position']) ?></span></span>
        <span class="badge ok">Confirmat</span>
      </a>
    <?php endforeach; ?>
  </section>

  <?php if ($past): ?>
    <details class="card">
      <summary class="small" style="cursor:pointer;font-weight:600">Ultimele servicii</summary>
      <ul class="uses" style="margin-top:8px">
        <?php foreach ($past as $p): ?><li><span><?= e($p['name']) ?> · <?= e(ro_date($p['event_date'])) ?></span><span><?= e($p['position']) ?></span></li><?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>
</main>
