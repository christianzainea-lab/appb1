<?php
$title = 'Persoane și invitații';
$qs = function (array $extra = []) use ($search, $posFilter, $roleFilter) {
    $p = array_filter(['q' => $search, 'poz' => $posFilter ?: '', 'rol' => $roleFilter] + $extra, fn($v) => $v !== '' && $v !== null);
    return $p ? '?' . http_build_query($p) : '';
};
$formAction = url('setari/persoane') . $qs();
$allPositions = [];
foreach ($tree as $g) foreach ($g['positions'] as $p) $allPositions[] = $p;
$waLink = function (string $text, ?string $phone = null) {
    $num = $phone ? preg_replace('/\D/', '', $phone) : '';
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($text);
};
?>
<main class="page">
  <div class="page-head">
    <div class="grow">
      <h1>Persoane</h1>
      <span class="small muted"><?= $counts['all'] ?> <?= $counts['all'] === 1 ? 'persoană' : 'persoane' ?> · <?= $counts['admin'] ?> admin · <?= $counts['editor'] ?> <?= $counts['editor'] === 1 ? 'editor' : 'editori' ?> · <?= $counts['voluntar'] ?> <?= $counts['voluntar'] === 1 ? 'voluntar' : 'voluntari' ?></span>
    </div>
    <?php if ($testTools): ?><button class="btn test-btn" type="button" data-open="dlg-test">+ Cont de test</button><?php endif; ?>
    <button class="btn" type="button" data-open="dlg-personal">Invitație personală</button>
    <button class="btn primary" type="button" data-open="dlg-team">+ Link nou de invitație</button>
  </div>

  <?php if ($requests): ?>
  <section class="card warn stack" aria-label="Cereri noi de înscriere">
    <h2>Cereri noi de înscriere <span style="color:var(--warn-text)">(<?= count($requests) ?>)</span></h2>
    <?php if (!$isAdmin): ?><p class="xs muted">Conturile noi le aprobă un administrator.</p><?php endif; ?>
    <?php foreach ($requests as $r): ?>
      <div class="request">
        <?= avatar_html($r, 36, '#FBEBD6', '#8A4205') ?>
        <div class="info">
          <strong style="font-size:14px"><?= e($r['name']) ?></strong>
          <span class="xs muted"><?= e($r['email']) ?> ·
            <?= $r['status'] === 'unverified' ? '<span style="color:var(--warn);font-weight:600">email neconfirmat</span>' : '<span style="color:var(--ok);font-weight:600">email confirmat</span>' ?>
            <?= $r['phone'] ? ' · ' . e($r['phone']) : '' ?></span>
          <span class="xs muted">S-a înscris <?= e(mb_strtolower(ago($r['created_at']))) ?><?= $r['invite'] ? ($r['invite']['kind'] === 'personal' ? ' cu invitație personală' : ' prin linkul echipei') : '' ?> · <?= $r['labels'] ? 'a ales: ' . e(implode(', ', $r['labels'])) : 'poziții nesetate' ?></span>
        </div>
        <?php if ($r['status'] === 'unverified'): ?>
          <form method="post" action="<?= e($formAction) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="resend_verify"><input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>"><button class="btn sm" type="submit">Retrimite emailul</button></form>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
          <form method="post" action="<?= e($formAction) ?>" class="row" style="gap:8px">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
            <label class="row xs" style="gap:6px;color:var(--text-2)">Rol
              <select name="role" style="min-height:36px;width:auto;font-size:13px"><option value="voluntar">Voluntar</option><option value="pastor"<?= $r['role'] === 'pastor' ? ' selected' : '' ?>>Pastor</option><option value="editor"<?= $r['role'] === 'editor' ? ' selected' : '' ?>>Editor</option></select>
            </label>
            <button class="btn primary sm" name="action" value="approve" type="submit"<?= $r['status'] === 'unverified' ? ' data-confirm="Emailul nu este confirmat încă. Aprobi totuși contul?"' : '' ?>>Aprobă</button>
            <button class="btn sm danger" name="action" value="reject" type="submit" data-confirm="Respingi și ștergi cererea lui <?= e($r['name']) ?>?">Respinge</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php foreach ($invites as $inv): $link = full_url('inscriere/' . $inv['token']); ?>
  <section class="card link-box" aria-label="Link de invitație">
    <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#650B0A" stroke-width="2" stroke-linecap="round"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
    <div class="info">
      <strong style="font-size:14px"><?= $inv['kind'] === 'team' ? 'Link de invitație pentru echipă · intră ca Voluntar' : 'Invitație personală · ' . e($inv['name']) . ' · ' . e(role_label($inv['role'])) ?></strong>
      <span class="xs muted" style="word-break:break-all"><?= e(preg_replace('#^https?://#', '', $link)) ?> · expiră pe <?= e(ro_date($inv['expires_at'])) ?><?= $inv['kind'] === 'team' ? ' · ' . (int)$inv['uses'] . ' ' . ((int)$inv['uses'] === 1 ? 'persoană s-a înscris' : 'persoane s-au înscris') : '' ?></span>
    </div>
    <button class="btn sm" type="button" data-copy="<?= e($link) ?>">Copiază link</button>
    <a class="btn sm ok" href="<?= e($waLink(($inv['kind'] === 'team' ? 'Înscrie-te în ' . app_name() . ' aici: ' : 'Salut, ' . first_name((string)$inv['name']) . '! Creează-ți contul aici: ') . $link)) ?>" target="_blank" rel="noopener">Trimite pe WhatsApp</a>
    <form method="post" action="<?= e($formAction) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="disable_invite"><input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>"><button class="btn ghost danger sm" type="submit" data-confirm="Dezactivezi linkul? Cine îl are nu se mai poate înscrie.">Dezactivează</button></form>
  </section>
  <?php endforeach; ?>

  <section class="people" aria-label="Lista de persoane">
    <form class="people-tools" method="get" action="<?= e(url('setari/persoane')) ?>">
      <label class="q"><span class="sr">Caută persoane</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Caută nume, telefon sau email…"></label>
      <label><span class="sr">Poziție</span>
        <select name="poz" data-autosubmit><option value="">Toate pozițiile</option>
          <?php foreach ($allPositions as $p): ?><option value="<?= (int)$p['id'] ?>"<?= $posFilter === (int)$p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
        </select></label>
      <label><span class="sr">Rol</span>
        <select name="rol" data-autosubmit><option value="">Toate rolurile</option>
          <?php foreach (['admin', 'editor', 'pastor', 'voluntar'] as $r): ?><option value="<?= $r ?>"<?= $roleFilter === $r ? ' selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?>
        </select></label>
      <button class="btn" type="submit">Caută</button>
    </form>
    <div class="person-row person-head"><span>Nume</span><span>Rol</span><span>Poziții</span><span>Autentificare</span><span>Ultima activitate</span><span></span></div>
    <?php if (!$people): ?>
      <div class="empty"><p class="muted small"><?= ($search || $posFilter || $roleFilter) ? 'Nicio persoană nu se potrivește căutării.' : 'Încă nu s-a înscris nimeni. Trimite un link de invitație echipei.' ?></p></div>
    <?php endif; ?>
    <?php foreach ($people as $p): $isOpen = $open === (int)$p['id']; $canEdit = $isAdmin || $p['role'] === 'voluntar'; ?>
      <div class="person" id="p<?= (int)$p['id'] ?>">
        <div class="person-row">
          <div class="person-name c-name">
            <?= avatar_html($p, 32) ?>
            <div><strong><?= e($p['name']) ?><?= !empty($p['is_test']) ? ' <span class="badge test">TEST</span>' : '' ?></strong><span class="xs muted"><?= e($p['phone'] ?: $p['email']) ?></span></div>
          </div>
          <span class="c-role small"><?= e(role_label($p['role'])) ?><?= $p['status'] === 'disabled' ? '<br><span class="badge grey">fără acces</span>' : '' ?></span>
          <div class="chips c-pos" style="gap:4px">
            <?php if (!empty($p['is_leader'])): ?><span class="tag lead">Lider</span><?php endif; ?>
            <?php foreach ($p['labels'] as $l): ?><span class="tag"><?= e($l) ?></span><?php endforeach; ?>
            <?php if (!$p['labels']): ?><span class="xs" style="color:#9A4A06">Nesetat · adaugă poziții</span><?php endif; ?>
          </div>
          <span class="c-login small" style="color:var(--text-2)"><?= $p['google_sub'] ? 'Google' : 'Parolă' ?></span>
          <span class="c-last small muted"><?= e(ago($p['last_seen_at'])) ?></span>
          <span class="c-meta"><?= e(role_label($p['role'])) ?> · activ: <?= e(mb_strtolower(ago($p['last_seen_at']))) ?><?= $p['status'] === 'disabled' ? ' · fără acces' : '' ?></span>
          <span class="c-act">
            <?php if ($canEdit): ?>
              <a class="btn sm" href="<?= e(url('setari/persoane') . $qs($isOpen ? [] : ['deschis' => (int)$p['id']]) . '#p' . (int)$p['id']) ?>"><?= $isOpen ? 'Închide' : 'Editează' ?></a>
            <?php endif; ?>
          </span>
        </div>
        <?php if ($isOpen && $canEdit): $self = (int)$p['id'] === (int)$me['id']; ?>
          <div class="person-edit">
            <form method="post" action="<?= e($formAction) ?>" class="stack" style="gap:10px" id="edit-<?= (int)$p['id'] ?>">
              <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="open" value="<?= (int)$p['id'] ?>">
              <?php if ($isAdmin): ?>
              <div class="row" style="gap:10px">
                <label class="field" style="flex:1 1 200px">Nume<input type="text" name="name" value="<?= e($p['name']) ?>" required maxlength="120"></label>
                <label class="field" style="flex:1 1 240px">Email <span class="hint">la schimbare, anunțăm ambele adrese</span><input type="email" name="email" value="<?= e($p['email']) ?>" required maxlength="190"></label>
              </div>
              <?php endif; ?>
              <div class="row">
                <strong class="grow">Rol și poziții – <?= e($p['name']) ?></strong>
                <?php if ($isAdmin && !$self): ?>
                <div class="seg" role="radiogroup" aria-label="Rol în aplicație">
                  <?php foreach (['voluntar' => 'Voluntar', 'pastor' => 'Pastor', 'editor' => 'Editor', 'admin' => 'Administrator'] as $rk => $rl): ?>
                    <label><input type="radio" name="role" value="<?= $rk ?>"<?= $p['role'] === $rk ? ' checked' : '' ?>><span><?= $rl ?></span></label>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                  <span class="badge grey"><?= e(role_label($p['role'])) ?></span>
                <?php endif; ?>
              </div>
              <label class="check leader-check"><input type="checkbox" name="is_leader" value="1"<?= !empty($p['is_leader']) ? ' checked' : '' ?>><span><strong>Lider</strong> <span class="xs muted">· poate fi ales lider la servicii și primește anunțurile „Nu pot ajunge”</span></span></label>
              <?php $selPos = $p['pos']; $selParts = $p['parts']; $small = true; include APP_DIR . '/views/partials/positions.php'; $small = false; ?>
              <p class="xs muted">Persoana își poate seta și singură pozițiile din profil. <?= $self ? 'Rolul tău îl poate schimba doar alt administrator.' : 'Rolul în aplicație îl schimbă doar un administrator.' ?></p>
            </form>
            <div class="row">
              <form method="post" action="<?= e($formAction) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="reset_link"><input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="open" value="<?= (int)$p['id'] ?>"><button class="btn ghost sm" type="submit" style="color:var(--accent)">Link resetare parolă</button></form>
              <?php if ($isAdmin && !$self): ?>
                <?php if ($p['status'] === 'active'): ?>
                  <form method="post" action="<?= e($formAction) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="disable"><input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="open" value="<?= (int)$p['id'] ?>"><button class="btn ghost danger sm" type="submit" data-confirm="<?= e($p['name']) ?> nu va mai putea intra în aplicație. Continui?">Oprește accesul</button></form>
                <?php else: ?>
                  <form method="post" action="<?= e($formAction) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="enable"><input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="open" value="<?= (int)$p['id'] ?>"><button class="btn ghost sm" type="submit">Redă accesul</button></form>
                  <form method="post" action="<?= e($formAction) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int)$p['id'] ?>"><button class="btn ghost danger sm" type="submit" data-confirm="Ștergi definitiv contul lui <?= e($p['name']) ?>? Nu se poate anula.">Șterge contul</button></form>
                <?php endif; ?>
              <?php endif; ?>
              <span class="spacer"></span>
              <button class="btn primary sm" type="submit" form="edit-<?= (int)$p['id'] ?>">Salvează</button>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
  <?php if ($testTools): ?>
  <section class="card stack test-box">
  <?php $bi = build_info(); ?>
  <div class="row"><strong class="grow small">Unelte de test · doar pe acest site de test</strong>
    <span class="xs" style="font-family:ui-monospace,Menlo,monospace;background:#EFEDFF;color:#5B3A99;border-radius:6px;padding:2px 8px" title="Versiunea care rulează acum pe server">v<?= e($bi['version']) ?> · <?= e($bi['sha']) ?><?= $bi['time'] ? ' · ' . e(ro_date($bi['time']) . ' ' . date('H:i', strtotime($bi['time']))) : '' ?></span><span class="xs muted"><?= $testCount ?> <?= $testCount === 1 ? 'cont de test' : 'conturi de test' ?></span></div>
  <?php if ($testCount): ?>
  <form method="post" action="<?= e($formAction) ?>" class="row" style="gap:10px"><?= csrf_field() ?><input type="hidden" name="action" value="test_delete_all">
    <label class="check xs"><input type="checkbox" required data-enables="test-del">Șterg toate conturile de test și le scot din planificare</label>
    <button class="btn ghost danger sm" id="test-del" type="submit" disabled>Șterge toate conturile de test</button></form>
  <?php endif; ?>
