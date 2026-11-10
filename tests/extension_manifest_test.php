<?php
$manifestPath = __DIR__ . '/../composer.json';
$manifest = json_decode(file_get_contents($manifestPath), true);

function manifest_check($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . "\n");
        exit(1);
    }
}

manifest_check(is_array($manifest), 'composer.json parses as JSON');
manifest_check(($manifest['type'] ?? null) === 'phpbb-extension', 'package type is phpbb-extension');
manifest_check(isset($manifest['name']) && is_string($manifest['name']) && $manifest['name'] !== '', 'package name exists');
manifest_check(isset($manifest['description']) && is_string($manifest['description']) && $manifest['description'] !== '', 'description exists');
manifest_check(isset($manifest['version']) && is_string($manifest['version']) && $manifest['version'] !== '', 'version exists');
manifest_check(isset($manifest['license']) && is_string($manifest['license']) && $manifest['license'] !== '', 'license exists');
manifest_check(isset($manifest['authors']) && is_array($manifest['authors']) && count($manifest['authors']) > 0, 'at least one author is declared');

foreach ($manifest['authors'] as $index => $author) {
    manifest_check(is_array($author), "author $index is an object");
    manifest_check(isset($author['name']) && is_string($author['name']) && trim($author['name']) !== '', "author $index has a name");
}

manifest_check(isset($manifest['extra']['display-name']) && is_string($manifest['extra']['display-name']), 'phpBB display-name metadata exists');
manifest_check(isset($manifest['extra']['soft-require']['phpbb/phpbb']), 'phpBB compatibility constraint exists');

echo "PASS: phpBB extension manifest metadata\n";
