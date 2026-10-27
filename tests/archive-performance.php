<?php
/**
 * Real WordPress archive regressions. Requires a disposable database and WordPress core.
 * Usage: WP_ROOT=/tmp/wordpress WP_DB_NAME=newspaper_test php tests/archive-performance.php
 *
 * @package SWNewspaper2
 */
declare(strict_types=1);

use SWNewspaper2\Admin\Admin_Page;
use SWNewspaper2\Newspaper_Repository;
use SWNewspaper2\Pdf_Thumbnailer;
use SWNewspaper2\Settings;
use SWNewspaper2\Upload_Service;

$complete = false;
register_shutdown_function( static function () use ( &$complete ): void {
	if ( ! $complete ) {
		fwrite( STDERR, 'FAIL: Archive suite did not complete.' . PHP_EOL );
		exit( 1 );
	}
} );
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

$repository = new Newspaper_Repository();
$settings = new Settings();
$admin = new Admin_Page( $settings, $repository, new Upload_Service( $settings, $repository, new Pdf_Thumbnailer() ) );
$ids = array();
$pdfs = array();
try {
	// No shortcode: registration alone must not query the archive or enqueue plugin assets.
	wp_styles();
	wp_scripts();
	$before = $wpdb->num_queries;
	( new \SWNewspaper2\Shortcodes( $settings, $repository ) )->register_assets();
	swnewspaper2_check( $before === $wpdb->num_queries, 'Asset registration does not query the archive.' );
	swnewspaper2_check( ! wp_style_is( 'swnewspaper2-frontend', 'enqueued' ), 'No frontend stylesheet is enqueued without a shortcode.' );
	$before = $wpdb->num_queries;
	$repository->prime_cover_cache( array() );
	swnewspaper2_check( $before === $wpdb->num_queries, 'An empty archive does not issue a cover query.' );

	for ( $i = 0; $i < 1000; ++$i ) {
		$cover = wp_insert_attachment( array( 'post_title' => 'Archive fixture cover', 'post_status' => 'inherit', 'post_mime_type' => 'image/png', 'guid' => 'https://newspaper.test/cover.png' ), '', 0, true );
		if ( is_wp_error( $cover ) ) { throw new RuntimeException( $cover->get_error_message() ); }
		$ids[] = $cover;
		// URLs need no files on disk; cleanup must never unlink a shared file.
		$pdf = wp_insert_attachment( array( 'post_title' => sprintf( 'Issue %04d', $i ), 'post_status' => 'inherit', 'post_mime_type' => 'application/pdf', 'guid' => 'https://newspaper.test/issue-' . $i . '.pdf' ), '', 0, true );
		if ( is_wp_error( $pdf ) ) { throw new RuntimeException( $pdf->get_error_message() ); }
		$ids[] = $pdf;
		$pdfs[] = $pdf;
		$repository->mark( $pdf, $cover, gmdate( 'Y-m-d', strtotime( '2020-01-01 UTC' ) + $i * DAY_IN_SECONDS ) );
	}
	// Existing attachment URLs are not restricted to inherit status.
	wp_update_post( array( 'ID' => $ids[0], 'post_status' => 'private' ) );
	foreach ( array( 10, 100, 1000 ) as $limit ) {
		wp_cache_flush();
		$before = $wpdb->num_queries;
		$html = do_shortcode( '[swnewspaper2_archive limit="' . $limit . '"]' );
		$queries = $wpdb->num_queries - $before;
		swnewspaper2_check( $queries <= 15, "Frontend $limit issues uses bounded SQL ($queries queries)." );
		swnewspaper2_check( $limit === substr_count( $html, '<article class="swnp-card">' ), 'The existing limit selects the same number of cards.' );
		swnewspaper2_check( strpos( $html, 'Issue 0999' ) < strpos( $html, 'Issue 0998' ), 'Issue dates remain in descending order.' );
		swnewspaper2_check( str_contains( $html, 'https://newspaper.test/issue-999.pdf' ) && str_contains( $html, 'loading="lazy"' ), 'PDF links and lazy cover images remain available.' );
	}
	wp_cache_flush();
	$html = do_shortcode( '[swnewspaper2_archive]' );
	swnewspaper2_check( 1000 === substr_count( $html, '<article class="swnp-card">' ), 'The default shortcode still displays all issues.' );
	// Exercise the actual admin controller and template, not just the repository helper.
	$_GET['tab'] = 'archive';
	wp_cache_flush();
	$before = $wpdb->num_queries;
	ob_start();
	$admin->render();
	$html = ob_get_clean();
	$queries = $wpdb->num_queries - $before;
	swnewspaper2_check( $queries <= 25, "Admin archive uses bounded SQL ($queries queries)." );
	swnewspaper2_check( 1000 === substr_count( $html, 'class="swnp-card swnp-item"' ), 'Admin archive retains every issue.' );
	unset( $_GET['tab'] );

	$cover_queries = 0;
	$watch_queries = static function ( $query ) use ( &$cover_queries ): void {
		if ( 'attachment' === $query->get( 'post_type' ) && $query->get( 'post__in' ) ) {
			++$cover_queries;
		}
	};
	add_action( 'pre_get_posts', $watch_queries );
	$download = do_shortcode( '[swnewspaper2_download]' );
	remove_action( 'pre_get_posts', $watch_queries );
	swnewspaper2_check( 0 === $cover_queries && str_contains( $download, 'issue-999.pdf' ), 'Download-only output does not prime unused cover data.' );

	// Shared, missing and deleted cover references must not cause per-card fallback queries.
	$shared = $repository->thumbnail_id( get_post( $pdfs[0] ) );
	$deleted = $repository->thumbnail_id( get_post( $pdfs[1] ) );
	wp_delete_attachment( $deleted, true );
	update_post_meta( $pdfs[2], Newspaper_Repository::META_THUMB_ID, $shared );
	update_post_meta( $pdfs[3], Newspaper_Repository::META_THUMB_ID, PHP_INT_MAX );
	delete_post_meta( $pdfs[4], Newspaper_Repository::META_THUMB_ID );
	wp_cache_flush();
	$posts = $repository->all();
	$repository->prime_cover_cache( $posts );
	swnewspaper2_check( '' !== $repository->thumbnail_url( get_post( $pdfs[0] ) ), 'Non-inherit cover statuses remain usable.' );
	$before = $wpdb->num_queries;
	foreach ( $posts as $post ) { $repository->thumbnail_url( $post ); }
	swnewspaper2_check( $before === $wpdb->num_queries, 'Valid/shared/missing/deleted covers need no individual SQL after priming.' );
	swnewspaper2_check( '' === $repository->thumbnail_url( get_post( $pdfs[1] ) ) && '' === $repository->thumbnail_url( get_post( $pdfs[3] ) ) && '' === $repository->thumbnail_url( get_post( $pdfs[4] ) ), 'Absent covers retain the empty-URL fallback.' );
	$html = do_shortcode( '[swnewspaper2_archive]' );
	swnewspaper2_check( 3 === substr_count( $html, 'swnp-cover__fallback' ), 'Frontend displays the PDF fallback for missing covers.' );
	// Without any covers, even a nonempty archive must not issue a batch query.
	delete_post_meta( $pdfs[4], Newspaper_Repository::META_THUMB_ID );
	$no_cover = get_post( $pdfs[4] );
	$repository->thumbnail_id( $no_cover );
	$before = $wpdb->num_queries;
	$repository->prime_cover_cache( array( $no_cover ) );
	swnewspaper2_check( $before === $wpdb->num_queries, 'A coverless selection does not query unrelated attachments.' );
} finally {
	foreach ( array_reverse( $ids ) as $id ) { wp_delete_attachment( $id, true ); }
}
$complete = true;
