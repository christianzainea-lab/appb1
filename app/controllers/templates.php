<?php
declare(strict_types=1);

function settings_templates(): void {
    require_role('editor');
    $tpls = rows('SELECT * FROM templates ORDER BY active DESC, sort, id');
    foreach ($tpls as &$t) {
        $t['slots'] = (int)val('SELECT COALESCE(SUM(slots), 0) FROM template_positions WHERE template_id = ?', [$t['id']]);
        $t['items'] = (int)val('SELECT COUNT(*) FROM template_items WHERE template_id = ?', [$t['id']]);
        $t['songs'] = (int)val("SELECT COUNT(*) FROM template_items WHERE template_id = ? AND kind = 'song'", [$t['id']]);
    }
    unset($t);
    render('settings/templates', ['nav' => 'setari', 'sub' => 'sabloane', 'tpls' => $tpls]);
}

function template_edit(string $id): void {
    require_role('editor');
    $isNew = $id === 'nou';
    $t = $isNew ? null : row('SELECT * FROM templates WHERE id = ?', [(int)$id]);
    if (!$isNew && !$t) not_found();
    $errors = [];
    if (is_post()) {
        csrf_check();
        if (post('action') === 'delete' && $t) {
            if (empty($_POST['confirm'])) { flash('Bifează confirmarea ca să ștergi șablonul.', 'error'); redirect('setari/sabloane/' . $t['id']); }
            q('DELETE FROM template_positions WHERE template_id = ?', [$t['id']]);
            q('DELETE FROM template_items WHERE template_id = ?', [$t['id']]);
            q('DELETE FROM templates WHERE id = ?', [$t['id']]);
            log_activity('settings', (int)$t['id'], $t['name'], 'template_deleted', 'A șters șablonul „' . $t['name'] . '”.');
            q('UPDATE events SET template_id = NULL WHERE template_id = ? AND status <> ?', [$t['id'], 'cancelled']);
            q("DELETE FROM events WHERE template_id = ? AND status = 'cancelled'", [$t['id']]);
            flash('Șablonul „' . $t['name'] . '” a fost șters. Evenimentele deja create rămân.');
            redirect('setari/sabloane');
        }
        $name = mb_substr(post('name'), 0, 120);
        $wd = post('weekday');
        $rule = post('rule');
        $time = post('start_time');
        $reh = post('rehearsal_time');
        if (mb_strlen($name) < 2) $errors['name'] = 'Scrie numele șablonului.';
        if (!valid_time($time)) $errors['start_time'] = 'Alege ora.';
        if ($reh !== '' && !valid_time($reh)) $errors['rehearsal_time'] = 'Ora repetiției nu e corectă.';
        if (!isset(RULES[$rule])) $rule = 'every';
        $weekday = ($rule === 'none' || $wd === '') ? null : max(0, min(6, (int)$wd));
        if ($weekday === null) $rule = 'none';
        if (!$errors) {
            $active = empty($_POST['active']) ? 0 : 1;
            $leaderId = (int)post('leader_id');
            if ($leaderId && $leaderId !== (int)($t['leader_id'] ?? 0) && !val("SELECT COUNT(*) FROM users WHERE id = ? AND status = 'active' AND is_leader = 1", [$leaderId])) $leaderId = 0;
            if ($t) {
                q('UPDATE templates SET name = ?, weekday = ?, rule = ?, start_time = ?, rehearsal_time = ?, active = ? WHERE id = ?', [$name, $weekday, $rule, $time, $reh ?: null, $active, $t['id']]);
                $tid = (int)$t['id'];
                q('UPDATE templates SET leader_id = ? WHERE id = ?', [$leaderId ?: null, $tid]);
            } else {
                q('INSERT INTO templates (name, weekday, rule, start_time, rehearsal_time, sort, active) VALUES (?, ?, ?, ?, ?, ?, ?)', [$name, $weekday, $rule, $time, $reh ?: null, (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM templates'), $active]);
                $tid = (int)db()->lastInsertId();
                q('UPDATE templates SET leader_id = ? WHERE id = ?', [$leaderId ?: null, $tid]);
            }
            // poziții: număr de locuri pe fiecare
            q('DELETE FROM template_positions WHERE template_id = ?', [$tid]);
            foreach ((array)($_POST['slots'] ?? []) as $pid => $n) {
                $n = max(0, min(10, (int)$n));
                if ($n > 0 && val('SELECT COUNT(*) FROM positions WHERE id = ?', [(int)$pid])) {
                    q('INSERT INTO template_positions (template_id, position_id, slots) VALUES (?, ?, ?)', [$tid, (int)$pid, $n]);
                }
            }
            // ordinea implicită
            q('DELETE FROM template_items WHERE template_id = ?', [$tid]);
            $titles = (array)($_POST['item_title'] ?? []);
            $kinds = (array)($_POST['item_kind'] ?? []);
            $sort = 0;
            foreach ($titles as $i => $title) {
                $title = mb_substr(trim((string)$title), 0, 120);
                $kind = ($kinds[$i] ?? 'item') === 'song' ? 'song' : 'item';
                if ($title === '' && $kind === 'song') $title = 'Cântare';
                if ($title === '') continue;
                q('INSERT INTO template_items (template_id, sort, kind, title) VALUES (?, ?, ?, ?)', [$tid, $sort++, $kind, $title]);
            }
            $updated = !empty($_POST['apply_drafts']) ? apply_template_to_drafts($tid) : 0;
            log_activity('settings', $tid, $name, $t ? 'template_updated' : 'template_created', ($t ? 'A salvat șablonul „' : 'A creat șablonul „') . $name . '” (' . template_when(['weekday' => $weekday, 'rule' => $rule]) . ', ' . $time . ', ' . (int)val('SELECT COALESCE(SUM(slots), 0) FROM template_positions WHERE template_id = ?', [$tid]) . ' locuri)' . ($updated ? ", actualizate $updated ciorne" : '') . '.');
            flash('Șablonul a fost salvat.' . ($updated ? " Am actualizat $updated " . ($updated === 1 ? 'ciornă viitoare.' : 'ciorne viitoare.') : ''));
            redirect('setari/sabloane/' . $tid);
        }
    }
    $slots = [];
    if ($t) foreach (rows('SELECT * FROM template_positions WHERE template_id = ?', [$t['id']]) as $r) $slots[(int)$r['position_id']] = (int)$r['slots'];
    $items = $t ? rows('SELECT * FROM template_items WHERE template_id = ? ORDER BY sort, id', [$t['id']]) : [['kind' => 'item', 'title' => 'Rugăciune de început'], ['kind' => 'song', 'title' => 'Cântare'], ['kind' => 'item', 'title' => 'Predică'], ['kind' => 'item', 'title' => 'Rugăciune de încheiere']];
    $drafts = $t ? (int)val("SELECT COUNT(*) FROM events WHERE template_id = ? AND status = 'draft' AND event_date >= ?", [$t['id'], date('Y-m-d')]) : 0;
    render('settings/template_edit', ['nav' => 'setari', 'sub' => 'sabloane', 't' => $t, 'tree' => position_tree(), 'slots' => $slots, 'items' => $items, 'errors' => $errors, 'drafts' => $drafts,
        'people' => rows("SELECT id, name FROM users WHERE status = 'active' AND (is_leader = 1 OR id = ?) ORDER BY name", [(int)($t['leader_id'] ?? 0)])]);
}

/** Potrivește locurile din ciornele viitoare cu șablonul: adaugă ce lipsește, scoate locurile goale în plus. */
function apply_template_to_drafts(int $tid): int {
    $want = [];
    foreach (rows('SELECT * FROM template_positions WHERE template_id = ?', [$tid]) as $r) $want[(int)$r['position_id']] = (int)$r['slots'];
    $t = row('SELECT * FROM templates WHERE id = ?', [$tid]);
    $events = rows("SELECT * FROM events WHERE template_id = ? AND status = 'draft' AND event_date >= ?", [$tid, date('Y-m-d')]);
    foreach ($events as $e) {
        q('UPDATE events SET name = ?, start_time = ?, rehearsal_time = ? WHERE id = ?', [$t['name'], $t['start_time'], $t['rehearsal_time'], $e['id']]);
        $have = [];
        foreach (rows('SELECT * FROM event_slots WHERE event_id = ? ORDER BY user_id IS NULL, sort', [$e['id']]) as $s) $have[(int)$s['position_id']][] = $s;
        foreach ($have as $pid => $list) {
            if ($pid === leader_position_id()) continue; // locul de lider nu vine din șablon
            $keep = $want[$pid] ?? 0;
            $filled = count(array_filter($list, fn($s) => $s['user_id']));
            $keep = max($keep, $filled); // nu scoatem pe nimeni deja ales
            foreach (array_slice(array_values(array_filter($list, fn($s) => !$s['user_id'])), max(0, $keep - $filled)) as $s) q('DELETE FROM event_slots WHERE id = ?', [$s['id']]);
        }
        $sort = (int)val('SELECT COALESCE(MAX(sort), 0) + 1 FROM event_slots WHERE event_id = ?', [$e['id']]);
        foreach ($want as $pid => $n) {
            for ($i = count($have[$pid] ?? []); $i < $n; $i++) q('INSERT INTO event_slots (event_id, position_id, sort) VALUES (?, ?, ?)', [$e['id'], $pid, $sort++]);
        }
        // liderul implicit pe locul de lider rămas liber
        if (!empty($t['leader_id']) && ($lp = leader_position_id())) {
            q('UPDATE event_slots SET user_id = ?, assigned_at = ? WHERE event_id = ? AND position_id = ? AND user_id IS NULL', [$t['leader_id'], now(), $e['id'], $lp]);
        }
        // ordinea serviciului: se înlocuiește doar dacă nu s-a ales încă nimic în ea
        if (!val('SELECT COUNT(*) FROM event_items WHERE event_id = ? AND (song_id IS NOT NULL OR note IS NOT NULL)', [$e['id']]) && !val('SELECT COUNT(*) FROM event_files WHERE event_id = ?', [$e['id']])) {
            q('DELETE FROM event_items WHERE event_id = ?', [$e['id']]);
            copy_template_items($tid, (int)$e['id']);
        }
    }
    return count($events);
}
