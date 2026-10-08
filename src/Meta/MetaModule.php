<?php
/**
 * Registers SEO meta fields and template settings.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Meta;

use DumpSEO\Helpers\Text;
use DumpSEO\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Registers per-post and per-term title/description meta (exposed to the REST
 * API for the block editor, with a per-object capability check) and adds the
 * search appearance template settings.
 */
final class MetaModule implements Module {

	/**
	 * Template field builder.
	 *
	 * @var SearchAppearanceFields
	 */
	private $template_fields;

	/**
	 * Constructor.
	 *
	 * @param SearchAppearanceFields $template_fields Template field builder.
	 */
	public function __construct( SearchAppearanceFields $template_fields ) {
		$this->template_fields = $template_fields;
	}

	/**
	 * Needed on every request: meta must be registered for REST and saving,
	 * and settings are read on the frontend.
	 */
	public function should_load(): bool {
		return true;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_filter( 'dumpseo_settings_fields', array( $this, 'add_template_fields' ) );
		add_filter( 'dumpseo_settings_sections', array( $this, 'add_section' ) );
		add_action( 'dumpseo_settings_section_' . SearchAppearanceFields::SECTION, array( $this->template_fields, 'render_help' ) );
	}

	/**
	 * Registers post and term meta for all object subtypes.
	 */
	public function register_meta(): void {
		$sanitizers = array(
			Keys::TITLE              => array( $this, 'sanitize' ),
			Keys::DESCRIPTION        => array( $this, 'sanitize' ),
			Keys::CANONICAL          => array( $this, 'sanitize_canonical' ),
			Keys::ROBOTS             => array( Robots::class, 'sanitize' ),
			Keys::SOCIAL_TITLE       => array( $this, 'sanitize' ),
			Keys::SOCIAL_DESCRIPTION => array( $this, 'sanitize' ),
			Keys::SOCIAL_IMAGE       => array( $this, 'sanitize_canonical' ),
			Keys::FOCUS_KEYPHRASE    => 'sanitize_text_field',
		);

		foreach ( $sanitizers as $key => $sanitizer ) {
			$common = array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => $sanitizer,
			);
			register_post_meta(
				'',
				$key,
				$common + array(
					'auth_callback' => static function ( $allowed, $meta_key, $post_id, $user_id ): bool {
						unset( $allowed, $meta_key );
						return user_can( (int) $user_id, 'edit_post', (int) $post_id );
					},
				)
			);
			register_term_meta(
				'',
				$key,
				$common + array(
					'auth_callback' => static function ( $allowed, $meta_key, $term_id, $user_id ): bool {
						unset( $allowed, $meta_key );
						return user_can( (int) $user_id, 'edit_term', (int) $term_id );
					},
				)
			);
		}
	}

	/**
	 * Meta sanitize callback: single-line plain text; %%variables%% preserved.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize( $value ): string {
		return Text::sanitize_line( $value );
	}

	/**
	 * URL sanitize callback (canonical, social image): absolute http(s) URL, or '' for anything invalid.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_canonical( $value ): string {
		return (string) Text::http_url( $value );
	}

	/**
	 * Adds template fields to the settings schema.
	 *
	 * @param mixed $fields Fields keyed by storage key.
	 * @return array<string, mixed>
	 */
	public function add_template_fields( $fields ): array {
		$fields = (array) $fields;
		foreach ( $this->template_fields->fields() as $field ) {
			$fields[ $field->key ] = $field;
		}
		return $fields;
	}

	/**
	 * Adds the "Search appearance" section after "Site identity".
	 *
	 * @param mixed $sections Section IDs => titles.
	 * @return array<string, string>
	 */
	public function add_section( $sections ): array {
		$result = array();
		foreach ( (array) $sections as $id => $title ) {
			$result[ (string) $id ] = (string) $title;
			if ( 'general' === $id ) {
				$result[ SearchAppearanceFields::SECTION ] = __( 'Search appearance', 'dumpseo' );
			}
		}
		if ( ! isset( $result[ SearchAppearanceFields::SECTION ] ) ) {
			$result[ SearchAppearanceFields::SECTION ] = __( 'Search appearance', 'dumpseo' );
		}
		return $result;
	}
}
