<?php
declare(strict_types=1);

/**
 * Trimite un email text + HTML simplu. Folosește SMTP din config (noreply@…),
 * altfel mail() din PHP. Erorile se scriu în storage/logs/mail.log.
 */
function send_mail(string $to, string $subject, string $text, ?array $button = null): bool {
    if (str_ends_with(strtolower($to), '@test.local')) return true; // conturi de test: nu se trimite nimic
    $appName = app_name();
    $from = (string)cfg('mail_from', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $html = mail_html($subject, $text, $button);
    $boundary = 'b1_' . bin2hex(random_bytes(8));
    $headers = [
        'From' => mime_header($appName) . " <$from>",
        'To' => $to,
        'Subject' => mime_header($subject),
        'Date' => date('r'),
        'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => "multipart/alternative; boundary=\"$boundary\"",
    ];
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";

    if (defined('MAIL_TEST_SINK')) { // folosit doar de testele automate
        $GLOBALS['MAIL_SENT'][] = compact('to', 'subject', 'text', 'button');
        return true;
    }

    $smtp = cfg('smtp');
    try {
        if ($smtp && !empty($smtp['host'])) {
            smtp_send($smtp, $from, $to, $headers, $body);
        } else {
            $h = $headers;
            unset($h['To'], $h['Subject']);
            $hs = '';
            foreach ($h as $k => $v) $hs .= "$k: $v\r\n";
            if (!mail($to, mime_header($subject), $body, $hs, '-f' . $from)) throw new RuntimeException('mail() a eșuat');
        }
        return true;
    } catch (Throwable $e) {
        log_error('mail', "Către $to ($subject): " . $e->getMessage());
        return false;
    }
}

function mime_header(string $s): string {
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

function mail_html(string $title, string $text, ?array $button): string {
    $paras = '';
    foreach (preg_split("/\n{2,}/", trim($text)) as $p) {
        if ($button && trim($p) === $button[1]) continue;
        $paras .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.5;color:#3F4652">' . nl2br(e($p)) . '</p>';
    }
    $btn = $button ? '<p style="margin:6px 0 18px"><a href="' . e($button[1]) . '" style="display:inline-block;background:#650B0A;color:#fff;text-decoration:none;font-weight:600;padding:12px 18px;border-radius:10px">' . e($button[0]) . '</a></p>'
        . '<p style="margin:0 0 14px;font-size:12px;color:#8A919E;word-break:break-all">' . e($button[1]) . '</p>' : '';
    return '<!doctype html><html><body style="margin:0;background:#F6F7F9;font-family:Inter,Segoe UI,Arial,sans-serif">'
        . '<div style="max-width:520px;margin:0 auto;padding:24px"><div style="background:#fff;border:1px solid #E3E6EB;border-radius:14px;padding:24px">'
        . '<h1 style="margin:0 0 16px;font-size:20px;color:#14171F">' . e($title) . '</h1>' . $paras . $btn
        . '</div><p style="font-size:12px;color:#8A919E;text-align:center;margin:14px 0 0">' . e(app_name()) . '</p></div></body></html>';
}

function smtp_send(array $c, string $from, string $to, array $headers, string $body): void {
    $host = $c['host'];
    $port = (int)($c['port'] ?? 465);
    $secure = $c['secure'] ?? ($port === 465 ? 'ssl' : 'tls');
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("Nu mă pot conecta la $remote: $errstr ($errno)");
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $line, array $ok) use ($fp, $read): string {
        fwrite($fp, $line . "\r\n");
        $resp = $read();
        if (!in_array((int)substr($resp, 0, 3), $ok, true)) {
            $shown = str_starts_with($line, 'AUTH') || strlen($line) > 60 ? '[…]' : $line;
            throw new RuntimeException("SMTP $shown → " . trim($resp));
        }
        return $resp;
    };

    $greet = $read();
    if ((int)substr($greet, 0, 3) !== 220) throw new RuntimeException('SMTP salut: ' . trim($greet));
    $ehloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $cmd("EHLO $ehloHost", [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('STARTTLS a eșuat');
        $cmd("EHLO $ehloHost", [250]);
    }
    if (!empty($c['user'])) {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['user']), [334]);
        $cmd(base64_encode((string)$c['pass']), [235]);
    }
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);
    $msg = '';
    foreach ($headers as $k => $v) $msg .= "$k: $v\r\n";
    $msg .= "\r\n" . $body;
    $msg = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $msg));
    $cmd($msg . "\r\n.", [250]);
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
}
