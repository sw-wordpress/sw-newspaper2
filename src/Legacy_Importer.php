<?php
/**
 * Reuse newspapers published by the original sw-newspaper plugin.
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

namespace SWNewspaper2;

final class Legacy_Importer {

	public function __construct( private readonly Newspaper_Repository $repository ) {
	}

	/**
	 * Only seed an empty installation; never change legacy metadata or files.
	 */
	public function import(): void {
		if ( ! is_file( WP_PLUGIN_DIR . '/sw-newspaper/sw-newspaper.php' ) ) {
			return;
		}

		// Do not use latest(): a marked record without a date must also block import.
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => Newspaper_Repository::META_FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( array() !== $existing ) {
			return;
		}

		$newspapers = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'application/pdf',
				'posts_per_page' => -1,
				'meta_key'       => '_newspaper', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		foreach ( $newspapers as $newspaper ) {
			$thumbnail_id = absint( get_post_meta( $newspaper->ID, '_thumbid', true ) );
			if ( ! wp_attachment_is_image( $thumbnail_id ) ) {
				$thumbnail_id = 0;
			}

			$date = (string) get_post_meta( $newspaper->ID, 'date', true );
			if ( ! $this->is_valid_date( $date ) ) {
				$date = substr( $newspaper->post_date, 0, 10 );
			}
			if ( ! $this->is_valid_date( $date ) ) {
				$date = wp_date( 'Y-m-d' );
			}

			$this->repository->mark( $newspaper->ID, $thumbnail_id, $date );
		}
	}

	private function is_valid_date( string $date ): bool {
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		return false !== $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}
}
