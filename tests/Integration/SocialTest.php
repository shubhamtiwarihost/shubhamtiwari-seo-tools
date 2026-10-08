<?php
/**
 * Open Graph and X Card output on real requests.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Admin\TermFields;
use DumpSEO\Frontend\CurrentPage;
use DumpSEO\Meta\Keys;
use DumpSEO\Plugin;
use DumpSEO\Settings\Settings;
use DumpSEO\Social\SocialModule;
use WP_UnitTestCase;

/**
 * Checks the social tags printed in wp_head.
 *
 * @covers \DumpSEO\Social\SocialModule
 * @covers \DumpSEO\Social\SocialTags
 * @covers \DumpSEO\Compatibility\Conflicts
 * @covers \DumpSEO\Frontend\CurrentPage
 */
final class SocialTest extends WP_UnitTestCase {

	/**
	 * Social module booted by the plugin.
	 *
	 * @var SocialModule
	 */
	private $social;

	public function set_up(): void {
		parent::set_up();
		update_option( 'blogname', 'Acme' );
		update_option( 'blog_public', '1' );
		update_option( Settings::OPTION, array( 'twitter_site' => 'acme' ) );
		$this->set_permalink_structure( '/%postname%/' );

		$social = Plugin::instance()->module( 'social' );
		$this->assertInstanceOf( SocialModule::class, $social );
		$this->social = $social;
	}

	/**
	 * Visits a URL and returns the printed social tags as key => list of values.
	 *
	 * @param string $url URL.
	 * @return array<string, string[]>
	 */
	private function tags( string $url ): array {
		$this->go_to( $url );
		$page = Plugin::instance()->container()->get( CurrentPage::class );
		$this->assertInstanceOf( CurrentPage::class, $page );
		$page->reset();

		$html = get_echo( array( $this->social, 'print_tags' ) );
		preg_match_all( '/<meta (property|name)="([^"]+)" content="([^"]*)" \/>/', $html, $m, PREG_SET_ORDER );
		$this->assertSame( substr_count( $html, '<meta' ), count( $m ), 'Every printed tag is well-formed.' );

		$tags = array();
		foreach ( $m as $match ) {
			$tags[ $match[2] ][] = html_entity_decode( $match[3], ENT_QUOTES );
		}
		return $tags;
	}

