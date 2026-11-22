<?php $title = 'Așteaptă aprobarea'; ?>
<div class="stack" style="gap:18px;padding-top:12px" data-poll="<?= e(url('asteptare?stare=1')) ?>" data-done="<?= e(url('')) ?>">
  <span class="big-icon" aria-hidden="true" style="background:var(--warn-soft);color:var(--warn-text)">
    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
  </span>
  <h1>Contul tău așteaptă aprobarea</h1>
  <p style="color:var(--text-2);font-size:15px">Mulțumim, <?= e(first_name($u['name'])) ?>! Un administrator al echipei trebuie să-ți aprobe contul. Până atunci nu vei vedea programul sau cântările.</p>
  <ol class="steps" aria-label="Pașii înscrierii">
    <li><span class="step-dot done"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12l5 5 9-10"/></svg></span>Cont creat</li>
    <li><span class="step-dot done"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12l5 5 9-10"/></svg></span>Email confirmat</li>
    <li><span class="step-dot now"></span><span><strong>Aprobare de la administrator</strong> <span class="muted">· în așteptare</span></span></li>
  </ol>
  <p class="small muted">Pagina se actualizează singură după aprobare. Dacă durează, scrie-i liderului de echipă.</p>
  <a class="btn lg block" href="<?= e(url('profil')) ?>">Între timp, completează-ți profilul</a>
</div>
