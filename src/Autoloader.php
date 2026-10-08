<?php
/**
 * PSR-4 autoloader for the DumpSEO namespace.
 *
 * The plugin ships without a Composer vendor directory, so it carries its own
 * tiny autoloader. Composer is used for development tooling only.
 *
 * @package DumpSEO
 */

namespace DumpSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Maps DumpSEO\Foo\Bar to src/Foo/Bar.php.
 */
final class Autoloader {

	private const PREFIX = 'DumpSEO\\';

	/**
	 * Base directory of the namespace root, with trailing slash.
	 *
	 * @var string
	 */
	private static $base_dir = '';

	/**
	 * Registers the autoloader.
	 *
	 * @param string $base_dir Absolute path to the src/ directory.
	 */
	public static function register( string $base_dir ): void {
		self::$base_dir = rtrim( $base_dir, '/\\' ) . '/';
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Loads a class file if it belongs to the DumpSEO namespace.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	public static function load( string $class_name ): void {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( self::PREFIX ) );

		// Reject anything that is not a plain class path (defence against path traversal).
		if ( ! preg_match( '/^[A-Za-z0-9_\\\\]+$/', $relative ) ) {
			return;
		}

		$file = self::$base_dir . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
