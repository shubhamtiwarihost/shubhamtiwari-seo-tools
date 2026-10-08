<?php
/**
 * Unit test bootstrap. WordPress is NOT loaded; WP functions are mocked with Brain Monkey.
 *
 * @package DumpSEO
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'DUMPSEO_VERSION' ) ) {
	define( 'DUMPSEO_VERSION', '0.1.0' );
	define( 'DUMPSEO_FILE', dirname( __DIR__, 2 ) . '/dumpseo.php' );
	define( 'DUMPSEO_DIR', dirname( __DIR__, 2 ) . '/' );
	define( 'DUMPSEO_URL', 'https://example.org/wp-content/plugins/dumpseo/' );
}

if ( ! class_exists( 'WP_Screen' ) ) {
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Stands in for a WordPress core class in unit tests, which run without WordPress.
	/**
	 * Minimal stand-in for core's WP_Screen (only the property the plugin reads).
	 */
	final class WP_Screen {

		/**
		 * Screen ID.
		 *
		 * @var string
		 */
		public $id = '';
	}
}
