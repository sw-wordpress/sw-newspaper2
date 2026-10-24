<?php
/**
 * Archive tab.
 *
 * @package SWNewspaper2
 *
 * @var array<string, mixed> $data
 */

defined( 'ABSPATH' ) || exit;

/** @var SWNewspaper2\Newspaper_Repository $repository */
$repository = $data['repository'];
?>
<?php if ( array() === $data['newspapers'] ) : ?>
	<div class="swnp-card swnp-empty">
		<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
		<p><?php esc_html_e( 'No newspapers published yet.', 'sw-newspaper2' ); ?></p>
	</div>
<?php else : ?>
	<div class="swnp-grid">
		<?php foreach ( $data['newspapers'] as $newspaper ) : ?>
			<?php $thumbnail = $repository->thumbnail_url( $newspaper ); ?>
			<article class="swnp-card swnp-item">
				<div class="swnp-item__cover">
					<?php if ( '' !== $thumbnail ) : ?>
						<img src="<?php echo esc_url( $thumbnail ); ?>" alt="" loading="lazy">
					<?php else : ?>
						<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
					<?php endif; ?>
				</div>
				<h2 class="swnp-item__title"><?php echo esc_html( get_the_title( $newspaper ) ); ?></h2>
				<p class="swnp-item__date"><?php echo esc_html( $repository->formatted_date( $newspaper ) ); ?></p>
				<div class="swnp-card__foot">
					<a class="swnp-btn swnp-btn--primary" href="<?php echo esc_url( $repository->pdf_url( $newspaper ) ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Open PDF', 'sw-newspaper2' ); ?>
					</a>
					<?php if ( current_user_can( 'delete_post', $newspaper->ID ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-swnp-confirm>
							<input type="hidden" name="action" value="swnewspaper2_delete">
							<input type="hidden" name="newspaper_id" value="<?php echo esc_attr( (string) $newspaper->ID ); ?>">
							<?php wp_nonce_field( 'swnewspaper2_delete_' . $newspaper->ID ); ?>
							<button type="submit" class="swnp-btn swnp-btn--danger"><?php esc_html_e( 'Delete', 'sw-newspaper2' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
