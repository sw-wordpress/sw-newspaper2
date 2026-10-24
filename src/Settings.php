<?php
/**
 * Plugin settings stored in one option.
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

namespace SWNewspaper2;

final class Settings {

	public const OPTION = 'swnewspaper2_settings';

	/**
	 * @return array{dflip_id: int, media_folder_id: int}
	 */
	public function get(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'dflip_id'        => isset( $stored['dflip_id'] ) ? (int) $stored['dflip_id'] : 0,
			'media_folder_id' => isset( $stored['media_folder_id'] ) ? (int) $stored['media_folder_id'] : 0,
		);
	}

	/**
	 * Persist settings after validating that the referenced objects exist.
	 */
	public function save( int $dflip_id, int $media_folder_id ): void {
		if ( $dflip_id > 0 && 'dflip' !== get_post_type( $dflip_id ) ) {
			$dflip_id = 0;
		}
		if ( $media_folder_id > 0 && ! term_exists( $media_folder_id, Newspaper_Repository::FOLDER_TAXONOMY ) ) {
			$media_folder_id = 0;
		}

		update_option(
			self::OPTION,
			array(
				'dflip_id'        => $dflip_id,
				'media_folder_id' => $media_folder_id,
			),
			false
		);
	}

	/**
	 * Available dFlip book configurations (empty when dFlip is not installed).
	 *
	 * @return array<int, \WP_Post>
	 */
	public function dflip_configs(): array {
		if ( ! post_type_exists( 'dflip' ) ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'      => 'dflip',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Media folders of the WP Media Folder plugin (empty when it is not installed).
	 *
	 * @return array<int, \WP_Term>
	 */
	public function media_folders(): array {
		if ( ! taxonomy_exists( Newspaper_Repository::FOLDER_TAXONOMY ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => Newspaper_Repository::FOLDER_TAXONOMY,
				'hide_empty' => false,
			)
		);
		return is_wp_error( $terms ) ? array() : $terms;
	}
}
