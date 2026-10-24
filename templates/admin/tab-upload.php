<?php
/**
 * Upload tab.
 *
 * @package SWNewspaper2
 *
 * @var array<string, mixed> $data
 */

defined( 'ABSPATH' ) || exit;
?>
<form class="swnp-card" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="swnewspaper2_upload">
	<?php wp_nonce_field( 'swnewspaper2_upload' ); ?>

	<div class="swnp-card__head">
		<h2><?php esc_html_e( 'Publish a new issue', 'sw-newspaper2' ); ?></h2>
		<span class="swnp-badge"><?php esc_html_e( 'PDF', 'sw-newspaper2' ); ?></span>
	</div>

	<div class="swnp-row">
		<div class="swnp-row__label">
			<label for="swnp-title"><?php esc_html_e( 'Title', 'sw-newspaper2' ); ?></label>
			<p><?php esc_html_e( 'Shown below the cover in the archive.', 'sw-newspaper2' ); ?></p>
		</div>
		<div class="swnp-row__field">
			<input type="text" id="swnp-title" name="title" class="swnp-input" required>
		</div>
	</div>

	<div class="swnp-row">
		<div class="swnp-row__label">
			<label for="swnp-date"><?php esc_html_e( 'Issue date', 'sw-newspaper2' ); ?></label>
			<p><?php esc_html_e( 'The archive is sorted by this date, newest first.', 'sw-newspaper2' ); ?></p>
		</div>
		<div class="swnp-row__field">
			<input type="date" id="swnp-date" name="date" class="swnp-input" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" required>
		</div>
	</div>

	<div class="swnp-row">
		<div class="swnp-row__label">
			<label for="swnp-file"><?php esc_html_e( 'PDF file', 'sw-newspaper2' ); ?></label>
			<p><?php esc_html_e( 'The cover image is created from the first page.', 'sw-newspaper2' ); ?></p>
		</div>
		<div class="swnp-row__field">
			<label class="swnp-drop" for="swnp-file">
				<span class="dashicons dashicons-upload" aria-hidden="true"></span>
				<strong><?php esc_html_e( 'Choose a PDF file', 'sw-newspaper2' ); ?></strong>
				<span class="swnp-drop__name" data-swnp-filename><?php esc_html_e( 'No file selected', 'sw-newspaper2' ); ?></span>
				<input type="file" id="swnp-file" name="uploadfile" accept="application/pdf,.pdf" required>
			</label>
		</div>
	</div>

	<div class="swnp-card__foot">
		<button type="submit" class="swnp-btn swnp-btn--primary"><?php esc_html_e( 'Publish', 'sw-newspaper2' ); ?></button>
	</div>
</form>
