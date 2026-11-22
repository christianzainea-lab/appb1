<?php
// Conținutul meniului ☰: în meniul derulant din bara de sus și pe pagina /meniu.
$me = current_user();
$active = $me['status'] === 'active';
$item = fn(string $path, string $label, string $hint = '') => '<a class="menu-item" href="' . e(url($path)) . '"><span class="grow"><strong>' . e($label) . '</strong>' . ($hint ? '<br><span class="xs muted">' . e($hint) . '</span>' : '') . '</span><span aria-hidden="true">›</span></a>';
?>
  <div class="row" style="gap:12px">
    <?= avatar_html($me, 48) ?>
    <div class="grow"><strong><?= e($me['name']) ?></strong><br><span class="xs muted"><?= e(role_label($me['role'])) ?><?= $active ? '' : ' · cont în așteptare' ?></span></div>
  </div>
  <section class="menu-list">
    <?php if ($active): ?>
      <?= $item('', 'Programul meu', 'Serviciile la care ești programat') ?>
      <?= $item('cantari', 'Cântări', 'Biblioteca, înregistrări și acorduri') ?>
      <?php if (can_plan()): ?><?= $item('planificare', 'Planificare', 'Joi, duminici și evenimente speciale') ?><?php endif; ?>
      <?= $item('indisponibil', 'Zile indisponibile', 'Când nu poți sluji') ?>
      <?= $item('profil', 'Profil', 'Date, poziții, parolă') ?>
      <?= $item('ghid', 'Cum funcționează', 'Ghid în imagini, un minut') ?>
    <?php else: ?>
      <?= $item('asteptare', 'Starea contului', 'Aprobarea de la administrator') ?>
      <?= $item('profil', 'Profil', 'Date, poziții, parolă') ?>
    <?php endif; ?>
  </section>
  <?php if (can('editor')): ?>
    <span class="group-label">Setări</span>
    <section class="menu-list">
      <?= $item('setari/persoane', 'Persoane și invitații', 'Cereri noi, linkuri, roluri') ?>
      <?= $item('setari/pozitii', 'Roluri și poziții', 'Pozițiile din echipă și partidele vocale') ?>
      <?= $item('setari/sabloane', 'Șabloane', 'Joi, duminică, Cina Domnului, după-amiaza') ?>
      <?= $item('setari/whatsapp', 'Mesaj WhatsApp', 'Textul pentru „Copiază pentru WhatsApp”') ?>
      <?= $item('setari/etichete', 'Etichete cântări', 'Ocazii și teme') ?>
      <?= $item('setari/tonuri', 'Tonuri', 'Tonuri în plus față de Do … Si') ?>
      <?= $item('setari/carti', 'Cărți de cântări', 'Cărțile din filtrul cântărilor') ?>
      <?= $item('setari/fisiere', 'Fișiere la servicii', 'Prezentări și PDF-uri atașate · spațiu ocupat') ?>
      <?= $item('setari/biserica', 'Biserică și logo', 'Numele aplicației, logo, email de test') ?>
    </section>
  <?php endif; ?>
  <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="btn ghost danger" type="submit">Ieși din cont</button></form>
