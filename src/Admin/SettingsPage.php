<?php
/**
 * Settings screen.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Admin;

use DumpSEO\Context;
use DumpSEO\Module;
use DumpSEO\Settings\Field;
use DumpSEO\Settings\Sanitizer;
use DumpSEO\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the settings with the Settings API and renders the settings screen.
 *
 * Security: saving goes through options.php, which verifies the nonce printed
 * by settings_fields() and the capability returned by the
 * option_page_capability_{group} filter (manage_options).
 */
final class SettingsPage implements Module {

	public const PAGE       = 'dumpseo';
	public const GROUP      = 'dumpseo_settings_group';
	public const CAPABILITY = 'manage_options';

	/**
	 * Request context.
	 *
	 * @var Context
	 */
	private $context;

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Settings sanitizer.
	 *
	 * @var Sanitizer
	 */
	private $sanitizer;

	/**
	 * Constructor.
	 *
	 * @param Context   $context   Request context.
	 * @param Settings  $settings  Settings repository.
	 * @param Sanitizer $sanitizer Settings sanitizer.
	 */
	public function __construct( Context $context, Settings $settings, Sanitizer $sanitizer ) {
		$this->context   = $context;
		$this->settings  = $settings;
		$this->sanitizer = $sanitizer;
	}

	/**
	 * Admin requests only (options.php saves are admin requests too).
	 */
	public function should_load(): bool {
		return $this->context->is_admin();
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( $this, 'capability' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DUMPSEO_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Capability required to save the settings group.
	 */
	public function capability(): string {
		return self::CAPABILITY;
	}

	/**
	 * Registers the option, sections and fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);

		$schema = $this->settings->schema();
		foreach ( $schema->sections() as $id => $title ) {
			add_settings_section(
				'dumpseo_' . $id,
				$title,
				static function () use ( $id ) {
					/**
					 * Fires above the fields of a settings section, e.g. to print help text.
					 */
					do_action( 'dumpseo_settings_section_' . $id );
				},
				self::PAGE
			);
		}

		foreach ( $schema->fields() as $field ) {
			add_settings_field(
				'dumpseo_' . $field->key,
				esc_html( $field->label ),
				array( $this, 'render_field' ),
				self::PAGE,
				'dumpseo_' . $field->section,
				array(
					'field'     => $field,
					'label_for' => Field::TYPE_BOOL === $field->type ? null : $this->input_id( $field ),
				)
			);
		}
	}

	/**
	 * Settings API sanitize callback.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, bool|string>
	 */
	public function sanitize( $input ): array {
		$clean = $this->sanitizer->sanitize( $input, $this->settings->all() );

		foreach ( $this->sanitizer->errors() as $key => $message ) {
			add_settings_error( Settings::OPTION, 'dumpseo_invalid_' . $key, $message, 'error' );
		}
		return $clean;
	}

	/**
	 * Adds the top-level menu.
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'DumpSEO Settings', 'dumpseo' ),
			__( 'DumpSEO', 'dumpseo' ),
			self::CAPABILITY,
			self::PAGE,
			array( $this, 'render_page' ),
			'dashicons-search',
			80
		);
	}

	/**
	 * Media library picker for image fields, on the DumpSEO screen only.
	 *
	 * @param mixed $hook_suffix Admin page.
	 */
	public function enqueue( $hook_suffix ): void {
		if ( 'toplevel_page_' . self::PAGE !== $hook_suffix || ! current_user_can( 'upload_files' ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'dumpseo-settings', DUMPSEO_URL . 'assets/js/settings.js', array( 'jquery', 'wp-i18n' ), DUMPSEO_VERSION, true );
		wp_set_script_translations( 'dumpseo-settings', 'dumpseo', DUMPSEO_DIR . 'languages' );
	}

	/**
	 * Adds a "Settings" link on the Plugins screen.
	 *
	 * @param array<int|string, string> $links Existing links.
	 * @return array<int|string, string>
	 */
	public function action_links( array $links ): array {
		if ( current_user_can( self::CAPABILITY ) ) {
			array_unshift(
				$links,
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ),
					esc_html__( 'Settings', 'dumpseo' )
				)
			);
		}
		return $links;
	}

	/**
	 * Renders the settings screen.
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage these settings.', 'dumpseo' ), 403 );
		}

		$sections = implode( ',', array_keys( $this->settings->schema()->sections() ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors( Settings::OPTION ); ?>
			<?php if ( '0' === (string) get_option( 'blog_public' ) ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( 'Warning:', 'dumpseo' ); ?></strong>
						<?php esc_html_e( 'Search engines are currently asked not to index this entire site (Settings → Reading → “Discourage search engines from indexing this site”). DumpSEO does not override this.', 'dumpseo' ); ?>
						<a href="<?php echo esc_url( admin_url( 'options-reading.php' ) ); ?>"><?php esc_html_e( 'Change reading settings', 'dumpseo' ); ?></a>
					</p>
				</div>
			<?php endif; ?>
			<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post" novalidate="novalidate">
				<?php settings_fields( self::GROUP ); ?>
				<input type="hidden" name="<?php echo esc_attr( Settings::OPTION . '[' . Sanitizer::SECTIONS_KEY . ']' ); ?>" value="<?php echo esc_attr( $sections ); ?>" />
				<?php do_settings_sections( self::PAGE ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders one control.
	 *
	 * @param array{field: Field} $args Field arguments from add_settings_field().
	 */
	public function render_field( array $args ): void {
		$field       = $args['field'];
		$value       = $this->settings->get( $field->key );
		$id          = $this->input_id( $field );
		$name        = Settings::OPTION . '[' . $field->key . ']';
		$description = '' !== $field->description ? $id . '-description' : '';
		$describedby = '' !== $description ? ' aria-describedby="' . esc_attr( $description ) . '"' : '';

		switch ( $field->type ) {
			case Field::TYPE_BOOL:
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s /> %5$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( true, $value, false ),
					$describedby, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above with esc_attr().
					esc_html( $field->label )
				);
				break;

			case Field::TYPE_ENUM:
				printf( '<select id="%1$s" name="%2$s"%3$s>', esc_attr( $id ), esc_attr( $name ), $describedby ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $describedby built with esc_attr().
				foreach ( $field->choices as $choice => $label ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $choice ), selected( $value, $choice, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			default:
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="%5$s"%6$s />',
					in_array( $field->type, array( Field::TYPE_URL, Field::TYPE_IMAGE_URL ), true ) ? 'url' : 'text',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					Field::TYPE_TEMPLATE === $field->type ? 'large-text code' : 'regular-text',
					$describedby // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built above with esc_attr().
				);
		}

		if ( Field::TYPE_IMAGE_URL === $field->type && current_user_can( 'upload_files' ) ) {
			printf(
				' <button type="button" class="button dumpseo-pick-image" data-target="%1$s" aria-controls="%1$s">%2$s</button>',
				esc_attr( $id ),
				esc_html__( 'Choose from media library', 'dumpseo' )
			);
		}

		if ( '' !== $description ) {
			printf( '<p class="description" id="%1$s">%2$s</p>', esc_attr( $description ), esc_html( $field->description ) );
		}
	}

	/**
	 * HTML id for a field's control.
	 *
	 * @param Field $field Field.
	 */
	private function input_id( Field $field ): string {
		return 'dumpseo-' . str_replace( '_', '-', $field->key );
	}
}
