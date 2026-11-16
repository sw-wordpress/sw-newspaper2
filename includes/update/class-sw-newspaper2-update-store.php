<?php
/**
 * Non-autoloaded, network-wide state and compare-and-delete locks.
 *
 * @package SWNewspaper2
 */

declare( strict_types=1 );

final class SWNewspaper2_Update_Store {
	public const STATE = 'swnewspaper2_verified_github_updates_v1';
	public const LOCK  = 'swnewspaper2_verified_github_updates_lock_v1';

	private static function name( bool $lock = false ): string {
		$source = require __DIR__ . '/source.php';
		return $source[ $lock ? 'lock_key' : 'state_key' ];
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function read(): array {
		$value = get_site_option( self::name(), array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * @param array<string,mixed> $state
	 */
	public static function write( array $state ): void {
		if ( is_multisite() ) {
			update_site_option( self::name(), $state );
		} else {
			update_option( self::name(), $state, false );
		}
	}

	/**
	 * @return array{token:string,expires:int}|false
	 */
	public static function acquire(): array|false {
		global $wpdb;
		// sitemeta has no unique index. Serialize network add_site_option's check/insert
		// with a database advisory mutex; the persisted lease still survives requests.
		$mutex = 'swnewspaper2_update_' . get_current_network_id();
		if ( is_multisite() && '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $mutex ) ) ) {
			return false;
		}
		try {
			if ( is_multisite() ) {
				wp_cache_delete( get_current_network_id() . ':' . self::name( true ), 'site-options' );
				wp_cache_delete( get_current_network_id() . ':notoptions', 'site-options' );
			}
			$previous = get_site_option( self::name( true ), false );
			if ( is_array( $previous ) && ( $previous['expires'] ?? PHP_INT_MAX ) < time() ) {
				self::release( $previous );
			}
			$lock  = array(
				'token'   => wp_generate_password( 32, false ),
				'expires' => time() + 120,
			);
			$added = is_multisite() ? add_site_option( self::name( true ), $lock ) : add_option( self::name( true ), $lock, '', false );
			return $added ? $lock : false;
		} finally {
			if ( is_multisite() ) {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $mutex ) );
			}
		}
	}

	/** @param array<string,mixed> $lock Exact lease acquired by this request. */
	public static function owns( array $lock ): bool {
		global $wpdb;
		if ( is_multisite() ) {
			$value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key = %s", get_current_network_id(), self::name( true ) ) );
		} else {
			$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::name( true ) ) );
		}
		return maybe_unserialize( $value ) === $lock && time() < $lock['expires'];
	}

	/**
	 * Atomic ownership check: an expired request must never delete its successor's lock.
	 * Uses conditional WordPress option SQL because the option API has no compare-and-delete.
	 *
	 * @param array<string,mixed> $lock Exact value originally acquired.
	 */
	public static function release( array $lock ): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Atomic platform lock; values are prepared, table identifiers are WordPress-owned.
		if ( is_multisite() ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key = %s AND meta_value = %s", get_current_network_id(), self::name( true ), maybe_serialize( $lock ) ) );
			wp_cache_delete( get_current_network_id() . ':' . self::name( true ), 'site-options' );
		} else {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::name( true ), maybe_serialize( $lock ) ) );
			wp_cache_delete( self::name( true ), 'options' );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
