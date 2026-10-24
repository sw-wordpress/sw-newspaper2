<?php
/**
 * Renders the first page of a PDF as PNG image.
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

namespace SWNewspaper2;

use WP_Error;

final class Pdf_Thumbnailer {

	private const RESOLUTION = 150;

	public function is_available(): bool {
		return class_exists( \Imagick::class );
	}

	/**
	 * Render page one into a temporary PNG file.
	 *
	 * The caller owns the returned file and must move or delete it.
	 *
	 * @return string|WP_Error Absolute path of the temporary PNG.
	 */
	public function render_first_page( string $pdf_path ) {
		if ( ! $this->is_available() ) {
			return new WP_Error( 'swnewspaper2_no_imagick', __( 'The PHP Imagick extension is required to create cover images.', 'sw-newspaper2' ) );
		}

		$temp_dir = get_temp_dir();
		$target   = trailingslashit( $temp_dir ) . wp_unique_filename( $temp_dir, 'swnewspaper2-' . wp_generate_password( 8, false ) . '.png' );

		try {
			$imagick = new \Imagick();
			// The resolution has to be set before reading, otherwise the PDF is rasterised at 72 dpi.
			$imagick->setResolution( self::RESOLUTION, self::RESOLUTION );
			$imagick->readImage( $pdf_path . '[0]' );
			$imagick->setImageBackgroundColor( 'white' );
			$imagick->setImageAlphaChannel( \Imagick::ALPHACHANNEL_REMOVE );
			$imagick->setImageFormat( 'png' );
			$imagick->writeImage( $target );
			$imagick->clear();
		} catch ( \ImagickException $exception ) {
			wp_delete_file( $target );
			return new WP_Error(
				'swnewspaper2_render_failed',
				__( 'The cover image could not be rendered from the PDF.', 'sw-newspaper2' ),
				array( 'reason' => $exception->getMessage() )
			);
		}

		return $target;
	}
}
