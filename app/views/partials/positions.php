<?php
/** @var array $tree @var array $selPos @var array $selParts */
$small = $small ?? false;
foreach ($tree as $_g):
    if (!$_g['positions']) continue; ?>
  <span class="group-label"><?= e($_g['name']) ?></span>
  <div class="chips">
    <?php foreach ($_g['positions'] as $_p): ?>
      <label class="chip<?= $small ? ' sm' : '' ?>"><input type="checkbox" name="positions[]" value="<?= (int)$_p['id'] ?>"<?= in_array((int)$_p['id'], $selPos, true) ? ' checked' : '' ?>><?= e($_p['name']) ?></label>
    <?php endforeach; ?>
  </div>
  <?php foreach ($_g['positions'] as $_p): if (!$_p['parts']) continue; ?>
    <span class="xs" style="font-weight:600;color:var(--text-2)"><?= e(mb_strtolower($_p['name']) === 'voce' ? 'Partida vocală' : $_p['name'] . ' · variantă') ?></span>
    <div class="chips">
      <?php foreach ($_p['parts'] as $_pt): ?>
        <label class="chip part<?= $small ? ' sm' : '' ?>"><input type="checkbox" name="parts[]" value="<?= (int)$_pt['id'] ?>" data-part-of="<?= (int)$_p['id'] ?>"<?= in_array((int)$_pt['id'], $selParts, true) ? ' checked' : '' ?>><?= e($_pt['name']) ?></label>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>
