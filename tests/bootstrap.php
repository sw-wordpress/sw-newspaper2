<?php
/**
 * Smoke-test a source checkout or staged release without a WordPress database.
 * Usage: php tests/bootstrap.php [plugin-directory]
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

define( 'ABSPATH', '/test/wordpress/' );

function plugin_dir_path( string $file ): string {
	return dirname( $file ) . '/';
}

function plugin_dir_url( string $file ): string {
	return 'https://example.test/wp-content/plugins/sw-newspaper2/';
}

function add_action( string $hook, mixed $callback ): void {
	$GLOBALS['test_actions'][ $hook ][] = $callback;
}

function add_shortcode( string $tag, mixed $callback ): void {
	$GLOBALS['test_shortcodes'][ $tag ] = $callback;
}

function register_activation_hook( string $file, mixed $callback ): void {
	$GLOBALS['test_activation'] = $callback;
}

function is_admin(): bool {
	return true;
}

$swnewspaper2_test_root = $argv[1] ?? dirname( __DIR__ );
require $swnewspaper2_test_root . '/sw-newspaper2.php';

if ( ! class_exists( \SWNewspaper2\Plugin::class ) || ! is_callable( $GLOBALS['test_activation'] ?? null ) ) {
	throw new RuntimeException( 'Plugin classes or activation callback could not be loaded.' );
}

foreach ( array( 'swnewspaper2_download', 'swnewspaper2_current', 'swnewspaper2_archive' ) as $swnewspaper2_test_tag ) {
	if ( ! is_callable( $GLOBALS['test_shortcodes'][ $swnewspaper2_test_tag ] ?? null ) ) {
		throw new RuntimeException( 'Shortcode was not registered: ' . $swnewspaper2_test_tag );
	}
}

if ( ! isset( $GLOBALS['test_actions']['admin_menu'] ) ) {
	throw new RuntimeException( 'Admin integration was not registered.' );
}

echo 'PASS: Plugin bootstrap, activation callback, shortcodes and admin integration.' . PHP_EOL;
