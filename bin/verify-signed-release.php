<?php
/** Validate downloaded draft artifacts with the local trust anchor, before publication. */
declare(strict_types=1);
require __DIR__ . '/verify-release.php';
require dirname(__DIR__) . '/includes/update/class-sw-newspaper2-update-manifest.php';
$release = json_decode(file_get_contents($argv[1]), true, 32, JSON_THROW_ON_ERROR);
if (($release['prerelease'] ?? null) !== false) throw new RuntimeException('Stable release required.');
// A draft is checked against the exact metadata it will have after publication.
$release['draft'] = false;
$dir = $argv[2];
$keys = require dirname(__DIR__) . '/includes/update/trusted-keys.php';
$manifest = SWNewspaper2_Update_Manifest::verify(file_get_contents($dir . '/sw-plugin-release.json'), file_get_contents($dir . '/sw-plugin-release.json.sig'), $keys, $release);
if ($manifest instanceof WP_Error) throw new RuntimeException($manifest->get_error_message());
foreach ($manifest['packages'] as $package) {
    $file = $dir . '/' . $package['filename'];
    swnewspaper2_verify_release($file);
    $result = SWNewspaper2_Update_Package::verify($file, $package);
    if ($result instanceof WP_Error) throw new RuntimeException($result->get_error_message());
}
echo "Verified signed release artifacts.\n";
