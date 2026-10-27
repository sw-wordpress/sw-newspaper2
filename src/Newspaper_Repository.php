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

	/** @var array<int, bool> Cover existence for the most recently primed archive. */
	private array $primed_covers = array();

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

	/**
	 * Prime attachment posts and metadata only for an archive that will display covers.
	 *
	 * @param array<int, \WP_Post> $newspapers Newspapers returned by all(), with their metadata already primed.
	 */
	public function prime_cover_cache( array $newspapers ): void {
		$ids = array();
		foreach ( $newspapers as $newspaper ) {
			$id = $this->thumbnail_id( $newspaper );
			if ( $id > 0 ) {
				$ids[ $id ] = false;
			}
		}
		$this->primed_covers = $ids;
		// An empty post__in would otherwise match unrelated attachments.
		if ( array() === $ids ) {
			return;
		}
		$covers = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => array_keys( get_post_stati() ),
				'post__in'               => array_keys( $ids ),
				'posts_per_page'         => count( $ids ),
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);
		foreach ( $covers as $cover ) {
			$this->primed_covers[ $cover->ID ] = true;
		}
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
		if ( isset( $this->primed_covers[ $thumbnail_id ] ) && ! $this->primed_covers[ $thumbnail_id ] ) {
			return '';
		}
		$url = $thumbnail_id > 0 ? wp_get_attachment_url( $thumbnail_id ) : false;
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
