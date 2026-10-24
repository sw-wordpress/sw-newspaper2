<?php
/**
 * Uninstall handler. Uploaded newspapers live in the media library and are never removed automatically.
 *
 * @package SWNewspaper2
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'swnewspaper2_settings' );
