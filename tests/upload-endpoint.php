<?php
/**
 * Router for PHP's built-in server, started by admin-workflows.php. Real multipart requests
 * are needed because wp_handle_upload() only accepts files that pass is_uploaded_file().
 * Query flags enable test-only WordPress filters; the response is JSON.
 *
 * @package SWNewspaper2
 */
declare(strict_types=1);

use SWNewspaper2\Admin\Admin_Page;
use SWNewspaper2\Newspaper_Repository;
use SWNewspaper2\Pdf_Thumbnailer;
use SWNewspaper2\Settings;
use SWNewspaper2\Upload_Service;

if ( 'cli-server' !== PHP_SAPI ) {
	exit( 1 );
}
// The built-in server ignores auto_prepend_file for router scripts.
if ( false !== getenv( 'SW_NEWSPAPER2_COVERAGE_FILE' ) ) {
	require __DIR__ . '/coverage/bootstrap.php';
}
require __DIR__ . '/wordpress.php';

// Stand-ins for the optional dFlip and WP Media Folder plugins.
register_post_type( 'dflip' );
register_taxonomy( Newspaper_Repository::FOLDER_TAXONOMY, 'attachment' );

$flag = static fn( string $name ): bool => isset( $_GET[ $name ] );
if ( $flag( 'as_pdf' ) ) {
	// Lets Imagick render a PNG payload, independent of the host's Ghostscript/PDF policy.
	add_filter(
		'wp_check_filetype_and_ext',
		static fn( array $check, string $file, string $name ): array => str_ends_with( $name, '.pdf' )
			? array( 'ext' => 'pdf', 'type' => 'application/pdf', 'proper_filename' => false )
			: $check,
		10,
		3
	);
}
if ( $flag( 'block_sideload' ) ) {
	add_filter( 'wp_handle_sideload_prefilter', static fn( array $file ): array => array( 'error' => 'Blocked by test.' ) + $file );
}
if ( $flag( 'reject_insert' ) ) {
	add_filter( 'wp_insert_post_empty_content', static fn( bool $empty, array $post ): bool => 'all' === $_GET['reject_insert'] || str_ends_with( (string) $post['post_title'], '(cover)' ), 10, 2 );
}

$settings   = new Settings();
$repository = new Newspaper_Repository();
$uploads    = new Upload_Service( $settings, $repository, new Pdf_Thumbnailer() );
header( 'Content-Type: application/json' );
if ( $flag( 'handler' ) ) {
	add_filter( 'wp_redirect', static function ( string $location ): never {
		echo wp_json_encode( array( 'location' => $location ) );
		exit;
	} );
	( new Admin_Page( $settings, $repository, $uploads ) )->handle_upload();
}
$result = $uploads->handle( $_FILES['uploadfile'] ?? array(), (string) ( $_POST['title'] ?? '' ), (string) ( $_POST['date'] ?? '' ) );
echo wp_json_encode(
	is_wp_error( $result )
		? array( 'error' => $result->get_error_code() )
		: array( 'pdf_id' => $result->pdf_id, 'thumbnail_id' => $result->thumbnail_id )
);
