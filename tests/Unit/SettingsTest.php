<?php
/**
 * Tests for Schema and Settings.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DumpSEO\Settings\Field;
use DumpSEO\Settings\Schema;
use DumpSEO\Settings\Settings;

/**
 * Covers defaults, stored-value handling and schema extension.
 *
 * @covers \DumpSEO\Settings\Settings
 * @covers \DumpSEO\Settings\Schema
 * @covers \DumpSEO\Settings\Field
 */
final class SettingsTest extends TestCase {

	protected function set_up() {
		parent::set_up();
		Functions\stubTranslationFunctions();
	}

	public function test_fresh_install_returns_defaults_and_writes_nothing(): void {
		$options  = OptionsStub::install();
		$settings = new Settings( new Schema() );

		$all = $settings->all();

		$this->assertSame( 'ndash', $all['separator'] );
		$this->assertSame( 'organization', $all['site_represents'] );
		$this->assertFalse( $all['remove_data_on_uninstall'], 'Data removal must be opt-in.' );
		$this->assertSame( array(), $options->options, 'Reading settings must not create the option.' );
	}

	public function test_stored_values_override_defaults_and_unknown_keys_are_dropped(): void {
		OptionsStub::install(
			array(
				Settings::OPTION => array(
					'separator'    => 'pipe',
					'twitter_site' => 'example',
					'not_a_field'  => 'x',
				),
			)
		);
		$settings = new Settings( new Schema() );

		$this->assertSame( 'pipe', $settings->get( 'separator' ) );
		$this->assertSame( 'example', $settings->get( 'twitter_site' ) );
		$this->assertNull( $settings->get( 'not_a_field' ) );
		$this->assertArrayNotHasKey( 'not_a_field', $settings->all() );
	}

	/**
	 * Corrupted stored data (e.g. edited directly in the database).
	 *
	 * @return array<string, array{mixed}>
	 */
	public function corrupt_provider(): array {
		return array(
			'string option' => array( 'garbage' ),
			'wrong types'   => array(
				array(
					'separator'                => 'not-a-choice',
					'remove_data_on_uninstall' => 'yes',
					'organization_name'        => array( 'nested' ),
				),
			),
		);
	}

	/**
	 * @dataProvider corrupt_provider
	 *
	 * @param mixed $stored Stored option value.
	 */
	public function test_corrupted_values_fall_back_to_defaults( $stored ): void {
		OptionsStub::install( array( Settings::OPTION => $stored ) );
		$settings = new Settings( new Schema() );

		$this->assertSame( ( new Schema() )->defaults(), $settings->all() );
	}

	public function test_extensions_can_add_fields_and_junk_is_ignored(): void {
		Filters\expectApplied( 'dumpseo_settings_fields' )->once()->andReturnUsing(
			static function ( array $fields ) {
				$fields['pro_feature'] = new Field( 'pro_feature', 'advanced', Field::TYPE_BOOL, true, 'Pro' );
				$fields['junk']        = 'not a field';
				return $fields;
			}
		);

		$defaults = ( new Schema() )->defaults();

		$this->assertTrue( $defaults['pro_feature'] );
		$this->assertArrayNotHasKey( 'junk', $defaults );
	}

	public function test_every_field_belongs_to_a_declared_section(): void {
		$schema = new Schema();
		foreach ( $schema->fields() as $field ) {
			$this->assertArrayHasKey( $field->section, $schema->sections(), $field->key );
		}
	}

	public function test_invalid_field_definitions_are_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		new Field( 'x', 'general', Field::TYPE_ENUM, 'missing', 'X', '', array( 'a' => 'A' ) );
	}

	public function test_invalid_field_key_is_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		new Field( 'Bad Key]', 'general', Field::TYPE_TEXT, '', 'X' );
	}
}
