<?php
require_once __DIR__ . '/../service/tls_policy.php';
use topofgames\phpbbmcp\service\tls_policy;
$p = new tls_policy();
$cases = [
    ['https://', 'on', '127.0.0.1', '', '', true],
    ['https://', '', '127.0.0.1', 'https', '', false],
    ['https://', '', '127.0.0.1', 'https', '127.0.0.1', true],
    ['https://', '', '127.0.0.1', 'http', '127.0.0.1', false],
    ['https://', '', '10.0.0.2', 'https', '127.0.0.1', false],
    ['http://', 'on', '127.0.0.1', '', '', false],
    ['https://', '', '10.0.0.1', 'https,http', '10.0.0.1', false],
    ['https://', '', '10.0.0.1', 'https', '10.0.0.1,10.0.0.2', true],
];
foreach ($cases as $i => $c) {
    $expected = array_pop($c);
    if ($p->is_allowed(...$c) !== $expected) {
        fwrite(STDERR, 'Failed TLS case ' . $i . PHP_EOL);
        exit(1);
    }
}
echo 'PASS: ' . count($cases) . ' TLS cases' . PHP_EOL;
