<?php
/**
 * Capabilities used by the plugin.
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

namespace SWNewspaper2;

final class Capabilities {

	/** Upload and delete newspapers. */
	public const PUBLISH = 'upload_files';

	/** Change plugin settings. */
	public const SETTINGS = 'manage_options';
}
