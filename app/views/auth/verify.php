<?php $title = 'Confirmă emailul'; ?>
<div class="stack" style="gap:18px;padding-top:12px">
  <span class="big-icon" aria-hidden="true" style="background:var(--accent-soft);color:var(--accent)">
    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
  </span>
  <h1>Verifică-ți emailul</h1>
  <p style="color:var(--text-2)">Am trimis un link de confirmare la <strong><?= e($u['email']) ?></strong>. Deschide-l ca să mergi mai departe.</p>
  <ol class="steps" aria-label="Pașii înscrierii">
    <li><span class="step-dot done"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12l5 5 9-10"/></svg></span>Cont creat</li>
    <li><span class="step-dot now"></span><strong>Confirmă emailul</strong></li>
    <li><span class="step-dot todo"></span><span class="muted">Aprobare de la administrator</span></li>
  </ol>
  <p class="small muted">Nu găsești emailul? Uită-te și în Spam sau Promoții.</p>
  <form method="post"><?= csrf_field() ?><button class="btn lg block" type="submit">Trimite din nou emailul</button></form>
</div>
