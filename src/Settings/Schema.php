<?php
/**
 * Settings schema.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The list of site-wide settings and the sections they belong to.
 *
 * Feature phases add their fields here. Extensions add fields with the
 * `dumpseo_settings_fields` filter.
 */
class Schema {

	/**
	 * Separator keys => characters used in titles.
	 */
	public const SEPARATORS = array(
		'hyphen' => '-',
		'ndash'  => '–',
		'mdash'  => '—',
		'pipe'   => '|',
		'middot' => '·',
		'bullet' => '•',
		'raquo'  => '»',
	);

	/**
	 * Built fields, cached per request.
	 *
	 * @var array<string, Field>|null
	 */
	private $fields = null;

	/**
	 * Cache validity code the fields were built under.
	 *
	 * @var string
	 */
	private $cache_code = '';

	/**
	 * Section IDs => translated titles, in display order.
	 *
	 * @return array<string, string>
	 */
	public function sections(): array {
		$sections = array(
			'general'  => __( 'Site identity', 'dumpseo' ),
			'sitemap'  => __( 'XML sitemap', 'dumpseo' ),
			'social'   => __( 'Social sharing', 'dumpseo' ),
			'schema'   => __( 'Structured data', 'dumpseo' ),
			'crumbs'   => __( 'Breadcrumbs', 'dumpseo' ),
			'images'   => __( 'Images', 'dumpseo' ),
			'advanced' => __( 'Advanced', 'dumpseo' ),
		);

		/**
		 * Filters the settings sections (ID => title, in display order).
		 *
		 * @param array<string, mixed> $sections Sections.
		 */
		$filtered = apply_filters( 'dumpseo_settings_sections', $sections );

		$clean = array();
		foreach ( (array) $filtered as $id => $title ) {
			if ( is_string( $title ) ) {
				$clean[ (string) $id ] = $title;
			}
		}
		return $clean;
	}

