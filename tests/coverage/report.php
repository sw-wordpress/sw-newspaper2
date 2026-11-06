<?php
/**
 * Merge trusted coverage fragments produced by this run and export standard Clover XML.
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require __DIR__ . '/gate.php';
$files = glob( ( $argv[1] ?? '' ) . '/*.cov' );
if ( false === $files || array() === $files ) { throw new RuntimeException( 'No coverage fragments were produced.' ); }
$merged = null;
foreach ( $files as $file ) {
	$coverage = unserialize( file_get_contents( $file ) );
	if ( ! $coverage instanceof CodeCoverage ) { throw new RuntimeException( 'Invalid coverage fragment: ' . $file ); }
	if ( null === $merged ) { $merged = $coverage; }
	else { $merged->merge( $coverage ); }
}
// Include production files never loaded by any scenario in the denominator.
$merged->includeUncoveredFiles();
$target = dirname( __DIR__, 2 ) . '/coverage/clover.xml';
( new Clover() )->process( $merged, $target );

// Relative names survive the transfer from the test job to the scanner's checkout.
$document = new DOMDocument();
$document->load( $target );
$root = dirname( __DIR__, 2 ) . '/';
foreach ( $document->getElementsByTagName( 'file' ) as $file ) {
	$name = $file->getAttribute( 'name' );
	if ( ! str_starts_with( $name, $root ) ) { throw new RuntimeException( 'Coverage escaped the project root: ' . $name ); }
	$file->setAttribute( 'name', substr( $name, strlen( $root ) ) );
}
$document->save( $target );
$report = $merged->getReport();
$executed   = $report->numberOfExecutedLines();
$executable = $report->numberOfExecutableLines();
printf( "Coverage: %d/%d executable lines (%.2f%%), merged from %d processes.\n",
	$executed, $executable, $report->percentageOfExecutedLines()->asFloat(), count( $files ) );
echo 'Clover report: coverage/clover.xml' . PHP_EOL;
if ( ! swnp_coverage_passes( $executed, $executable ) ) {
	fwrite( STDERR, sprintf( 'FAIL: PHP line coverage must be greater than %d %%.', SWNP_COVERAGE_MINIMUM ) . PHP_EOL );
	exit( 1 );
}
printf( "PASS: PHP line coverage is greater than %d %%.\n", SWNP_COVERAGE_MINIMUM );
