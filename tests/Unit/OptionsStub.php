<?php
/**
 * In-memory replacement for the WordPress options API.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Backs get/add/update/delete_option with an array so unit tests can observe writes.
 */
final class OptionsStub {

	/**
	 * Stored options.
	 *
	 * @var array<string, mixed>
	 */
	public $options = array();

	/**
	 * Autoload flag per option.
	 *
	 * @var array<string, bool|null>
	 */
	public $autoload = array();

	/**
	 * Installs the stub via Brain Monkey.
	 *
	 * @param array<string, mixed> $initial Initial options.
	 */
	public static function install( array $initial = array() ): self {
		$stub          = new self();
		$stub->options = $initial;

		Functions\when( 'get_option' )->alias(
			static function ( $name, $default_value = false ) use ( $stub ) {
				return array_key_exists( $name, $stub->options ) ? $stub->options[ $name ] : $default_value;
			}
		);
		Functions\when( 'add_option' )->alias(
			static function ( $name, $value = '', $deprecated = '', $autoload = null ) use ( $stub ) {
				unset( $deprecated );
				if ( array_key_exists( $name, $stub->options ) ) {
					return false;
				}
				$stub->options[ $name ]  = $value;
				$stub->autoload[ $name ] = (bool) $autoload;
				return true;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( $name, $value, $autoload = null ) use ( $stub ) {
				$stub->options[ $name ]  = $value;
				$stub->autoload[ $name ] = $autoload;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( $name ) use ( $stub ) {
				unset( $stub->options[ $name ], $stub->autoload[ $name ] );
				return true;
			}
		);

		return $stub;
	}
}
