<?php
/**
 * Performs redirects.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Redirects;

use DumpSEO\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the redirect post type and answers matching frontend requests.
 *
 * Matching runs on `parse_request` (before the main query, so a redirected
 * request costs no content queries) for GET and HEAD requests only, never in
 * the dashboard, REST API, cron or on protected paths. 301/302/307 send a
 * Location header; 410 serves the theme's 404 template with status 410.
 */
final class RedirectsModule implements Module {

	/**
	 * Storage.
	 *
	 * @var Store
	 */
	private $store;

	/**
	 * Whether this request answers 410 Gone.
	 *
	 * @var bool
	 */
	private $gone = false;

	/**
	 * Constructor.
	 *
	 * @param Store $store Storage.
	 */
	public function __construct( Store $store ) {
		$this->store = $store;
	}

	/**
	 * The post type is needed everywhere; frontend handling checks the request itself.
	 */
	public function should_load(): bool {
		return true;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'init', array( Store::class, 'register_post_type' ) );
		add_action( 'dumpseo_installed', array( $this->store, 'rebuild' ) );

		// Keep the index current however redirects change: admin screen, WP-CLI, imports or code.
		foreach ( array( 'save_post', 'trashed_post', 'untrashed_post', 'deleted_post' ) as $hook ) {
			add_action( $hook, array( $this->store, 'changed' ), 20 );
		}
		if ( ! is_admin() ) {
			add_action( 'parse_request', array( $this, 'maybe_redirect' ), 1 );
			add_action( 'wp', array( $this, 'maybe_gone' ) );
		}
	}

	/**
	 * Redirects the request when an active redirect matches its path.
	 *
	 * @param mixed $wp WordPress environment (unused).
	 */
	public function maybe_redirect( $wp = null ): void {
		unset( $wp );
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only parsed into a normalised path and compared with stored keys; never output.
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = Paths::request( $uri );
		if ( null === $path || '/' === $path ) {
			return;
		}

		$redirect = $this->store->find( $path );

		/**
		 * Filters the redirect for a request path. Return null to skip.
		 *
		 * @param mixed  $redirect Matching redirect {id, target, type}, or null. Validated after filtering.
		 * @param string $path     Normalised request path.
		 */
		$redirect = apply_filters( 'dumpseo_redirect', $redirect, $path );
		if ( ! is_array( $redirect ) || ! in_array( (int) ( $redirect['type'] ?? 0 ), Store::TYPES, true ) ) {
			return;
		}

		if ( 410 === (int) $redirect['type'] ) {
			$this->gone = true;
			return;
		}

		$location = $this->location( (string) $redirect['target'], $uri );
		if ( null === $location || Paths::local_path( $location, (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === $path ) {
			return; // Invalid, or would redirect to itself.
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Targets may be other sites by design; only administrators create them and they are validated on save.
		if ( wp_redirect( $location, (int) $redirect['type'], 'DumpSEO' ) ) {
			$this->finish();
		}
	}

	/**
	 * Answers 410 Gone with the theme's 404 template.
	 */
	public function maybe_gone(): void {
		if ( ! $this->gone ) {
			return;
		}
		global $wp_query;
		if ( $wp_query instanceof \WP_Query ) {
			$wp_query->set_404();
		}
		status_header( 410 );
		nocache_headers();
	}

	/**
	 * Absolute Location for a stored target. The request's query string is
	 * kept unless the target has its own.
	 *
	 * @param string $target      Stored target.
	 * @param string $request_uri Request URI.
	 */
	private function location( string $target, string $request_uri ): ?string {
		$target = Paths::target( $target );
		if ( null === $target ) {
			return null;
		}
		if ( '/' === $target[0] ) {
			$target = home_url( $target );
		}
		$query = (string) wp_parse_url( $request_uri, PHP_URL_QUERY );
		if ( '' !== $query && false === strpos( $target, '?' ) ) {
			$target .= '?' . $query;
		}
		return $target;
	}

	/**
	 * Ends the request after a redirect. Separate so tests can stop it.
	 */
	private function finish(): void {
		/**
		 * Fires after a redirect header was sent, before the request ends. Tests use it to stop exit.
		 */
		do_action( 'dumpseo_redirected' );
		exit;
	}
}
