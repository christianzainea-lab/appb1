<?php
declare(strict_types=1);

function people_index(): void {
    $me = require_role('editor');
    $isAdmin = $me['role'] === 'admin';

    if (is_post()) {
        csrf_check();
        people_action($me, post('action'));
        $back = 'setari/persoane';
        $keep = array_filter(['q' => $_GET['q'] ?? '', 'poz' => $_GET['poz'] ?? '', 'rol' => $_GET['rol'] ?? '']);
        if (!empty($_POST['open'])) $keep['deschis'] = (int)$_POST['open'];
        redirect($back . ($keep ? '?' . http_build_query($keep) : ''));
    }

    $search = trim((string)($_GET['q'] ?? ''));
    $posFilter = (int)($_GET['poz'] ?? 0);
    $roleFilter = (string)($_GET['rol'] ?? '');

    $sql = "SELECT * FROM users WHERE status IN ('active', 'disabled')";
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    if (in_array($roleFilter, ['admin', 'editor', 'voluntar'], true)) { $sql .= ' AND role = ?'; $params[] = $roleFilter; }
    if ($posFilter) { $sql .= ' AND id IN (SELECT user_id FROM user_positions WHERE position_id = ?)'; $params[] = $posFilter; }
    $sql .= ' ORDER BY status, name';
    $people = rows($sql, $params);
    foreach ($people as &$p) {
        $p['labels'] = user_position_labels((int)$p['id']);
        $p['pos'] = user_position_ids((int)$p['id']);
        $p['parts'] = user_part_ids((int)$p['id']);
    }
    unset($p);

    $requests = rows("SELECT * FROM users WHERE status IN ('pending', 'unverified') ORDER BY status DESC, created_at");
    foreach ($requests as &$r) {
        $r['labels'] = user_position_labels((int)$r['id']);
        $r['invite'] = $r['invite_id'] ? row('SELECT kind, name FROM invites WHERE id = ?', [$r['invite_id']]) : null;
    }
    unset($r);

    $invites = rows('SELECT i.*, u.name AS creator FROM invites i LEFT JOIN users u ON u.id = i.created_by WHERE i.active = 1 ORDER BY i.kind DESC, i.created_at DESC');
    $invites = array_values(array_filter($invites, 'invite_valid'));

    $counts = ['all' => 0, 'admin' => 0, 'editor' => 0, 'voluntar' => 0];
    foreach (rows("SELECT role, COUNT(*) AS n FROM users WHERE status = 'active' GROUP BY role") as $c) {
        $counts[$c['role']] = (int)$c['n'];
        $counts['all'] += (int)$c['n'];
    }

    render('settings/people', [
        'nav' => 'setari', 'sub' => 'persoane', 'me' => $me, 'isAdmin' => $isAdmin,
        'people' => $people, 'requests' => $requests, 'invites' => $invites, 'counts' => $counts,
        'tree' => position_tree(), 'search' => $search, 'posFilter' => $posFilter, 'roleFilter' => $roleFilter,
        'open' => (int)($_GET['deschis'] ?? 0), 'newLink' => $_SESSION['new_link'] ?? null,
        'testTools' => $isAdmin && test_tools_enabled(), 'testCount' => (int)val('SELECT COUNT(*) FROM users WHERE is_test = 1'),
    ]);
    unset($_SESSION['new_link']);
}

