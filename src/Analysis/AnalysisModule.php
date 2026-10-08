<?php
/**
 * SEO analysis REST endpoint.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Analysis;

use DumpSEO\Helpers\Text;
use DumpSEO\Module;

defined( 'ABSPATH' ) || exit;

/**
 * `POST /dumpseo/v1/analysis` — analyses a post, optionally with unsaved
 * editor values, and returns two reports (`seo`, `readability`) plus the
 * rendered title and description for the search preview (`preview`).
 * Read-only: nothing is saved.
 * Requires permission to edit that post.
 */
final class AnalysisModule implements Module {

	public const REST_NAMESPACE = 'dumpseo/v1';

	/**
	 * Input builder.
	 *
	 * @var InputFactory
	 */
	private $factory;

	/**
	 * SEO checks.
	 *
	 * @var Engine
	 */
	private $seo;

	/**
	 * Readability checks.
	 *
	 * @var Engine
	 */
	private $readability;

	/**
	 * Constructor.
	 *
	 * @param InputFactory $factory     Input builder.
	 * @param Engine       $seo         SEO checks.
	 * @param Engine       $readability Readability checks.
	 */
	public function __construct( InputFactory $factory, Engine $seo, Engine $readability ) {
		$this->factory     = $factory;
		$this->seo         = $seo;
		$this->readability = $readability;
	}

	/**
	 * REST cannot be detected this early, so the route is always registered on rest_api_init.
	 */
	public function should_load(): bool {
		return true;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the route.
	 */
	public function register_routes(): void {
		$args = array(
			'post_id' => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
		);
		foreach ( InputFactory::OVERRIDES as $name ) {
			$args[ $name ] = array(
				'type'      => 'string',
				'maxLength' => 'content' === $name ? 2000000 : 1000,
			);
		}

		register_rest_route(
			self::REST_NAMESPACE,
			'/analysis',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'analyse' ),
				'permission_callback' => array( $this, 'can_analyse' ),
				'args'                => $args,
			)
		);
	}

	/**
	 * Permission: the user must be able to edit the post.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function can_analyse( \WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		if ( current_user_can( 'edit_post', $post_id ) ) {
			return true;
		}
		return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to analyse this content.', 'dumpseo' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Runs the analysis.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function analyse( \WP_REST_Request $request ) {
		$post = get_post( (int) $request->get_param( 'post_id' ) );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.', 'dumpseo' ), array( 'status' => 404 ) );
		}

		$overrides = array();
		foreach ( InputFactory::OVERRIDES as $name ) {
			$value = $request->get_param( $name );
			if ( is_string( $value ) ) {
				// Content stays HTML (only parsed, never printed or saved); everything else is plain text.
				$overrides[ $name ] = 'content' === $name ? $value : Text::sanitize_line( $value );
			}
		}

		$input = $this->factory->for_post( $post, $overrides );
		return rest_ensure_response(
			array(
				'seo'         => $this->seo->run( $input ),
				'readability' => $this->readability->run( $input ),
				'preview'     => array(
					'title'       => $input->title,
					'description' => $input->description,
				),
			)
		);
	}
}
