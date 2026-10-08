<?php
/**
 * Minimal dependency injection container.
 *
 * @package DumpSEO
 */

namespace DumpSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Lazily builds shared services from factories.
 *
 * Deliberately small: no autowiring, no reflection. Each service is declared
 * with a factory that receives the container.
 */
final class Container {

	/**
	 * Service factories keyed by ID.
	 *
	 * @var array<string, callable(Container): mixed>
	 */
	private $factories = array();

	/**
	 * Built services keyed by ID.
	 *
	 * @var array<string, mixed>
	 */
	private $instances = array();

	/**
	 * Declares a service. Replacing a service that was already built is not allowed.
	 *
	 * @param string                     $id      Service ID (usually a class name).
	 * @param callable(Container): mixed $factory Builds the service.
	 *
	 * @throws \LogicException When the service has already been built.
	 */
	public function set( string $id, callable $factory ): void {
		if ( array_key_exists( $id, $this->instances ) ) {
			throw new \LogicException( sprintf( 'Service "%s" is already built and cannot be replaced.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing message, never rendered as HTML.
		}
		$this->factories[ $id ] = $factory;
	}

	/**
	 * Whether a service is declared.
	 *
	 * @param string $id Service ID.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	/**
	 * Returns a shared service, building it on first use.
	 *
	 * @param string $id Service ID.
	 * @return mixed
	 *
	 * @throws \OutOfBoundsException When the service is not declared.
	 */
	public function get( string $id ) {
		if ( ! array_key_exists( $id, $this->instances ) ) {
			if ( ! $this->has( $id ) ) {
				throw new \OutOfBoundsException( sprintf( 'Service "%s" is not declared.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing message, never rendered as HTML.
			}
			$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
		}
		return $this->instances[ $id ];
	}
}