function people_action(array $me, string $action): void {
    $isAdmin = $me['role'] === 'admin';
    $uid = (int)($_POST['user_id'] ?? 0);
    $target = $uid ? row('SELECT * FROM users WHERE id = ?', [$uid]) : null;

    switch ($action) {
        case 'approve':
            if (!$isAdmin || !$target || !in_array($target['status'], ['pending', 'unverified'], true)) return;
            $role = in_array(post('role'), ['editor', 'pastor'], true) ? post('role') : 'voluntar';
            q('UPDATE users SET status = ?, role = ?, approved_by = ?, approved_at = ?, email_verified_at = COALESCE(email_verified_at, ?), updated_at = ? WHERE id = ?',
                ['active', $role, $me['id'], now(), now(), now(), $uid]);
            $link = full_url('');
            send_mail($target['email'], 'Contul tău a fost aprobat',
                "Salut, " . first_name($target['name']) . "!\n\nContul tău în " . app_name() . " a fost aprobat. Acum îți poți vedea programul și cântările.\n\n$link",
                ['Deschide aplicația', $link]);
            log_activity('user', $uid, $target['name'], 'approved', 'A aprobat contul (' . $target['email'] . ') ca ' . role_label($role) . '.');
            flash($target['name'] . ' a fost aprobat ca ' . role_label($role) . '.');
            return;

        case 'reject':
            if (!$isAdmin || !$target || !in_array($target['status'], ['pending', 'unverified'], true)) return;
            delete_user((int)$target['id']);
            log_activity('user', $uid, $target['name'], 'rejected', 'A respins și a șters cererea de înscriere (' . $target['email'] . ').');
            flash('Cererea lui ' . $target['name'] . ' a fost respinsă și ștearsă.');
            return;

        case 'resend_verify':
            if (!$target || $target['status'] !== 'unverified') return;
            flash(send_verification($target) ? 'Am retrimis emailul de confirmare către ' . $target['email'] . '.' : 'Emailul nu a putut fi trimis. Verifică setările de email.', 'ok');
            return;

        case 'update':
            if (!$target || !in_array($target['status'], ['active', 'disabled'], true)) return;
            // editorii lucrează doar cu voluntari; editorii, pastorii și adminii îi modifică doar un administrator
            if ($target['role'] !== 'voluntar' && !$isAdmin) { flash('Doar un administrator poate modifica un ' . mb_strtolower(role_label($target['role'])) . '.', 'error'); return; }
            $role = post('role');
            $allowed = $isAdmin ? ['voluntar', 'pastor', 'editor', 'admin'] : [];
            if (in_array($role, $allowed, true) && $role !== $target['role']) {
                if ((int)$target['id'] === (int)$me['id']) {
                    flash('Nu îți poți schimba singur rolul. Roagă alt administrator.', 'error');
                } elseif ($target['role'] === 'admin' && (int)val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'") <= 1) {
                    flash('Trebuie să rămână cel puțin un administrator.', 'error');
                } else {
                    q('UPDATE users SET role = ?, updated_at = ? WHERE id = ?', [$role, now(), $uid]);
                    log_activity('user', $uid, $target['name'], 'role', 'Rol: ' . role_label($target['role']) . ' → ' . role_label($role) . '.');
                }
            }
            // numele și emailul: doar adminii
            if ($isAdmin && isset($_POST['name'], $_POST['email'])) {
                $newName = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$_POST['name'])), 0, 120);
                $newEmail = mb_strtolower(trim((string)$_POST['email']));
                if ($newName === '') { flash('Numele nu poate fi gol.', 'error'); return; }
                if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) { flash('Adresa de email nu pare corectă.', 'error'); return; }
                if ($newEmail !== mb_strtolower($target['email']) && val('SELECT COUNT(*) FROM users WHERE LOWER(email) = ? AND id <> ?', [$newEmail, $uid])) {
                    flash('Adresa ' . $newEmail . ' este deja folosită de alt cont.', 'error'); return;
                }
                if ($newName !== $target['name']) {
                    q('UPDATE users SET name = ?, updated_at = ? WHERE id = ?', [$newName, now(), $uid]);
                    log_activity('user', $uid, $newName, 'name', 'Nume: ' . $target['name'] . ' → ' . $newName . '.');
                }
                if ($newEmail !== mb_strtolower($target['email'])) {
                    $old = $target['email'];
                    q('UPDATE users SET email = ?, updated_at = ? WHERE id = ?', [$newEmail, now(), $uid]);
                    log_activity('user', $uid, $newName, 'email', 'Email: ' . $old . ' → ' . $newEmail . '.');
                    $body = 'Salut, ' . first_name($newName) . "!\n\nUn administrator (" . $me['name'] . ") a schimbat adresa de email a contului tău din " . app_name() . ".\n\nAdresa veche: $old\nAdresa nouă: $newEmail\n\nDe acum intri în aplicație cu adresa nouă. Parola rămâne aceeași.\nDacă nu știai de această schimbare, anunță un administrator.";
                    send_mail($old, 'Adresa ta de email a fost schimbată', $body);
                    send_mail($newEmail, 'Adresa ta de email a fost schimbată', $body, ['Intră în aplicație', full_url('login')]);
                    flash('Emailul a fost schimbat. Am trimis o notificare la ambele adrese.');
                }
                $target['name'] = $newName;
            }
            $lead = empty($_POST['is_leader']) ? 0 : 1;
            if ($lead !== (int)($target['is_leader'] ?? 0)) {
                q('UPDATE users SET is_leader = ? WHERE id = ?', [$lead, $uid]);
                log_activity('user', $uid, $target['name'], 'leader', $lead ? 'A primit eticheta „Lider”.' : 'Nu mai are eticheta „Lider”.');
            }
            $posBefore = user_position_labels($uid);
            save_user_positions($uid, (array)($_POST['positions'] ?? []), (array)($_POST['parts'] ?? []));
            log_positions_change($uid, $target['name'], $posBefore);
            flash('Modificările pentru ' . $target['name'] . ' au fost salvate.');
            return;

        case 'disable':
        case 'enable':
            if (!$isAdmin || !$target || (int)$target['id'] === (int)$me['id']) return;
            if ($action === 'disable' && $target['role'] === 'admin' && (int)val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'") <= 1) return;
            q('UPDATE users SET status = ?, updated_at = ? WHERE id = ?', [$action === 'disable' ? 'disabled' : 'active', now(), $uid]);
            if ($action === 'disable') q('DELETE FROM remember_tokens WHERE user_id = ?', [$uid]);
            log_activity('user', $uid, $target['name'], $action === 'disable' ? 'disabled' : 'enabled', $action === 'disable' ? 'A oprit accesul contului.' : 'A redat accesul contului.');
            flash($target['name'] . ($action === 'disable' ? ' nu mai are acces.' : ' are din nou acces.'));
            return;

        case 'delete':
            if (!$isAdmin || !$target || (int)$target['id'] === (int)$me['id'] || $target['status'] !== 'disabled') return;
            delete_user((int)$target['id']);
            log_activity('user', $uid, $target['name'], 'deleted', 'A șters contul (' . $target['email'] . ').');
            flash('Contul lui ' . $target['name'] . ' a fost șters.');
            return;

        case 'reset_link':
            if (!$target || ($target['role'] !== 'voluntar' && !$isAdmin)) return;
            $token = issue_user_token((int)$target['id'], 'reset', 48);
            $link = full_url('parola-noua/' . $token);
            log_activity('user', $uid, $target['name'], 'reset_link', 'A generat un link de resetare a parolei.');
            send_mail($target['email'], 'Setează o parolă nouă',
                "Salut, " . first_name($target['name']) . "!\n\nPoți seta o parolă nouă pentru contul tău aici:\n\n$link\n\nLinkul este valabil 2 zile.",
                ['Alege parola nouă', $link]);
            $_SESSION['new_link'] = ['title' => 'Link de resetare pentru ' . $target['name'], 'url' => $link, 'note' => 'L-am trimis și pe email. Este valabil 2 zile și se poate folosi o singură dată.',
                'wa' => 'Salut, ' . first_name($target['name']) . '! Aici îți poți seta o parolă nouă: ' . $link, 'phone' => $target['phone']];
            return;

        case 'team_link':
            $days = max(1, min(365, (int)post('days', '30')));
            $token = random_token(12);
            q('INSERT INTO invites (token, kind, role, expires_at, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$token, 'team', 'voluntar', date('Y-m-d 23:59:59', strtotime("+$days days")), $me['id'], now()]);
            $link = full_url('inscriere/' . $token);
            log_activity('settings', null, 'Link de echipă', 'invite', "A creat un link de invitație pentru echipă, valabil $days zile.");
            $_SESSION['new_link'] = ['title' => 'Link nou pentru echipă', 'url' => $link, 'note' => 'Oricine are linkul se poate înscrie până pe ' . ro_date(date('Y-m-d', strtotime("+$days days")), true) . '. Fiecare cont nou așteaptă aprobarea unui administrator.',
                'wa' => 'Înscrie-te în ' . app_name() . ' aici: ' . $link];
            return;

        case 'personal':
            $name = post('name');
            $email = mb_strtolower(post('email'));
            $role = ($isAdmin && in_array(post('role'), ['editor', 'pastor'], true)) ? post('role') : 'voluntar';
            if (mb_strlen($name) < 3) { flash('Scrie numele persoanei invitate.', 'error'); return; }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('Adresa de email nu pare corectă.', 'error'); return; }
            if ($email !== '' && val('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) { flash('Există deja un cont cu acest email.', 'error'); return; }
            $token = random_token(12);
            q('INSERT INTO invites (token, kind, role, name, email, expires_at, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$token, 'personal', $role, $name, $email ?: null, date('Y-m-d 23:59:59', strtotime('+14 days')), $me['id'], now()]);
            $link = full_url('inscriere/' . $token);
            log_activity('settings', null, $name, 'invite', 'A creat o invitație personală pentru ' . $name . ($email !== '' ? ' (' . $email . ')' : '') . ' ca ' . role_label($role) . '.');
            if ($email !== '') {
                send_mail($email, 'Invitație în ' . app_name(),
                    "Salut, " . first_name($name) . "!\n\n" . first_name($me['name']) . " te invită în echipă. Creează-ți contul aici:\n\n$link\n\nLinkul este doar pentru tine și este valabil 14 zile.",
                    ['Creează contul', $link]);
            }
            $_SESSION['new_link'] = ['title' => 'Invitație pentru ' . $name, 'url' => $link,
                'note' => ($email !== '' ? 'Am trimis-o și pe email. ' : '') . 'Se poate folosi o singură dată, timp de 14 zile. Contul se activează direct, ca ' . role_label($role) . ', după confirmarea emailului.',
                'wa' => 'Salut, ' . first_name($name) . '! Creează-ți contul în ' . app_name() . ' aici: ' . $link];
            return;

        case 'test_create':
            if (!$isAdmin || !test_tools_enabled()) return;
            $name = mb_substr(post('name'), 0, 100) ?: 'Test';
            $qty = max(1, min(20, (int)post('qty', '1')));
            $role = post('role') === 'editor' ? 'editor' : 'voluntar';
            $pass = (string)($_POST['password'] ?? '');
            if (mb_strlen($pass) < 8) { flash('Parola contului de test trebuie să aibă cel puțin 8 caractere.', 'error'); return; }
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $made = [];
            for ($i = 1; $i <= $qty; $i++) {
                $n = (int)val('SELECT COUNT(*) FROM users WHERE is_test = 1') + 1;
                while (val('SELECT COUNT(*) FROM users WHERE email = ?', ["test$n@test.local"])) $n++;
                $full = $qty > 1 ? "$name $i" : $name;
                q('INSERT INTO users (name, email, password_hash, role, status, email_verified_at, calendar_token, approved_by, approved_at, is_test, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
                    [$full, "test$n@test.local", $hash, $role, 'active', now(), random_token(24), $me['id'], now(), now(), now()]);
                $nid = (int)db()->lastInsertId();
                save_user_positions($nid, (array)($_POST['positions'] ?? []), (array)($_POST['parts'] ?? []));
                $made[] = "test$n@test.local";
                log_activity('user', $nid, $full, 'test_created', 'A creat contul de test ' . "test$n@test.local" . ' (' . role_label($role) . ').');
            }
            flash((count($made) === 1 ? 'Cont de test creat: ' : count($made) . ' conturi de test create: ') . implode(', ', $made) . '. Se intră cu parola aleasă.');
            return;

        case 'test_delete_all':
            if (!$isAdmin || !test_tools_enabled()) return;
            $ids = array_map('intval', q('SELECT id FROM users WHERE is_test = 1')->fetchAll(PDO::FETCH_COLUMN));
            foreach ($ids as $tid) {
                q('UPDATE event_slots SET user_id = NULL, response = NULL, response_note = NULL, responded_at = NULL, notified_at = NULL WHERE user_id = ?', [$tid]);
                q('DELETE FROM blockouts WHERE user_id = ?', [$tid]);
                delete_user($tid);
            }
            log_activity('user', null, 'Conturi de test', 'test_deleted', 'A șters toate cele ' . count($ids) . ' conturi de test.');
            flash(count($ids) . ' conturi de test au fost șterse; locurile lor din planificare sunt din nou libere.');
            return;

        case 'disable_invite':
            q('UPDATE invites SET active = 0 WHERE id = ?', [(int)post('invite_id')]);
            $inv = row('SELECT kind, name FROM invites WHERE id = ?', [(int)post('invite_id')]);
            if ($inv) log_activity('settings', null, $inv['name'] ?: 'Link de echipă', 'invite_off', 'A dezactivat ' . ($inv['kind'] === 'team' ? 'linkul de echipă' : 'invitația pentru ' . $inv['name']) . '.');
            flash('Linkul a fost dezactivat.');
            return;
    }
}

function delete_user(int $id): void {
    $u = row('SELECT avatar FROM users WHERE id = ?', [$id]);
    if ($u && $u['avatar']) @unlink(STORAGE_DIR . '/avatars/' . $u['avatar']);
    foreach (['user_positions', 'user_parts', 'user_tokens', 'remember_tokens'] as $t) q("DELETE FROM $t WHERE user_id = ?", [$id]);
    q('DELETE FROM users WHERE id = ?', [$id]);
}
