<?php
/**
 * Everything schema pieces need to know about the page.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Schema;

use DumpSEO\Meta\PageContext;

defined( 'ABSPATH' ) || exit;

/**
 * Plain values resolved once per request and shared by every piece, plus the
 * `@id` of each node so pieces can reference each other without knowing
 * which other pieces exist.
 */
final class SchemaContext {

	/**
	 * Page context.
	 *
	 * @var PageContext
	 */
	public $page;

	/**
	 * Canonical URL of the page ('' when the page has none, e.g. noindex).
	 *
	 * @var string
	 */
	public $url;

	/**
	 * SEO title (plain text).
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Meta description (plain text).
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Site home URL with trailing slash.
	 *
	 * @var string
	 */
	public $home;

	/**
	 * Site name (plain text).
	 *
	 * @var string
	 */
	public $site_name;

	/**
	 * BCP 47 language tag, e.g. "en-US".
	 *
	 * @var string
	 */
	public $language;

	/**
	 * "organization" or "person".
	 *
	 * @var string
	 */
	public $represents;

	/**
	 * Publisher name (plain text).
	 *
	 * @var string
	 */
	public $publisher_name;

	/**
	 * Publisher logo URL, or ''.
	 *
	 * @var string
	 */
	public $publisher_logo;

	/**
	 * Featured image of the page, or null.
	 *
	 * @var array{url: string, width: int, height: int, caption: string}|null
	 */
	public $image;

	/**
	 * Breadcrumb trail (empty when there is none).
	 *
	 * @var array<int, array{name: string, url: string}>
	 */
	public $trail;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $values Property values keyed by name.
	 */
	public function __construct( array $values ) {
		foreach ( $values as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
	}

	/**
	 * `@id` of the WebSite node.
	 */
	public function website_id(): string {
		return $this->home . '#website';
	}

	/**
	 * `@id` of the publisher (Organization or Person) node.
	 */
	public function publisher_id(): string {
		return $this->home . ( 'person' === $this->represents ? '#person' : '#organization' );
	}

	/**
	 * `@id` of the WebPage node.
	 */
	public function webpage_id(): string {
		return $this->url . '#webpage';
	}

	/**
	 * `@id` of the page's main image.
	 */
	public function image_id(): string {
		return $this->url . '#primaryimage';
	}

	/**
	 * `@id` of the BreadcrumbList node.
	 */
	public function breadcrumb_id(): string {
		return $this->url . '#breadcrumb';
	}

	/**
	 * `@id` of the Article node.
	 */
	public function article_id(): string {
		return $this->url . '#article';
	}

	/**
	 * `@id` of a post author's Person node. Based on the public author slug.
	 *
	 * @param \WP_User $user Author.
	 */
	public function author_id( \WP_User $user ): string {
		return $this->home . '#/schema/person/' . md5( $user->user_nicename );
	}

	/**
	 * Whether the page is a single post/page/CPT item (not the front or blog page).
	 */
	public function is_singular(): bool {
		return PageContext::SINGULAR === $this->page->type && $this->page->object instanceof \WP_Post;
	}

	/**
	 * A reference to another node.
	 *
	 * @param string $id Node `@id`.
	 * @return array{"@id": string}
	 */
	public static function ref( string $id ): array {
		return array( '@id' => $id );
	}
}
