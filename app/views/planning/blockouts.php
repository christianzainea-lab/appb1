<?php
$title = 'Zile indisponibile';
$err = fn($k) => isset($errors[$k]) ? '<span class="err">' . e($errors[$k]) . '</span>' : '';
$range = fn($b) => $b['start_date'] === $b['end_date'] ? RO_DAYS[(int)date('w', strtotime($b['start_date']))] . ', ' . ro_date($b['start_date'], true) : ro_date($b['start_date']) . ' – ' . ro_date($b['end_date'], true);
$blockedDays = [];
foreach ($list as $b) for ($d = $b['start_date']; $d <= $b['end_date']; $d = date('Y-m-d', strtotime($d . ' +1 day'))) $blockedDays[$d] = $b;
$serveDays = [];
foreach ($mine as $m) if ($m['response'] !== 'declined') $serveDays[$m['event_date']] = $m['name'] . ' ' . $m['start_time'];
$today = date('Y-m-d');
?>
<main class="page narrow">
  <div class="page-head"><div class="grow"><h1>Zile indisponibile</h1><span class="small muted">Apasă pe zilele în care nu poți sluji. Pentru o perioadă, apasă prima și ultima zi.</span></div></div>
  <div class="legend xs"><span><i class="lg bad"></i>Indisponibil</span><span><i class="lg ok"></i>Ești programat</span><span><i class="lg sel"></i>Selectat acum</span></div>

  <div class="row cal-nav">
    <button class="icon-btn bordered" type="button" data-cal-prev aria-label="Luna anterioară"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg></button>
    <strong class="grow" data-cal-title style="text-transform:capitalize;text-align:center"></strong>
    <button class="icon-btn bordered" type="button" data-cal-next aria-label="Luna următoare"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
  </div>
  <div class="cal-wrap cal-scroll" data-cal>
    <?php for ($k = 0; $k < 13; $k++): $first = strtotime(date('Y-m-01') . " +$k month"); $days = (int)date('t', $first); $lead = ((int)date('N', $first)) - 1; ?>
      <section class="card cal" data-month="<?= e(ro_month_year(date('Y-m-d', $first))) ?>">
        <h2 style="text-transform:capitalize"><?= e(ro_month_year(date('Y-m-d', $first))) ?></h2>
        <div class="cal-grid">
          <?php foreach (['L', 'Ma', 'Mi', 'J', 'V', 'S', 'D'] as $dn): ?><span class="cal-h"><?= $dn ?></span><?php endforeach; ?>
          <?php for ($i = 0; $i < $lead; $i++): ?><span></span><?php endfor; ?>
          <?php for ($d = 1; $d <= $days; $d++):
            $ds = date('Y-m-', $first) . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
            $b = $blockedDays[$ds] ?? null;
            $cls = $b ? 'off' : (isset($serveDays[$ds]) ? 'serve' : '');
            $label = $d . ' ' . RO_MONTHS[(int)date('n', $first) - 1] . ($b ? ', indisponibil' : '') . (isset($serveDays[$ds]) ? ', programat: ' . $serveDays[$ds] : ''); ?>
            <?php if ($ds < $today): ?><span class="cal-d past"><?= $d ?></span>
            <?php else: ?><button type="button" class="cal-d <?= $cls ?><?= $ds === $today ? ' today' : '' ?>" data-date="<?= $ds ?>" data-label="<?= e(ro_date($ds)) ?>"
              <?= $b ? 'data-block="' . (int)$b['id'] . '" data-range="' . e($range($b)) . '" data-reason="' . e($b['reason'] ?? '') . '"' : '' ?><?= isset($serveDays[$ds]) ? ' data-serve="' . e($serveDays[$ds]) . '"' : '' ?> aria-label="<?= e($label) ?>"><?= $d ?></button>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
      </section>
    <?php endfor; ?>
  </div>

  <div class="cal-panel hidden" data-cal-panel role="region" aria-live="polite">
    <form method="post" class="stack" data-panel-add>
      <?= csrf_field() ?><input type="hidden" name="from" data-from><input type="hidden" name="to" data-to>
      <div class="row"><strong class="grow" data-sel-text></strong><button class="icon-btn" type="button" data-cal-clear aria-label="Renunță"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
      <span class="xs muted" data-sel-hint>Apasă pe altă zi ca să alegi o perioadă.</span>
      <span class="xs" data-sel-warn style="color:var(--warn-text)"></span>
      <div class="row" style="flex-wrap:nowrap"><label class="grow"><span class="sr">Motiv (opțional)</span><input type="text" name="reason" maxlength="160" placeholder="Motiv (opțional), ex. concediu"></label><button class="btn primary" type="submit">Marchează</button></div>
    </form>
    <div class="stack hidden" data-panel-block>
      <div class="row"><strong class="grow" data-block-text></strong><button class="icon-btn" type="button" data-cal-clear aria-label="Închide"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
      <span class="xs muted" data-block-reason></span>
      <div class="row">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unmark_day"><input type="hidden" name="date" data-unmark-date><button class="btn" type="submit" data-unmark-btn>Deblochează doar ziua aceasta</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" data-block-id><button class="btn ghost danger" type="submit" data-del-btn>Șterge toată perioada</button></form>
      </div>
    </div>
  </div>

  <section class="card stack">
    <h2>Perioadele mele</h2>
    <?php if (!$list): ?><p class="small muted">Nu ai marcat nicio zi.</p><?php endif; ?>
    <?php foreach ($list as $b): ?>
      <div class="row" style="border-top:1px solid var(--line-2);padding-top:8px">
        <div class="grow"><strong class="small"><?= e($range($b)) ?></strong><?php if ($b['reason']): ?><br><span class="xs muted"><?= e($b['reason']) ?></span><?php endif; ?></div>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn ghost danger sm" type="submit">Șterge</button></form>
      </div>
    <?php endforeach; ?>
  </section>

  <details class="card"<?= $errors ? ' open' : '' ?>>
    <summary class="small" style="cursor:pointer;font-weight:600">Introdu datele manual</summary>
    <form method="post" class="stack" style="margin-top:12px" novalidate>
      <?= csrf_field() ?>
      <div class="grid-3">
        <label class="field<?= isset($errors['from']) ? ' has-err' : '' ?>">De la<input type="date" name="from" value="<?= e($_POST['from'] ?? '') ?>" min="<?= $today ?>" required><?= $err('from') ?></label>
        <label class="field<?= isset($errors['to']) ? ' has-err' : '' ?>">Până la<input type="date" name="to" value="<?= e($_POST['to'] ?? '') ?>" min="<?= $today ?>"><?= $err('to') ?></label>
        <label class="field">Motiv<input type="text" name="reason" value="<?= e($_POST['reason'] ?? '') ?>" maxlength="160" placeholder="opțional"></label>
      </div>
      <span class="xs muted">Lasă „Până la” gol pentru o singură zi.</span>
      <div class="row" style="justify-content:flex-end"><button class="btn primary" type="submit">Adaugă</button></div>
    </form>
  </details>
</main>
