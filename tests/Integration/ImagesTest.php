<?php
/**
 * Image SEO: missing alt text detection and image pickers.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Integration;

use DumpSEO\Admin\SettingsPage;
use DumpSEO\Context;
use DumpSEO\Images\ImagesModule;
use DumpSEO\Plugin;
use DumpSEO\Settings\Sanitizer;
use DumpSEO\Settings\Settings;
use WP_UnitTestCase;

/**
 * @covers \DumpSEO\Images\ImagesModule
 * @covers \DumpSEO\Admin\SettingsPage::render_field
 * @covers \DumpSEO\Admin\SettingsPage::enqueue
 * @covers \DumpSEO\Settings\Sanitizer
 */
final class ImagesTest extends WP_UnitTestCase {

	/**
	 * Module under test.
	 *
	 * @var ImagesModule
	 */
	private $images;

	/**
	 * Image IDs: with alt, empty alt, no alt row, whitespace alt.
	 *
	 * @var array<string, int>
	 */
	private $ids = array();

	public function set_up(): void {
		parent::set_up();
		$this->images = new ImagesModule( new Context() );

		foreach ( array( 'with', 'empty', 'none' ) as $kind ) {
			$this->ids[ $kind ] = self::factory()->attachment->create_object( $kind . '.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		}
		update_post_meta( $this->ids['with'], '_wp_attachment_image_alt', 'A red boot' );
		update_post_meta( $this->ids['empty'], '_wp_attachment_image_alt', '' );
		self::factory()->attachment->create_object( 'manual.pdf', 0, array( 'post_mime_type' => 'application/pdf' ) );
	}

	public function tear_down(): void {
		unset( $_GET[ ImagesModule::FILTER_VAR ] );
		parent::tear_down();
	}

	public function test_counts_images_without_alt_only(): void {
		$this->assertSame( 2, ImagesModule::missing_alt_count(), 'Empty and missing rows count; PDFs and images with alt do not.' );
	}

	public function test_media_list_filter(): void {
		$_GET[ ImagesModule::FILTER_VAR ] = 'missing';
		$query                            = new \WP_Query();
		$GLOBALS['wp_the_query']          = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Makes this the main query, as on upload.php; reset by the test case.
		add_action( 'pre_get_posts', array( $this->images, 'apply_filter' ) );
		$ids = $query->query(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'fields'      => 'ids',
			)
		);
		remove_action( 'pre_get_posts', array( $this->images, 'apply_filter' ) );

		sort( $ids );
		$expected = array( $this->ids['empty'], $this->ids['none'] );
		sort( $expected );
		$this->assertSame( $expected, array_map( 'intval', $ids ) );

		$_GET[ ImagesModule::FILTER_VAR ] = '<script>';
		$html                             = get_echo( array( $this->images, 'render_filter' ), array( 'attachment' ) );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertSame( '', get_echo( array( $this->images, 'render_filter' ), array( 'post' ) ), 'Only on the media list.' );
	}

	public function test_column(): void {
		$columns = $this->images->add_column(
			array(
				'cb'     => '',
				'title'  => 'File',
				'author' => 'Author',
			)
		);
		$this->assertSame( array( 'cb', 'title', ImagesModule::COLUMN, 'author' ), array_keys( $columns ) );

		update_post_meta( $this->ids['with'], '_wp_attachment_image_alt', 'A <b>red</b> boot' );
		$this->assertSame( 'A red boot', get_echo( array( $this->images, 'render_column' ), array( ImagesModule::COLUMN, $this->ids['with'] ) ) );
		$this->assertStringContainsString( 'Missing', get_echo( array( $this->images, 'render_column' ), array( ImagesModule::COLUMN, $this->ids['none'] ) ) );
		$this->assertSame( '', get_echo( array( $this->images, 'render_column' ), array( 'author', $this->ids['none'] ) ) );
	}

	public function test_report_needs_manage_options_and_links_to_filtered_list(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( '', get_echo( array( $this->images, 'render_report' ) ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$html = get_echo( array( $this->images, 'render_report' ) );
		$this->assertStringContainsString( '2 images in the media library have no alternative text.', $html );
		$this->assertStringContainsString( 'upload.php?mode=list&#038;dumpseo_alt=missing', $html );

		update_post_meta( $this->ids['empty'], '_wp_attachment_image_alt', 'x' );
		update_post_meta( $this->ids['none'], '_wp_attachment_image_alt', 'y' );
		$this->assertStringContainsString( 'Every image', get_echo( array( $this->images, 'render_report' ) ) );
	}

	public function test_detection_never_changes_media(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$before = array_map( 'get_post_meta', array_values( $this->ids ) );

		ImagesModule::missing_alt_count();
		get_echo( array( $this->images, 'render_report' ) );
		foreach ( $this->ids as $id ) {
			get_echo( array( $this->images, 'render_column' ), array( ImagesModule::COLUMN, $id ) );
		}

		$this->assertSame( $before, array_map( 'get_post_meta', array_values( $this->ids ) ) );
	}

	public function test_image_fields_get_a_picker_and_validate_like_urls(): void {
		$page = Plugin::build_container()->get( SettingsPage::class );
		$this->assertInstanceOf( SettingsPage::class, $page );
		$field = Plugin::build_container()->get( Settings::class )->schema()->fields()['default_social_image'];

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$html = get_echo( array( $page, 'render_field' ), array( array( 'field' => $field ) ) );
		$this->assertStringContainsString( 'type="url"', $html );
		$this->assertStringContainsString( 'class="button dumpseo-pick-image" data-target="dumpseo-default-social-image"', $html );

		// Users who cannot upload never see the picker (the field itself stays).
		$user = wp_get_current_user();
		$user->remove_cap( 'upload_files' );
		$user->add_cap( 'upload_files', false );
		$this->assertStringNotContainsString( 'dumpseo-pick-image', get_echo( array( $page, 'render_field' ), array( array( 'field' => $field ) ) ) );

		$sanitizer = Plugin::build_container()->get( Sanitizer::class );
		$this->assertInstanceOf( Sanitizer::class, $sanitizer );
		$clean = $sanitizer->sanitize(
			array(
				'default_social_image' => 'https://example.org/share.png',
				'organization_logo'    => 'javascript:alert(1)',
			),
			array()
		);
		$this->assertSame( 'https://example.org/share.png', $clean['default_social_image'] );
		$this->assertArrayNotHasKey( 'organization_logo', array_filter( $clean ), 'Invalid URL rejected.' );
	}
}
