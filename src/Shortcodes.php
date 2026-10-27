<?php
/**
 * Public shortcodes: current download link, current cover and archive grid.
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

namespace SWNewspaper2;

final class Shortcodes {

	private const STYLE_HANDLE = 'swnewspaper2-frontend';

	public function __construct(
		private readonly Settings $settings,
		private readonly Newspaper_Repository $repository
	) {
	}

	public function register(): void {
		add_shortcode( 'swnewspaper2_download', array( $this, 'render_download' ) );
		add_shortcode( 'swnewspaper2_current', array( $this, 'render_current' ) );
		add_shortcode( 'swnewspaper2_archive', array( $this, 'render_archive' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets(): void {
		wp_register_style( self::STYLE_HANDLE, SWNEWSPAPER2_URL . 'assets/frontend.css', array(), SWNEWSPAPER2_VERSION );
	}

	/**
	 * @param array<string, string>|string $atts Shortcode attributes: label.
	 */
	public function render_download( $atts = array() ): string {
		$atts   = shortcode_atts( array( 'label' => __( 'Download', 'sw-newspaper2' ) ), (array) $atts, 'swnewspaper2_download' );
		$latest = $this->repository->latest();
		if ( null === $latest ) {
			return '';
		}

		wp_enqueue_style( self::STYLE_HANDLE );

		return sprintf(
			'<a class="swnp-button" href="%1$s">%2$s</a>',
			esc_url( $this->repository->pdf_url( $latest ) ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * @param array<string, string>|string $atts Shortcode attributes: width (pixels).
	 */
	public function render_current( $atts = array() ): string {
		$atts   = shortcode_atts( array( 'width' => '200' ), (array) $atts, 'swnewspaper2_current' );
		$width  = max( 50, min( 1200, (int) $atts['width'] ) );
		$latest = $this->repository->latest();
		if ( null === $latest ) {
			return '';
		}

		wp_enqueue_style( self::STYLE_HANDLE );

		$dflip_id = $this->settings->get()['dflip_id'];
		if ( $dflip_id > 0 && shortcode_exists( 'dflip' ) ) {
			$inner = do_shortcode( sprintf( '[dflip id="%d" type="thumb"][/dflip]', $dflip_id ) );
		} else {
			$inner = $this->cover_link( $latest );
		}

		return sprintf( '<div class="swnp-current" style="width:%dpx;max-width:100%%;">%s</div>', $width, $inner );
	}

	/**
	 * @param array<string, string>|string $atts Shortcode attributes: columns (1-6), limit.
	 */
	public function render_archive( $atts = array() ): string {
		$atts       = shortcode_atts(
			array(
				'columns' => '4',
				'limit'   => '-1',
			),
			(array) $atts,
			'swnewspaper2_archive'
		);
		$columns    = max( 1, min( 6, (int) $atts['columns'] ) );
		$newspapers = $this->repository->all( (int) $atts['limit'] );
		if ( array() === $newspapers ) {
			return '';
		}

		$this->repository->prime_cover_cache( $newspapers );
		wp_enqueue_style( self::STYLE_HANDLE );

		$html = sprintf( '<div class="swnp-archive" style="--swnp-columns:%d;">', $columns );
		foreach ( $newspapers as $newspaper ) {
			$html .= sprintf(
				'<article class="swnp-card">%1$s<div class="swnp-card__body"><h3 class="swnp-card__title">%2$s</h3><span class="swnp-card__date">%3$s</span></div></article>',
				$this->cover_link( $newspaper ),
				esc_html( get_the_title( $newspaper ) ),
				esc_html( $this->repository->formatted_date( $newspaper ) )
			);
		}

		return $html . '</div>';
	}

	private function cover_link( \WP_Post $newspaper ): string {
		$thumbnail = $this->repository->thumbnail_url( $newspaper );
		$inner     = '' !== $thumbnail
			? sprintf( '<img src="%1$s" alt="%2$s" loading="lazy">', esc_url( $thumbnail ), esc_attr( get_the_title( $newspaper ) ) )
			: '<span class="swnp-cover__fallback">PDF</span>';

		return sprintf(
			'<a class="swnp-cover" href="%1$s" target="_blank" rel="noopener">%2$s</a>',
			esc_url( $this->repository->pdf_url( $newspaper ) ),
			$inner
		);
	}
}