	/**
	 * Creates an image attachment with known dimensions and alt text.
	 *
	 * @param string $file File name.
	 */
	private function image( string $file = 'share.jpg' ): int {
		$id = self::factory()->attachment->create_object( $file, 0, array( 'post_mime_type' => 'image/jpeg' ) );
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => 1200,
				'height' => 630,
				'file'   => $file,
			)
		);
		update_post_meta( $id, '_wp_attachment_image_alt', 'A <b>red</b> boot' );
		return $id;
	}

	public function test_post_with_featured_image(): void {
		$post = self::factory()->post->create(
			array(
				'post_title'    => 'Boots',
				'post_excerpt'  => 'All about boots.',
				'post_date_gmt' => '2026-03-04 10:00:00',
				'post_date'     => '2026-03-04 10:00:00',
			)
		);
		set_post_thumbnail( $post, $this->image() );

		$tags = $this->tags( get_permalink( $post ) );

		$this->assertSame( array( 'article' ), $tags['og:type'] );
		$this->assertSame( array( 'Boots – Acme' ), $tags['og:title'] );
		$this->assertSame( array( 'All about boots.' ), $tags['og:description'] );
		$this->assertSame( array( get_permalink( $post ) ), $tags['og:url'] );
		$this->assertSame( array( 'Acme' ), $tags['og:site_name'] );
		$this->assertStringEndsWith( 'share.jpg', $tags['og:image'][0] );
		$this->assertSame( array( '1200' ), $tags['og:image:width'] );
		$this->assertSame( array( '630' ), $tags['og:image:height'] );
		$this->assertSame( array( 'image/jpeg' ), $tags['og:image:type'] );
		$this->assertSame( array( 'A red boot' ), $tags['og:image:alt'] );
		$this->assertSame( array( '2026-03-04T10:00:00+00:00' ), $tags['article:published_time'] );
		$this->assertSame( array( 'summary_large_image' ), $tags['twitter:card'] );
		$this->assertSame( array( '@acme' ), $tags['twitter:site'] );
		$this->assertSame( $tags['og:image'], $tags['twitter:image'] );
		foreach ( $tags as $key => $values ) {
			$this->assertCount( 1, $values, "{$key} printed once." );
		}
	}

	public function test_custom_values_override_and_support_variables(): void {
		$post = self::factory()->post->create( array( 'post_title' => 'Plain' ) );
		set_post_thumbnail( $post, $this->image() );
		update_post_meta( $post, Keys::SOCIAL_TITLE, 'Share me %%separator%% %%site_name%%' );
		update_post_meta( $post, Keys::SOCIAL_DESCRIPTION, 'Custom share text' );
		update_post_meta( $post, Keys::SOCIAL_IMAGE, 'https://cdn.example.com/card.png' );

		$tags = $this->tags( get_permalink( $post ) );

		$this->assertSame( array( 'Share me – Acme' ), $tags['og:title'] );
		$this->assertSame( array( 'Custom share text' ), $tags['twitter:description'] );
		$this->assertSame( array( 'https://cdn.example.com/card.png' ), $tags['og:image'] );
		$this->assertArrayNotHasKey( 'og:image:width', $tags, 'Unknown dimensions are omitted, not guessed.' );
	}

	public function test_page_and_home_are_websites_with_default_image(): void {
		update_option(
			Settings::OPTION,
			array( 'default_social_image' => 'https://example.org/default.png' )
		);
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		foreach ( array( get_permalink( $page ), home_url( '/' ) ) as $url ) {
			$tags = $this->tags( $url );
			$this->assertSame( array( 'website' ), $tags['og:type'], $url );
			$this->assertSame( array( 'https://example.org/default.png' ), $tags['og:image'], $url );
			$this->assertArrayNotHasKey( 'article:published_time', $tags, $url );
		}
	}

	public function test_small_images_get_the_small_card(): void {
		$post = self::factory()->post->create();
		$tiny = self::factory()->attachment->create_object( 'tiny.png', 0, array( 'post_mime_type' => 'image/png' ) );
		wp_update_attachment_metadata(
			$tiny,
			array(
				'width'  => 20,
				'height' => 20,
				'file'   => 'tiny.png',
			)
		);
		set_post_thumbnail( $post, $tiny );

		$tags = $this->tags( get_permalink( $post ) );

		$this->assertSame( array( 'summary' ), $tags['twitter:card'], 'Below 300x157 X cannot show a large card.' );
		$this->assertArrayHasKey( 'twitter:image', $tags );
	}

	public function test_no_image_anywhere_uses_summary_card(): void {
		$tags = $this->tags( get_permalink( self::factory()->post->create() ) );

		$this->assertArrayNotHasKey( 'og:image', $tags );
		$this->assertSame( array( 'summary' ), $tags['twitter:card'] );
	}

	public function test_password_protected_post_hides_image_and_text(): void {
		update_option( Settings::OPTION, array( 'default_social_image' => 'https://example.org/default.png' ) );
		$post = self::factory()->post->create(
			array(
				'post_password' => 'pw',
				'post_excerpt'  => 'Secret',
			)
		);
		set_post_thumbnail( $post, $this->image( 'secret.jpg' ) );

		$tags = $this->tags( get_permalink( $post ) );

		$this->assertSame( array( 'https://example.org/default.png' ), $tags['og:image'] );
		$this->assertArrayNotHasKey( 'og:description', $tags );
	}

	public function test_nothing_on_search_and_404(): void {
		self::factory()->post->create();

		$this->assertSame( array(), $this->tags( home_url( '/?s=boots' ) ) );
		$this->assertSame( array(), $this->tags( home_url( '/missing-page-xyz/' ) ) );
	}

	public function test_noindex_page_has_no_og_url(): void {
		$post = self::factory()->post->create();
		update_post_meta( $post, Keys::ROBOTS, 'noindex' );

		$this->assertArrayNotHasKey( 'og:url', $this->tags( get_permalink( $post ) ) );
	}

	public function test_switches(): void {
		$post = self::factory()->post->create();

		update_option( Settings::OPTION, array( 'social_og_enabled' => false ) );
		$tags = $this->tags( get_permalink( $post ) );
		$this->assertArrayNotHasKey( 'og:title', $tags );
		$this->assertArrayHasKey( 'twitter:card', $tags );

		update_option(
			Settings::OPTION,
			array(
				'social_og_enabled'      => true,
				'social_twitter_enabled' => false,
			)
		);
		$tags = $this->tags( get_permalink( $post ) );
		$this->assertArrayHasKey( 'og:title', $tags );
		$this->assertArrayNotHasKey( 'twitter:card', $tags );

		update_option(
			Settings::OPTION,
			array(
				'social_og_enabled'      => false,
				'social_twitter_enabled' => false,
			)
		);
		$this->assertSame( array(), $this->tags( get_permalink( $post ) ) );
	}

	public function test_steps_aside_for_other_seo_plugins_and_says_so(): void {
		$post  = self::factory()->post->create();
		$other = static function () {
			return 'Other SEO Plugin';
		};
		add_filter( 'dumpseo_social_conflict', $other );

		$this->assertSame( array(), $this->tags( get_permalink( $post ) ) );
		$this->assertTrue( $this->social->jetpack_open_graph( true ), 'Jetpack left alone when DumpSEO is not printing.' );
		$this->assertStringContainsString( 'Other SEO Plugin', get_echo( array( $this->social, 'render_notice' ) ) );

		remove_filter( 'dumpseo_social_conflict', $other );
		$this->assertFalse( $this->social->jetpack_open_graph( true ), 'Jetpack OG off while DumpSEO prints OG.' );
		$this->assertSame( '', get_echo( array( $this->social, 'render_notice' ) ) );
	}

	public function test_values_are_escaped(): void {
		$post = self::factory()->post->create( array( 'post_title' => '"><script>alert(1)</script>Quote "me"' ) );
		update_post_meta( $post, Keys::SOCIAL_DESCRIPTION, "It's <b>bold</b> & \"quoted\"" );

		$this->go_to( get_permalink( $post ) );
		Plugin::instance()->container()->get( CurrentPage::class )->reset();
		$html = get_echo( array( $this->social, 'print_tags' ) );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '"me"', $html );
		$this->assertSame( array( "It's bold & \"quoted\"" ), $this->tags( get_permalink( $post ) )['og:description'] );
	}

	public function test_term_social_fields(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$cat = self::factory()->category->create( array( 'name' => 'Guides' ) );
		self::factory()->post->create( array( 'post_category' => array( $cat ) ) );

		$fields = Plugin::build_container()->get( TermFields::class );
		$this->assertInstanceOf( TermFields::class, $fields );
		$_POST = array(
			TermFields::NONCE_FIELD        => wp_create_nonce( 'dumpseo_term_' . $cat ),
			TermFields::SOCIAL_TITLE_FIELD => 'Guides for %%site_name%%',
			TermFields::SOCIAL_DESC_FIELD  => 'Every guide we wrote.',
			TermFields::SOCIAL_IMAGE_FIELD => 'javascript:alert(1)',
		);
		$fields->save( $cat, 0, 'category' );
		$_POST = array();

		$this->assertSame( '', get_term_meta( $cat, Keys::SOCIAL_IMAGE, true ), 'Invalid image URL rejected.' );

		$tags = $this->tags( get_category_link( $cat ) );
		$this->assertSame( array( 'Guides for Acme' ), $tags['og:title'] );
		$this->assertSame( array( 'Every guide we wrote.' ), $tags['og:description'] );
		$this->assertSame( array( 'website' ), $tags['og:type'] );
	}
}
