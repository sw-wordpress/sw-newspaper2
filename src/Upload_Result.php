<?php
/**
 * Outcome of a successful newspaper upload.
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

namespace SWNewspaper2;

final class Upload_Result {

	public function __construct(
		public readonly int $pdf_id,
		public readonly int $thumbnail_id
	) {
	}

	public function has_thumbnail(): bool {
		return $this->thumbnail_id > 0;
	}
}
