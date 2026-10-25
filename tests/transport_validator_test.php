<?php
require_once __DIR__ . '/../service/transport_validator.php';

use topofgames\phpbbmcp\service\transport_validator;

$v = new transport_validator();
$count = 0;
function check($ok, $name) {
    global $count;
    ++$count;
    if (!$ok) {
        fwrite(STDERR, "FAIL: " . $name . "\n");
        exit(1);
    }
}
$body = [
    'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
    'params' => [
        'name' => 'posts.get',
        'arguments' => ['post_id' => 123],
        '_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => [],
        ],
    ],
];
function validate($v, $body, $ver = '2026-07-28', $method = 'tools/call',
    $name = 'posts.get', $accept = 'application/json, text/event-stream',
    $type = 'application/json') {
    return $v->validate($ver, $method, $name, $accept, $type, $body);
}
check(validate($v, $body) === null, 'modern request');
check(validate($v, $body, '2026-07-28', 'wrong')['code'] === -32020, 'method mismatch');
check(validate($v, $body, '2026-07-28', 'tools/call', 'posts.delete')['code'] === -32020, 'name mismatch');
check(validate($v, $body, '2026-07-28', 'tools/call', '=?base64?' . base64_encode('posts.get') . '?=') === null, 'base64 name');
$bad = $body;
unset($bad['params']['_meta']['io.modelcontextprotocol/clientCapabilities']);
check(validate($v, $bad)['status'] === 400, 'capabilities required');
$bad = $body;
$bad['params']['_meta']['io.modelcontextprotocol/protocolVersion'] = '2025-11-25';
check(validate($v, $bad)['code'] === -32020, 'version mismatch');
check(validate($v, $body, '2040-01-01')['code'] === -32022, 'unsupported version');
check(validate($v, $body, '2026-07-28', 'tools/call', 'posts.get', '*/*')['status'] === 406, 'Accept header');
check(validate($v, $body, '2026-07-28', 'tools/call', 'posts.get',
    'application/json,text/event-stream', 'text/plain')['status'] === 415, 'content type');
check($v->origin_allowed('', 'forum.example', 443), 'no Origin');
check($v->origin_allowed('https://forum.example', 'forum.example', 443), 'same Origin');
check(!$v->origin_allowed('http://forum.example', 'forum.example', 443), 'HTTP rejected');
check(!$v->origin_allowed('https://forum.example.evil.invalid', 'forum.example', 443), 'host suffix');
check(!$v->origin_allowed('https://forum.example:444', 'forum.example', 443), 'wrong port');
check($v->origin_allowed('https://forum.example:8443', 'forum.example', 8443), 'correct custom port');
check(!$v->origin_allowed('https://forum.example/path', 'forum.example', 443), 'path rejected');
check(!$v->origin_allowed('https://forum.example@evil.invalid', 'forum.example', 443), 'userinfo');
$legacy = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
    'params' => ['protocolVersion' => '2025-06-18']];
check(validate($v, $legacy, '', '', '') === null, 'legacy bootstrap');
check(validate($v, $legacy, '2025-11-25', '', '') === null, '2025 legacy');
check(in_array('2026-07-28', $v->supported_versions(), true), 'advertised version');
echo "PASS: $count transport assertions\n";
