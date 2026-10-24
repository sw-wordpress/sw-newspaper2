<?php
/**
 * Admin shell: header, tab navigation, notice and the active tab.
 *
 * @package SWNewspaper2
 *
 * @var array<string, mixed> $data
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap swnp-wrap">
	<div class="swnp-root">
		<header class="swnp-header">
			<span class="swnp-logo" aria-hidden="true"><span class="dashicons dashicons-media-document"></span></span>
			<div>
				<h1 class="swnp-title"><?php esc_html_e( 'Newspaper', 'sw-newspaper2' ); ?></h1>
				<p class="swnp-subtitle">
					<?php
					/* translators: %s: plugin version */
					echo esc_html( sprintf( __( 'sw-newspaper2 v%s', 'sw-newspaper2' ), SWNEWSPAPER2_VERSION ) );
					?>
				</p>
			</div>
		</header>

		<nav class="swnp-tabs" aria-label="<?php esc_attr_e( 'Newspaper sections', 'sw-newspaper2' ); ?>">
			<?php foreach ( $data['tabs'] as $slug => $tab_item ) : ?>
				<a class="swnp-tab<?php echo $slug === $data['tab'] ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( $tab_item['url'] ); ?>"
					<?php echo $slug === $data['tab'] ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( $tab_item['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<?php if ( null !== $data['notice'] ) : ?>
			<div class="swnp-notice swnp-notice--<?php echo esc_attr( $data['notice']['type'] ); ?>" role="status">
				<?php echo esc_html( $data['notice']['message'] ); ?>
			</div>
		<?php endif; ?>

		<?php $this->include_template( 'tab-' . $data['tab'], $data ); ?>

		<p class="swnp-footer">
			<?php esc_html_e( 'Powered by sw-newspaper2 from Stefan Warnat', 'sw-newspaper2' ); ?>
		</p>
	</div>
</div>
