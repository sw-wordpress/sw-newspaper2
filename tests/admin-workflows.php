<?php
/**
 * Real WordPress regressions for uploads, settings, admin handlers, shortcodes and uninstall.
 * Requires the same disposable database and WordPress core as archive-performance.php.
 * Usage: WP_ROOT=/tmp/wordpress WP_DB_NAME=newspaper_test php tests/admin-workflows.php
 *
 * @package SWNewspaper2
 */
declare(strict_types=1);

use SWNewspaper2\Admin\Admin_Page;
use SWNewspaper2\Newspaper_Repository;
use SWNewspaper2\Pdf_Thumbnailer;
use SWNewspaper2\Plugin;
use SWNewspaper2\Settings;
use SWNewspaper2\Shortcodes;
use SWNewspaper2\Upload_Result;
use SWNewspaper2\Upload_Service;

$complete = false;
register_shutdown_function( static function () use ( &$complete ): void {
	if ( ! $complete ) {
		fwrite( STDERR, 'FAIL: Admin workflow suite did not complete.' . PHP_EOL );
		exit( 1 );
	}
} );
require __DIR__ . '/wordpress.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

/** Thrown instead of redirecting or dying, so handlers can be asserted without ending the process. */
final class SWNP_Test_Stop extends RuntimeException {
}

add_filter( 'wp_redirect', static function ( string $location ): never {
	throw new SWNP_Test_Stop( $location );
} );
add_filter( 'wp_die_handler', static fn() => static function ( $message ): never {
	throw new SWNP_Test_Stop( 'die: ' . ( is_wp_error( $message ) ? $message->get_error_message() : (string) $message ) );
} );
/** Run a handler and return where it redirected to or why it died. */
function swnp_stop( callable $callback ): string {
	try {
		$callback();
	} catch ( SWNP_Test_Stop $stop ) {
		return $stop->getMessage();
	}
	return '';
}


/** Upload through a real multipart request to tests/upload-endpoint.php. */
function swnp_post( string $query, string $name, string $content, array $fields ): array {
	$path = swnp_file( $name, $content )['tmp_name'];
	$curl = curl_init( 'http://' . $GLOBALS['server_address'] . '/?' . $query );
	curl_setopt_array( $curl, array(
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => array( 'uploadfile' => new CURLFile( $path, 'application/pdf', $name ) ) + $fields,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 60,
	) );
	$body = (string) curl_exec( $curl );
	curl_close( $curl );
	// The server wrote to the database; drop this process's stale object cache.
	wp_cache_flush();
	$data = json_decode( $body, true );
	if ( ! is_array( $data ) ) { throw new RuntimeException( 'Invalid endpoint response: ' . $body ); }
	return $data;
}

/** Start PHP's built-in server; the endpoint enables coverage from the inherited environment. */
function swnp_start_server(): array {
	$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
	$address = stream_socket_get_name( $socket, false );
	fclose( $socket );
	$command = array( PHP_BINARY, '-S', $address, __DIR__ . '/upload-endpoint.php' );
	$log = wp_tempnam( 'swnp-server.log' );
	$process = proc_open( $command, array( array( 'file', '/dev/null', 'r' ), array( 'file', $log, 'a' ), array( 'file', $log, 'a' ) ), $pipes );
	if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Could not start the upload server.' ); }
	for ( $i = 0; $i < 100; ++$i ) {
		$connection = @fsockopen( '127.0.0.1', (int) substr( $address, strrpos( $address, ':' ) + 1 ) );
		if ( false !== $connection ) { fclose( $connection ); return array( $process, $address, $log ); }
		usleep( 100000 );
	}
	throw new RuntimeException( 'Upload server did not start: ' . file_get_contents( $log ) );
}

$temp_files = array();
function swnp_file( string $name, string $content ): array {
	$path = wp_tempnam( $name );
	file_put_contents( $path, $content );
	$GLOBALS['temp_files'][] = $path;
	return array( 'name' => $name, 'type' => 'application/pdf', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen( $content ) );
}

function swnp_render( Admin_Page $admin, array $get ): string {
	$_GET = $get;
	ob_start();
	try {
		$admin->render();
	} finally {
		$html = (string) ob_get_clean();
		$_GET = array();
	}
	return $html;
}

function swnp_png(): string {
	$image = new Imagick();
	$image->newImage( 4, 6, 'red' );
	$image->setImageFormat( 'png' );
	return $image->getImageBlob();
}

$pdf_content = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
	. "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 10 10]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
