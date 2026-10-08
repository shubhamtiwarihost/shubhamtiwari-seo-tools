<?php
/**
 * Search appearance settings: templates and indexing per page type.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Meta;

use DumpSEO\Settings\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the search appearance settings per page type: a title template, a
 * description template and (where it makes sense) a noindex toggle for the
 * homepage, each public post type, each post type archive, each public
 * taxonomy, author archives, date archives, search results and 404.
 *
 * The homepage has no noindex toggle on purpose; search and 404 are always noindex.
 *
 * Built after `init`, when post types and taxonomies are registered.
 */
final class SearchAppearanceFields {

	public const SECTION = 'search_appearance';

	/**
	 * All template fields.
	 *
	 * @return Field[]
	 */
	public function fields(): array {
		$page   = ' %%separator%% %%page%% %%separator%% %%site_name%%';
		$fields = array(
			$this->title( 'home', __( 'Homepage', 'dumpseo' ), '%%site_name%% %%separator%% %%page%% %%separator%% %%sitedesc%%' ),
			$this->desc( 'home', __( 'Homepage', 'dumpseo' ), '%%sitedesc%%' ),
		);

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			$slug     = Keys::slug( $type->name );
			$label    = (string) $type->labels->name;
			$fields[] = $this->title( 'pt_' . $slug, $label, '%%title%%' . $page );
			$fields[] = $this->desc( 'pt_' . $slug, $label, '%%excerpt%%' );
			$fields[] = $this->noindex( 'pt_' . $slug, $label );

			if ( $type->has_archive ) {
				/* translators: %s: post type plural name, e.g. "Products". */
				$archive  = sprintf( __( '%s archive', 'dumpseo' ), $label );
				$fields[] = $this->title( 'ptarchive_' . $slug, $archive, '%%pt_plural%%' . $page );
				$fields[] = $this->desc( 'ptarchive_' . $slug, $archive, '%%description%%' );
			}
		}

		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			$slug     = Keys::slug( $taxonomy->name );
			$label    = (string) $taxonomy->labels->name;
			$fields[] = $this->title( 'tax_' . $slug, $label, '%%title%%' . $page );
			$fields[] = $this->desc( 'tax_' . $slug, $label, '%%description%%' );
			$fields[] = $this->noindex( 'tax_' . $slug, $label );
		}

		$author   = __( 'Author archives', 'dumpseo' );
		$fields[] = $this->title( 'author', $author, '%%author%%' . $page );
		$fields[] = $this->desc( 'author', $author, '%%description%%' );
		$fields[] = $this->noindex( 'author', $author );

		$date     = __( 'Date archives', 'dumpseo' );
		$fields[] = $this->title( 'date', $date, '%%date%%' . $page );
		$fields[] = $this->desc( 'date', $date, '' );
		$fields[] = $this->noindex( 'date', $date );

		$fields[] = $this->title(
			'search',
			__( 'Search results', 'dumpseo' ),
			/* translators: Keep the %%searchphrase%%, %%page%%, %%separator%% and %%site_name%% variables unchanged. */
			__( 'Search results for “%%searchphrase%%” %%separator%% %%page%% %%separator%% %%site_name%%', 'dumpseo' )
		);
		$fields[] = $this->title(
			'404',
			__( 'Page not found (404)', 'dumpseo' ),
			/* translators: Keep the %%separator%% and %%site_name%% variables unchanged. */
			__( 'Page not found %%separator%% %%site_name%%', 'dumpseo' )
		);

		return $fields;
	}

	/**
	 * Title template field.
	 *
	 * @param string $suffix  Key suffix.
	 * @param string $label   Page type label.
	 * @param string $default_value Default template.
	 */
	private function title( string $suffix, string $label, string $default_value ): Field {
		/* translators: %s: page type, e.g. "Posts" or "Homepage". */
		return new Field( 'title_' . $suffix, self::SECTION, Field::TYPE_TEMPLATE, $default_value, sprintf( __( '%s: title', 'dumpseo' ), $label ) );
	}

	/**
	 * Description template field.
	 *
	 * @param string $suffix  Key suffix.
	 * @param string $label   Page type label.
	 * @param string $default_value Default template.
	 */
	private function desc( string $suffix, string $label, string $default_value ): Field {
		/* translators: %s: page type, e.g. "Posts" or "Homepage". */
		return new Field( 'desc_' . $suffix, self::SECTION, Field::TYPE_TEMPLATE, $default_value, sprintf( __( '%s: meta description', 'dumpseo' ), $label ) );
	}

	/**
	 * "Hide from search engines" toggle. Off by default: nothing is hidden unless the owner asks.
	 *
	 * @param string $suffix Key suffix.
	 * @param string $label  Page type label.
	 */
	private function noindex( string $suffix, string $label ): Field {
		return new Field(
			'noindex_' . $suffix,
			self::SECTION,
			Field::TYPE_BOOL,
			false,
			/* translators: %s: page type, e.g. "Posts" or "Author archives". */
			sprintf( __( '%s: hide from search engines (noindex)', 'dumpseo' ), $label ),
			__( 'Individual items can still be set to “Index” in their own SEO settings.', 'dumpseo' )
		);
	}

	/**
	 * Help text listing the variables, shown above the section.
	 */
	public function render_help(): void {
		$variables = array(
			'%%title%%'        => __( 'Title of the post, term, author or archive', 'dumpseo' ),
			'%%site_name%%'    => __( 'Site title', 'dumpseo' ),
			'%%sitedesc%%'     => __( 'Site tagline', 'dumpseo' ),
			'%%separator%%'    => __( 'Title separator chosen above', 'dumpseo' ),
			'%%excerpt%%'      => __( 'Post excerpt, or the start of the content', 'dumpseo' ),
			'%%description%%'  => __( 'Term description, author biography or post type description', 'dumpseo' ),
			'%%category%%'     => __( 'First category of the post', 'dumpseo' ),
			'%%author%%'       => __( 'Author name', 'dumpseo' ),
			'%%date%%'         => __( 'Publish date, or the date of a date archive', 'dumpseo' ),
			'%%page%%'         => __( '“Page 2 of 5” on paginated pages; empty on page 1', 'dumpseo' ),
			'%%searchphrase%%' => __( 'Search terms', 'dumpseo' ),
			'%%pt_singular%%'  => __( 'Post type name, singular', 'dumpseo' ),
			'%%pt_plural%%'    => __( 'Post type name, plural', 'dumpseo' ),
			'%%currentyear%%'  => __( 'Current year', 'dumpseo' ),
		);

		echo '<p>' . esc_html__( 'Templates are used when a post, page or term has no custom title or description of its own. Available variables:', 'dumpseo' ) . '</p><dl class="dumpseo-variables">';
		foreach ( $variables as $variable => $meaning ) {
			printf( '<dt><code>%1$s</code></dt><dd>%2$s</dd>', esc_html( $variable ), esc_html( $meaning ) );
		}
		echo '</dl>';
	}
}
