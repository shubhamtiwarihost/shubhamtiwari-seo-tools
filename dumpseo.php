<?php
/**
 * Plugin Name:       DumpSEO
 * Description:       Search appearance, sitemaps, social metadata, structured data and content analysis for WordPress.
 * Version:           1.0.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Shubham Tiwari
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dumpseo
 * Domain Path:       /languages
 *
 * @package DumpSEO
 */

defined( 'ABSPATH' ) || exit;

define( 'DUMPSEO_VERSION', '1.0.2' );
define( 'DUMPSEO_FILE', __FILE__ );
define( 'DUMPSEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'DUMPSEO_URL', plugin_dir_url( __FILE__ ) );

require_once DUMPSEO_DIR . 'src/Autoloader.php';
\DumpSEO\Autoloader::register( DUMPSEO_DIR . 'src/' );
require_once DUMPSEO_DIR . 'src/functions.php';

register_activation_hook( __FILE__, array( \DumpSEO\Lifecycle::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \DumpSEO\Lifecycle::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		$requirements = new \DumpSEO\Requirements( PHP_VERSION, get_bloginfo( 'version' ) );
		if ( ! $requirements->met() ) {
			// Prints on the Plugins screen only (see Requirements::render_notice()).
			add_action( 'admin_notices', array( $requirements, 'render_notice' ) );
			add_action( 'network_admin_notices', array( $requirements, 'render_notice' ) );
			return;
		}
		\DumpSEO\Plugin::instance()->boot();
	}
);
