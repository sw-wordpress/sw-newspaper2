<?php
/**
 * Strict line-coverage gate shared by the coverage report and its boundary test.
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

/** Required line coverage in percent; the measured value must be strictly greater. */
const SWNP_COVERAGE_MINIMUM = 80;

/**
 * Integer arithmetic avoids float rounding at the boundary: exactly 80 % fails.
 * An empty denominator fails as well, because missing measurements prove nothing.
 */
function swnp_coverage_passes( int $executed, int $executable, int $minimum = SWNP_COVERAGE_MINIMUM ): bool {
	if ( $executable <= 0 || $executed < 0 || $executed > $executable ) {
		return false;
	}
	return $executed * 100 > $minimum * $executable;
}
