<?php
/**
 * Shared WordPress bootstrap for scenarios that need WordPress core and a disposable database.
 * Environment: WP_ROOT, WP_DB_NAME, WP_DB_HOST, WP_DB_USER, WP_DB_PASSWORD (see tests/README.md).
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

define( 'ABSPATH', rtrim( getenv( 'WP_ROOT' ) ?: '/tmp/wordpress', '/' ) . '/' );
define( 'DB_NAME', getenv( 'WP_DB_NAME' ) ?: 'newspaper_test' );
define( 'DB_USER', getenv( 'WP_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WP_DB_PASSWORD' ) ?: '' );
define( 'DB_HOST', getenv( 'WP_DB_HOST' ) ?: '127.0.0.1' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'WP_INSTALLING', true );
define( 'WP_ADMIN', true );
define( 'WP_DEBUG', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST'] = 'newspaper.test';
$_SERVER['REQUEST_URI'] = '/';
$table_prefix = 'swnp_test_';
require_once ABSPATH . 'wp-includes/plugin.php';
add_filter( 'pre_option_active_plugins', static fn() => array() );
require ABSPATH . 'wp-settings.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if ( ! is_blog_installed() ) {
	add_filter( 'pre_wp_mail', '__return_true' );
	wp_install( 'Archive tests', 'testadmin', 'test@example.test', true, '', wp_generate_password( 32 ) );
	remove_filter( 'pre_wp_mail', '__return_true' );
}
wp_installing( false );
wp_set_current_user( (int) get_user_by( 'login', 'testadmin' )->ID );
require ( getenv( 'SWNEWSPAPER2_TEST_PLUGIN' ) ?: dirname( __DIR__ ) ) . '/sw-newspaper2.php';

function swnewspaper2_check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		throw new RuntimeException( $message );
	}
	echo 'PASS: ' . $message . PHP_EOL;
}
