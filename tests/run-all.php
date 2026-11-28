<?php
/**
 * Runs every test file (each with WordPress and WooCommerce stand-ins) and
 * exits with 1 when any check fails. Usage: php tests/run-all.php
 */
$total  = 0;
$failed = 0;
foreach ( glob( __DIR__ . '/run[0-9]*.php' ) as $file ) {
	$out = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $file ) . ' 2>&1' );
	if ( preg_match( '/(\d+) passed, (\d+) failed\s*$/', $out, $m ) ) {
		$total  += (int) $m[1] + (int) $m[2];
		$failed += (int) $m[2];
		echo basename( $file ) . ': ' . $m[1] . ' passed, ' . $m[2] . " failed\n";
		if ( (int) $m[2] > 0 ) {
			echo $out . "\n";
		}
	} else {
		++$failed;
		echo basename( $file ) . ": did not finish\n" . $out . "\n";
	}
}
echo "\nTotal: {$total} checks, {$failed} failed\n";
exit( $failed ? 1 : 0 );
