<?php
/** End-to-end signed releases with isolated HTTP and disposable signing keys. */
final class SWNEWSPAPER2_Verified_Update_Exit extends Error {}

final class VerifiedUpdatesTest extends WP_UnitTestCase {
	protected int $admin_id = 0;
	private string $secret;
	private array $keys;
	private array $release;
	private array $manifest;
	private string $raw;
	private string $signature;
	private string $zip;
	private array $requests = array();

	public function set_up(): void {
		parent::set_up();
		$_REQUEST = $_POST = $_GET = array();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
		if ( is_multisite() ) {
			grant_super_admin( $this->admin_id );
		}
		set_current_screen( is_multisite() ? 'plugins-network' : 'plugins' );
		require_once SWNEWSPAPER2_DIR . 'includes/update/class-sw-newspaper2-update-service.php';
		delete_site_option( SWNewspaper2_Update_Store::STATE );
		delete_site_option( SWNewspaper2_Update_Store::LOCK );
		$keypair = sodium_crypto_sign_keypair();
		$this->secret = sodium_crypto_sign_secretkey( $keypair );
		$this->keys = array( 'test' => base64_encode( sodium_crypto_sign_publickey( $keypair ) ) );
		add_filter( 'swnewspaper2_verified_release_public_keys', fn () => $this->keys );
		$this->zip = wp_tempnam( 'swnewspaper2-signed-test.zip' );
		$archive = new ZipArchive();
		$archive->open( $this->zip, ZipArchive::OVERWRITE );
		$archive->addFromString( 'sw-newspaper2/sw-newspaper2.php', "<?php\n/**\n * Plugin Name: Signed test plugin\n * Version: 9.0.0.0\n * Requires PHP: 8.2\n * Requires at least: 6.0\n */\n" );
		$archive->close();
		$package = array( 'slug' => 'sw-newspaper2', 'version' => '9.0.0.0', 'asset_id' => 55, 'filename' => 'sw-newspaper2-9.0.0.0.zip', 'bytes' => filesize( $this->zip ), 'sha256' => hash_file( 'sha256', $this->zip ), 'requires_php' => '8.2', 'requires_wp' => '6.0', 'main_file' => 'sw-newspaper2.php' );
		$this->release = array( 'id' => 42, 'tag_name' => 'v9.0.0.0', 'draft' => false, 'prerelease' => false, 'assets' => array( array( 'id' => 55, 'name' => $package['filename'], 'size' => $package['bytes'] ), array( 'id' => 56, 'name' => 'sw-plugin-release.json' ), array( 'id' => 57, 'name' => 'sw-plugin-release.json.sig' ) ) );
		$this->manifest = array( 'schema' => 1, 'repository' => SWNewspaper2_Update_Manifest::source()['repository'], 'repository_id' => SWNewspaper2_Update_Manifest::source()['repository_id'], 'release_id' => 42, 'tag' => 'v9.0.0.0', 'commit' => str_repeat( 'a', 40 ), 'issued_at' => time(), 'packages' => array( $package ) );
		$this->sign();
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
			$this->requests[] = $url;
			if ( str_contains( $url, 'api.wordpress.org/' ) ) {
				$body = wp_json_encode( array( 'plugins' => array(), 'themes' => array(), 'no_update' => array(), 'translations' => array() ) );
			} elseif ( ! empty( $args['stream'] ) ) {
				copy( $this->zip, $args['filename'] );
				$body = '';
			} elseif ( str_ends_with( $url, '/releases/latest' ) ) {
				$body = wp_json_encode( $this->release );
			} elseif ( str_ends_with( $url, '.json.sig' ) ) {
				$body = $this->signature;
			} else {
				$body = $this->raw;
			}
			return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => 200 ) );
		}, 10, 3 );
	}

	private function sign(): void {
		$this->raw = wp_json_encode( $this->manifest );
		$this->signature = wp_json_encode( array( 'key_id' => 'test', 'signature' => base64_encode( sodium_crypto_sign_detached( $this->raw, $this->secret ) ) ) );
	}

	public function tear_down(): void {
		if ( is_multisite() ) {
			revoke_super_admin( $this->admin_id );
		}
		wp_delete_file( $this->zip );
		set_current_screen( 'front' );
		$_REQUEST = $_POST = $_GET = array();
		parent::tear_down();
	}

	public function test_signed_release_is_cached_and_displayed_without_additional_http(): void {
		$this->assertIsArray( SWNewspaper2_Update_Service::check() );
		$this->assertCount( 3, $this->requests );
		$file = array_key_first( SWNewspaper2_Update_Provider::descriptors() );
		$offers = SWNewspaper2_Update_Provider::offers( (object) array( 'checked' => array( $file => SWNEWSPAPER2_VERSION ) ) );
		$this->assertSame( '9.0.0.0', $offers->response[ $file ]->new_version );
		$this->assertFalse( $offers->response[ $file ]->autoupdate );
		$this->assertCount( 3, $this->requests );
		$this->assertWPError( SWNewspaper2_Update_Service::check() );
		delete_site_transient( 'update_plugins' );
		$this->assertWPError( SWNewspaper2_Update_Service::check( true ) );
		$this->assertCount( 3, $this->requests );
	}

	public function test_failed_requests_are_throttled_and_manual_check_can_override_daily_limit(): void {
		$this->release['draft'] = true;
		$this->assertWPError( SWNewspaper2_Update_Service::check() );
		$this->assertWPError( SWNewspaper2_Update_Service::check() );
		$this->assertCount( 1, $this->requests );
		$state = SWNewspaper2_Update_Store::read();
		$state['last_attempt'] = time() - 61;
		SWNewspaper2_Update_Store::write( $state );
		$this->release['draft'] = false;
		$this->assertIsArray( SWNewspaper2_Update_Service::check( true ) );
		$this->assertCount( 4, $this->requests );
	}

	public function test_frontend_and_unprivileged_users_never_check_or_load_remote_offers(): void {
		set_current_screen( 'front' );
		$this->assertWPError( SWNewspaper2_Update_Service::check( true ) );
		$value = (object) array( 'checked' => array() );
		$this->assertSame( $value, SWNewspaper2_Update_Provider::offers( $value ) );
		set_current_screen( is_multisite() ? 'plugins-network' : 'plugins' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertWPError( SWNewspaper2_Update_Service::check( true ) );
		$this->assertCount( 0, $this->requests );
	}

	public function test_unknown_key_and_modified_signature_fail_closed(): void {
		$this->keys = array();
		$this->assertWPError( SWNewspaper2_Update_Service::check() );
		$this->assertCount( 0, $this->requests );
		$this->keys = array( 'test' => base64_encode( random_bytes( 32 ) ) );
		$this->assertWPError( SWNewspaper2_Update_Service::check() );
		$this->assertArrayNotHasKey( 'manifest', SWNewspaper2_Update_Store::read() );
	}

	/** @dataProvider invalid_manifests */
	public function test_signed_but_invalid_metadata_is_rejected( string $field, $value ): void {
		$this->manifest[ $field ] = $value;
		$this->sign();
		$this->assertWPError( SWNewspaper2_Update_Service::check() );
	}
	public static function invalid_manifests(): array {
		return array( 'repository' => array( 'repository', 'attacker/repo' ), 'repository ID' => array( 'repository_id', 1 ), 'release ID' => array( 'release_id', 1 ), 'schema' => array( 'schema', 2 ), 'future' => array( 'issued_at', time() + 3600 ), 'duplicates' => array( 'packages', array() ) );
	}

	public function test_even_validly_signed_duplicate_json_keys_are_rejected(): void {
		$this->raw = str_replace( '"schema":1', '"schema":2,"schema":1', $this->raw );
		$this->signature = wp_json_encode( array( 'key_id' => 'test', 'signature' => base64_encode( sodium_crypto_sign_detached( $this->raw, $this->secret ) ) ) );
		$this->assertWPError( SWNewspaper2_Update_Service::check() );
	}

	public function test_lock_owner_cannot_release_another_request_lock(): void {
		$lock = SWNewspaper2_Update_Store::acquire();
		$this->assertIsArray( $lock );
		$this->assertFalse( SWNewspaper2_Update_Store::acquire() );
		SWNewspaper2_Update_Store::release( array( 'token' => 'wrong', 'expires' => 0 ) );
		$this->assertFalse( SWNewspaper2_Update_Store::acquire() );
		SWNewspaper2_Update_Store::release( $lock );
		$this->assertIsArray( $next = SWNewspaper2_Update_Store::acquire() );
		SWNewspaper2_Update_Store::release( $lock );
		$this->assertSame( $next, get_site_option( SWNewspaper2_Update_Store::LOCK ) );
	}

	public function test_core_download_boundary_returns_only_verified_zip_and_rejects_tampered_urls(): void {
		$this->assertIsArray( SWNewspaper2_Update_Service::check() );
		$file = array_key_first( SWNewspaper2_Update_Provider::descriptors() );
		$_REQUEST['action'] = 'upgrade-plugin';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'upgrade-plugin_' . $file );
		$url = SWNewspaper2_Update_Manifest::url( $this->manifest, $this->manifest['packages'][0] );
		$download = apply_filters( 'upgrader_pre_download', false, $url, new stdClass(), array( 'plugin' => $file ) );
		$this->assertIsString( $download );
		$this->assertSame( $this->manifest['packages'][0]['sha256'], hash_file( 'sha256', $download ) );
		wp_delete_file( $download );
		$this->assertWPError( apply_filters( 'upgrader_pre_download', false, 'https://evil.invalid/a.zip', new stdClass(), array( 'plugin' => $file ) ) );
		$_REQUEST['action'] = 'other-action';
		$this->assertWPError( apply_filters( 'upgrader_pre_download', false, $url, new stdClass(), array( 'plugin' => $file ) ) );
		$this->assertFalse( apply_filters( 'auto_update_plugin', true, (object) array( 'plugin' => $file ) ) );
		$this->assertTrue( apply_filters( 'auto_update_plugin', true, (object) array( 'plugin' => 'other/other.php' ) ) );
	}

	/** @dataProvider unsafe_entries */
	public function test_zip_paths_are_rejected_even_with_a_matching_hash( string $entry, bool $symlink = false ): void {
		$zip = new ZipArchive();
		$zip->open( $this->zip );
		$zip->addFromString( $entry, 'outside' );
		if ( $symlink ) $zip->setExternalAttributesName( $entry, ZipArchive::OPSYS_UNIX, 0120777 << 16 );
		$zip->close();
		$package = $this->manifest['packages'][0];
		$package['bytes'] = filesize( $this->zip );
		$package['sha256'] = hash_file( 'sha256', $this->zip );
		$this->assertWPError( SWNewspaper2_Update_Package::verify( $this->zip, $package ) );
	}
	public static function unsafe_entries(): array {
		return array( array( 'sw-newspaper2/../outside.php' ), array( '/absolute.php' ), array( 'sw-newspaper2\\outside.php' ), array( 'sw-newspaper2/C:/outside' ), array( 'sw-newspaper2/link', true ), array( 'SW-NEWSPAPER2/SW-NEWSPAPER2.PHP' ) );
	}

	public function test_missing_key_is_reported_only_in_plugin_management(): void {
        $this->keys = array();
        $file = array_key_first( SWNewspaper2_Update_Provider::descriptors() );
        $meta = SWNewspaper2_Update_Provider::row_meta( array('foreign'), $file );
        $this->assertStringContainsString( 'blocked', implode('', $meta) );
        $this->assertStringContainsString( 'swnewspaper2-verified-updates-check', implode('', $meta) );
        $this->assertSame( array('foreign'), SWNewspaper2_Update_Provider::row_meta(array('foreign'), 'other/other.php') );
        delete_option('swnewspaper2_verified_updates_migrated');
        update_site_option('auto_update_plugins', array($file, 'other/other.php'));
        SWNewspaper2_Update_Provider::migrate();
        $this->assertSame(array('other/other.php'), get_site_option('auto_update_plugins'));
        $this->assertSame(1, get_option('swnewspaper2_verified_updates_migrated'));
    }
	public function test_frontend_filters_add_no_database_queries_and_cron_cannot_download(): void {
		set_current_screen( 'front' );
		$value = (object) array( 'checked' => array() );
		$before = get_num_queries();
		$this->assertSame( $value, SWNewspaper2_Update_Provider::offers( $value ) );
		$this->assertSame( $before, get_num_queries() );
		set_current_screen( is_multisite() ? 'plugins-network' : 'plugins' );
		add_filter( 'wp_doing_cron', '__return_true' );
		$this->assertWPError( SWNewspaper2_Update_Service::check( true ) );
		$this->assertCount( 0, $this->requests );
	}

	public function test_expired_snapshot_and_corrupt_zip_are_rejected_before_core_installation(): void {
		$this->assertIsArray( SWNewspaper2_Update_Service::check() );
		$state = SWNewspaper2_Update_Store::read();
		$state['last_success'] = time() - 8 * DAY_IN_SECONDS;
		SWNewspaper2_Update_Store::write( $state );
		$this->assertWPError( SWNewspaper2_Update_Service::verified_snapshot() );
		$state['last_success'] = time();
		SWNewspaper2_Update_Store::write( $state );
		file_put_contents( $this->zip, 'tampered package' );
		$descriptors = SWNewspaper2_Update_Provider::descriptors();
		$descriptor = reset( $descriptors );
		$url = SWNewspaper2_Update_Manifest::url( $this->manifest, $this->manifest['packages'][0] );
		$this->assertWPError( SWNewspaper2_Update_Service::download( $url, $descriptor ) );
	}

	public function test_redireswnewspaper2_and_size_limits_fail_closed(): void {
		remove_all_filters( 'pre_http_request' );
		$seen = array();
		add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( &$seen ) {
			$seen[] = $url;
			return array( 'headers' => array( 'location' => 'https://127.0.0.1/private' ), 'body' => '', 'response' => array( 'code' => 302 ) );
		}, 10, 3 );
		$this->assertWPError( SWNewspaper2_Update_Transport::fetch( 'https://api.github.com/repos/sw-wordpress/sw-newspaper2/releases/latest', 1024 ) );
		$this->assertCount( 1, $seen );
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', static fn () => array( 'headers' => array(), 'body' => str_repeat( 'a', 1025 ), 'response' => array( 'code' => 200 ) ) );
		$this->assertWPError( SWNewspaper2_Update_Transport::fetch( 'https://api.github.com/repos/sw-wordpress/sw-newspaper2/releases/latest', 1024 ) );
	}

	public function test_release_signer_and_runtime_verifier_agree(): void {
		$directory = get_temp_dir() . 'swnewspaper2-sign-' . wp_generate_uuid4();
		wp_mkdir_p( $directory );
		$zipfile = $directory . '/' . $this->manifest['packages'][0]['filename'];
		copy( $this->zip, $zipfile );
		$metadata = $directory . '/release.json';
		file_put_contents( $metadata, wp_json_encode( $this->release ) );
		$process = proc_open( array( PHP_BINARY, SWNEWSPAPER2_DIR . 'bin/release-manifest.php', 'sign', $metadata, $directory, str_repeat( 'a', 40 ) ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes, null, array( 'SW_PLUGIN_RELEASE_SIGNING_KEY' => base64_encode( $this->secret ), 'SW_PLUGIN_RELEASE_KEY_ID' => 'test' ) );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$exit = proc_close( $process );
		try {
			$this->assertSame( 0, $exit, $output );
			$this->assertIsArray( SWNewspaper2_Update_Manifest::verify( file_get_contents( $directory . '/sw-plugin-release.json' ), file_get_contents( $directory . '/sw-plugin-release.json.sig' ), $this->keys, $this->release ) );
			$this->assertStringNotContainsString( base64_encode( $this->secret ), $output );
		} finally {
			foreach ( glob( $directory . '/*' ) as $file ) wp_delete_file( $file );
			rmdir( $directory );
		}
	}

	/** @dataProvider core_modes */
	public function test_native_wordpress_upgrader_installs_verified_package_only_in_private_wp( string $mode ): void {
		$this->assertStringStartsWith( SWNEWSPAPER2_DIR . 'tests/wp/vendor/', realpath( WP_PLUGIN_DIR ) );
		$root = WP_PLUGIN_DIR . '/sw-newspaper2';
		$this->assertDirectoryDoesNotExist( $root );
		wp_mkdir_p( $root );
		$buffer_level = ob_get_level();
		ob_start();
		try {
			$file = 'sw-newspaper2/sw-newspaper2.php';
			add_filter( 'swnewspaper2_verified_update_descriptors', static fn () => array( $file => array( 'slug' => 'sw-newspaper2', 'version' => '1.0.0.0' ) ) );
			file_put_contents( $root . '/sw-newspaper2.php', "<?php\n/*\nPlugin Name: Old test plugin\nVersion: 1.0.0.0\n*/\n" );
			$this->assertIsArray( SWNewspaper2_Update_Service::check() );
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			WP_Filesystem();
			set_site_transient( 'update_plugins', (object) array( 'checked' => array( $file => '1.0.0.0' ) ) );
			$this->assertArrayHasKey( $file, get_site_transient( 'update_plugins' )->response ?? array() );
			$bulk = 'bulk' === $mode;
			$_REQUEST['action'] = $bulk ? 'update-selected' : ( 'ajax' === $mode ? 'update-plugin' : 'upgrade-plugin' );
			$_REQUEST['_wpnonce'] = wp_create_nonce( $bulk ? 'bulk-update-plugins' : ( 'ajax' === $mode ? 'updates' : 'upgrade-plugin_' . $file ) );
			$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
			$result = $bulk ? $upgrader->bulk_upgrade( array( $file ), array( 'clear_update_cache' => false ) ) : $upgrader->upgrade( $file, array( 'clear_update_cache' => false ) );
			if ( $bulk ) {
				$this->assertIsArray( $result );
				$this->assertIsArray( $result[ $file ] );
				$result = true;
			}
			$this->assertTrue( $result, is_wp_error( $result ) ? $result->get_error_message() : 'Native upgrade failed: ' . $upgrader->skin->get_error_messages() );
			$this->assertStringContainsString( 'Version: 9.0.0.0', file_get_contents( $root . '/sw-newspaper2.php' ) );
		} finally {
			while ( ob_get_level() > $buffer_level ) ob_end_clean();
			wp_delete_file( $root . '/sw-newspaper2.php' );
			if ( is_dir( $root ) ) rmdir( $root );
		}
	}
	public static function core_modes(): array { return array( 'single' => array( 'single' ), 'bulk' => array( 'bulk' ), 'ajax' => array( 'ajax' ) ); }
	public function test_wordpress_details_and_update_uri_use_only_cached_signed_metadata(): void {
		$this->assertIsArray( SWNewspaper2_Update_Service::check() );
		$file = array_key_first( SWNewspaper2_Update_Provider::descriptors() );
		$uri = 'https://github.com/sw-wordpress/sw-newspaper2';
		$offer = SWNewspaper2_Update_Provider::update_uri( false, array( 'UpdateURI' => $uri, 'Version' => SWNEWSPAPER2_VERSION ), $file );
		$this->assertSame( '9.0.0.0', $offer->new_version );
		$details = SWNewspaper2_Update_Provider::details( false, 'plugin_information', (object) array( 'slug' => 'sw-newspaper2' ) );
		$this->assertSame( '9.0.0.0', $details->version );
		$original = (object) array( 'foreign' => true );
		$this->assertSame( $original, SWNewspaper2_Update_Provider::update_uri( $original, array( 'UpdateURI' => 'https://evil.invalid', 'Version' => '1' ), $file ) );
		$this->assertSame( $original, SWNewspaper2_Update_Provider::details( $original, 'plugin_information', (object) array( 'slug' => 'other-plugin' ) ) );
		$this->assertCount( 3, $this->requests );
		$this->assertStringContainsString( 'manuelle', SWNewspaper2_Update_Provider::automatic_html( '', $file ) );
		$this->assertSame( 'unchanged', SWNewspaper2_Update_Provider::automatic_html( 'unchanged', 'foreign/plugin.php' ) );
	}

	private function ajax( string $nonce ): mixed {
		$_POST = array( 'nonce' => $nonce, 'manual' => '1' );
		$_REQUEST = $_POST;
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', static fn () => static function ( $message ) { echo $message; throw new SWNEWSPAPER2_Verified_Update_Exit(); } );
		ob_start();
		try {
			do_action( 'wp_ajax_swnewspaper2_check_github_updates' );
		} catch ( SWNEWSPAPER2_Verified_Update_Exit $exception ) {
			// Capture WordPress's terminating response without ending the test process.
		}
		return json_decode( ob_get_clean(), true, 32, JSON_THROW_ON_ERROR );
	}

	public function test_check_ajax_rejects_invalid_nonce_before_http(): void {
		$this->assertSame( -1, $this->ajax( 'invalid' ) );
		$this->assertCount( 0, $this->requests );
	}

	public function test_check_ajax_rejects_users_without_wordpress_update_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertFalse( $this->ajax( wp_create_nonce( 'swnewspaper2_verified_updates' ) )['success'] );
		$this->assertCount( 0, $this->requests );
	}

	public function test_authorized_check_ajax_checks_but_never_installs(): void {
		// The shared AJAX endpoint has a site-admin screen even when called by network admin.
		set_current_screen( 'dashboard' );
		$this->assertTrue( $this->ajax( wp_create_nonce( 'swnewspaper2_verified_updates' ) )['success'] );
		$this->assertCount( 3, $this->requests );
		$this->assertFalse( $this->ajax( wp_create_nonce( 'swnewspaper2_verified_updates' ) )['success'] );
		$this->assertCount( 3, $this->requests );
	}

	public function test_multisite_site_admin_context_cannot_check_without_network_ajax(): void {
		if ( ! is_multisite() ) {
			$this->assertTrue( SWNewspaper2_Update_Service::interactive() );
			return;
		}
		set_current_screen( 'dashboard' );
		$this->assertWPError( SWNewspaper2_Update_Service::check( true ) );
		$this->assertCount( 0, $this->requests );
		add_filter( 'wp_doing_ajax', '__return_true' );
		revoke_super_admin( $this->admin_id );
		$this->assertWPError( SWNewspaper2_Update_Service::check( true ) );
		$this->assertCount( 0, $this->requests );
	}

	public function test_deactivation_clears_only_own_offers_and_automatic_selections(): void {
		$this->assertIsArray( SWNewspaper2_Update_Service::check() );
		$file = array_key_first( SWNewspaper2_Update_Provider::descriptors() );
		update_site_option( 'auto_update_plugins', array( $file, 'other/other.php' ) );
		set_site_transient( 'update_plugins', (object) array(
			'checked' => array( $file => '1.0.0', 'other/other.php' => '1.0.0' ),
			'response' => array( 'other/other.php' => (object) array( 'new_version' => '2.0.0' ) ),
		) );
		$this->assertArrayHasKey( $file, get_site_transient( 'update_plugins' )->response );
		SWNewspaper2_Update_Provider::deactivate();
		$this->assertSame( array( 'other/other.php' ), get_site_option( 'auto_update_plugins' ) );
		$this->assertArrayNotHasKey( $file, get_site_transient( 'update_plugins' )->response );
		$this->assertArrayHasKey( 'other/other.php', get_site_transient( 'update_plugins' )->response );
	}

	public function test_expired_lock_is_taken_over_and_new_owner_is_preserved(): void {
		$old = array( 'token' => 'expired', 'expires' => time() - 10 );
		add_site_option( SWNewspaper2_Update_Store::LOCK, $old );
		$this->assertIsArray( $new = SWNewspaper2_Update_Store::acquire() );
		SWNewspaper2_Update_Store::release( $old );
		$this->assertSame( $new, get_site_option( SWNewspaper2_Update_Store::LOCK ) );
	}

	public function test_signed_rollback_cannot_replace_a_previously_verified_release(): void {
		$this->assertIsArray( SWNewspaper2_Update_Service::check() );
		$state = SWNewspaper2_Update_Store::read();
		$state['last_attempt'] = time() - 61;
		SWNewspaper2_Update_Store::write( $state );
		$this->manifest['packages'][0]['version'] = '8.0.0.0';
		$this->manifest['packages'][0]['filename'] = 'sw-newspaper2-8.0.0.0.zip';
		$this->manifest['tag'] = 'v8.0.0.0';
		$this->release['tag_name'] = 'v8.0.0.0';
		$this->release['assets'][0]['name'] = 'sw-newspaper2-8.0.0.0.zip';
		$this->sign();
		$this->assertWPError( SWNewspaper2_Update_Service::check( true ) );
		$this->assertSame( '9.0.0.0', SWNewspaper2_Update_Store::read()['highest']['sw-newspaper2'] );
	}

	public function test_invalid_native_update_nonce_never_downloads_a_package(): void {
		$this->assertIsArray( SWNewspaper2_Update_Service::check() );
		$file = array_key_first( SWNewspaper2_Update_Provider::descriptors() );
		$_REQUEST = array( 'action' => 'upgrade-plugin', '_wpnonce' => 'forged' );
		$url = SWNewspaper2_Update_Manifest::url( $this->manifest, $this->manifest['packages'][0] );
		$this->assertWPError( apply_filters( 'upgrader_pre_download', false, $url, new stdClass(), array( 'plugin' => $file ) ) );
		$this->assertCount( 3, $this->requests );
	}

    /** @dataProvider invalid_packages */
    public function test_signed_invalid_packages_fail_closed(string $field, mixed $value): void {
        $this->manifest['packages'][0][$field] = $value;
        $this->sign();
        $this->assertWPError(SWNewspaper2_Update_Service::check());
    }
    public static function invalid_packages(): array {
        return array_map(static fn($pair) => $pair, array(
            array('slug', 'foreign'), array('version', '9.0.0-beta'), array('filename','other.zip'),
            array('main_file','other.php'), array('sha256',str_repeat('z',64)), array('bytes',0),
            array('bytes',52428801), array('asset_id',99), array('requires_php','invalid'), array('requires_wp',''),
            array('asset_id','55'), array('bytes','100'),
        ));
    }
    public function test_exact_bytes_and_duplicate_signature_keys_are_rejected(): void {
        $this->assertWPError(SWNewspaper2_Update_Manifest::verify($this->raw . "\n",$this->signature,$this->keys,$this->release));
        $signature = str_replace('"key_id":"test"','"key_id":"other","key_id":"test"',$this->signature);
        $this->assertWPError(SWNewspaper2_Update_Manifest::verify($this->raw,$signature,$this->keys,$this->release));
        $this->release['assets'][] = $this->release['assets'][0];
        $this->assertWPError(SWNewspaper2_Update_Manifest::verify($this->raw,$this->signature,$this->keys,$this->release));
    }
    public function test_stale_lease_cannot_publish_after_successor_takes_over(): void {
        add_filter('pre_http_request', function($pre) {
            delete_site_option(SWNewspaper2_Update_Store::LOCK);
            add_site_option(SWNewspaper2_Update_Store::LOCK,array('token'=>'successor','expires'=>time()+120));
            return $pre;
        },20);
        $result=SWNewspaper2_Update_Service::check();
        $this->assertSame('updates_lease_expired',$result->get_error_code());
        $this->assertArrayNotHasKey('manifest',SWNewspaper2_Update_Store::read());
        $this->assertSame('successor',get_site_option(SWNewspaper2_Update_Store::LOCK)['token']);
    }
    public function test_status_and_script_are_local_and_only_ui_is_in_plugin_list(): void {
        $GLOBALS['wp_scripts'] = new WP_Scripts();
        SWNewspaper2_Update_Provider::enqueue();
        $this->assertTrue(wp_script_is('swnewspaper2-verified-updates','enqueued'));
        $this->assertStringContainsString('swnewspaper2VerifiedUpdates',wp_scripts()->get_inline_script_data('swnewspaper2-verified-updates','before'));
        $this->assertStringContainsString('No verified',SWNewspaper2_Update_Provider::status_text());
        $this->assertIsArray(SWNewspaper2_Update_Service::check());
        SWNewspaper2_Update_Store::write(array_merge(SWNewspaper2_Update_Store::read(),array('error'=>'<failed>')));
        $meta=SWNewspaper2_Update_Provider::row_meta(array(),array_key_first(SWNewspaper2_Update_Provider::descriptors()));
        $this->assertStringContainsString('&lt;failed&gt;',implode('',$meta));
        $this->assertStringContainsString('Last verified',SWNewspaper2_Update_Provider::status_text());
        $this->assertStringContainsString('Last attempt',SWNewspaper2_Update_Provider::status_text());
        $this->assertCount(3,$this->requests);
        set_current_screen('front');
        $this->assertSame(array(),SWNewspaper2_Update_Provider::row_meta(array(),array_key_first(SWNewspaper2_Update_Provider::descriptors())));
    }
    public function test_automatic_and_stale_arbitrary_offers_cannot_bypass_download_guard(): void {
        require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
        $file=array_key_first(SWNewspaper2_Update_Provider::descriptors());
        $_REQUEST=array('action'=>'upgrade-plugin','_wpnonce'=>wp_create_nonce('upgrade-plugin_'.$file));
        $this->assertWPError(SWNewspaper2_Update_Provider::download_guard('/tmp/untrusted','https://evil.invalid/a.zip',new stdClass(),array('plugin'=>$file)));
        $upgrader=(object)array('skin'=>new Automatic_Upgrader_Skin());
        $this->assertWPError(SWNewspaper2_Update_Provider::download_guard(false,'https://github.com/sw-wordpress/sw-newspaper2/releases/download/v9/a.zip',$upgrader,array('plugin'=>$file)));
        $this->assertSame('foreign',SWNewspaper2_Update_Provider::download_guard('foreign','https://example.org/a.zip',new stdClass(),array('plugin'=>'other/other.php')));
        $this->assertWPError(SWNewspaper2_Update_Provider::download_guard(false,'https://github.com/sw-wordpress/sw-newspaper2/releases/download/v9/a.zip',new stdClass()));
        $this->assertCount(0,$this->requests);
    }
    public function test_rollback_and_incompatibility_at_download_and_expired_offers(): void {
        $this->assertIsArray(SWNewspaper2_Update_Service::check());
        $state=SWNewspaper2_Update_Store::read();
        $state['highest']['sw-newspaper2']='10.0.0';
        SWNewspaper2_Update_Store::write($state);
        $this->assertWPError(SWNewspaper2_Update_Service::verified_snapshot());
        $state['last_success']=time()-8*DAY_IN_SECONDS;
        SWNewspaper2_Update_Store::write($state);
        $file=array_key_first(SWNewspaper2_Update_Provider::descriptors());
        $offer=SWNewspaper2_Update_Provider::offers((object)array('checked'=>array($file=>'1.0.0')));
        $this->assertEmpty($offer->response ?? array());
        $this->assertFalse(SWNewspaper2_Update_Service::compatible(array('requires_php'=>'99.0','requires_wp'=>'6.0')));
    }
    public function test_network_state_and_limit_are_shared_between_sites(): void {
        if(!is_multisite()) { $this->assertTrue(true); return; }
        $this->assertIsArray(SWNewspaper2_Update_Service::check());
        $site=self::factory()->blog->create();
        switch_to_blog($site);
        try {
            set_current_screen('plugins-network');
            $this->assertSame('updates_throttled',SWNewspaper2_Update_Service::check()->get_error_code());
            $this->assertSame('9.0.0.0',SWNewspaper2_Update_Store::read()['highest']['sw-newspaper2']);
        } finally { restore_current_blog(); }
        $this->assertCount(3,$this->requests);
    }
    /** @dataProvider network_failures */
    public function test_network_failures_count_as_daily_attempts(mixed $response): void {
        remove_all_filters('pre_http_request');
        $count=0;
        add_filter('pre_http_request',static function() use (&$count,$response) { ++$count; return $response === false ? new WP_Error('timeout') : array('headers'=>array(),'body'=>'','response'=>array('code'=>$response)); });
        $this->assertWPError(SWNewspaper2_Update_Service::check());
        delete_site_transient('update_plugins');
        $this->assertSame('updates_throttled',SWNewspaper2_Update_Service::check()->get_error_code());
        $this->assertSame(1,$count);
    }
    public static function network_failures(): array { return array(array(false),array(404),array(429)); }
    public function test_redirect_allowlist_and_redirect_limit(): void {
        foreach(array('http://github.com/a','https://github.com:444/a','https://user@github.com/a','https://github.com/sw-wordpress/sw-newspaper2/releases/download/../a','https://github.com/sw-wordpress/sw-newspaper2/releases/download/%2e%2e/a','https://evil.githubusercontent.com/a','https://127.0.0.1/a','https://github.com/sw-wordpress/foreign/releases/download/a') as $url) {
            $this->assertFalse(SWNewspaper2_Update_Transport::allowed($url),$url);
        }
        remove_all_filters('pre_http_request');
        $count=0;
        add_filter('pre_http_request',static function($pre,$args,$url) use (&$count) {
            ++$count;
            if($count===1) return array('headers'=>array('location'=>'https://release-assets.githubusercontent.com/a'),'body'=>'','response'=>array('code'=>302));
            return array('headers'=>array(),'body'=>'valid','response'=>array('code'=>200));
        },10,3);
        $this->assertSame('valid',SWNewspaper2_Update_Transport::fetch('https://github.com/sw-wordpress/sw-newspaper2/releases/download/v9/a',10));
        $this->assertSame(2,$count);
        remove_all_filters('pre_http_request');
        add_filter('pre_http_request',static fn()=>array('headers'=>array('location'=>'https://objects.githubusercontent.com/a'),'body'=>'','response'=>array('code'=>302)));
        $this->assertSame('update_redirect_limit',SWNewspaper2_Update_Transport::fetch('https://objects.githubusercontent.com/a',10)->get_error_code());
    }
    /** @dataProvider zip_defects */
    public function test_archive_structure_and_header_defects(string $defect): void {
        $zip=new ZipArchive();$zip->open($this->zip);
        if($defect==='header') $zip->addFromString('sw-newspaper2/sw-newspaper2.php',"<?php\n/* Plugin Name: Invalid */");
        if($defect==='version') $zip->addFromString('sw-newspaper2/sw-newspaper2.php',"<?php\n/*\nPlugin Name: Invalid\nVersion: 8.0.0\nRequires PHP: 8.2\nRequires at least: 6.0\n*/");
        if($defect==='missing') { $zip->deleteName('sw-newspaper2/sw-newspaper2.php'); $zip->addFromString('sw-newspaper2/readme.txt','missing main'); }
        if($defect==='duplicate') $zip->addFromString('sw-newspaper2/SW-NEWSPAPER2.php','case conflict');
        if($defect==='bomb') $zip->addFromString('sw-newspaper2/bomb',str_repeat('x',2000000));
        $zip->close();clearstatcache(true,$this->zip);
        $package=$this->manifest['packages'][0];$package['bytes']=filesize($this->zip);$package['sha256']=hash_file('sha256',$this->zip);
        $this->assertWPError(SWNewspaper2_Update_Package::verify($this->zip,$package));
    }
    public static function zip_defects(): array { return array(array('header'),array('version'),array('missing'),array('duplicate'),array('bomb')); }

    public function test_network_mutex_blocks_a_second_database_connection(): void {
        if (!is_multisite()) { $this->assertTrue(true); return; }
        global $wpdb;
        $other = new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
        $name='swnewspaper2_update_'.get_current_network_id();
        $this->assertSame('1',(string)$other->get_var($other->prepare('SELECT GET_LOCK(%s, 0)',$name)));
        try { $this->assertFalse(SWNewspaper2_Update_Store::acquire()); }
        finally { $other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)',$name)); $other->close(); }
        $lock=SWNewspaper2_Update_Store::acquire();$this->assertIsArray($lock);SWNewspaper2_Update_Store::release($lock);
    }

}
