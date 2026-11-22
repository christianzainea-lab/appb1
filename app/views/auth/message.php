<?php $title = $title ?? ''; ?>
<div class="stack" style="gap:10px">
  <h1><?= e($title) ?></h1>
  <p class="muted"><?= e($text ?? '') ?></p>
  <?php if (!empty($link)): ?>
    <a class="btn lg block" href="<?= e(url($link[0])) ?>" style="margin-top:8px"><?= e($link[1]) ?></a>
  <?php endif; ?>
</div>
