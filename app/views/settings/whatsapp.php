<?php $title = 'Mesaj WhatsApp'; ?>
<main class="page">
  <div class="cols">
    <form method="post" class="card stack" style="flex:999 1 520px">
      <?= csrf_field() ?>
      <div class="stack" style="gap:4px">
        <h2>Șablonul mesajului „Copiază pentru WhatsApp”</h2>
        <span class="small muted">Se folosește la toate evenimentele. Câmpurile din acolade se completează singure.</span>
      </div>
      <label class="field">Text
        <textarea id="wa-text" class="mono" name="template" rows="13"><?= e(setting('whatsapp_template', DEFAULT_WHATSAPP)) ?></textarea>
      </label>
      <div class="row" style="gap:6px">
        <span class="xs" style="font-weight:600;color:var(--text-2)">Inserează:</span>
        <?php foreach (['{eveniment}', '{data}', '{ora}', '{repetitie}', '{ordinea}', '{cantari}', '{echipa}', '{notite}', '{link}'] as $v): ?>
          <button class="btn sm" type="button" data-insert="<?= e($v) ?>" data-target="wa-text" style="font:500 12px ui-monospace,Menlo,monospace;background:#F8F9FA;min-height:30px;padding:0 8px"><?= e($v) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="stack" style="gap:8px;border-top:1px solid var(--line-2);padding-top:12px">
        <label class="check"><input type="checkbox" name="whatsapp_include_key" value="1"<?= setting('whatsapp_include_key', '1') === '1' ? ' checked' : '' ?>>Include tonalitatea la cântări</label>
        <label class="check"><input type="checkbox" name="whatsapp_include_items" value="1"<?= setting('whatsapp_include_items', '1') === '1' ? ' checked' : '' ?>>Include elementele care nu sunt cântări (predică, rugăciune…)</label>
        <label class="check"><input type="checkbox" name="whatsapp_first_names" value="1"<?= setting('whatsapp_first_names', '1') === '1' ? ' checked' : '' ?>>Folosește doar prenumele în echipă</label>
      </div>
      <div class="row" style="justify-content:flex-end">
        <button class="btn ghost" type="submit" name="reset" value="1" data-confirm="Revii la textul implicit?">Revino la textul implicit</button>
        <button class="btn primary" type="submit">Salvează</button>
      </div>
    </form>
    <aside class="card stack" style="flex:1 1 300px">
      <h2>Ce înseamnă fiecare câmp</h2>
      <dl class="small" style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:6px 12px">
        <?php foreach (['{eveniment}' => 'numele serviciului', '{data}' => 'ziua și data', '{ora}' => 'ora de început', '{repetitie}' => 'ora repetiției', '{ordinea}' => 'ordinea completă a serviciului', '{cantari}' => 'doar cântările, cu tonalitate', '{echipa}' => 'cine slujește, pe poziții', '{notite}' => 'notițele editorului', '{link}' => 'linkul către program în aplicație'] as $k => $d): ?>
          <dt style="font-family:ui-monospace,Menlo,monospace;color:var(--accent)"><?= e($k) ?></dt><dd style="margin:0;color:var(--text-2)"><?= e($d) ?></dd>
        <?php endforeach; ?>
      </dl>
      <?php if (!empty($preview)): ?>
        <h2 style="border-top:1px solid var(--line-2);padding-top:12px">Exemplu: <?= e($preview['name'] . ' ' . ro_date($preview['event_date'])) ?></h2>
        <textarea class="wa-preview" rows="12" readonly aria-label="Exemplu de mesaj"><?= e(whatsapp_text($preview)) ?></textarea>
        <span class="xs muted">Exemplul arată textul salvat. Salvează ca să vezi modificările.</span>
      <?php endif; ?>
    </aside>
  </div>
</main>
