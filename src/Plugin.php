<?php
/**
 * Plugin bootstrap and service wiring.
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

namespace SWNewspaper2;

use SWNewspaper2\Admin\Admin_Page;

final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				try {
					( new Legacy_Importer( new Newspaper_Repository() ) )->import();
				} finally {
					restore_current_blog();
				}
			}
			return;
		}

		( new Legacy_Importer( new Newspaper_Repository() ) )->import();
	}

	public function boot(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		$settings    = new Settings();
		$repository  = new Newspaper_Repository();
		$thumbnailer = new Pdf_Thumbnailer();
		$uploads     = new Upload_Service( $settings, $repository, $thumbnailer );

		( new Shortcodes( $settings, $repository ) )->register();

		if ( is_admin() ) {
			( new Admin_Page( $settings, $repository, $uploads ) )->register();
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'sw-newspaper2', false, dirname( plugin_basename( SWNEWSPAPER2_FILE ) ) . '/languages' );
	}
}
