<?php
/**
 * Settings tab and shortcode documentation.
 *
 * @package SWNewspaper2
 *
 * @var array<string, mixed> $data
 */

defined( 'ABSPATH' ) || exit;
?>
<form class="swnp-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="swnewspaper2_settings">
	<?php wp_nonce_field( 'swnewspaper2_settings' ); ?>

	<div class="swnp-card__head">
		<h2><?php esc_html_e( 'Integrations', 'sw-newspaper2' ); ?></h2>
	</div>

	<div class="swnp-row">
		<div class="swnp-row__label">
			<label for="swnp-dflip"><?php esc_html_e( 'dFlip book', 'sw-newspaper2' ); ?></label>
			<p><?php esc_html_e( 'The newest upload is set as source of this book.', 'sw-newspaper2' ); ?></p>
		</div>
		<div class="swnp-row__field">
			<?php if ( array() === $data['dflip_configs'] ) : ?>
				<p class="swnp-hint"><?php esc_html_e( 'Install the dFlip plugin and create a book to use the flipbook.', 'sw-newspaper2' ); ?></p>
			<?php else : ?>
				<select id="swnp-dflip" name="dflip_id" class="swnp-input">
					<option value="0"><?php esc_html_e( 'None', 'sw-newspaper2' ); ?></option>
					<?php foreach ( $data['dflip_configs'] as $config ) : ?>
						<option value="<?php echo esc_attr( (string) $config->ID ); ?>" <?php selected( $data['settings']['dflip_id'], $config->ID ); ?>>
							<?php echo esc_html( get_the_title( $config ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		</div>
	</div>

	<div class="swnp-row">
		<div class="swnp-row__label">
			<label for="swnp-folder"><?php esc_html_e( 'Media folder', 'sw-newspaper2' ); ?></label>
			<p><?php esc_html_e( 'Uploaded PDFs and covers are moved into this folder.', 'sw-newspaper2' ); ?></p>
		</div>
		<div class="swnp-row__field">
			<?php if ( array() === $data['folders'] ) : ?>
				<p class="swnp-hint"><?php esc_html_e( 'Install WP Media Folder to sort uploads into a folder.', 'sw-newspaper2' ); ?></p>
			<?php else : ?>
				<select id="swnp-folder" name="media_folder_id" class="swnp-input">
					<option value="0"><?php esc_html_e( 'None', 'sw-newspaper2' ); ?></option>
					<?php foreach ( $data['folders'] as $folder ) : ?>
						<option value="<?php echo esc_attr( (string) $folder->term_id ); ?>" <?php selected( $data['settings']['media_folder_id'], $folder->term_id ); ?>>
							<?php echo esc_html( $folder->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		</div>
	</div>

	<div class="swnp-card__foot">
		<button type="submit" class="swnp-btn swnp-btn--primary"><?php esc_html_e( 'Save', 'sw-newspaper2' ); ?></button>
	</div>
</form>

<div class="swnp-card">
	<div class="swnp-card__head">
		<h2><?php esc_html_e( 'Shortcodes', 'sw-newspaper2' ); ?></h2>
	</div>
	<dl class="swnp-docs">
		<dt><code>[swnewspaper2_archive columns="4" limit="12"]</code></dt>
		<dd><?php esc_html_e( 'Grid of all issues, newest first.', 'sw-newspaper2' ); ?></dd>
		<dt><code>[swnewspaper2_current width="300"]</code></dt>
		<dd><?php esc_html_e( 'Cover of the newest issue. Uses the dFlip book when configured.', 'sw-newspaper2' ); ?></dd>
		<dt><code>[swnewspaper2_download label="Download"]</code></dt>
		<dd><?php esc_html_e( 'Download button for the newest issue.', 'sw-newspaper2' ); ?></dd>
	</dl>
</div>
