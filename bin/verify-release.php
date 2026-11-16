<?php
/** Artifact validation shared by local builds and CI; no test suite or WordPress boot. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/update/class-sw-newspaper2-update-package.php';
if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(public string $code, public string $message) {}
        public function get_error_message(): string { return $this->message; }
    }
}
function swnewspaper2_verify_release(string $file): void {
    $zip = new ZipArchive();
    if (true !== $zip->open($file)) throw new RuntimeException('Unreadable release ZIP.');
    foreach (['sw-newspaper2.php', 'vendor-prefixed/autoload.php', 'vendor-prefixed/composer/autoload_real.php', 'src/Plugin.php', 'docs/GITHUB-UPDATES-BETRIEB.md', 'assets/admin.js', 'includes/update/trusted-keys.php', 'includes/update/class-sw-newspaper2-update-provider.php', 'templates/admin/layout.php', 'uninstall.php', 'LICENSE'] as $required) {
        if ($zip->locateName('sw-newspaper2/' . $required) === false) throw new RuntimeException('Missing runtime file: ' . $required);
    }
    for ($i = 0; $i < $zip->numFiles; ++$i) {
        if (preg_match('~^sw-newspaper2/(vendor/|tests/|bin/|coverage/|node_modules/|\.git|\.env)|(?:secret|signing-key)~i', $zip->getNameIndex($i))) throw new RuntimeException('Unexpected release file.');
    }
    $main = $zip->getFromName('sw-newspaper2/sw-newspaper2.php');
    $zip->close();
    $package = ['slug' => 'sw-newspaper2', 'main_file' => 'sw-newspaper2.php', 'bytes' => filesize($file), 'sha256' => hash_file('sha256', $file)];
    foreach (['Version' => 'version', 'Requires PHP' => 'requires_php', 'Requires at least' => 'requires_wp'] as $header => $field) {
        if (!preg_match('/^ \* ' . $header . ':\s*(.+)$/m', $main, $matches)) throw new RuntimeException('Missing header.');
        $package[$field] = trim($matches[1]);
    }
    if (basename($file) !== 'sw-newspaper2-' . $package['version'] . '.zip') throw new RuntimeException('ZIP version mismatch.');
    $result = SWNewspaper2_Update_Package::verify($file, $package);
    if ($result !== true) throw new RuntimeException($result->get_error_message());
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) swnewspaper2_verify_release($argv[1]);
