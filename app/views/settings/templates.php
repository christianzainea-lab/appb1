<?php $title = 'Șabloane'; ?>
<main class="page narrow">
  <div class="page-head">
    <div class="grow"><h1>Șabloane</h1><span class="small muted">Fiecare eveniment nou pornește de la un șablon. Apasă pe un șablon ca să-l editezi.</span></div>
    <a class="btn primary" href="<?= e(url('setari/sabloane/nou')) ?>">+ Șablon nou</a>
  </div>
  <section class="menu-list">
    <?php foreach ($tpls as $t): ?>
      <a class="menu-item" href="<?= e(url('setari/sabloane/' . (int)$t['id'])) ?>">
        <span class="grow">
          <strong><?= e($t['name']) ?></strong><?= $t['active'] ? '' : ' <span class="badge grey">oprit</span>' ?><br>
          <span class="xs muted"><?= e(template_when($t)) ?> · <?= e($t['start_time']) ?><?= $t['rehearsal_time'] ? ' · repetiție ' . e($t['rehearsal_time']) : '' ?> · <?= (int)$t['slots'] ?> locuri · <?= (int)$t['items'] ?> elemente (<?= (int)$t['songs'] ?> cântări)</span>
        </span>
        <span aria-hidden="true">›</span>
      </a>
    <?php endforeach; ?>
  </section>
</main>
