<?php
/**
 * Standalone importer regression tests using in-memory WordPress doubles.
 * Run from the plugin directory: php tests/legacy-import.php
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

namespace SWNewspaper2 {

	// Keep plugin-presence tests independent of the real installation.
	function is_file( string $path ): bool {
		return $GLOBALS['legacy_installed'] && WP_PLUGIN_DIR . '/sw-newspaper/sw-newspaper.php' === $path;
	}
}

namespace {

	define( 'WP_PLUGIN_DIR', '/test/plugins' );

	final class WP_Post {
		public function __construct(
			public int $ID,
			public string $post_date,
			public string $post_mime_type = 'application/pdf'
		) {
		}
	}

	function get_posts( array $args ): array {
		++$GLOBALS['queries'];
		$posts = array_filter(
			$GLOBALS['posts'],
			static fn ( WP_Post $post ): bool =>
				'1' === (string) get_post_meta( $post->ID, $args['meta_key'], true )
				&& ( ! isset( $args['post_mime_type'] ) || $args['post_mime_type'] === $post->post_mime_type )
		);
		$posts = array_values( $posts );
		if ( $args['posts_per_page'] > 0 ) {
			$posts = array_slice( $posts, 0, $args['posts_per_page'] );
		}
		return 'ids' === ( $args['fields'] ?? '' ) ? array_column( $posts, 'ID' ) : $posts;
	}

	function get_post_meta( int $id, string $key, bool $single ): mixed {
		return $GLOBALS['meta'][ $id ][ $key ] ?? '';
	}

	function update_post_meta( int $id, string $key, mixed $value ): void {
		$GLOBALS['meta'][ $id ][ $key ] = $value;
	}

	function absint( mixed $value ): int {
		return abs( (int) $value );
	}

	function wp_attachment_is_image( int $id ): bool {
		return 20 === $id;
	}

	function wp_date( string $format ): string {
		return '2026-10-24';
	}

	function check( bool $condition, string $message ): void {
		if ( ! $condition ) {
			throw new \RuntimeException( $message );
		}
		echo 'PASS: ' . $message . PHP_EOL;
	}

	require dirname( __DIR__ ) . '/src/Newspaper_Repository.php';
	require dirname( __DIR__ ) . '/src/Legacy_Importer.php';

	$posts = array(
		new WP_Post( 1, '2024-01-01 12:00:00' ),
		new WP_Post( 2, '2024-02-03 12:00:00' ),
		new WP_Post( 3, '0000-00-00 00:00:00' ),
		new WP_Post( 4, '2024-01-01 12:00:00', 'image/png' ),
		new WP_Post( 5, '2024-01-01 12:00:00' ),
	);
	$meta = array(
		1 => array( '_newspaper' => '1', '_thumbid' => '20', 'date' => '2024-06-15' ),
		2 => array( '_newspaper' => '1', '_thumbid' => '999', 'date' => '2024-02-30' ),
		3 => array( '_newspaper' => '1' ),
		4 => array( '_newspaper' => '1' ),
	);
	$original_meta    = $meta;
	$queries          = 0;
	$legacy_installed = false;
	$importer         = new \SWNewspaper2\Legacy_Importer( new \SWNewspaper2\Newspaper_Repository() );
	$importer->import();
	check( 0 === $queries && $meta === $original_meta, 'Absent legacy plugin prevents import and queries.' );

	$legacy_installed = true;
	$meta[5]         = array( '_swnewspaper2_newspaper' => '1' );
	$before          = $meta;
	$importer->import();
	check( $meta === $before, 'Existing new record blocks import even without an issue date.' );
	unset( $meta[5] );

	$importer->import();
	check( 1 === $meta[1]['_swnewspaper2_newspaper'], 'Legacy PDF is marked without creating a new attachment.' );
	check( 20 === $meta[1]['_swnewspaper2_thumb_id'], 'Existing cover ID is reused.' );
	check( '2024-06-15' === $meta[1]['_swnewspaper2_date'], 'Valid legacy issue date is preserved.' );
	check( '2024-02-03' === $meta[2]['_swnewspaper2_date'], 'Invalid issue date falls back to attachment date.' );
	check( ! isset( $meta[2]['_swnewspaper2_thumb_id'] ), 'Missing cover attachment is not imported.' );
	check( '2026-10-24' === $meta[3]['_swnewspaper2_date'], 'Missing dates fall back to the site date.' );
	check( ! isset( $meta[4]['_swnewspaper2_newspaper'] ) && ! isset( $meta[5]['_swnewspaper2_newspaper'] ), 'Non-PDF and unmarked attachments are excluded.' );
	foreach ( $original_meta as $id => $values ) {
		foreach ( $values as $key => $value ) {
			check( $value === $meta[ $id ][ $key ], 'Legacy metadata preserved: ' . $id . '/' . $key );
		}
	}

	$before = $meta;
	$importer->import();
	check( $before === $meta, 'Repeated activation does not change imported records.' );

	$posts = array();
	$meta  = array();
	$importer->import();
	check( array() === $meta, 'No legacy records is a safe no-op.' );
}