</section>
  <?php endif; ?>
</main>

<?php if ($testTools): ?>
<dialog id="dlg-test">
  <form method="post" action="<?= e($formAction) ?>" class="dlg">
    <?= csrf_field() ?><input type="hidden" name="action" value="test_create">
    <h2>Cont de test</h2>
    <p class="small muted">Activ imediat, fără email și fără aprobare. Primește o adresă ca test1@test.local, nu primește niciun email și apare cu eticheta TEST.</p>
    <div class="grid-2">
      <label class="field">Nume<input type="text" name="name" value="Test" maxlength="100" required></label>
      <label class="field">Câte conturi<input type="number" name="qty" value="1" min="1" max="20"></label>
    </div>
    <div class="grid-2">
      <label class="field">Rol<select name="role"><option value="voluntar">Voluntar</option><option value="editor">Editor</option></select></label>
      <label class="field">Parolă<input type="text" name="password" value="test1234" minlength="8" required></label>
    </div>
    <div class="stack" style="gap:6px" data-scope><?php $selPos = []; $selParts = []; $small = true; include APP_DIR . '/views/partials/positions.php'; $small = false; ?></div>
    <div class="row" style="justify-content:flex-end"><button class="btn ghost" type="button" data-close>Renunță</button><button class="btn primary" type="submit">Creează</button></div>
  </form>
