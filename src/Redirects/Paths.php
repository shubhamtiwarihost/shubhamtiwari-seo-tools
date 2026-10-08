<?php
/**
 * Redirect source/target normalisation and loop detection.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Pure functions (no WordPress calls) so they are unit-tested directly.
 *
 * Sources are matched on the path only: lowercase, percent-decoded, one
 * leading slash, no trailing slash, query string ignored. "/Old-Page/",
 * "old-page" and "https://example.org/old-page?x=1" all become "/old-page".
 */
final class Paths {

	/**
	 * Paths that can never be a redirect source, so a mistake cannot lock
	 * anyone out of the dashboard, login or API.
	 */
	private const PROTECTED_PREFIXES = array( '/wp-admin', '/wp-login.php', '/wp-json', '/wp-cron.php', '/xmlrpc.php', '/wp-content', '/wp-includes' );

	/**
	 * Normalised source path, or null when invalid (other host, protected path, homepage).
	 *
	 * @param string $source    Path or URL as entered.
	 * @param string $home_host This site's host.
	 * @param string $home_path Path WordPress lives under ("" or "/blog").
	 */
	public static function source( string $source, string $home_host, string $home_path = '' ): ?string {
		$source = trim( $source );
		if ( '' === $source ) {
			return null;
		}
		if ( preg_match( '@^[a-z][a-z0-9+.-]*://@i', $source ) || 0 === strpos( $source, '//' ) ) {
			$host = (string) parse_url( $source, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure PHP on purpose (unit-tested without WordPress).
			if ( self::bare_host( $host ) !== self::bare_host( $home_host ) ) {
				return null;
			}
			$source = (string) parse_url( $source, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- See above.
		}

		$path = self::path( $source );
		if ( null === $path || '/' === $path ) {
			return null;
		}

		$relative = self::strip_home_path( $path, $home_path );
		foreach ( self::PROTECTED_PREFIXES as $prefix ) {
			if ( $relative === $prefix || 0 === strpos( $relative, $prefix . '/' ) || 0 === strpos( $relative, $prefix . '?' ) ) {
				return null;
			}
		}
		return $path;
	}

	/**
	 * Normalised path of a request URI ("/old-page"), or null.
	 *
	 * @param string $request_uri Raw REQUEST_URI.
	 */
	public static function request( string $request_uri ): ?string {
		$path = (string) parse_url( 'http://x' . ( '/' === substr( $request_uri, 0, 1 ) ? '' : '/' ) . $request_uri, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- See above.
		return self::path( $path );
	}

	/**
	 * Target as stored: an absolute http(s) URL, or a site path starting with "/".
	 * Null when invalid.
	 *
	 * @param string $target Target as entered.
	 */
	public static function target( string $target ): ?string {
		$target = trim( $target );
		if ( '' === $target || preg_match( '/[\s<>"\x00-\x1f]/', $target ) ) {
			return null;
		}
		if ( '/' === $target[0] && ( ! isset( $target[1] ) || '/' !== $target[1] ) ) {
			return $target;
		}
		$scheme = strtolower( (string) parse_url( $target, PHP_URL_SCHEME ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- See above.
		$host   = (string) parse_url( $target, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- See above.
		return in_array( $scheme, array( 'http', 'https' ), true ) && '' !== $host ? $target : null;
	}

	/**
	 * Whether following redirects from $target (through $index) comes back to
	 * $source, or runs longer than 10 steps. Targets on other hosts end the chain.
	 *
	 * @param string                                          $source    Normalised source.
	 * @param string                                          $target    Stored target.
	 * @param array<string, array{target: string, type: int}> $index     Other active redirects: source => data.
	 * @param string                                          $home_host This site's host.
	 */
	public static function loops( string $source, string $target, array $index, string $home_host ): bool {
		$seen    = array( $source => true );
		$current = $target;
		for ( $step = 0; $step < 10; $step++ ) {
			$path = self::local_path( $current, $home_host );
			if ( null === $path ) {
				return false; // Leaves the site.
			}
			if ( isset( $seen[ $path ] ) ) {
				return true;
			}
			if ( ! isset( $index[ $path ] ) || 410 === $index[ $path ]['type'] ) {
				return false; // Chain ends on a real page (or a 410).
			}
			$seen[ $path ] = true;
			$current       = $index[ $path ]['target'];
		}
		return true; // Too long to be intentional.
	}

	/**
	 * Normalised path of a target on this site, or null for other hosts.
	 *
	 * @param string $target    Stored target.
	 * @param string $home_host This site's host.
	 */
	public static function local_path( string $target, string $home_host ): ?string {
		if ( '/' === $target[0] && ( ! isset( $target[1] ) || '/' !== $target[1] ) ) {
			return self::request( $target );
		}
		$host = (string) parse_url( $target, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- See above.
		if ( self::bare_host( $host ) !== self::bare_host( $home_host ) ) {
			return null;
		}
		return self::path( (string) parse_url( $target, PHP_URL_PATH ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- See above.
	}

	/**
	 * Lowercase, decoded path with one leading slash and no trailing slash ("/" for the root).
	 *
	 * @param string $path Raw path.
	 */
	private static function path( string $path ): ?string {
		$path = strtok( $path, '?#' );
		$path = rawurldecode( false === $path ? '' : $path );
		if ( preg_match( '/[\x00-\x1f]/', $path ) ) {
			return null;
		}
		$path = '/' . trim( (string) preg_replace( '@/+@', '/', mb_strtolower( $path, 'UTF-8' ) ), '/' );
		return $path;
	}

	/**
	 * Removes the WordPress sub-directory ("/blog") from a path, for the protected-path check.
	 *
	 * @param string $path      Normalised path.
	 * @param string $home_path Home path.
	 */
	private static function strip_home_path( string $path, string $home_path ): string {
		$home_path = '/' . trim( strtolower( $home_path ), '/' );
		if ( '/' !== $home_path && 0 === strpos( $path . '/', $home_path . '/' ) ) {
			$rest = substr( $path, strlen( $home_path ) );
			return '' === $rest ? '/' : $rest;
		}
		return $path;
	}

	/**
	 * Lowercase host without "www.".
	 *
	 * @param string $host Host.
	 */
	private static function bare_host( string $host ): string {
		$host = strtolower( $host );
		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}
}
