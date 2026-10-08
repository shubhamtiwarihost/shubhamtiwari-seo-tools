<?php
/**
 * SEO fields on the term edit screen.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Admin;

use DumpSEO\Context;
use DumpSEO\Meta\Keys;
use DumpSEO\Meta\Robots;
use DumpSEO\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Adds SEO title, meta description, canonical URL and robots settings to the
 * edit screen of every public taxonomy that has an admin UI, and saves them.
 *
 * Security: nonce per term, `edit_term` capability, and the taxonomy must be
 * one we render fields for.
 */
final class TermFields implements Module {

	public const NONCE_FIELD        = 'dumpseo_term_nonce';
	public const TITLE_FIELD        = SeoForm::TITLE_FIELD;
	public const DESC_FIELD         = SeoForm::DESC_FIELD;
	public const CANON_FIELD        = SeoForm::CANON_FIELD;
	public const INDEX_FIELD        = SeoForm::INDEX_FIELD;
	public const ROBOT_FIELD        = SeoForm::ROBOT_FIELD;
	public const SOCIAL_TITLE_FIELD = SeoForm::SOCIAL_TITLE_FIELD;
	public const SOCIAL_DESC_FIELD  = SeoForm::SOCIAL_DESC_FIELD;
	public const SOCIAL_IMAGE_FIELD = SeoForm::SOCIAL_IMAGE_FIELD;

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
		add_action( 'admin_init', array( $this, 'add_form_hooks' ) );
		add_action( 'edited_term', array( $this, 'save' ), 10, 3 );
	}

	/**
	 * Taxonomies that get SEO fields.
	 *
	 * @return string[]
	 */
	public function taxonomies(): array {
		return array_values(
			get_taxonomies(
				array(
					'public'  => true,
					'show_ui' => true,
				)
			)
		);
	}

	/**
	 * Hooks the form renderer into each taxonomy's edit screen.
	 */
	public function add_form_hooks(): void {
		foreach ( $this->taxonomies() as $taxonomy ) {
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'render' ), 10, 1 );
		}
	}

	/**
	 * Renders the fields as table rows on the term edit screen.
	 *
	 * @param mixed $term Term being edited (from a core action; verified below).
	 */
	public function render( $term ): void {
		if ( ! $term instanceof \WP_Term || ! current_user_can( 'edit_term', $term->term_id ) ) {
			return;
		}

		$title       = (string) get_term_meta( $term->term_id, Keys::TITLE, true );
		$description = (string) get_term_meta( $term->term_id, Keys::DESCRIPTION, true );
		$canonical   = (string) get_term_meta( $term->term_id, Keys::CANONICAL, true );
		$social      = array(
			'title'       => (string) get_term_meta( $term->term_id, Keys::SOCIAL_TITLE, true ),
			'description' => (string) get_term_meta( $term->term_id, Keys::SOCIAL_DESCRIPTION, true ),
			'image'       => (string) get_term_meta( $term->term_id, Keys::SOCIAL_IMAGE, true ),
		);
		$robots      = Robots::parse( get_term_meta( $term->term_id, Keys::ROBOTS, true ) );
		$index       = in_array( 'noindex', $robots, true ) ? 'noindex' : ( in_array( 'index', $robots, true ) ? 'index' : '' );
		$directives  = SeoForm::directive_labels();

		wp_nonce_field( $this->nonce_action( $term->term_id ), self::NONCE_FIELD );
		?>
		<tr class="form-field dumpseo-term-title">
			<th scope="row"><label for="dumpseo-term-title"><?php esc_html_e( 'SEO title', 'dumpseo' ); ?></label></th>
			<td>
				<input type="text" id="dumpseo-term-title" name="<?php echo esc_attr( self::TITLE_FIELD ); ?>" value="<?php echo esc_attr( $title ); ?>" aria-describedby="dumpseo-term-title-description" />
				<p class="description" id="dumpseo-term-title-description"><?php esc_html_e( 'Leave empty to use the title template from DumpSEO settings. Variables such as %%site_name%% are allowed.', 'dumpseo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field dumpseo-term-description">
			<th scope="row"><label for="dumpseo-term-description"><?php esc_html_e( 'Meta description', 'dumpseo' ); ?></label></th>
			<td>
				<textarea id="dumpseo-term-description" name="<?php echo esc_attr( self::DESC_FIELD ); ?>" rows="3" aria-describedby="dumpseo-term-description-help"><?php echo esc_textarea( $description ); ?></textarea>
				<p class="description" id="dumpseo-term-description-help"><?php esc_html_e( 'Leave empty to use the description template.', 'dumpseo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field dumpseo-term-canonical">
			<th scope="row"><label for="dumpseo-term-canonical"><?php esc_html_e( 'Canonical URL', 'dumpseo' ); ?></label></th>
			<td>
				<input type="url" id="dumpseo-term-canonical" name="<?php echo esc_attr( self::CANON_FIELD ); ?>" value="<?php echo esc_attr( $canonical ); ?>" aria-describedby="dumpseo-term-canonical-help" />
				<p class="description" id="dumpseo-term-canonical-help"><?php esc_html_e( 'Only if this archive duplicates another page. Must be a full address starting with https:// or http://. Leave empty for the archive’s own address.', 'dumpseo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field dumpseo-term-robots">
			<th scope="row"><label for="dumpseo-term-robots-index"><?php esc_html_e( 'Search engines', 'dumpseo' ); ?></label></th>
			<td>
				<select id="dumpseo-term-robots-index" name="<?php echo esc_attr( self::INDEX_FIELD ); ?>">
					<option value="" <?php selected( $index, '' ); ?>><?php esc_html_e( 'Default (from DumpSEO settings)', 'dumpseo' ); ?></option>
					<option value="index" <?php selected( $index, 'index' ); ?>><?php esc_html_e( 'Show in search results (index)', 'dumpseo' ); ?></option>
					<option value="noindex" <?php selected( $index, 'noindex' ); ?>><?php esc_html_e( 'Hide from search results (noindex)', 'dumpseo' ); ?></option>
				</select>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Additional search engine directives', 'dumpseo' ); ?></legend>
					<?php foreach ( $directives as $directive => $label ) : ?>
						<label><input type="checkbox" name="<?php echo esc_attr( self::ROBOT_FIELD ); ?>[]" value="<?php echo esc_attr( $directive ); ?>" <?php checked( in_array( $directive, $robots, true ) ); ?> /> <?php echo esc_html( $label ); ?></label><br />
					<?php endforeach; ?>
				</fieldset>
			</td>
		</tr>
		<tr class="form-field dumpseo-term-social-title">
			<th scope="row"><label for="dumpseo-term-social-title"><?php esc_html_e( 'Social sharing title', 'dumpseo' ); ?></label></th>
			<td>
				<input type="text" id="dumpseo-term-social-title" name="<?php echo esc_attr( self::SOCIAL_TITLE_FIELD ); ?>" value="<?php echo esc_attr( $social['title'] ); ?>" aria-describedby="dumpseo-term-social-title-help" />
				<p class="description" id="dumpseo-term-social-title-help"><?php esc_html_e( 'Shown when this archive is shared on social media. Leave empty to use the SEO title.', 'dumpseo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field dumpseo-term-social-description">
			<th scope="row"><label for="dumpseo-term-social-description"><?php esc_html_e( 'Social sharing description', 'dumpseo' ); ?></label></th>
			<td>
				<textarea id="dumpseo-term-social-description" name="<?php echo esc_attr( self::SOCIAL_DESC_FIELD ); ?>" rows="2" aria-describedby="dumpseo-term-social-description-help"><?php echo esc_textarea( $social['description'] ); ?></textarea>
				<p class="description" id="dumpseo-term-social-description-help"><?php esc_html_e( 'Leave empty to use the meta description.', 'dumpseo' ); ?></p>
			</td>
		</tr>
		<tr class="form-field dumpseo-term-social-image">
			<th scope="row"><label for="dumpseo-term-social-image"><?php esc_html_e( 'Social sharing image URL', 'dumpseo' ); ?></label></th>
			<td>
				<input type="url" id="dumpseo-term-social-image" name="<?php echo esc_attr( self::SOCIAL_IMAGE_FIELD ); ?>" value="<?php echo esc_attr( $social['image'] ); ?>" aria-describedby="dumpseo-term-social-image-help" />
				<p class="description" id="dumpseo-term-social-image-help"><?php esc_html_e( 'Full image address starting with https://. Leave empty to use the default sharing image.', 'dumpseo' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Saves the fields.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function save( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		unset( $tt_id );
		$term_id = (int) $term_id;

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return; // Not our form (e.g. quick edit or a programmatic update).
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, $this->nonce_action( $term_id ) ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) || ! in_array( $taxonomy, $this->taxonomies(), true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is validated and sanitized in SeoForm::values().
		$values = SeoForm::values( wp_unslash( $_POST ), false );
		SeoForm::apply(
			$values,
			static function ( string $key, string $value ) use ( $term_id ): void {
				update_term_meta( $term_id, $key, wp_slash( $value ) );
			},
			static function ( string $key ) use ( $term_id ): void {
				delete_term_meta( $term_id, $key );
			}
		);
	}

	/**
	 * Nonce action for a term.
	 *
	 * @param int $term_id Term ID.
	 */
	private function nonce_action( int $term_id ): string {
		return 'dumpseo_term_' . $term_id;
	}
}
