<?php
/**
 * Runs one request through PHP's CGI SAPI and prints a machine-readable
 * transcript. Used by qa/run-tests.php so the real front controllers, sessions,
 * status codes and security headers are exercised without a web server.
 *
 *   php qa/req.php <METHOD> <path> <json-body> <session-id> [csrf-token]
 */
declare(strict_types=1);

[$script, $method, $path, $json, $sid] = array_pad($argv, 5, '');
$method = strtoupper($method ?: 'GET');
$path   = ltrim((string) $path, '/');
$body   = json_decode((string) $json, true);
$body   = is_array($body) ? $body : [];
$csrf   = (string) ($argv[5] ?? '');

// Windows intermittently fails to create the session mutex when the system temp
// folder is shared by many short-lived CGI processes ("Cannot create mutex").
// Every QA run gets its own session directory instead.
$sessionDir = __DIR__ . '/../storage/cache/qa-sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0775, true);
}
if (is_dir($sessionDir)) {
    ini_set('session.save_path', realpath($sessionDir) ?: $sessionDir);
}

$public = realpath(__DIR__ . '/../public_html');
$parts  = explode('?', $path, 2);
$file   = rtrim($parts[0], '/');
if ($file === '') {
    $file = 'index.php';
}
$query = $parts[1] ?? '';
$target = $public . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);

if (!is_file($target)) {
    echo "<<<STATUS:404>>>\n<<<HEADERS-END>>>\nNot found: $file\n";
    exit(0);
}

$phpDir = dirname(PHP_BINARY);
$cgi = $phpDir . DIRECTORY_SEPARATOR . 'php-cgi.exe';
if (!is_file($cgi)) {
    $cgi = $phpDir . DIRECTORY_SEPARATOR . 'php-cgi';
}

$stdin = $method === 'GET' ? '' : http_build_query($body);

$env = [
    'REDIRECT_STATUS'      => '1',
    'GATEWAY_INTERFACE'    => 'CGI/1.1',
    'SERVER_SOFTWARE'      => 'qa-harness',
    'SERVER_PROTOCOL'      => 'HTTP/1.1',
    'SERVER_NAME'          => '127.0.0.1',
    'SERVER_PORT'          => '8081',
    'REMOTE_ADDR'          => '127.0.0.1',
    'HTTP_HOST'            => '127.0.0.1:8081',
    'DOCUMENT_ROOT'        => $public,
    'SCRIPT_FILENAME'      => $target,
    'SCRIPT_NAME'          => '/' . $file,
    'REQUEST_URI'          => '/' . $path,
    'QUERY_STRING'         => $query,
    'REQUEST_METHOD'       => $method,
    'HTTP_ACCEPT'          => 'text/html,application/json',
    'HTTP_USER_AGENT'      => 'qa-harness/1.0',
    'HTTP_REFERER'         => 'http://127.0.0.1:8081/',
    'HTTP_COOKIE'          => 'quran_session=' . rawurlencode((string) $sid),
    'SYSTEMROOT'           => (string) getenv('SYSTEMROOT'),
    'TEMP'                 => (string) getenv('TEMP'),
    'TMP'                  => (string) getenv('TMP'),
    'PATH'                 => (string) getenv('PATH'),
];
if ($method !== 'GET') {
    $env['CONTENT_TYPE']   = 'application/x-www-form-urlencoded';
    $env['CONTENT_LENGTH'] = (string) strlen($stdin);
}
if ($csrf !== '') {
    $env['HTTP_X_CSRF_TOKEN'] = $csrf;
}

$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open([$cgi], $descriptors, $pipes, $public, $env);
if (!is_resource($proc)) {
    echo "<<<STATUS:500>>>\n<<<HEADERS-END>>>\nproc_open failed\n";
    exit(0);
}
fwrite($pipes[0], $stdin);
fclose($pipes[0]);
$raw = (string) stream_get_contents($pipes[1]);
$err = (string) stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($proc);

$split = preg_split("/\r?\n\r?\n/", $raw, 2);
$head  = $split[0] ?? '';
$out   = $split[1] ?? '';
$status = 200;

$lines = preg_split("/\r?\n/", $head) ?: [];
$sidOut = '';
foreach ($lines as $line) {
    if (preg_match('/^Status:\s*(\d{3})/i', $line, $m)) {
        $status = (int) $m[1];
        continue;
    }
    if (stripos($line, 'HTTP/1.1 ') === 0) {
        $status = (int) substr($line, 9, 3);
        continue;
    }
    if (preg_match('/^Set-Cookie:\s*quran_session=([^;]+)/i', $line, $m)) {
        $sidOut = urldecode($m[1]);
    }
    if ($line !== '') {
        echo "<<<H:", str_replace("\n", ' ', $line), ">>>\n";
    }
}

if ($out === '' && $err !== '') {
    $out = $err;
}
echo "<<<STATUS:", $status, ">>>\n";
if ($sidOut !== '') {
    echo "<<<SID:", $sidOut, ">>>\n";
}
echo "<<<HEADERS-END>>>\n";
echo $out;
