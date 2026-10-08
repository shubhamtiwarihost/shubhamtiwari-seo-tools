<?php
/**
 * Redirect storage.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Redirects are posts of type `dumpseo_redirect`:
 * - post_title  = normalised source path ("/old-page")
 * - meta `_dumpseo_redirect_target` = target URL or "/path" ('' for 410)
 * - meta `_dumpseo_redirect_type`   = 301, 302, 307 or 410
 * - post_status "publish" = active, "draft" = inactive
 *
 * Frontend matching never queries posts: active redirects are copied into the
 * `dumpseo_redirect_index` option (source => target/type) whenever one
 * changes. The option is autoloaded while it holds up to 500 redirects, so
 * matching costs no query at all; above that it is loaded on demand (one
 * query per request, cached by a persistent object cache).
 */
class Store {

	public const POST_TYPE    = 'dumpseo_redirect';
	public const META_TARGET  = '_dumpseo_redirect_target';
	public const META_TYPE    = '_dumpseo_redirect_type';
	public const INDEX_OPTION = 'dumpseo_redirect_index';
	public const TYPES        = array( 301, 302, 307, 410 );
	public const AUTOLOAD_MAX = 500;

	/**
	 * Whether a redirect changed since the index was last built.
	 *
	 * @var bool
	 */
	private $dirty = false;

	/**
	 * Marks the index as stale after a redirect changed. It is rebuilt once
	 * at the end of the request (however many redirects a bulk action or
	 * import touched), or earlier if something reads it first.
	 *
	 * @param mixed $post_id Changed post (any type; others are ignored).
	 */
	public function changed( $post_id ): void {
		if ( self::POST_TYPE !== get_post_type( (int) $post_id ) || $this->dirty ) {
			return;
		}
		$this->dirty = true;
		add_action( 'shutdown', array( $this, 'rebuild' ) );
	}

	/**
	 * Registers the post type. Every capability maps to manage_options.
	 */
	public static function register_post_type(): void {
		$cap = 'manage_options';
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Redirects', 'dumpseo' ),
					'singular_name'      => __( 'Redirect', 'dumpseo' ),
					'add_new'            => __( 'Add redirect', 'dumpseo' ),
					'add_new_item'       => __( 'Add redirect', 'dumpseo' ),
					'edit_item'          => __( 'Edit redirect', 'dumpseo' ),
					'new_item'           => __( 'New redirect', 'dumpseo' ),
					'search_items'       => __( 'Search redirects', 'dumpseo' ),
					'not_found'          => __( 'No redirects yet.', 'dumpseo' ),
					'not_found_in_trash' => __( 'No redirects in the trash.', 'dumpseo' ),
					'all_items'          => __( 'Redirects', 'dumpseo' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'dumpseo',
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'map_meta_cap'    => false,
				'capability_type' => 'dumpseo_redirect',
				'capabilities'    => array(
					'edit_post'              => $cap,
					'read_post'              => $cap,
					'delete_post'            => $cap,
					'edit_posts'             => $cap,
					'edit_others_posts'      => $cap,
					'delete_posts'           => $cap,
					'publish_posts'          => $cap,
					'read_private_posts'     => $cap,
					'create_posts'           => $cap,
					'delete_others_posts'    => $cap,
					'delete_published_posts' => $cap,
					'delete_private_posts'   => $cap,
					'edit_published_posts'   => $cap,
					'edit_private_posts'     => $cap,
				),
			)
		);
	}

	/**
	 * Active redirects: normalised source => {id, target, type}.
	 *
	 * @return array<string, array{id: int, target: string, type: int}>
	 */
	public function index(): array {
		if ( $this->dirty ) {
			$this->rebuild();
		}
		$index = get_option( self::INDEX_OPTION, null );
		if ( null === $index ) {
			// Created on install; rebuilt once here if it was ever lost (e.g. restored database).
			$this->rebuild();
			$index = get_option( self::INDEX_OPTION, array() );
		}
		return is_array( $index ) ? $index : array();
	}

	/**
	 * The active redirect for a normalised path, or null.
	 *
	 * @param string $path Normalised request path.
	 * @return array{id: int, target: string, type: int}|null
	 */
	public function find( string $path ): ?array {
		$index = $this->index();
		return $index[ $path ] ?? null;
	}

	/**
	 * Rebuilds the index from the active redirect posts (admin, on change only).
	 */
	public function rebuild(): void {
		$ids         = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		$this->dirty = false;
		remove_action( 'shutdown', array( $this, 'rebuild' ) );

		// Redirects created outside the admin screen (WP-CLI, imports, code) skip its validation,
		// so sources and targets are checked again here and invalid ones are left out.
		$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$base  = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		$index = array();
		foreach ( $ids as $id ) {
			$id     = (int) $id;
			$source = Paths::source( (string) get_post_field( 'post_title', $id, 'raw' ), $host, $base ); // Not get_the_title(): display filters could change the path.
			$type   = (int) get_post_meta( $id, self::META_TYPE, true );
			$target = (string) get_post_meta( $id, self::META_TARGET, true );
			if ( null === $source || ! in_array( $type, self::TYPES, true ) || isset( $index[ $source ] ) ) {
				continue; // Oldest wins for duplicates; the admin screen prevents them.
			}
			if ( 410 !== $type && null === Paths::target( $target ) ) {
				continue;
			}
			$index[ $source ] = array(
				'id'     => $id,
				'target' => 410 === $type ? '' : $target,
				'type'   => $type,
			);
		}

		// Always stored, even empty: a missing option would cost a query on every request.
		// Deleted first because update_option() cannot change the autoload flag on older WordPress.
		delete_option( self::INDEX_OPTION );
		add_option( self::INDEX_OPTION, $index, '', count( $index ) <= self::AUTOLOAD_MAX );
	}
}
