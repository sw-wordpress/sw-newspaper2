<?php
/**
 * Plugin Name:       sw-newspaper2
 * Plugin URI:        https://stefanwarnat.de
 * Description:       Publish PDF newspapers: automatic cover thumbnail, archive grid, download link and dFlip integration.
 * Version:           2.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Stefan Warnat
 * Author URI:        https://stefanwarnat.de
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sw-newspaper2
 * Domain Path:       /languages
 *
 * @package SWNewspaper2
 */

defined( 'ABSPATH' ) || exit;

define( 'SWNEWSPAPER2_VERSION', '2.0.0' );
define( 'SWNEWSPAPER2_FILE', __FILE__ );
define( 'SWNEWSPAPER2_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWNEWSPAPER2_URL', plugin_dir_url( __FILE__ ) );
define( 'SWNEWSPAPER2_MIN_PHP', '8.2' );

if ( version_compare( PHP_VERSION, SWNEWSPAPER2_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						__( 'sw-newspaper2 requires PHP %1$s or newer. You are running PHP %2$s.', 'sw-newspaper2' ),
						SWNEWSPAPER2_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

$swnewspaper2_autoload = SWNEWSPAPER2_DIR . 'vendor/autoload.php';
if ( ! is_readable( $swnewspaper2_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'sw-newspaper2: the vendor directory is missing. Install the release package or run "composer install".', 'sw-newspaper2' )
			);
		}
	);
	return;
}
require_once $swnewspaper2_autoload;
unset( $swnewspaper2_autoload );

register_activation_hook( __FILE__, array( \SWNewspaper2\Plugin::class, 'activate' ) );

\SWNewspaper2\Plugin::instance()->boot();