	/**
	 * All fields keyed by storage key.
	 *
	 * @return array<string, Field>
	 */
	public function fields(): array {
		if ( null !== $this->fields && $this->cache_code === $this->cache_code() ) {
			return $this->fields;
		}

		$fields = array(
			new Field(
				'separator',
				'general',
				Field::TYPE_ENUM,
				'ndash',
				__( 'Title separator', 'dumpseo' ),
				__( 'Placed between parts of generated titles, for example “Post title – Site name”.', 'dumpseo' ),
				self::SEPARATORS
			),
			new Field(
				'site_represents',
				'general',
				Field::TYPE_ENUM,
				'organization',
				__( 'This website represents', 'dumpseo' ),
				__( 'Used in structured data to describe who publishes this site.', 'dumpseo' ),
				array(
					'organization' => __( 'An organization', 'dumpseo' ),
					'person'       => __( 'A person', 'dumpseo' ),
				)
			),
			new Field(
				'organization_name',
				'general',
				Field::TYPE_TEXT,
				'',
				__( 'Organization or person name', 'dumpseo' ),
				__( 'Leave empty to use the site title.', 'dumpseo' )
			),
			new Field(
				'organization_logo',
				'general',
				Field::TYPE_IMAGE_URL,
				'',
				__( 'Logo URL', 'dumpseo' ),
				__( 'A square image of at least 112 × 112 pixels works best.', 'dumpseo' )
			),
			new Field(
				'sitemap_enabled',
				'sitemap',
				Field::TYPE_BOOL,
				true,
				__( 'Enable the XML sitemap', 'dumpseo' ),
				__( 'Helps search engines find your content. Uses the sitemap built into WordPress at /wp-sitemap.xml.', 'dumpseo' )
			),
			new Field(
				'sitemap_images',
				'sitemap',
				Field::TYPE_BOOL,
				true,
				__( 'Include images', 'dumpseo' ),
				__( 'Lists the featured image and up to 10 images from this site found in each post.', 'dumpseo' )
			),
			new Field(
				'sitemap_users',
				'sitemap',
				Field::TYPE_BOOL,
				true,
				__( 'Include author archives', 'dumpseo' ),
				__( 'Always left out while author archives are hidden from search engines.', 'dumpseo' )
			),
			new Field(
				'social_og_enabled',
				'social',
				Field::TYPE_BOOL,
				true,
				__( 'Add Open Graph tags', 'dumpseo' ),
				__( 'Controls the title, description and image shown when a page is shared on Facebook, LinkedIn, WhatsApp, Slack and similar apps.', 'dumpseo' )
			),
			new Field(
				'social_twitter_enabled',
				'social',
				Field::TYPE_BOOL,
				true,
				__( 'Add X (Twitter) Card tags', 'dumpseo' ),
				__( 'Controls how links look when shared on X.', 'dumpseo' )
			),
			new Field(
				'default_social_image',
				'social',
				Field::TYPE_IMAGE_URL,
				'',
				__( 'Default sharing image URL', 'dumpseo' ),
				__( 'Used when a page has no featured image. 1200 × 630 pixels is recommended.', 'dumpseo' )
			),
			new Field(
				'twitter_site',
				'social',
				Field::TYPE_TWITTER_HANDLE,
				'',
				__( 'X (Twitter) username', 'dumpseo' ),
				__( 'Without the @. Letters, numbers and underscores, up to 15 characters.', 'dumpseo' )
			),
			new Field(
				'schema_enabled',
				'schema',
				Field::TYPE_BOOL,
				true,
				__( 'Add structured data (schema.org)', 'dumpseo' ),
				__( 'Describes your site, pages, articles, authors and breadcrumbs to search engines in one JSON-LD block. Uses the “Site identity” settings above.', 'dumpseo' )
			),
			new Field(
				'breadcrumbs_home',
				'crumbs',
				Field::TYPE_TEXT,
				'',
				__( 'Label for the homepage', 'dumpseo' ),
				__( 'First item of the trail. Leave empty for “Home”.', 'dumpseo' )
			),
			new Field(
				'breadcrumbs_separator',
				'crumbs',
				Field::TYPE_TEXT,
				'›',
				__( 'Separator', 'dumpseo' ),
				__( 'Shown between items, for example › or / or ». Screen readers skip it.', 'dumpseo' )
			),
			new Field(
				'remove_data_on_uninstall',
				'advanced',
				Field::TYPE_BOOL,
				false,
				__( 'Remove all DumpSEO data when the plugin is deleted', 'dumpseo' ),
				__( 'Deletes DumpSEO settings and SEO data stored for your content. Your posts and pages are never deleted. This cannot be undone.', 'dumpseo' )
			),
		);

		$by_key = array();
		foreach ( $fields as $field ) {
			$by_key[ $field->key ] = $field;
		}

		/**
		 * Filters the settings fields.
		 *
		 * Entries that are not Field instances are ignored.
		 *
		 * @param array<string, mixed> $by_key Fields keyed by storage key.
		 */
		$filtered = apply_filters( 'dumpseo_settings_fields', $by_key );

		$result = array();
		foreach ( (array) $filtered as $field ) {
			if ( $field instanceof Field ) {
				$result[ $field->key ] = $field;
			}
		}

		$this->fields     = $result;
		$this->cache_code = $this->cache_code();
		return $result;
	}

	/**
	 * Changes whenever a post type or taxonomy is registered or unregistered,
	 * because fields added by extensions (search appearance templates) are
	 * derived from them.
	 */
	private function cache_code(): string {
		return implode(
			':',
			array(
				did_action( 'init' ),
				did_action( 'registered_post_type' ),
				did_action( 'unregistered_post_type' ),
				did_action( 'registered_taxonomy' ),
				did_action( 'unregistered_taxonomy' ),
			)
		);
	}

	/**
	 * Default value for every field.
	 *
	 * @return array<string, bool|string>
	 */
	public function defaults(): array {
		$defaults = array();
		foreach ( $this->fields() as $key => $field ) {
			$defaults[ $key ] = $field->default;
		}
		return $defaults;
	}
}
