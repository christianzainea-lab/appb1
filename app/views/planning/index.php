<?php $title = 'Planificare'; ?>
<main class="page narrow">
  <div class="page-head">
    <div class="grow">
      <h1>Planificare</h1>
      <span class="small muted"><?= !empty($pastor) ? 'Serviciile următoarelor ' . (int)$weeks . ' săptămâni. Deschide unul ca să lucrezi la ordinea serviciului.' : 'Următoarele ' . (int)$weeks . ' săptămâni. Joile și duminicile apar singure din șabloane.' ?></span>
    </div>
    <?php if (empty($pastor)): ?><a class="btn primary" href="<?= e(url('planificare/nou')) ?>">+ Eveniment special</a><?php endif; ?>
  </div>
  <?php if (empty($pastor)): ?>
  <div class="seg-links" role="tablist">
    <a href="<?= e(url('planificare')) ?>" aria-current="page">Următoarele</a>
    <a class="tab-up" href="<?= e(url('planificare/luna?l=' . date('Y-m'))) ?>">Pe lună</a>
  </div>
  <?php endif; ?>

  <?php if (!$byMonth): ?>
    <section class="card empty"><h2>Niciun eveniment</h2><p class="small muted">Nu există șabloane active care se repetă. Verifică <a href="<?= e(url('setari/sabloane')) ?>">Șabloanele</a> sau adaugă un eveniment special.</p></section>
  <?php endif; ?>

  <?php foreach ($byMonth as $ym => $list): ?>
    <div class="row" style="margin-top:6px">
      <h2 class="grow" style="text-transform:capitalize"><?= e(ro_month_year($ym . '-01')) ?></h2>
      <?php if (empty($pastor)): ?><a class="btn ghost sm tab-up" href="<?= e(url('planificare/luna?l=' . $ym)) ?>">Tabelul lunii</a><?php endif; ?>
    </div>
    <div class="ev-list">
      <?php foreach ($list as $e): $st = $stats[(int)$e['id']]; $t = strtotime($e['event_date']);
        $free = $st['total'] - $st['filled'];
        $needs = $e['status'] === 'draft' || $free > 0 || $st['declined'] > 0; ?>
        <a class="ev-card<?= $needs ? ' needs' : '' ?>" href="<?= e(url('planificare/eveniment/' . (int)$e['id'])) ?>">
          <span class="ev-date"><span><?= e(mb_substr(RO_DAYS[(int)date('w', $t)], 0, 3)) ?></span><strong><?= date('j', $t) ?></strong></span>
          <span class="grow stack" style="gap:3px;min-width:0">
            <strong><?= e($e['name']) ?></strong>
            <span class="xs muted"><?= e($e['start_time']) ?><?= $e['rehearsal_time'] ? ' · repetiție ' . e($e['rehearsal_time']) : '' ?></span>
            <span class="row" style="gap:6px">
              <?= $e['status'] === 'draft' ? '<span class="badge grey">Ciornă</span>' : '<span class="badge ok">Publicat</span>' ?>
              <?php if ($free > 0): ?><span class="badge warn"><?= $free ?> <?= $free === 1 ? 'loc liber' : 'locuri libere' ?></span><?php endif; ?>
              <?php if ($st['declined']): ?><span class="badge bad"><?= $st['declined'] ?> <?= $st['declined'] === 1 ? 'refuz' : 'refuzuri' ?></span><?php endif; ?>
              <?php if ($e['status'] === 'published' && $st['pending']): ?><span class="xs muted"><?= $st['pending'] ?> neconfirmați</span><?php endif; ?>
            </span>
          </span>
          <span class="ev-fill" aria-label="<?= $st['filled'] ?> din <?= $st['total'] ?> ocupate"><span style="width:<?= $st['total'] ? round($st['filled'] / $st['total'] * 100) : 0 ?>%"></span></span>
          <span class="small" style="font-weight:700;white-space:nowrap"><?= $st['filled'] ?>/<?= $st['total'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <a class="btn ghost sm" href="<?= e(url('planificare?saptamani=' . min(26, $weeks + 8))) ?>" style="align-self:center">Arată mai departe</a>
</main>
