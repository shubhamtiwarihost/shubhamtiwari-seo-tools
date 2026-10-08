<?php
/**
 * Settings repository.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Read access to site-wide settings, with defaults applied.
 *
 * All settings live in one autoloaded option. Nothing is written until the
 * site owner saves the settings page, so a fresh install adds no rows.
 */
class Settings {

	public const OPTION = 'dumpseo_settings';

	/**
	 * Settings schema.
	 *
	 * @var Schema
	 */
	private $schema;

	/**
	 * Constructor.
	 *
	 * @param Schema $schema Settings schema.
	 */
	public function __construct( Schema $schema ) {
		$this->schema = $schema;
	}

	/**
	 * Every setting: stored values over defaults, unknown keys dropped.
	 *
	 * A value whose type does not match its field (e.g. corrupted by a direct
	 * database edit) falls back to the default.
	 *
	 * @return array<string, bool|string>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$values = array();
		foreach ( $this->schema->fields() as $key => $field ) {
			$value          = $stored[ $key ] ?? null;
			$values[ $key ] = $this->matches_type( $field, $value ) ? $value : $field->default;
		}
		return $values;
	}

	/**
	 * One setting, or null for an unknown key.
	 *
	 * @param string $key Setting key.
	 * @return bool|string|null
	 */
	public function get( string $key ) {
		return $this->all()[ $key ] ?? null;
	}

	/**
	 * The settings schema.
	 */
	public function schema(): Schema {
		return $this->schema;
	}

	/**
	 * Whether a stored value has the shape its field expects.
	 *
	 * @param Field $field Field.
	 * @param mixed $value Stored value.
	 */
	private function matches_type( Field $field, $value ): bool {
		if ( Field::TYPE_BOOL === $field->type ) {
			return is_bool( $value );
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		if ( Field::TYPE_ENUM === $field->type ) {
			return array_key_exists( $value, $field->choices );
		}
		return true;
	}
}