</dialog>
<?php endif; ?>

<dialog id="dlg-team">
  <form method="post" action="<?= e($formAction) ?>" class="dlg">
    <?= csrf_field() ?><input type="hidden" name="action" value="team_link">
    <h2>Link nou de invitație pentru echipă</h2>
    <p class="small muted">Oricine are linkul își poate face cont ca Voluntar. Fiecare cont nou așteaptă aprobarea unui administrator.</p>
    <label class="field">Valabil
      <select name="days"><option value="7">7 zile</option><option value="14">14 zile</option><option value="30" selected>30 de zile</option><option value="90">90 de zile</option></select>
    </label>
    <div class="row" style="justify-content:flex-end"><button class="btn ghost" type="button" data-close>Renunță</button><button class="btn primary" type="submit">Creează linkul</button></div>
  </form>
</dialog>

<dialog id="dlg-personal">
  <form method="post" action="<?= e($formAction) ?>" class="dlg">
    <?= csrf_field() ?><input type="hidden" name="action" value="personal">
    <h2>Invitație personală</h2>
    <p class="small muted">Un link pentru o singură persoană, valabil 14 zile. Contul se activează direct după ce își confirmă emailul.</p>
    <label class="field">Nume<input type="text" name="name" required></label>
    <label class="field">Email <span class="hint">opțional – dacă îl scrii, trimitem invitația și pe email</span><input type="email" name="email"></label>
    <?php if ($isAdmin): ?>
      <label class="field">Rol<select name="role"><option value="voluntar">Voluntar</option><option value="pastor">Pastor</option><option value="editor">Editor</option></select></label>
    <?php endif; ?>
    <div class="row" style="justify-content:flex-end"><button class="btn ghost" type="button" data-close>Renunță</button><button class="btn primary" type="submit">Creează invitația</button></div>
  </form>
</dialog>

<?php if ($newLink): ?>
<dialog id="dlg-link" data-autoshow>
  <div class="dlg">
    <h2><?= e($newLink['title']) ?></h2>
    <div class="link-url"><?= e($newLink['url']) ?></div>
    <p class="small muted"><?= e($newLink['note']) ?></p>
    <div class="row">
      <button class="btn" type="button" data-copy="<?= e($newLink['url']) ?>">Copiază link</button>
      <a class="btn ok" href="<?= e($waLink($newLink['wa'], $newLink['phone'] ?? null)) ?>" target="_blank" rel="noopener">Trimite pe WhatsApp</a>
      <span class="spacer"></span>
      <button class="btn primary" type="button" data-close>Gata</button>
    </div>
  </div>
</dialog>
<?php endif; ?>
