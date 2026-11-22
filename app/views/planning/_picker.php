<?php
/** Lista de alegere pentru un loc. $popup = true când se deschide din tabelul lunii. */
$action = url('planificare/eveniment/' . (int)$e['id']);
$base = url('planificare/eveniment/' . (int)$e['id']);
$avail = array_filter($cands, fn($c) => $c['why'] === null);
$na = array_filter($cands, fn($c) => $c['why'] !== null);
$hid = fn(string $a) => csrf_field() . '<input type="hidden" name="action" value="' . $a . '"><input type="hidden" name="slot_id" value="' . (int)$pick['id'] . '">';
$noteColor = ['warn' => 'var(--warn-text)', 'bad' => 'var(--danger)', 'muted' => 'var(--muted)'];
?>
<section class="<?= $popup ? 'stack' : 'card picker stack' ?>" id="alege" aria-label="Alege o persoană" data-picker>
  <div class="row" style="flex-wrap:nowrap">
    <div class="grow stack" style="gap:2px">
      <span class="xs muted">Alege pentru · <?= e($e['name']) ?></span>
      <h2><?= e($pick['label']) ?> – <?= e(event_short_date($e['event_date'])) ?></h2>
    </div>
    <?php if ($popup): ?><button class="icon-btn" type="button" data-close aria-label="Închide"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
    <?php else: ?><a class="icon-btn" href="<?= e($base . '#slot' . (int)$pick['id']) ?>" aria-label="Închide"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></a><?php endif; ?>
  </div>
  <?php if ($pick['user_id']): ?>
    <div class="row" style="background:var(--accent-tint);border-radius:10px;padding:8px 10px">
      <span class="grow small">Acum: <strong><?= e($pick['user_name']) ?></strong> <?= response_badge($pick, $e['status'] === 'published') ?></span>
      <form method="post" action="<?= e($action) ?>"><?= $hid('unassign') ?><button class="btn ghost sm" type="submit">Lasă locul liber</button></form>
    </div>
  <?php endif; ?>
  <?php if (!$cands): ?>
    <?php if (!empty($pick['is_leader'])): ?>
      <p class="small muted">Nimeni altcineva nu are eticheta „Lider”. O dai din <a href="<?= e(url('setari/persoane')) ?>">Persoane</a> (bifa „Lider” la fiecare om).</p>
    <?php else: ?>
      <p class="small muted">Nimeni altcineva nu are poziția „<?= e($pick['position']) ?>” în profil. Adaug-o oamenilor din <a href="<?= e(url('setari/persoane')) ?>">Persoane</a>.</p>
    <?php endif; ?>
  <?php endif; ?>
  <?php foreach ($avail as $c): ?>
    <div class="cand">
      <div class="grow stack" style="gap:3px;min-width:0">
        <span class="row" style="gap:6px"><strong class="small"><?= e($c['name']) ?><?= (int)$c['id'] === (int)$me['id'] ? ' (tu)' : '' ?></strong><?= $c['rec'] ? '<span class="badge">Recomandat</span>' : '' ?><?= $c['is_test'] ? '<span class="badge test">TEST</span>' : '' ?><?= $c['parts'] ? '<span class="tag" style="font-size:11px">' . e($c['parts']) . '</span>' : '' ?></span>
        <?= load_bar($c['count']) ?>
        <?php foreach ($c['notes'] as [$txt, $kind]): ?><span class="xs" style="color:<?= $noteColor[$kind] ?>;<?= $kind !== 'muted' ? 'font-weight:600' : '' ?>"><?= e($txt) ?></span><?php endforeach; ?>
      </div>
      <?php if ($c['current']): ?><span class="badge ok">Ales</span>
      <?php else: ?>
        <form method="post" action="<?= e($action) ?>"><?= $hid('assign') ?><input type="hidden" name="user_id" value="<?= (int)$c['id'] ?>"><button class="btn sm" style="border-color:var(--accent);color:var(--accent)" type="submit"<?= $c['warn'] >= 2 ? ' data-confirm="' . e($c['name']) . ' ar sluji prea des. Îl/o alegi totuși?"' : '' ?>>Alege</button></form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($na): ?>
    <span class="xs" style="font-weight:700;color:var(--muted);border-top:1px solid var(--line-2);padding-top:8px">Nu sunt disponibili</span>
    <?php foreach ($na as $c): ?>
      <div class="cand na">
        <div class="grow stack" style="gap:2px"><strong class="small" style="color:var(--muted)"><?= e($c['name']) ?></strong><span class="xs" style="color:var(--warn-text)"><?= e($c['why']) ?></span></div>
        <form method="post" action="<?= e($action) ?>"><?= $hid('assign') ?><input type="hidden" name="user_id" value="<?= (int)$c['id'] ?>"><button class="btn ghost sm" type="submit" data-confirm="<?= e($c['name']) ?> nu este disponibil(ă). Îl/o alegi totuși?">Alege totuși</button></form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  <p class="xs muted">Cine e deja pe altă poziție la acest eveniment nu apare în listă.</p>
</section>
