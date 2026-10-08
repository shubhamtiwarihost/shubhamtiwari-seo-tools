<?php
/**
 * What the current request is showing.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Meta;

defined( 'ABSPATH' ) || exit;

/**
 * A plain description of the page being rendered, independent of globals.
 *
 * Built once from the main WP_Query; everything downstream (resolver,
 * variables) works from this object so it can be tested without a request.
 */
final class PageContext {

	public const SINGULAR   = 'singular';
	public const FRONT      = 'front';
	public const BLOG       = 'blog';
	public const TERM       = 'term';
	public const AUTHOR     = 'author';
	public const DATE       = 'date';
	public const SEARCH     = 'search';
	public const NOT_FOUND  = 'not_found';
	public const PT_ARCHIVE = 'post_type_archive';
	public const OTHER      = 'other';

	/**
	 * One of the type constants.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * The queried object, when there is one.
	 *
	 * @var \WP_Post|\WP_Term|\WP_User|\WP_Post_Type|null
	 */
	public $object;

	/**
	 * Current page number (1-based).
	 *
	 * @var int
	 */
	public $page;

	/**
	 * Total pages, or 0 when unknown.
	 *
	 * @var int
	 */
	public $total_pages;

	/**
	 * Search phrase (SEARCH only). Raw, unescaped.
	 *
	 * @var string
	 */
	public $search;

	/**
	 * Human-readable date archive label (DATE only).
	 *
	 * @var string
	 */
	public $date_label;

	/**
	 * Constructor.
	 *
	 * @param string                                        $type        Type constant.
	 * @param \WP_Post|\WP_Term|\WP_User|\WP_Post_Type|null $object_data Queried object.
	 * @param int                                           $page        Current page.
	 * @param int                                           $total_pages Total pages.
	 * @param string                                        $search      Search phrase.
	 * @param string                                        $date_label  Date archive label.
	 */
	public function __construct( string $type, $object_data = null, int $page = 1, int $total_pages = 0, string $search = '', string $date_label = '' ) {
		$this->type        = $type;
		$this->object      = $object_data;
		$this->page        = max( 1, $page );
		$this->total_pages = max( 0, $total_pages );
		$this->search      = $search;
		$this->date_label  = $date_label;
	}

	/**
	 * Builds the context from a query (normally the main query).
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function from_query( \WP_Query $query ): self {
		$page  = max( 1, (int) $query->get( 'paged' ), (int) $query->get( 'page' ) );
		$total = (int) $query->max_num_pages;

		if ( $query->is_404() ) {
			return new self( self::NOT_FOUND );
		}
		if ( $query->is_search() ) {
			return new self( self::SEARCH, null, $page, $total, (string) $query->get( 's' ) );
		}

		$object = $query->get_queried_object();

		if ( $query->is_front_page() ) {
			return new self( self::FRONT, $object instanceof \WP_Post ? $object : null, $page, $total );
		}
		if ( $query->is_home() ) {
			return new self( self::BLOG, $object instanceof \WP_Post ? $object : null, $page, $total );
		}
		if ( $query->is_singular() && $object instanceof \WP_Post ) {
			return new self( self::SINGULAR, $object, $page, 0 );
		}
		if ( ( $query->is_category() || $query->is_tag() || $query->is_tax() ) && $object instanceof \WP_Term ) {
			return new self( self::TERM, $object, $page, $total );
		}
		if ( $query->is_author() && $object instanceof \WP_User ) {
			return new self( self::AUTHOR, $object, $page, $total );
		}
		if ( $query->is_post_type_archive() ) {
			$post_type = $query->get( 'post_type' );
			$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$type_obj  = is_string( $post_type ) ? get_post_type_object( $post_type ) : null;
			return new self( self::PT_ARCHIVE, $type_obj instanceof \WP_Post_Type ? $type_obj : null, $page, $total );
		}
		if ( $query->is_date() ) {
			return new self( self::DATE, null, $page, $total, '', self::date_label( $query ) );
		}

		return new self( self::OTHER, null, $page, $total );
	}

	/**
	 * "March 4, 2026", "March 2026" or "2026" for date archives.
	 *
	 * @param \WP_Query $query Query.
	 */
	private static function date_label( \WP_Query $query ): string {
		$year  = (int) $query->get( 'year' );
		$month = (int) $query->get( 'monthnum' );
		$day   = (int) $query->get( 'day' );

		if ( $query->is_day() && $year && $month && $day ) {
			return (string) mysql2date( (string) get_option( 'date_format' ), sprintf( '%04d-%02d-%02d 00:00:00', $year, $month, $day ) );
		}
		if ( $query->is_month() && $year && $month ) {
			/* translators: Date format for monthly archives, see https://www.php.net/manual/datetime.format.php */
			return (string) mysql2date( _x( 'F Y', 'monthly archives date format', 'dumpseo' ), sprintf( '%04d-%02d-01 00:00:00', $year, $month ) );
		}
		return $year ? (string) $year : '';
	}
}
