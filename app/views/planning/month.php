<?php
$title = 'Planificare · ' . ro_month_year($ym . '-01');
$prev = date('Y-m', strtotime($ym . '-01 -1 month'));
$next = date('Y-m', strtotime($ym . '-01 +1 month'));
$short = function (?string $n): string { $p = preg_split('/\s+/u', trim((string)$n)); return $p[0] . (isset($p[1]) ? ' ' . mb_substr($p[1], 0, 1) . '.' : ''); };
$cols = count($events);
$ziLabels = ['toate' => 'Toate', 'duminica' => 'Duminică', 'joi' => 'Joi'];
?>
<main class="page" data-month-page>
  <div class="page-head">
    <div class="grow"><h1>Planificare</h1></div>
    <a class="btn" href="<?= e(url('planificare/nou')) ?>">+ Eveniment special</a>
  </div>
  <div class="seg-links">
    <a href="<?= e(url('planificare')) ?>">Următoarele</a>
    <a href="<?= e(url('planificare/luna?l=' . $ym)) ?>" aria-current="page">Pe lună</a>
  </div>
  <div class="row">
    <a class="icon-btn bordered" href="<?= e(url('planificare/luna?l=' . $prev . '&zi=' . $zi)) ?>" aria-label="Luna anterioară"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg></a>
    <h2 style="text-transform:capitalize;font-size:22px"><?= e(ro_month_year($ym . '-01')) ?></h2>
    <a class="icon-btn bordered" href="<?= e(url('planificare/luna?l=' . $next . '&zi=' . $zi)) ?>" aria-label="Luna următoare"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></a>
    <span class="xs muted grow">Apasă pe o căsuță ca să alegi omul · pe dată ca să deschizi evenimentul</span>
  </div>
  <div class="seg-links" aria-label="Filtru">
    <?php foreach ($ziLabels as $k => $lbl): ?>
      <a href="<?= e(url('planificare/luna?l=' . $ym . '&zi=' . $k)) ?>"<?= $zi === $k ? ' aria-current="page"' : '' ?>><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="legend xs"><span><i class="lg ok"></i>Confirmat</span><span><i class="lg wait"></i>Neconfirmat</span><span><i class="lg draft"></i>Ciornă</span><span><i class="lg bad"></i>A refuzat</span><span><i class="lg free"></i>Loc liber</span><span><b>—</b> poziție nefolosită</span></div>

  <?php if (!$events): ?>
    <section class="card empty"><p class="small muted"><?= $zi === 'toate' ? 'Nu există evenimente în această lună.' : 'Nu există evenimente de ' . e(mb_strtolower($ziLabels[$zi])) . ' în această lună.' ?></p></section>
  <?php else: ?>
  <div class="grid-wrap" data-grid data-live="grid">
    <table class="mgrid">
      <thead><tr><th class="pcol">Poziție</th>
        <?php foreach ($events as $e): ?>
          <th><a href="<?= e(url('planificare/eveniment/' . (int)$e['id'])) ?>"><strong><?= e(event_short_date($e['event_date'])) ?></strong><span><?= e($e['name']) ?></span><?= $e['status'] === 'draft' ? '<em>ciornă</em>' : '' ?></a></th>
        <?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($tree as $g):
        $gpos = array_filter($g['positions'], fn($p) => !empty($posUsed[(int)$p['id']]));
        if (!$gpos) continue; ?>
        <tr class="grp"><td colspan="<?= $cols + 1 ?>"><?= e($g['name']) ?></td></tr>
        <?php foreach ($gpos as $p): $pid = (int)$p['id']; for ($i = 0; $i < $posUsed[$pid]; $i++): ?>
          <tr>
            <td class="pcol"><?= e($p['name']) ?><?= $posUsed[$pid] > 1 ? ' ' . ($i + 1) : '' ?></td>
            <?php foreach ($events as $e): $s = $cells[$e['id']][$pid][$i] ?? null; $draft = $e['status'] === 'draft'; ?>
              <td>
                <?php if (!$s): ?><span class="cell na">—</span>
                <?php elseif (!$s['user_id']): ?><a class="cell free" href="<?= e(url('planificare/eveniment/' . (int)$e['id'] . '?alege=' . (int)$s['id'] . '#alege')) ?>" data-pick="<?= e(url('planificare/eveniment/' . (int)$e['id'] . '?alege=' . (int)$s['id'])) ?>" aria-label="Loc liber, alege"></a>
                <?php else: $cls = $s['response'] === 'declined' ? 'bad' : ($s['response'] === 'accepted' ? 'ok' : ($draft ? 'draft' : 'wait')); $n = $load[(int)$s['user_id']] ?? 0; ?>
                  <a class="cell <?= $cls ?>" href="<?= e(url('planificare/eveniment/' . (int)$e['id'] . '?alege=' . (int)$s['id'] . '#alege')) ?>" data-pick="<?= e(url('planificare/eveniment/' . (int)$e['id'] . '?alege=' . (int)$s['id'])) ?>" title="<?= e($s['user_name']) ?>"><span><?= $s['is_test'] ? '<i class="tdot" title="Cont de test">T</i>' : '' ?><?= e($short($s['user_name'])) ?></span><b style="color:<?= load_level($n)[1] ?>"><?= $n ?></b></a>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endfor; endforeach; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="xs muted">Cifra de lângă nume = câte duminici slujește omul în luna aceasta (verde până la 2, portocaliu 3, roșu 4 sau mai multe). Joile nu se numără.</p>
  <?php endif; ?>
</main>
<dialog id="pick-dlg" class="pick-dlg" aria-label="Alege o persoană"><div class="dlg" data-pick-body></div></dialog>
