<?php
/**
 * Request context.
 *
 * @package DumpSEO
 */

namespace DumpSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Describes the current request so modules can decide whether to load.
 *
 * REST requests are NOT detectable at plugins_loaded (REST_REQUEST is defined
 * later, during parse_request). Modules that serve REST must register their
 * routes on rest_api_init rather than rely on this class.
 */
class Context {

	/**
	 * Admin screens, including admin-ajax.php.
	 */
	public function is_admin(): bool {
		return is_admin();
	}

	/**
	 * Admin AJAX request.
	 */
	public function is_ajax(): bool {
		return wp_doing_ajax();
	}

	/**
	 * WP-Cron request.
	 */
	public function is_cron(): bool {
		return wp_doing_cron();
	}

	/**
	 * WP-CLI command.
	 */
	public function is_cli(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * A request that may render a theme template (frontend or REST).
	 */
	public function is_frontend(): bool {
		return ! $this->is_admin() && ! $this->is_cron() && ! $this->is_cli();
	}
}
