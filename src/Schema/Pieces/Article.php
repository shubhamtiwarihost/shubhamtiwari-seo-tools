<?php
/**
 * Articles and their authors.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Schema\Pieces;

use DumpSEO\Helpers\Text;
use DumpSEO\Schema\Piece;
use DumpSEO\Schema\SchemaContext;

defined( 'ABSPATH' ) || exit;

/**
 * BlogPosting for posts, Article for other public post types (never pages or
 * media), plus a Person node for the author.
 */
final class Article implements Piece {

	/**
	 * Needed on single posts/CPT items with a canonical URL.
	 *
	 * @param SchemaContext $context Schema context.
	 */
	public function is_needed( SchemaContext $context ): bool {
		return '' !== $context->url && '' !== $this->type( $context );
	}

	/**
	 * Article node and author Person node.
	 *
	 * @param SchemaContext $context Schema context.
	 * @return array<int, array<string, mixed>>
	 */
	public function generate( SchemaContext $context ): array {
		$post = $context->page->object;
		if ( ! $post instanceof \WP_Post ) {
			return array();
		}

		$protected = '' !== $post->post_password;
		$node      = array(
			'@type'            => $this->type( $context ),
			'@id'              => $context->article_id(),
			'isPartOf'         => SchemaContext::ref( $context->webpage_id() ),
			'mainEntityOfPage' => SchemaContext::ref( $context->webpage_id() ),
			'headline'         => Text::plain( $post->post_title ),
			'description'      => $protected ? '' : $context->description,
			'datePublished'    => WebPage::date( $post->post_date_gmt ),
			'dateModified'     => WebPage::date( $post->post_modified_gmt ),
			'publisher'        => '' !== $context->publisher_name ? SchemaContext::ref( $context->publisher_id() ) : null,
			'image'            => null !== $context->image ? SchemaContext::ref( $context->image_id() ) : null,
			'wordCount'        => $protected ? null : $this->word_count( $post ),
			'articleSection'   => $this->term_names( $post, 'category' ),
			'keywords'         => $this->term_names( $post, 'post_tag' ),
			'inLanguage'       => $context->language,
		);

		$nodes  = array();
		$author = get_userdata( (int) $post->post_author );
		if ( $author instanceof \WP_User && post_type_supports( $post->post_type, 'author' ) ) {
			$node['author'] = SchemaContext::ref( $context->author_id( $author ) );
			$nodes[]        = array(
				'@type' => 'Person',
				'@id'   => $context->author_id( $author ),
				'name'  => Text::plain( $author->display_name ),
				'url'   => get_author_posts_url( $author->ID, $author->user_nicename ),
			);
		}

		array_unshift( $nodes, $node );
		return $nodes;
	}

	/**
	 * "BlogPosting", "Article", or '' when the page is not an article.
	 *
	 * @param SchemaContext $context Schema context.
	 */
	private function type( SchemaContext $context ): string {
		if ( ! $context->is_singular() ) {
			return '';
		}
		$post = $context->page->object;
		if ( ! $post instanceof \WP_Post || in_array( $post->post_type, array( 'page', 'attachment' ), true ) ) {
			return '';
		}
		$type = 'post' === $post->post_type ? 'BlogPosting' : 'Article';

		/**
		 * Filters the article type of a post. Return '' for no Article node.
		 *
		 * @param mixed    $type BlogPosting for posts, Article for other post types. Anything but a plain type name means none.
		 * @param \WP_Post $post Post.
		 */
		$filtered = apply_filters( 'dumpseo_schema_article_type', $type, $post );
		return is_string( $filtered ) && preg_match( '/^[A-Za-z]+$/', $filtered ) ? $filtered : '';
	}

	/**
	 * Names of the post's terms in a taxonomy (uses the term cache primed by the main query).
	 *
	 * @param \WP_Post $post     Post.
	 * @param string   $taxonomy Taxonomy.
	 * @return string[]
	 */
	private function term_names( \WP_Post $post, string $taxonomy ): array {
		if ( ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
			return array();
		}
		$terms = get_the_terms( $post, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return array();
		}
		$names = array();
		foreach ( $terms as $term ) {
			if ( 'uncategorized' !== $term->slug ) {
				$names[] = Text::plain( $term->name );
			}
		}
		return $names;
	}

	/**
	 * Words in the post content (shortcodes and tags removed).
	 *
	 * @param \WP_Post $post Post.
	 */
	private function word_count( \WP_Post $post ): ?int {
		$text  = Text::plain( strip_shortcodes( $post->post_content ) );
		$count = '' === $text ? 0 : count( (array) preg_split( '/\s+/u', $text ) );
		return $count > 0 ? $count : null;
	}
}
