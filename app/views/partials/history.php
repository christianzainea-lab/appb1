<?php /** Istoricul unui obiect (cântare sau eveniment). Doar editori și admini. */ ?>
<details class="card history">
  <summary><h2 style="display:inline">Istoric</h2> <span class="xs muted"><?= count($history) ?> <?= count($history) === 1 ? 'modificare' : 'modificări' ?> · vizibil doar editorilor</span></summary>
  <ul class="hist">
    <?php foreach ($history as $h): ?>
      <li>
        <span class="xs muted"><?= e(activity_when($h['created_at'])) ?> · <strong style="color:var(--text-2)"><?= e($h['user_name'] ?: 'Sistem') ?></strong></span>
        <span class="small"><?= e($h['summary']) ?></span>
      </li>
    <?php endforeach; ?>
    <?php if (!empty($created)): ?>
      <li><span class="xs muted"><?= e(activity_when($created)) ?></span><span class="small muted"><?= e($createdLabel) ?></span></li>
    <?php endif; ?>
  </ul>
</details>