$admin_id = get_current_user_id();
$author_id = 0;
$subscriber_id = 0;
$dflip_id = 0;
$folder_id = 0;
$ids = array();
$server = null;
$server_address = '';
$settings = new Settings();
$repository = new Newspaper_Repository();
$thumbnailer = new Pdf_Thumbnailer();
$uploads = new Upload_Service( $settings, $repository, $thumbnailer );
$admin = new Admin_Page( $settings, $repository, $uploads );
$shortcodes = new Shortcodes( $settings, $repository );
try {
	swnewspaper2_check( array() === $repository->all(), 'The suite starts with an empty archive.' );
	Plugin::activate();
	Plugin::instance()->load_textdomain();
	swnewspaper2_check( array() === $repository->all(), 'Activation without the legacy plugin imports nothing.' );

	// Shortcodes and admin views for an empty archive.
	swnewspaper2_check( '' === $shortcodes->render_download() && '' === $shortcodes->render_current() && '' === $shortcodes->render_archive(), 'Shortcodes render nothing without newspapers.' );
	$html = swnp_render( $admin, array( 'tab' => 'archive' ) );
	swnewspaper2_check( str_contains( $html, 'swnp-empty' ), 'The admin archive shows the empty state.' );

	// Settings without the optional integrations.
	swnewspaper2_check( array() === $settings->dflip_configs() && array() === $settings->media_folders(), 'Integrations are empty when dFlip and WP Media Folder are missing.' );
	update_option( Settings::OPTION, 'corrupt' );
	swnewspaper2_check( array( 'dflip_id' => 0, 'media_folder_id' => 0 ) === $settings->get(), 'Corrupt settings fall back to defaults.' );
	$settings->save( 999999, 999999 );
	swnewspaper2_check( array( 'dflip_id' => 0, 'media_folder_id' => 0 ) === $settings->get(), 'Settings reject unknown dFlip books and folders.' );
	$html = swnp_render( $admin, array( 'tab' => 'settings' ) );
	swnewspaper2_check( str_contains( $html, 'Install the dFlip plugin' ) && str_contains( $html, 'Install WP Media Folder' ), 'The settings tab explains missing integrations.' );

	// Register stand-ins for the optional dFlip and WP Media Folder plugins.
	register_post_type( 'dflip' );
	register_taxonomy( Newspaper_Repository::FOLDER_TAXONOMY, 'attachment' );
	$dflip_id = wp_insert_post( array( 'post_type' => 'dflip', 'post_title' => 'Book', 'post_status' => 'publish' ), true );
	$term = wp_insert_term( 'Newspapers ' . wp_generate_password( 6, false ), Newspaper_Repository::FOLDER_TAXONOMY );
	if ( is_wp_error( $dflip_id ) || is_wp_error( $term ) ) { throw new RuntimeException( 'Could not create integration fixtures.' ); }
	$folder_id = (int) $term['term_id'];
	update_post_meta( $dflip_id, '_dflip_data', array( 'pdf_source' => 'old', 'other' => 'kept' ) );

	// Settings handler: nonce, capability and persistence.
	$_POST = array( 'dflip_id' => (string) $dflip_id, 'media_folder_id' => (string) $folder_id );
	$_REQUEST = array( '_wpnonce' => 'invalid' );
	swnewspaper2_check( str_starts_with( swnp_stop( array( $admin, 'handle_settings' ) ), 'die:' ), 'Settings reject an invalid nonce.' );
	$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'swnewspaper2_settings' ) );
	$location = swnp_stop( array( $admin, 'handle_settings' ) );
	swnewspaper2_check( str_contains( $location, 'tab=settings' ) && str_contains( $location, 'swnp_notice=settings_saved' ), 'Saving settings redirects with a notice.' );
	swnewspaper2_check( array( 'dflip_id' => $dflip_id, 'media_folder_id' => $folder_id ) === $settings->get(), 'Valid dFlip book and folder are stored.' );
	$html = swnp_render( $admin, array( 'tab' => 'settings', 'swnp_notice' => 'settings_saved' ) );
	swnewspaper2_check( str_contains( $html, 'Settings saved.' ) && str_contains( $html, 'value="' . $dflip_id . '"  selected' ) && str_contains( $html, 'value="' . $folder_id . '"  selected' ), 'The settings tab selects the stored integrations.' );

	// Validation errors of the upload service.
	$pdf = swnp_file( 'issue.pdf', $pdf_content );
	$errors = array(
		'swnewspaper2_title' => $uploads->handle( $pdf, '', '2024-05-01' ),
		'swnewspaper2_date'  => $uploads->handle( $pdf, 'Issue', '2024-02-30' ),
		'swnewspaper2_file'  => $uploads->handle( array( 'error' => UPLOAD_ERR_NO_FILE ) + $pdf, 'Issue', '2024-05-01' ),
	);
	foreach ( $errors as $code => $error ) {
		swnewspaper2_check( is_wp_error( $error ) && $code === $error->get_error_code(), "Upload validation returns $code." );
	}
	$server = swnp_start_server();
	$server_address = $server[1];
	$fields = array( 'title' => 'Issue', 'date' => '2024-05-01' );
	swnewspaper2_check( array( 'error' => 'swnewspaper2_file' ) === swnp_post( '', 'fake.pdf', 'plain text, not a PDF', $fields ), 'Uploads whose content is not a PDF are rejected.' );
	swnewspaper2_check( array( 'error' => 'swnewspaper2_file' ) === swnp_post( '', 'image.png', swnp_png(), $fields ), 'Non-PDF file types are rejected.' );
	swnewspaper2_check( array( 'error' => 'empty_content' ) === swnp_post( 'reject_insert=all', 'issue.pdf', $pdf_content, $fields ) && array() === $repository->all(), 'A failed attachment insert publishes nothing.' );

	// Cover rendering. A PNG payload keeps Imagick independent of the host's Ghostscript/PDF policy.
	$broken = $thumbnailer->render_first_page( swnp_file( 'broken.pdf', 'no image data' )['tmp_name'] );
	swnewspaper2_check( is_wp_error( $broken ) && 'swnewspaper2_render_failed' === $broken->get_error_code(), 'Unreadable PDFs produce a render error.' );
	swnewspaper2_check( $thumbnailer->is_available(), 'Imagick is available for cover rendering.' );
	$png = $thumbnailer->render_first_page( swnp_file( 'image.pdf', swnp_png() )['tmp_name'] );
	swnewspaper2_check( is_string( $png ) && 'image/png' === wp_get_image_mime( $png ), 'The first page is rendered as PNG.' );
	wp_delete_file( $png );
	$result = swnp_post( 'as_pdf=1', 'cover-issue.pdf', swnp_png(), array( 'title' => 'Cover issue', 'date' => '2024-05-02' ) );
	$ids[] = $result['pdf_id'] ?? 0;
	$ids[] = $result['thumbnail_id'] ?? 0;
	swnewspaper2_check( ( $result['pdf_id'] ?? 0 ) > 0 && ( $result['thumbnail_id'] ?? 0 ) > 0, 'Uploads create a PDF and a cover attachment.' );
	$newspaper = get_post( $result['pdf_id'] );
	swnewspaper2_check( $repository->is_newspaper( $newspaper->ID ) && '2024-05-02' === $repository->date( $newspaper ) && $result['thumbnail_id'] === $repository->thumbnail_id( $newspaper ), 'Uploads are marked with date and cover.' );
	swnewspaper2_check( 'Cover issue (cover)' === get_the_title( $result['thumbnail_id'] ) && 'image/png' === get_post_mime_type( $result['thumbnail_id'] ), 'The cover is a PNG attachment named after the issue.' );
	$in_folder = static fn( int $id ): bool => array( $folder_id ) === wp_get_object_terms( $id, Newspaper_Repository::FOLDER_TAXONOMY, array( 'fields' => 'ids' ) );
	swnewspaper2_check( $in_folder( $result['pdf_id'] ) && $in_folder( $result['thumbnail_id'] ), 'PDF and cover are moved into the configured folder.' );
	$dflip = get_post_meta( $dflip_id, '_dflip_data', true );
	swnewspaper2_check( wp_get_attachment_url( $result['pdf_id'] ) === $dflip['pdf_source'] && wp_get_attachment_url( $result['thumbnail_id'] ) === $dflip['pdf_thumb'] && 'kept' === $dflip['other'], 'The dFlip book points at the newest upload and keeps other data.' );

	// Missing covers are not fatal; the dFlip thumbnail is cleared.
	$result = swnp_post( 'as_pdf=1&block_sideload=1', 'no-sideload.pdf', swnp_png(), array( 'title' => 'No sideload', 'date' => '2024-05-03' ) );
	$ids[] = $result['pdf_id'] ?? 0;
	swnewspaper2_check( ( $result['pdf_id'] ?? 0 ) > 0 && 0 === $result['thumbnail_id'], 'A rejected cover sideload keeps the PDF without cover.' );
	$result = swnp_post( 'as_pdf=1&reject_insert=cover', 'no-cover.pdf', swnp_png(), array( 'title' => 'No cover', 'date' => '2024-05-04' ) );
	$ids[] = $result['pdf_id'] ?? 0;
	swnewspaper2_check( ( $result['pdf_id'] ?? 0 ) > 0 && 0 === $result['thumbnail_id'], 'A failed cover insert keeps the PDF without cover.' );
	$dflip = get_post_meta( $dflip_id, '_dflip_data', true );
	swnewspaper2_check( '' === $dflip['pdf_thumb'] && wp_get_attachment_url( $result['pdf_id'] ) === $dflip['pdf_source'], 'The dFlip thumbnail is cleared without a cover.' );

	// Upload handler: nonce, real PDF (cover depends on the host's PDF support) and notices.
	$fields = array( 'title' => 'Handler issue', 'date' => '2024-05-05', '_wpnonce' => wp_create_nonce( 'swnewspaper2_upload' ) );
	$response = swnp_post( 'handler=1', 'handler.pdf', $pdf_content, $fields );
	swnewspaper2_check( 1 === preg_match( '/swnp_notice=uploaded(_no_cover)?$/', $response['location'] ?? '' ), 'The upload handler publishes and redirects.' );
	$latest = $repository->latest();
	$ids[] = $latest->ID;
	$ids[] = $repository->thumbnail_id( $latest );
	swnewspaper2_check( 'Handler issue' === get_the_title( $latest ) && 'application/pdf' === get_post_mime_type( $latest ), 'The upload handler stores the submitted PDF and title.' );
	$response = swnp_post( 'handler=1', 'handler.pdf', $pdf_content, array( 'title' => '' ) + $fields );
	swnewspaper2_check( str_ends_with( $response['location'] ?? '', 'swnp_notice=swnewspaper2_title' ), 'The upload handler redirects with validation errors.' );

	// Frontend shortcodes with newspapers.
	$width = do_shortcode( '[swnewspaper2_current width="5000"]' );
	swnewspaper2_check( str_contains( $width, 'width:1200px' ) && str_contains( $width, 'class="swnp-cover"' ), 'The current cover clamps its width and links the newest PDF.' );
	add_shortcode( 'dflip', static fn( $atts ): string => '<div class="dflip-stub" data-id="' . esc_attr( $atts['id'] ) . '"></div>' );
	$dflip_html = do_shortcode( '[swnewspaper2_current]' );
	remove_shortcode( 'dflip' );
	swnewspaper2_check( str_contains( $dflip_html, 'data-id="' . $dflip_id . '"' ) && str_contains( $dflip_html, 'width:200px' ), 'The current cover uses the configured dFlip book.' );
	$download = do_shortcode( '[swnewspaper2_download label="<b>Get</b>"]' );
	swnewspaper2_check( str_contains( $download, '&lt;b&gt;Get&lt;/b&gt;' ) && str_contains( $download, (string) wp_get_attachment_url( $latest->ID ) ), 'The download label is escaped and links the newest PDF.' );

	// Admin page: menu, assets, tabs, notices and permissions.
	$admin->enqueue( 'index.php' );
	swnewspaper2_check( ! wp_script_is( 'swnewspaper2-admin', 'enqueued' ), 'Admin assets stay off unrelated screens.' );
	$admin->add_menu();
	$admin->enqueue( 'toplevel_page_' . Admin_Page::SLUG );
	swnewspaper2_check( wp_script_is( 'swnewspaper2-admin', 'enqueued' ) && wp_style_is( 'swnewspaper2-admin', 'enqueued' ), 'Admin assets load on the plugin page.' );
	swnewspaper2_check( str_contains( (string) wp_scripts()->get_data( 'swnewspaper2-admin', 'data' ), '"noFile"' ), 'Admin script receives localized texts.' );
	$html = swnp_render( $admin, array( 'swnp_notice' => 'uploaded_no_cover' ) );
	swnewspaper2_check( str_contains( $html, 'name="uploadfile"' ) && str_contains( $html, 'swnp-notice--warning' ), 'The upload tab is the default and shows warning notices.' );
	$html = swnp_render( $admin, array( 'tab' => 'unknown', 'swnp_notice' => '<script>' ) );
	swnewspaper2_check( str_contains( $html, 'name="uploadfile"' ) && ! str_contains( $html, 'swnp-notice' ), 'Unknown tabs and notices fall back safely.' );
	$html = swnp_render( $admin, array( 'tab' => 'archive' ) );
	swnewspaper2_check( 4 === substr_count( $html, 'class="swnp-card swnp-item"' ) && substr_count( $html, '<img src=' ) >= 1 && str_contains( $html, 'dashicons-media-document' ), 'The admin archive lists issues with and without covers.' );

	$author_id = wp_insert_user( array( 'user_login' => 'swnp_author_' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password( 24 ), 'role' => 'author' ) );
	$subscriber_id = wp_insert_user( array( 'user_login' => 'swnp_subscriber_' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password( 24 ), 'role' => 'subscriber' ) );
	if ( is_wp_error( $author_id ) || is_wp_error( $subscriber_id ) ) { throw new RuntimeException( 'Could not create test users.' ); }
	wp_set_current_user( $author_id );
	$html = swnp_render( $admin, array( 'tab' => 'settings' ) );
	swnewspaper2_check( str_contains( $html, 'name="uploadfile"' ) && ! str_contains( $html, 'tab=settings' ), 'Authors cannot open or see the settings tab.' );
	$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'swnewspaper2_settings' ) );
	swnewspaper2_check( str_starts_with( swnp_stop( array( $admin, 'handle_settings' ) ), 'die:' ), 'Authors cannot save settings.' );
	$_POST = array( 'newspaper_id' => (string) $latest->ID );
	$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'swnewspaper2_delete_' . $latest->ID ) );
	swnewspaper2_check( str_starts_with( swnp_stop( array( $admin, 'handle_delete' ) ), 'die:' ) && $repository->is_newspaper( $latest->ID ), 'Authors cannot delete newspapers of other users.' );
	wp_set_current_user( $subscriber_id );
	swnewspaper2_check( str_starts_with( swnp_stop( static fn() => swnp_render( $admin, array() ) ), 'die:' ), 'Users without upload rights cannot open the admin page.' );
	$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'swnewspaper2_upload' ) );
	swnewspaper2_check( str_starts_with( swnp_stop( array( $admin, 'handle_upload' ) ), 'die:' ), 'Users without upload rights cannot upload.' );
	wp_set_current_user( $admin_id );

	// Delete handler.
	$other = get_post( $ids[0] );
	$_POST = array( 'newspaper_id' => (string) $other->ID );
	$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'swnewspaper2_delete_' . $other->ID ) );
	$keep = static fn() => false;
	add_filter( 'pre_delete_attachment', $keep );
	$location = swnp_stop( array( $admin, 'handle_delete' ) );
	remove_filter( 'pre_delete_attachment', $keep );
	swnewspaper2_check( str_contains( $location, 'swnp_notice=delete_failed' ) && $repository->is_newspaper( $other->ID ), 'A blocked deletion reports a failure.' );
	$cover = $repository->thumbnail_id( $other );
	$location = swnp_stop( array( $admin, 'handle_delete' ) );
	swnewspaper2_check( str_contains( $location, 'tab=archive' ) && str_contains( $location, 'swnp_notice=deleted' ), 'Deleting a newspaper redirects with a notice.' );
	swnewspaper2_check( null === get_post( $other->ID ) && null === get_post( $cover ), 'Deleting removes the PDF and its cover.' );
	$_POST = array( 'newspaper_id' => (string) $dflip_id );
	$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'swnewspaper2_delete_' . $dflip_id ) );
	swnewspaper2_check( str_starts_with( swnp_stop( array( $admin, 'handle_delete' ) ), 'die:' ) && null !== get_post( $dflip_id ), 'Only newspapers can be deleted.' );

	// Uninstall removes settings but keeps the media library.
	define( 'WP_UNINSTALL_PLUGIN', 'sw-newspaper2/sw-newspaper2.php' );
	require dirname( __DIR__ ) . '/uninstall.php';
	swnewspaper2_check( false === get_option( Settings::OPTION ) && null !== get_post( $latest->ID ), 'Uninstall deletes settings but keeps newspapers.' );
} finally {
	if ( null !== $server ) {
		proc_terminate( $server[0] );
		proc_close( $server[0] );
		$temp_files[] = $server[2];
	}
	wp_set_current_user( $admin_id );
	$_GET = $_POST = $_REQUEST = $_FILES = array();
	foreach ( $repository->all() as $newspaper ) { $ids[] = $newspaper->ID; $ids[] = $repository->thumbnail_id( $newspaper ); }
	foreach ( array_unique( array_filter( $ids ) ) as $id ) { wp_delete_attachment( $id, true ); }
	foreach ( array_filter( array( $author_id, $subscriber_id ), 'is_int' ) as $user_id ) { if ( $user_id > 0 ) { wp_delete_user( $user_id ); } }
	if ( is_int( $dflip_id ) && $dflip_id > 0 ) { wp_delete_post( $dflip_id, true ); }
	if ( $folder_id > 0 ) { wp_delete_term( $folder_id, Newspaper_Repository::FOLDER_TAXONOMY ); }
	delete_option( Settings::OPTION );
	foreach ( $temp_files as $file ) { if ( is_file( $file ) ) { unlink( $file ); } }
}
$complete = true;
