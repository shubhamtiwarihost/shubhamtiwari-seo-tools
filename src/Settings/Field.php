<?php
/**
 * Settings field definition.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable description of one setting: storage key, type, default and UI text.
 */
final class Field {

	public const TYPE_BOOL           = 'bool';
	public const TYPE_TEXT           = 'text';
	public const TYPE_URL            = 'url';
	public const TYPE_ENUM           = 'enum';
	public const TYPE_TWITTER_HANDLE = 'twitter_handle';
	public const TYPE_TEMPLATE       = 'template';
	public const TYPE_IMAGE_URL      = 'image_url'; // Stored and validated like TYPE_URL; the settings screen adds a media library picker.

	/**
	 * Storage key inside the settings array.
	 *
	 * @var string
	 */
	public $key;

	/**
	 * Section ID the field is rendered in.
	 *
	 * @var string
	 */
	public $section;

	/**
	 * One of the TYPE_* constants.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Default value.
	 *
	 * @var bool|string
	 */
	public $default;

	/**
	 * Translated label.
	 *
	 * @var string
	 */
	public $label;

	/**
	 * Translated help text shown under the control.
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Allowed values => translated labels (TYPE_ENUM only).
	 *
	 * @var array<string, string>
	 */
	public $choices;

	/**
	 * Constructor.
	 *
	 * @param string                $key         Storage key.
	 * @param string                $section     Section ID.
	 * @param string                $type        TYPE_* constant.
	 * @param bool|string           $default_value Default value.
	 * @param string                $label       Translated label.
	 * @param string                $description Translated help text.
	 * @param array<string, string> $choices     Enum choices.
	 *
	 * @throws \InvalidArgumentException On an invalid definition.
	 */
	public function __construct( string $key, string $section, string $type, $default_value, string $label, string $description = '', array $choices = array() ) {
		if ( ! preg_match( '/^[a-z][a-z0-9_]*$/', $key ) ) {
			throw new \InvalidArgumentException( 'Invalid settings key.' );
		}
		if ( self::TYPE_ENUM === $type && ! array_key_exists( (string) $default_value, $choices ) ) {
			throw new \InvalidArgumentException( 'Enum default must be one of its choices.' );
		}

		$this->key         = $key;
		$this->section     = $section;
		$this->type        = $type;
		$this->default     = $default_value;
		$this->label       = $label;
		$this->description = $description;
		$this->choices     = $choices;
	}
}
