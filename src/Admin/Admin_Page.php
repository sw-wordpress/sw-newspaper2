<?php
/**
 * Admin integration: menu page with the upload, archive and settings tabs plus their form handlers.
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

namespace SWNewspaper2\Admin;

use SWNewspaper2\Capabilities;
use SWNewspaper2\Newspaper_Repository;
use SWNewspaper2\Settings;
use SWNewspaper2\Upload_Service;

final class Admin_Page {

	public const SLUG    = 'sw-newspaper2';
	private const HANDLE = 'swnewspaper2-admin';

	private const TABS = array( 'upload', 'archive', 'settings' );

	private string $hook = '';

	public function __construct(
		private readonly Settings $settings,
		private readonly Newspaper_Repository $repository,
		private readonly Upload_Service $uploads
	) {
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_swnewspaper2_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_swnewspaper2_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_swnewspaper2_settings', array( $this, 'handle_settings' ) );
	}

	public function add_menu(): void {
		$this->hook = (string) add_menu_page(
			__( 'Newspaper', 'sw-newspaper2' ),
			__( 'Newspaper', 'sw-newspaper2' ),
			Capabilities::PUBLISH,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-media-document',
			6
		);
	}

	public function enqueue( string $hook ): void {
		if ( $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_style( self::HANDLE, SWNEWSPAPER2_URL . 'assets/admin.css', array(), SWNEWSPAPER2_VERSION );
		wp_enqueue_script( self::HANDLE, SWNEWSPAPER2_URL . 'assets/admin.js', array(), SWNEWSPAPER2_VERSION, true );
		wp_localize_script(
			self::HANDLE,
			'swnewspaper2Admin',
			array(
				'confirmDelete' => __( 'Delete this newspaper and its cover image permanently?', 'sw-newspaper2' ),
				'noFile'        => __( 'No file selected', 'sw-newspaper2' ),
			)
		);
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::PUBLISH ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sw-newspaper2' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab selection is read-only navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'upload';
		if ( ! in_array( $tab, self::TABS, true ) || ( 'settings' === $tab && ! current_user_can( Capabilities::SETTINGS ) ) ) {
			$tab = 'upload';
		}

		$data = array(
			'tab'    => $tab,
			'tabs'   => $this->tabs(),
			'notice' => $this->current_notice(),
		);

		if ( 'archive' === $tab ) {
			$data['newspapers'] = $this->repository->all();
			$data['repository'] = $this->repository;
		}
		if ( 'settings' === $tab ) {
			$data['settings']      = $this->settings->get();
			$data['dflip_configs'] = $this->settings->dflip_configs();
			$data['folders']       = $this->settings->media_folders();
		}

		$this->include_template( 'layout', $data );
	}

	public function handle_upload(): void {
		check_admin_referer( 'swnewspaper2_upload' );
		$this->require_capability( Capabilities::PUBLISH );

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$date  = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- The upload service validates the structure and wp_handle_upload() the content.
		$file = isset( $_FILES['uploadfile'] ) && is_array( $_FILES['uploadfile'] ) ? $_FILES['uploadfile'] : array();

		$result = $this->uploads->handle( $file, $title, $date );
		if ( is_wp_error( $result ) ) {
			$this->redirect( 'upload', $result->get_error_code() );
		}

		$this->redirect( 'upload', $result->has_thumbnail() ? 'uploaded' : 'uploaded_no_cover' );
	}

	public function handle_delete(): void {
		$id = isset( $_POST['newspaper_id'] ) ? absint( $_POST['newspaper_id'] ) : 0;
		check_admin_referer( 'swnewspaper2_delete_' . $id );

		if ( $id < 1 || ! $this->repository->is_newspaper( $id ) || ! current_user_can( 'delete_post', $id ) ) {
			wp_die( esc_html__( 'You do not have permission to delete this newspaper.', 'sw-newspaper2' ), 403 );
		}

		$this->redirect( 'archive', $this->repository->delete( $id ) ? 'deleted' : 'delete_failed' );
	}

	public function handle_settings(): void {
		check_admin_referer( 'swnewspaper2_settings' );
		$this->require_capability( Capabilities::SETTINGS );

		$dflip_id  = isset( $_POST['dflip_id'] ) ? absint( $_POST['dflip_id'] ) : 0;
		$folder_id = isset( $_POST['media_folder_id'] ) ? absint( $_POST['media_folder_id'] ) : 0;
		$this->settings->save( $dflip_id, $folder_id );

		$this->redirect( 'settings', 'settings_saved' );
	}

	/**
	 * @return array<string, array{label: string, url: string}>
	 */
	private function tabs(): array {
		$tabs = array(
			'upload'  => __( 'Upload', 'sw-newspaper2' ),
			'archive' => __( 'Archive', 'sw-newspaper2' ),
		);
		if ( current_user_can( Capabilities::SETTINGS ) ) {
			$tabs['settings'] = __( 'Settings', 'sw-newspaper2' );
		}

		$result = array();
		foreach ( $tabs as $slug => $label ) {
			$result[ $slug ] = array(
				'label' => $label,
				'url'   => add_query_arg(
					array(
						'page' => self::SLUG,
						'tab'  => $slug,
					),
					admin_url( 'admin.php' )
				),
			);
		}
		return $result;
	}

	/**
	 * Notice texts are an allowlist, so the query argument is never echoed.
	 *
	 * @return array{type: string, message: string}|null
	 */
	private function current_notice(): ?array {
		$messages = array(
			'uploaded'           => array( 'success', __( 'The newspaper was published.', 'sw-newspaper2' ) ),
			'uploaded_no_cover'  => array( 'warning', __( 'The newspaper was published, but the cover image could not be created. Check that the PHP Imagick extension can read PDF files.', 'sw-newspaper2' ) ),
			'deleted'            => array( 'success', __( 'The newspaper was deleted.', 'sw-newspaper2' ) ),
			'delete_failed'      => array( 'error', __( 'The newspaper could not be deleted.', 'sw-newspaper2' ) ),
			'settings_saved'     => array( 'success', __( 'Settings saved.', 'sw-newspaper2' ) ),
			'swnewspaper2_title' => array( 'error', __( 'Please enter a title.', 'sw-newspaper2' ) ),
			'swnewspaper2_date'  => array( 'error', __( 'Please enter a valid date.', 'sw-newspaper2' ) ),
			'swnewspaper2_file'  => array( 'error', __( 'The file could not be uploaded. Only PDF files are accepted.', 'sw-newspaper2' ) ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects a message from the allowlist above.
		$code = isset( $_GET['swnp_notice'] ) ? sanitize_key( wp_unslash( $_GET['swnp_notice'] ) ) : '';
		if ( ! isset( $messages[ $code ] ) ) {
			return null;
		}
		return array(
			'type'    => $messages[ $code ][0],
			'message' => $messages[ $code ][1],
		);
	}

	private function require_capability( string $capability ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sw-newspaper2' ), 403 );
		}
	}

	private function redirect( string $tab, string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::SLUG,
					'tab'         => $tab,
					'swnp_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Templates read their variables from the $data array of this method.
	 *
	 * @param string               $name Template file name without extension.
	 * @param array<string, mixed> $data View data.
	 */
	public function include_template( string $name, array $data ): void {
		include SWNEWSPAPER2_DIR . 'templates/admin/' . $name . '.php';
	}
}
