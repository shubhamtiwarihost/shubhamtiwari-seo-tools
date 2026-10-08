<?php
/**
 * Fails when version numbers disagree across the plugin header, the
 * DUMPSEO_VERSION constant, readme.txt "Stable tag", package.json and composer.json.
 *
 * Usage: php bin/check-versions.php
 *
 * @package DumpSEO
 */

$dumpseo_root = dirname( __DIR__ );
$dumpseo_main = (string) file_get_contents( $dumpseo_root . '/dumpseo.php' );
$dumpseo_read = (string) file_get_contents( $dumpseo_root . '/readme.txt' );
$dumpseo_pkg  = json_decode( (string) file_get_contents( $dumpseo_root . '/package.json' ), true );
$dumpseo_comp = json_decode( (string) file_get_contents( $dumpseo_root . '/composer.json' ), true );

$dumpseo_versions = array(
	'plugin header'     => preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $dumpseo_main, $m ) ? $m[1] : null,
	'DUMPSEO_VERSION'   => preg_match( "/define\(\s*'DUMPSEO_VERSION',\s*'([^']+)'/", $dumpseo_main, $m ) ? $m[1] : null,
	'readme Stable tag' => preg_match( '/^Stable tag:\s*(\S+)/mi', $dumpseo_read, $m ) ? $m[1] : null,
	'package.json'      => $dumpseo_pkg['version'] ?? null,
	'composer.json'     => $dumpseo_comp['version'] ?? null,
);

foreach ( $dumpseo_versions as $dumpseo_source => $dumpseo_version ) {
	echo str_pad( $dumpseo_source, 20 ) . ( $dumpseo_version ?? '(missing)' ) . PHP_EOL;
}

if ( in_array( null, $dumpseo_versions, true ) || 1 !== count( array_unique( $dumpseo_versions ) ) ) {
	fwrite( STDERR, "Version mismatch.\n" );
	exit( 1 );
}

echo "Versions consistent.\n";
