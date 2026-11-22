<?php
$title = 'Importă cântări';
$count = fn(string $st) => count(array_filter($plan, fn($r) => $r['status'] === $st));
$statusBadge = [
    'new' => '<span class="badge ok">Nouă</span>',
    'exists' => '<span class="badge grey">Există</span>',
    'dupfile' => '<span class="badge warn">Dublură</span>',
    'error' => '<span class="badge bad">Eroare</span>',
];
?>
<main class="page narrow">
  <div class="page-head"><div class="grow"><h1>Importă cântări</h1><span class="small muted">Adaugă mai multe cântări odată dintr-un fișier Excel (.xlsx) sau CSV.</span></div>
    <a class="btn ghost" href="<?= e(url('cantari')) ?>">Înapoi la cântări</a></div>

<?php if (!$d): ?>
  <section class="card stack">
    <h2>1. Descarcă modelul</h2>
    <p class="small muted" style="margin:0">Primul rând are numele coloanelor. Doar <strong>Titlu</strong> e obligatoriu; restul se pot lăsa goale.</p>
    <div class="row" style="flex-wrap:wrap;gap:8px">
      <?php if ($canXlsx): ?><a class="btn" href="<?= e(url('cantari/import/model.xlsx')) ?>">Model Excel (.xlsx)</a><?php endif; ?>
      <a class="btn" href="<?= e(url('cantari/import/model.csv')) ?>">Model CSV</a>
    </div>
    <ul class="small" style="margin:0;padding-left:18px;color:var(--text-2);display:flex;flex-direction:column;gap:4px">
      <li><strong>Carte</strong> și <strong>Număr</strong>: ex. „CR” și „245”. Cărțile care nu sunt încă în <a href="<?= e(url('setari/carti')) ?>">Setări → Cărți</a> se adaugă singure.</li>
      <li><strong>Ton</strong>: „Sol”, „La m”, „Mi♭” sau „G”, „Am”, „Eb” (se traduc singure).</li>
      <li><strong>Ocazii</strong> și <strong>Teme</strong>: mai multe, despărțite prin virgulă. Etichetele noi se creează singure.</li>
      <li><strong>YouTube</strong>: unul sau mai multe linkuri, despărțite prin spațiu sau virgulă. Primul devine înregistrarea principală.</li>
      <li><strong>Versuri</strong>: textul întreg, pe mai multe rânduri în aceeași celulă (Alt+Enter în Excel).</li>
      <li>Înregistrările MP3 și fișierele cu acorduri se adaugă apoi la fiecare cântare, ca până acum.</li>
    </ul>
  </section>
  <form method="post" enctype="multipart/form-data" class="card stack">
    <?= csrf_field() ?>
    <h2>2. Încarcă fișierul completat</h2>
    <label class="field">Fișier .xlsx sau .csv <span class="hint">până la <?= IMPORT_MAX_MB ?> MB, maximum <?= IMPORT_MAX_ROWS ?> de cântări</span>
      <input type="file" name="file" accept=".xlsx,.csv,.txt,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>
    <span class="xs muted">Nimic nu se salvează încă: vezi întâi ce se va importa.</span>
    <div class="row" style="justify-content:flex-end"><button class="btn primary" type="submit">Verifică fișierul</button></div>
  </form>

<?php else: $new = $count('new'); $exists = $count('exists'); $bad = $count('error') + $count('dupfile'); ?>
  <section class="card stack">
    <h2>3. Verifică și importă</h2>
    <p class="small" style="margin:0">Fișier: <strong><?= e($d['file']) ?></strong> · coloane recunoscute: <?= e(implode(', ', $d['cols'])) ?></p>
    <div class="row" style="flex-wrap:wrap;gap:8px">
      <span class="badge ok"><?= $new ?> noi</span>
      <span class="badge grey"><?= $exists ?> există deja</span>
      <?php if ($bad): ?><span class="badge bad"><?= $bad ?> cu probleme (se sar)</span><?php endif; ?>
    </div>
    <?php if ($newBooks): ?><p class="small muted" style="margin:0">Cărți noi care se vor adăuga: <?= e(implode(', ', $newBooks)) ?>.</p><?php endif; ?>
    <form method="post" class="stack" style="gap:10px">
      <?= csrf_field() ?><input type="hidden" name="action" value="apply"><input type="hidden" name="f" value="<?= e($tok) ?>">
      <?php if ($exists): ?>
        <fieldset class="plain stack" style="gap:6px">
          <legend class="small"><strong>Cântările care există deja</strong> (aceeași carte și număr sau același titlu)</legend>
          <label class="check"><input type="radio" name="mode" value="skip" checked>Le las neschimbate (se sar)</label>
          <label class="check"><input type="radio" name="mode" value="update">Le completez cu datele din fișier (doar câmpurile completate; etichetele și linkurile se adaugă, nimic nu se șterge)</label>
        </fieldset>
      <?php endif; ?>
      <div class="row" style="justify-content:flex-end;gap:8px;flex-wrap:wrap">
        <button class="btn ghost" type="submit" name="action" value="cancel" formnovalidate>Renunță</button>
        <button class="btn primary" type="submit"<?= ($new + $exists) ? '' : ' disabled' ?>>Importă</button>
      </div>
    </form>
  </section>

  <section class="card stack" style="gap:0;padding:0">
    <?php foreach ($plan as $r): ?>
      <div class="imp-row<?= in_array($r['status'], ['error', 'dupfile'], true) ? ' is-bad' : '' ?>">
        <span class="xs muted imp-line"><?= (int)$r['line'] ?></span>
        <div class="grow stack" style="gap:2px;min-width:0">
          <div class="row" style="gap:6px;flex-wrap:wrap"><?= $statusBadge[$r['status']] ?><strong class="small"><?= e($r['title'] !== '' ? $r['title'] : '(fără titlu)') ?></strong>
            <?php $ref = trim($r['book'] . ' ' . $r['number']); if ($ref !== ''): ?><span class="xs muted"><?= e($ref) ?></span><?php endif; ?>
            <?php if ($r['key'] !== ''): ?><span class="tag" style="font-size:11px"><?= e($r['key']) ?></span><?php endif; ?></div>
          <?php $extra = array_filter([$r['author'], $r['bpm'] !== '' ? $r['bpm'] . ' BPM' : '', implode(', ', array_merge($r['ocazii_list'] ?? [], $r['teme_list'] ?? [])), !empty($r['yt']) ? count($r['yt']) . ' YouTube' : '', $r['lyrics'] !== '' ? 'versuri' : '']); ?>
          <?php if ($extra): ?><span class="xs muted"><?= e(implode(' · ', $extra)) ?></span><?php endif; ?>
          <?php foreach ($r['warn'] as $w): ?><span class="xs" style="color:var(--warn-text)"><?= e($w) ?></span><?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>
</main>
