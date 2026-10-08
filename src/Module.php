<?php
/**
 * Contract for feature modules.
 *
 * @package DumpSEO
 */

namespace DumpSEO;

defined( 'ABSPATH' ) || exit;

/**
 * A self-contained feature that attaches itself to WordPress hooks.
 */
interface Module {

	/**
	 * Whether the module should load for the current request.
	 *
	 * Keeps admin code off the frontend and vice versa.
	 */
	public function should_load(): bool;

	/**
	 * Registers hooks. Must not perform expensive work itself.
	 */
	public function register(): void;
}
