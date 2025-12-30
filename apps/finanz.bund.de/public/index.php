<?php
declare(strict_types=1);

$host = $_SERVER['HTTP_HOST'] ?? '';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$isBund = str_ends_with($host, 'bund.de');
$isGvAt = str_ends_with($host, 'region.gv.at') || str_ends_with($host, 'gv.at');

if ($uri === '/set-cookie') {
    $value = 'sess_' . bin2hex(random_bytes(8));

    if ($isBund) {
        setcookie('session', $value, [
            'expires' => time() + 3600,
            'path' => '/',
            'domain' => '.bund.de',
            'secure' => true,
            'httponly' => false,
            'samesite' => 'None',
        ]);
    } elseif ($isGvAt) {
        setcookie('__Host-session', $value, [
            'expires' => time() + 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => false,
            'samesite' => 'None',
        ]);
    } else {
        setcookie('session', $value, [
            'expires' => time() + 3600,
            'path' => '/',
        ]);
    }

    header('Location: /');
    exit;
}

header('Content-Type: text/html; charset=utf-8');

$forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$scheme = ($forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';
$meldeamtHost = $isBund ? 'meldeamt.bund.de' : ($isGvAt ? 'meldeamt.region.gv.at' : '');
$meldeamtBase = $meldeamtHost !== '' ? ($scheme . '://' . $meldeamtHost) : '';

echo '<!doctype html><html><head><meta charset="utf-8"><title>finanz</title></head><body>';
echo '<h1>finanz service</h1>';
echo '<p><strong>Host:</strong> ' . h($host) . '</p>';

echo '<h2>1) Cookie leakage / fixation demo</h2>';
if ($isBund) {
    echo '<p>This host is in <code>bund.de</code>. Clicking below sets a cookie with <code>Domain=.bund.de</code> so other subdomains (like <code>meldeamt.bund.de</code>) will receive it.</p>';
} elseif ($isGvAt) {
    echo '<p>This host is in <code>gv.at</code>. Clicking below sets a host-only <code>__Host-</code> cookie (no Domain attribute). Other subdomains should not receive it.</p>';
} else {
    echo '<p>Unknown domain type for this demo.</p>';
}
echo '<p><a href="/set-cookie">Set demo cookie</a></p>';

echo '<h3>Cookies currently visible on this host</h3>';
echo '<pre>' . h((string)($_SERVER['HTTP_COOKIE'] ?? '')) . '</pre>';

if ($meldeamtBase !== '') {
    echo '<p>Now visit the meldeamt service to see what cookies it receives: <a href="' . h($meldeamtBase) . '">' . h($meldeamtBase) . '</a></p>';
}

echo '</body></html>';
