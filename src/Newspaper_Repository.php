<?php
/**
 * Access to newspapers, which are PDF attachments flagged with post meta.
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

namespace SWNewspaper2;

final class Newspaper_Repository {

	public const META_FLAG       = '_swnewspaper2_newspaper';
	public const META_THUMB_ID   = '_swnewspaper2_thumb_id';
	public const META_DATE       = '_swnewspaper2_date';
	public const FOLDER_TAXONOMY = 'wpmf-category';

	/**
	 * Newspapers ordered by issue date, newest first.
	 *
	 * @param int $limit Maximum number of results, -1 for all.
	 * @return array<int, \WP_Post>
	 */
	public function all( int $limit = -1 ): array {
		return get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => $limit,
				'meta_key'       => self::META_DATE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_FLAG,
						'value' => '1',
					),
				),
			)
		);
	}

	public function latest(): ?\WP_Post {
		$items = $this->all( 1 );
		return $items[0] ?? null;
	}

	public function is_newspaper( int $attachment_id ): bool {
		return 'attachment' === get_post_type( $attachment_id ) && '1' === (string) get_post_meta( $attachment_id, self::META_FLAG, true );
	}

	public function mark( int $pdf_id, int $thumbnail_id, string $date ): void {
		update_post_meta( $pdf_id, self::META_FLAG, 1 );
		update_post_meta( $pdf_id, self::META_DATE, $date );
		if ( $thumbnail_id > 0 ) {
			update_post_meta( $pdf_id, self::META_THUMB_ID, $thumbnail_id );
		}
	}

	public function date( \WP_Post $newspaper ): string {
		return (string) get_post_meta( $newspaper->ID, self::META_DATE, true );
	}

	public function formatted_date( \WP_Post $newspaper ): string {
		$date = $this->date( $newspaper );
		$time = '' !== $date ? strtotime( $date . ' 12:00:00' ) : false;
		return false === $time ? '' : wp_date( get_option( 'date_format' ), $time );
	}

	public function thumbnail_id( \WP_Post $newspaper ): int {
		return (int) get_post_meta( $newspaper->ID, self::META_THUMB_ID, true );
	}

	public function thumbnail_url( \WP_Post $newspaper ): string {
		$thumbnail_id = $this->thumbnail_id( $newspaper );
		$url          = $thumbnail_id > 0 ? wp_get_attachment_url( $thumbnail_id ) : false;
		return false === $url ? '' : $url;
	}

	public function pdf_url( \WP_Post $newspaper ): string {
		$url = wp_get_attachment_url( $newspaper->ID );
		return false === $url ? '' : $url;
	}

	/**
	 * Delete the PDF and its cover image permanently.
	 */
	public function delete( int $pdf_id ): bool {
		$thumbnail_id = (int) get_post_meta( $pdf_id, self::META_THUMB_ID, true );
		if ( $thumbnail_id > 0 ) {
			wp_delete_attachment( $thumbnail_id, true );
		}
		return false !== wp_delete_attachment( $pdf_id, true );
	}
}
