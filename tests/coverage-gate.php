<?php
/**
 * Boundary regression for the strict coverage gate (> 80 %, exactly 80 % fails).
 * Usage: php tests/coverage-gate.php
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

require __DIR__ . '/coverage/gate.php';

$cases = array(
	'exactly 80 %'                => array( 80, 100, false ),
	'exactly 80 % (4/5)'          => array( 4, 5, false ),
	'exactly 80 % (large)'        => array( 800000, 1000000, false ),
	'just above 80 %'             => array( 800001, 1000000, true ),
	'just below 80 %'             => array( 799999, 1000000, false ),
	'81 %'                        => array( 81, 100, true ),
	'100 %'                       => array( 10, 10, true ),
	'no executable lines'         => array( 0, 0, false ),
	'more executed than possible' => array( 11, 10, false ),
	'negative executed lines'     => array( -1, 10, false ),
);
foreach ( $cases as $name => list( $executed, $executable, $expected ) ) {
	if ( swnp_coverage_passes( $executed, $executable ) !== $expected ) {
		fwrite( STDERR, "FAIL: coverage gate, {$name}." . PHP_EOL );
		exit( 1 );
	}
}
if ( 80 !== SWNP_COVERAGE_MINIMUM ) {
	fwrite( STDERR, 'FAIL: coverage minimum must stay at 80 % (strictly greater).' . PHP_EOL );
	exit( 1 );
}

// The report script must fail the process below or at the limit.
$report = (string) file_get_contents( __DIR__ . '/coverage/report.php' );
if ( ! str_contains( $report, 'swnp_coverage_passes(' ) || ! str_contains( $report, 'exit( 1 )' ) ) {
	fwrite( STDERR, 'FAIL: coverage report does not enforce the gate.' . PHP_EOL );
	exit( 1 );
}
echo 'PASS: Coverage gate boundaries.' . PHP_EOL;
