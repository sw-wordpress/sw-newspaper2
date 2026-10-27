<?php
/**
 * Run all regression scenarios in separate PHP processes, optionally collecting coverage.
 * Usage: WP_ROOT=/tmp/wordpress WP_DB_HOST=127.0.0.1:3306 php tests/run.php [--coverage]
 *
 * @package SWNewspaper2
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
chdir( $root );
$collect = in_array( '--coverage', $argv, true );
$directory = $root . '/coverage/raw/' . bin2hex( random_bytes( 8 ) );
if ( $collect ) {
	require $root . '/vendor/autoload.php';
	if ( is_file( $root . '/coverage/clover.xml' ) ) { unlink( $root . '/coverage/clover.xml' ); }
	$filter = new \SebastianBergmann\CodeCoverage\Filter();
	// Fail before running any tests if the requested coverage driver is unavailable.
	( new \SebastianBergmann\CodeCoverage\Driver\Selector() )->forLineCoverage( $filter );
	mkdir( $directory, 0777, true );
}

$scenarios = array(
	array( 'tests/bootstrap.php' ),
	array( 'tests/legacy-import.php' ),
	array( 'tests/archive-performance.php' ),
);

$run = static function ( array $command ): void {
	echo PHP_EOL . 'RUN: ' . implode( ' ', $command ) . PHP_EOL;
	$process = proc_open( $command, array( STDIN, STDOUT, STDERR ), $pipes );
	if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Could not start test process.' ); }
	$status = proc_close( $process );
	if ( 0 !== $status ) { exit( $status > 0 ? $status : 1 ); }
};

foreach ( $scenarios as $index => $scenario ) {
	$command = array( PHP_BINARY );
	if ( $collect ) {
		putenv( 'SW_NEWSPAPER2_COVERAGE_FILE=' . $directory . '/' . $index . '.cov' );
		putenv( 'SW_NEWSPAPER2_COVERAGE_ID=' . implode( ' ', $scenario ) );
		$command[] = '-d';
		$command[] = 'auto_prepend_file=' . __DIR__ . '/coverage/bootstrap.php';
	}
	$run( array_merge( $command, $scenario ) );
	if ( $collect && ! is_file( $directory . '/' . $index . '.cov' ) ) {
		throw new RuntimeException( 'Test process did not write its coverage report.' );
	}
}
putenv( 'SW_NEWSPAPER2_COVERAGE_FILE' );
putenv( 'SW_NEWSPAPER2_COVERAGE_ID' );
if ( $collect ) {
	$run( array( PHP_BINARY, __DIR__ . '/coverage/report.php', $directory ) );
	if ( ! is_file( $root . '/coverage/clover.xml' ) || 0 === filesize( $root . '/coverage/clover.xml' ) ) {
		throw new RuntimeException( 'Merged Clover report was not produced.' );
	}
}
echo PHP_EOL . 'PASS: All regression scenarios completed.' . PHP_EOL;
