<?php
/**
 * Local trust and storage configuration; never populated from release metadata.
 * Copies in other plugins change this file and descriptors.php, with their own class prefix.
 *
 * @package SWNewspaper2
 */

return array(
	'repository'    => 'sw-wordpress/sw-newspaper2',
	'repository_id' => 1432343893,
	'allowed_slugs' => array( 'sw-newspaper2' ),
	'main_slug'     => 'sw-newspaper2',
	'state_key'     => 'swnewspaper2_verified_github_updates_v1',
	'lock_key'      => 'swnewspaper2_verified_github_updates_lock_v1',
);
