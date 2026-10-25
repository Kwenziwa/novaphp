<?php

// Native-session smoke test: starts an isolated development server on a free local port.
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/bootstrap/autoload.php';
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) { throw new RuntimeException($error); }
$address = stream_socket_get_name($socket, false);
fclose($socket);
$directory = sys_get_temp_dir() . '/nova-http-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$log = $directory . '/server.log';
$testDatabase = $directory . '/database.sqlite';
(new \Nova\Database\Migrator(new \Nova\Database\Connection(['driver' => 'sqlite', 'database' => $testDatabase]),
    $root . '/database/migrations'))->migrate();
$process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $directory, '-S', $address,
    '-t', $root . '/public', $root . '/public/router.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root, array_replace(getenv(), ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $testDatabase]));
if (!is_resource($process)) { throw new RuntimeException('Cannot start HTTP test server.'); }
fclose($pipes[0]);

function httpCheck(bool $condition, string $label): void
{
    if (!$condition) { throw new RuntimeException('FAIL ' . $label); }
    echo "PASS $label\n";
}

try {
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
        if ($probe !== false) { fclose($probe); $ready = true; break; }
        usleep(20000);
    }
    if (!$ready) { throw new RuntimeException('Server failed: ' . file_get_contents($log)); }
    $cookie = '';
    $fetch = static function (string $method, string $path, array $data = [], bool $json = false) use ($address, &$cookie): array {
        $headers = ['Connection: close'];
        if ($cookie !== '') { $headers[] = 'Cookie: ' . $cookie; }
        if ($method !== 'GET') { $headers[] = 'Content-Type: ' . ($json ? 'application/json' : 'application/x-www-form-urlencoded'); }
        if ($json) { $headers[] = 'Accept: application/json'; }
        $options = ['method' => $method, 'header' => implode("\r\n", $headers), 'ignore_errors' => true,
            'follow_location' => 0, 'timeout' => 5];
        if ($method !== 'GET') { $options['content'] = $json ? json_encode($data, JSON_THROW_ON_ERROR) : http_build_query($data); }
        $body = file_get_contents('http://' . $address . $path, false, stream_context_create(['http' => $options]));
        if ($body === false) { throw new RuntimeException('HTTP request failed.'); }
        $responseHeaders = $http_response_header;
        foreach ($responseHeaders as $header) {
            if (preg_match('/^Set-Cookie: (nova_session=[^;]+)/i', $header, $match)) { $cookie = $match[1]; }
        }
        preg_match('/\s(\d{3})\s/', $responseHeaders[0], $status);
        return [(int) $status[1], $body, implode("\n", $responseHeaders)];
    };
    [$status, $body, $headers] = $fetch('GET', '/contact');
    httpCheck($status === 200 && preg_match('/name="_token" value="([a-f0-9]{64})"/', $body, $match) === 1, 'native session form token');
    $token = $match[1];
    httpCheck(str_contains(strtolower($headers), 'httponly') && str_contains(strtolower($headers), 'samesite=lax'), 'native cookie security attributes');
    httpCheck($fetch('POST', '/contact')[0] === 419, 'native CSRF rejection');
    [$status, $body] = $fetch('GET', '/contact');
    httpCheck(str_contains($body, $token), 'native token persists across requests');
    [$status, , $headers] = $fetch('POST', '/contact', ['_token' => $token, 'name' => 'Ada', 'email' => 'ada@example.com']);
    httpCheck($status === 303 && str_contains($headers, 'Location: /contact'), 'native form redirect');
    httpCheck(str_contains($fetch('GET', '/contact')[1], 'Thanks, Ada'), 'native flash persistence');
    httpCheck(!str_contains($fetch('GET', '/contact')[1], 'Thanks, Ada'), 'native flash expiration');
    [$status, $body] = $fetch('POST', '/contact', ['_token' => $token], true);
    httpCheck($status === 422 && isset(json_decode($body, true)['errors']['name']), 'native JSON validation errors');
    httpCheck($fetch('POST', '/api/echo', ['message' => 'compatible'], true)[0] === 201, 'native API compatibility');
    httpCheck(!str_contains(strtolower($fetch('GET', '/api/health')[2]), 'set-cookie:'), 'stateless API creates no session');
    [$status, $body] = $fetch('GET', '/notes');
    httpCheck($status === 200 && preg_match('/name="_token" value="([a-f0-9]{64})"/', $body, $noteToken) === 1, 'native notes form');
    httpCheck($fetch('POST', '/notes', ['title' => 'forbidden', 'body' => 'no token'])[0] === 419, 'native notes CSRF rejection');
    [$status, $body] = $fetch('POST', '/notes', ['_token' => $noteToken[1], 'title' => '<b>Note</b>', 'body' => 'Native persistence'], true);
    $id = json_decode($body, true)['note']['id'] ?? null;
    httpCheck($status === 201 && $id !== null, 'native notes create persists');
    httpCheck(str_contains($fetch('GET', '/notes')[1], '&lt;b&gt;Note&lt;/b&gt;'), 'native notes read escapes persisted HTML');
    httpCheck($fetch('POST', '/notes/' . $id, ['_token' => $noteToken[1], 'title' => 'Updated', 'body' => 'Updated body'], true)[0] === 200, 'native notes update persists');
    httpCheck(str_contains($fetch('GET', '/notes/' . $id . '/edit')[1], 'Updated body'), 'native notes edit reloads persisted data');
    httpCheck($fetch('POST', '/notes/' . $id . '/delete', ['_token' => $noteToken[1]], true)[0] === 200, 'native notes delete');
    httpCheck($fetch('GET', '/notes/' . $id . '/edit')[0] === 404, 'native deleted note returns 404');
    [$status, $body, $headers] = $fetch('GET', '/docs/migrations');
    httpCheck($status === 200 && str_contains($body, 'Field::id()') && !str_contains(strtolower($headers), 'set-cookie:'), 'native documentation renders without a session');
    httpCheck($fetch('GET', '/assets/docs.css')[0] === 200, 'native documentation stylesheet served');
    httpCheck($fetch('GET', '/docs/unknown')[0] === 404, 'native unknown documentation page returns 404');
    httpCheck(str_contains($fetch('GET', '/docs/controllers')[1], 'make:controller'), 'native controller documentation');
    httpCheck(str_contains($fetch('GET', '/docs/models')[1], 'Nova Records'), 'native ORM documentation');
    echo "HTTP tests passed.\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    foreach (glob($directory . '/*') ?: [] as $file) { unlink($file); }
    rmdir($directory);
}
