<?php
/**
 * Environment requirement checks.
 *
 * @package DumpSEO
 */

namespace DumpSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Compares running PHP/WordPress versions with the plugin minimums.
 */
final class Requirements {

	public const MIN_PHP = '7.4';
	public const MIN_WP  = '6.4';

	/**
	 * Running PHP version.
	 *
	 * @var string
	 */
	private $php_version;

	/**
	 * Running WordPress version.
	 *
	 * @var string
	 */
	private $wp_version;

	/**
	 * Constructor.
	 *
	 * @param string $php_version Running PHP version.
	 * @param string $wp_version  Running WordPress version.
	 */
	public function __construct( string $php_version, string $wp_version ) {
		$this->php_version = $php_version;
		$this->wp_version  = $wp_version;
	}

	/**
	 * Whether PHP meets the minimum.
	 */
	public function php_ok(): bool {
		return version_compare( $this->php_version, self::MIN_PHP, '>=' );
	}

	/**
	 * Whether WordPress meets the minimum.
	 */
	public function wp_ok(): bool {
		return version_compare( $this->wp_version, self::MIN_WP, '>=' );
	}

	/**
	 * Whether all requirements are met.
	 */
	public function met(): bool {
		return $this->php_ok() && $this->wp_ok();
	}

	/**
	 * Whether the current admin screen is a Plugins screen (site or network).
	 */
	public static function on_plugins_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen instanceof \WP_Screen && in_array( $screen->id, array( 'plugins', 'plugins-network' ), true );
	}

	/**
	 * Notice shown when requirements are not met.
	 *
	 * Limited to the Plugins screen, the only place where the reader can act on
	 * it (update the environment or deactivate the plugin), and to users who
	 * manage plugins. It is never printed on the Dashboard or any other screen.
	 */
	public function render_notice(): void {
		if ( ! self::on_plugins_screen() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: required WordPress version. */
					__( 'DumpSEO is inactive: it requires PHP %1$s and WordPress %2$s or newer.', 'dumpseo' ),
					self::MIN_PHP,
					self::MIN_WP
				)
			)
		);
	}
}
