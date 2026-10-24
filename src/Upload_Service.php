<?php
/**
 * Stores an uploaded PDF in the media library, creates the cover image and updates the dFlip configuration.
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

namespace SWNewspaper2;

use WP_Error;

final class Upload_Service {

	public function __construct(
		private readonly Settings $settings,
		private readonly Newspaper_Repository $repository,
		private readonly Pdf_Thumbnailer $thumbnailer
	) {
	}

	/**
	 * @param array<string, mixed> $file  One entry of $_FILES.
	 * @param string               $title Sanitised newspaper title.
	 * @param string               $date  Issue date as Y-m-d.
	 * @return Upload_Result|WP_Error
	 */
	public function handle( array $file, string $title, string $date ) {
		if ( '' === $title ) {
			return new WP_Error( 'swnewspaper2_title', __( 'Please enter a title.', 'sw-newspaper2' ) );
		}

		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		if ( false === $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
			return new WP_Error( 'swnewspaper2_date', __( 'Please enter a valid date.', 'sw-newspaper2' ) );
		}

		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'swnewspaper2_file', __( 'The file could not be uploaded.', 'sw-newspaper2' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array( 'pdf' => 'application/pdf' ),
			)
		);
		if ( isset( $upload['error'] ) ) {
			return new WP_Error( 'swnewspaper2_file', __( 'Only PDF files can be uploaded.', 'sw-newspaper2' ) );
		}

		$pdf_id = $this->insert_attachment( $upload, $title );
		if ( is_wp_error( $pdf_id ) ) {
			wp_delete_file( $upload['file'] );
			return $pdf_id;
		}

		$thumbnail_id = $this->create_thumbnail( $pdf_id, $upload['file'], $title );

		$this->repository->mark( $pdf_id, $thumbnail_id, $date );
		$this->assign_folder( array_filter( array( $pdf_id, $thumbnail_id ) ) );
		$this->update_dflip( $pdf_id, $thumbnail_id );

		return new Upload_Result( $pdf_id, $thumbnail_id );
	}

	/**
	 * @param array<string, string> $upload Result of wp_handle_upload()/wp_handle_sideload().
	 * @return int|WP_Error
	 */
	private function insert_attachment( array $upload, string $title ) {
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $upload['type'],
				'post_title'     => $title,
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file'],
			0,
			true
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		return (int) $attachment_id;
	}

	/**
	 * A missing cover image is not fatal: the PDF stays published and the admin gets a warning.
	 */
	private function create_thumbnail( int $pdf_id, string $pdf_path, string $title ): int {
		$temp_file = $this->thumbnailer->render_first_page( $pdf_path );
		if ( is_wp_error( $temp_file ) ) {
			return 0;
		}

		// wp_handle_sideload() takes the file array by reference.
		$sideload_file = array(
			'name'     => pathinfo( $pdf_path, PATHINFO_FILENAME ) . '.png',
			'type'     => 'image/png',
			'tmp_name' => $temp_file,
			'size'     => (int) filesize( $temp_file ),
			'error'    => 0,
		);
		$sideload      = wp_handle_sideload(
			$sideload_file,
			array(
				'test_form' => false,
				'mimes'     => array( 'png' => 'image/png' ),
			)
		);
		if ( isset( $sideload['error'] ) ) {
			wp_delete_file( $temp_file );
			return 0;
		}

		$thumbnail_id = $this->insert_attachment( $sideload, $title . ' (cover)' );
		if ( is_wp_error( $thumbnail_id ) ) {
			wp_delete_file( $sideload['file'] );
			return 0;
		}

		return $thumbnail_id;
	}

	/**
	 * @param array<int, int> $attachment_ids
	 */
	private function assign_folder( array $attachment_ids ): void {
		$folder_id = $this->settings->get()['media_folder_id'];
		if ( $folder_id < 1 || ! taxonomy_exists( Newspaper_Repository::FOLDER_TAXONOMY ) ) {
			return;
		}
		foreach ( $attachment_ids as $attachment_id ) {
			wp_set_object_terms( $attachment_id, array( $folder_id ), Newspaper_Repository::FOLDER_TAXONOMY );
		}
	}

	/**
	 * Point the configured dFlip book at the newest upload.
	 */
	private function update_dflip( int $pdf_id, int $thumbnail_id ): void {
		$dflip_id = $this->settings->get()['dflip_id'];
		if ( $dflip_id < 1 || 'dflip' !== get_post_type( $dflip_id ) ) {
			return;
		}

		$data = get_post_meta( $dflip_id, '_dflip_data', true );
		$data = is_array( $data ) ? $data : array();

		$data['pdf_source'] = (string) wp_get_attachment_url( $pdf_id );
		$data['pdf_thumb']  = $thumbnail_id > 0 ? (string) wp_get_attachment_url( $thumbnail_id ) : '';

		update_post_meta( $dflip_id, '_dflip_data', $data );
	}
}
