<?php
/**
 * Classic Editor metabox.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Admin;

use DumpSEO\Analysis\AnalysisModule;
use DumpSEO\Context;
use DumpSEO\Meta\Keys;
use DumpSEO\Meta\Robots;
use DumpSEO\Module;

defined( 'ABSPATH' ) || exit;

/**
 * "DumpSEO" box below the Classic Editor: SEO fields, focus keyphrase,
 * social fields, search visibility and the analysis (assets/js/metabox.js
 * calls the analysis endpoint with the current, unsaved form values).
 *
 * Not shown in the block editor, which has the sidebar instead.
 * Security: nonce per post, `edit_post` capability, supported post types only.
 */
final class Metabox implements Module {

	public const NONCE_FIELD = 'dumpseo_post_nonce';

	/**
	 * Request context.
	 *
	 * @var Context
	 */
	private $context;

	/**
	 * Constructor.
	 *
	 * @param Context $context Request context.
	 */
	public function __construct( Context $context ) {
		$this->context = $context;
	}

	/**
	 * Admin requests only.
	 */
	public function should_load(): bool {
		return $this->context->is_admin();
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Adds the box on Classic Editor screens of supported post types.
	 *
	 * @param mixed $post_type Post type.
	 * @param mixed $post      Post.
	 */
	public function add( $post_type, $post = null ): void {
		if ( ! is_string( $post_type ) || ! PostTypes::is_supported( $post_type ) || ! $post instanceof \WP_Post || use_block_editor_for_post( $post ) ) {
			return;
		}
		// Core derives the heading id from the box id ("{id}-title"), so this must not collide with field ids.
		add_meta_box( 'dumpseo-box', __( 'DumpSEO', 'dumpseo' ), array( $this, 'render' ), $post_type, 'normal', 'high' );
	}

	/**
	 * Enqueues the metabox script and styles on Classic Editor screens.
	 *
	 * @param mixed $hook_suffix Admin page.
	 */
	public function enqueue( $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! PostTypes::is_supported( $post->post_type ) || use_block_editor_for_post( $post ) ) {
			return;
		}
		wp_enqueue_style( 'dumpseo-metabox', DUMPSEO_URL . 'assets/css/metabox.css', array(), DUMPSEO_VERSION );
		wp_enqueue_script( 'dumpseo-metabox', DUMPSEO_URL . 'assets/js/metabox.js', array( 'wp-api-fetch', 'wp-i18n' ), DUMPSEO_VERSION, true );
		wp_set_script_translations( 'dumpseo-metabox', 'dumpseo', DUMPSEO_DIR . 'languages' );
		wp_add_inline_script(
			'dumpseo-metabox',
			'window.dumpseoMetabox = ' . wp_json_encode(
				array(
					'postId' => $post->ID,
					'path'   => '/' . AnalysisModule::REST_NAMESPACE . '/analysis',
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Renders the box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render( \WP_Post $post ): void {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$meta = array();
		foreach ( Keys::ALL as $key ) {
			$meta[ $key ] = (string) get_post_meta( $post->ID, $key, true );
		}
		$robots = Robots::parse( $meta[ Keys::ROBOTS ] );
		$index  = in_array( 'noindex', $robots, true ) ? 'noindex' : ( in_array( 'index', $robots, true ) ? 'index' : '' );

		wp_nonce_field( 'dumpseo_post_' . $post->ID, self::NONCE_FIELD );
		?>
		<div class="dumpseo-metabox">
			<p>
				<label for="dumpseo-keyphrase"><strong><?php esc_html_e( 'Focus keyphrase', 'dumpseo' ); ?></strong></label>
				<input type="text" class="widefat" id="dumpseo-keyphrase" name="<?php echo esc_attr( SeoForm::KEYPHRASE_FIELD ); ?>" value="<?php echo esc_attr( $meta[ Keys::FOCUS_KEYPHRASE ] ); ?>" aria-describedby="dumpseo-keyphrase-help" />
				<span class="description" id="dumpseo-keyphrase-help"><?php esc_html_e( 'The words people would search for to find this page.', 'dumpseo' ); ?></span>
			</p>
			<p>
				<label for="dumpseo-title"><strong><?php esc_html_e( 'SEO title', 'dumpseo' ); ?></strong></label>
				<input type="text" class="widefat" id="dumpseo-title" name="<?php echo esc_attr( SeoForm::TITLE_FIELD ); ?>" value="<?php echo esc_attr( $meta[ Keys::TITLE ] ); ?>" aria-describedby="dumpseo-title-help" />
				<span class="description" id="dumpseo-title-help"><?php esc_html_e( 'Leave empty to use the title template. Variables such as %%site_name%% are allowed.', 'dumpseo' ); ?></span>
			</p>
			<p>
				<label for="dumpseo-description"><strong><?php esc_html_e( 'Meta description', 'dumpseo' ); ?></strong></label>
				<textarea class="widefat" rows="3" id="dumpseo-description" name="<?php echo esc_attr( SeoForm::DESC_FIELD ); ?>" aria-describedby="dumpseo-description-help"><?php echo esc_textarea( $meta[ Keys::DESCRIPTION ] ); ?></textarea>
				<span class="description" id="dumpseo-description-help"><?php esc_html_e( 'Leave empty to use the description template.', 'dumpseo' ); ?></span>
			</p>

			<div class="dumpseo-preview" aria-live="polite">
				<p class="dumpseo-preview__label"><?php esc_html_e( 'Search result preview', 'dumpseo' ); ?></p>
				<p class="dumpseo-preview__title"></p>
				<p class="dumpseo-preview__url"><?php echo esc_html( (string) get_permalink( $post ) ); ?></p>
				<p class="dumpseo-preview__description"></p>
			</div>

			<p>
				<button type="button" class="button" id="dumpseo-analyse"><?php esc_html_e( 'Check SEO and readability', 'dumpseo' ); ?></button>
				<span class="dumpseo-analysis-status" role="status"></span>
			</p>
			<div class="dumpseo-results" id="dumpseo-results"></div>

			<details>
				<summary><?php esc_html_e( 'Social sharing', 'dumpseo' ); ?></summary>
				<p>
					<label for="dumpseo-social-title"><?php esc_html_e( 'Social sharing title', 'dumpseo' ); ?></label>
					<input type="text" class="widefat" id="dumpseo-social-title" name="<?php echo esc_attr( SeoForm::SOCIAL_TITLE_FIELD ); ?>" value="<?php echo esc_attr( $meta[ Keys::SOCIAL_TITLE ] ); ?>" />
				</p>
				<p>
					<label for="dumpseo-social-description"><?php esc_html_e( 'Social sharing description', 'dumpseo' ); ?></label>
					<textarea class="widefat" rows="2" id="dumpseo-social-description" name="<?php echo esc_attr( SeoForm::SOCIAL_DESC_FIELD ); ?>"><?php echo esc_textarea( $meta[ Keys::SOCIAL_DESCRIPTION ] ); ?></textarea>
				</p>
				<p>
					<label for="dumpseo-social-image"><?php esc_html_e( 'Social sharing image URL', 'dumpseo' ); ?></label>
					<input type="url" class="widefat" id="dumpseo-social-image" name="<?php echo esc_attr( SeoForm::SOCIAL_IMAGE_FIELD ); ?>" value="<?php echo esc_attr( $meta[ Keys::SOCIAL_IMAGE ] ); ?>" aria-describedby="dumpseo-social-image-help" />
					<span class="description" id="dumpseo-social-image-help"><?php esc_html_e( 'Leave empty to use the featured image, then the default sharing image.', 'dumpseo' ); ?></span>
				</p>
			</details>

			<details>
				<summary><?php esc_html_e( 'Advanced', 'dumpseo' ); ?></summary>
				<p>
					<label for="dumpseo-robots-index"><?php esc_html_e( 'Search engines', 'dumpseo' ); ?></label>
					<select id="dumpseo-robots-index" name="<?php echo esc_attr( SeoForm::INDEX_FIELD ); ?>">
						<option value="" <?php selected( $index, '' ); ?>><?php esc_html_e( 'Default (from DumpSEO settings)', 'dumpseo' ); ?></option>
						<option value="index" <?php selected( $index, 'index' ); ?>><?php esc_html_e( 'Show in search results (index)', 'dumpseo' ); ?></option>
						<option value="noindex" <?php selected( $index, 'noindex' ); ?>><?php esc_html_e( 'Hide from search results (noindex)', 'dumpseo' ); ?></option>
					</select>
				</p>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Additional search engine directives', 'dumpseo' ); ?></legend>
					<?php foreach ( SeoForm::directive_labels() as $directive => $label ) : ?>
						<label><input type="checkbox" name="<?php echo esc_attr( SeoForm::ROBOT_FIELD ); ?>[]" value="<?php echo esc_attr( $directive ); ?>" <?php checked( in_array( $directive, $robots, true ) ); ?> /> <?php echo esc_html( $label ); ?></label><br />
					<?php endforeach; ?>
				</fieldset>
				<p>
					<label for="dumpseo-canonical"><?php esc_html_e( 'Canonical URL', 'dumpseo' ); ?></label>
					<input type="url" class="widefat" id="dumpseo-canonical" name="<?php echo esc_attr( SeoForm::CANON_FIELD ); ?>" value="<?php echo esc_attr( $meta[ Keys::CANONICAL ] ); ?>" aria-describedby="dumpseo-canonical-help" />
					<span class="description" id="dumpseo-canonical-help"><?php esc_html_e( 'Only if this page duplicates another one. Leave empty for the page’s own address.', 'dumpseo' ); ?></span>
				</p>
			</details>
		</div>
		<?php
	}

	/**
	 * Saves the fields.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $post    Post (from a core action; verified below).
	 */
	public function save( $post_id, $post ): void {
		$post_id = (int) $post_id;
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! $post instanceof \WP_Post ) {
			return; // Not our form: block editor (saves meta over REST), quick edit, programmatic updates.
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, 'dumpseo_post_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! PostTypes::is_supported( $post->post_type ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is validated and sanitized in SeoForm::values().
		$values = SeoForm::values( wp_unslash( $_POST ), true );
		SeoForm::apply(
			$values,
			static function ( string $key, string $value ) use ( $post_id ): void {
				update_post_meta( $post_id, $key, wp_slash( $value ) );
			},
			static function ( string $key ) use ( $post_id ): void {
				delete_post_meta( $post_id, $key );
			}
		);
	}
}
