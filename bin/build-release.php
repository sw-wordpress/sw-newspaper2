<?php
/** Build an installable ZIP without changing the checkout or requiring Docker. */
declare(strict_types=1);
$root = dirname(__DIR__);
$out = $argv[1] ?? $root . '/dist';
$main = file_get_contents($root . '/sw-newspaper2.php');
if (!preg_match('/^ \* Version:\s+([0-9]+(?:\.[0-9]+){2,3})\s*$/m', $main, $header)
    || !preg_match("/define\( 'SWNEWSPAPER2_VERSION', '([^']+)' \);/", $main, $constant)
    || $header[1] !== $constant[1]) {
    throw new RuntimeException('Inconsistent plugin version.');
}
$stage = sys_get_temp_dir() . '/swnewspaper2-build-' . bin2hex(random_bytes(8));
mkdir($stage, 0700);
$copy = static function (string $from, string $to) use (&$copy): void {
    if (is_link($from)) throw new RuntimeException('Symlinks are forbidden in release input.');
    if (is_dir($from)) {
        mkdir($to, 0755, true);
        foreach (new DirectoryIterator($from) as $item) {
            if (!$item->isDot()) $copy($item->getPathname(), $to . '/' . $item->getFilename());
        }
    } elseif (!copy($from, $to)) throw new RuntimeException('Cannot stage release.');
};
$remove = static function (string $path) use (&$remove): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (new DirectoryIterator($path) as $item) if (!$item->isDot()) $remove($item->getPathname());
        rmdir($path);
    } else unlink($path);
};
try {
    foreach (['src', 'templates', 'assets', 'includes', 'languages', 'docs', 'sw-newspaper2.php', 'uninstall.php', 'LICENSE', 'README.md', 'composer.json', 'composer.lock'] as $entry) {
        $copy($root . '/' . $entry, $stage . '/' . $entry);
    }
    // There are no production third-party dependencies. Composer generates only our isolated loader.
    $config = json_decode(file_get_contents($stage . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    if (array_diff(array_keys($config['require']), ['php'])) throw new RuntimeException('New runtime dependencies need an explicit isolation review.');
    $process = proc_open(['composer', 'install', '--working-dir=' . $stage, '--no-dev', '--no-scripts', '--no-plugins', '--no-interaction', '--no-progress', '--optimize-autoloader'], [STDIN, STDOUT, STDERR], $pipes);
    if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('Composer release loader failed.');
    rename($stage . '/vendor', $stage . '/vendor-prefixed');
    unlink($stage . '/composer.json');
    unlink($stage . '/composer.lock');
    if (!is_dir($out)) mkdir($out, 0755, true);
    $target = $out . '/sw-newspaper2-' . $header[1] . '.zip';
    $zip = new ZipArchive();
    if (true !== $zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE)) throw new RuntimeException('Cannot create ZIP.');
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS)) as $file) {
        $zip->addFile($file->getPathname(), 'sw-newspaper2/' . substr($file->getPathname(), strlen($stage) + 1));
    }
    $zip->close();
    require __DIR__ . '/verify-release.php';
    swnewspaper2_verify_release($target);
    echo 'Built ' . $target . PHP_EOL;
} finally {
    $remove($stage);
}
