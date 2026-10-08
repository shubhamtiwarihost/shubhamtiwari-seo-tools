<?php
/**
 * Tests for the readability checks.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use Brain\Monkey\Functions;
use DumpSEO\Analysis\Engine;
use DumpSEO\Analysis\Input;
use DumpSEO\Analysis\Result;
use DumpSEO\Readability\English;
use DumpSEO\Readability\Rules\ReadingEase;
use DumpSEO\Readability\Sentences;

/**
 * Covers sentence splitting, syllables, English detectors and every readability rule.
 *
 * @covers \DumpSEO\Readability\Sentences
 * @covers \DumpSEO\Readability\English
 * @covers \DumpSEO\Readability\Rules\SentenceLength
 * @covers \DumpSEO\Readability\Rules\ParagraphLength
 * @covers \DumpSEO\Readability\Rules\SubheadingDistribution
 * @covers \DumpSEO\Readability\Rules\PassiveVoice
 * @covers \DumpSEO\Readability\Rules\TransitionWords
 * @covers \DumpSEO\Readability\Rules\ReadingEase
 * @covers \DumpSEO\Analysis\Input::sentences
 * @covers \DumpSEO\Analysis\Document
 */
final class ReadabilityTest extends TestCase {

	protected function set_up() {
		parent::set_up();
		Functions\stubTranslationFunctions();
		English::reset();
	}

	/**
	 * Readability results keyed by rule ID.
	 *
	 * @param string $html     Content.
	 * @param string $language Language code.
	 * @return array<string, array<string, mixed>>
	 */
	private function analyse( string $html, string $language = 'en' ): array {
		$report = Engine::readability()->run(
			new Input(
				array(
					'content'  => $html,
					'language' => $language,
				)
			)
		);
		return array_column( $report['results'], null, 'id' );
	}

	/**
	 * A paragraph of N sentences with W words each.
	 *
	 * @param int    $sentences Sentences.
	 * @param int    $words     Words per sentence.
	 * @param string $start     First word of each sentence.
	 */
	private static function paragraph( int $sentences, int $words, string $start = 'Cats' ): string {
		return '<p>' . trim( str_repeat( $start . str_repeat( ' sit', $words - 1 ) . '. ', $sentences ) ) . '</p>';
	}

	public function test_sentence_splitting(): void {
		$this->assertSame(
			array( 'Dr. Smith paid $3.50 for it, e.g. a pen.', 'Was it worth it?', '“Yes!” she said.', 'J. R. Tolkien wrote books…', '2026 was busy.' ),
			Sentences::split( 'Dr. Smith paid $3.50 for it, e.g. a pen. Was it worth it? “Yes!” she said. J. R. Tolkien wrote books… 2026 was busy.' )
		);
		$this->assertSame( array( 'no ending punctuation here' ), Sentences::split( '  no ending   punctuation here ' ) );
		$this->assertSame( array( 'See v2.0 now.' ), Sentences::split( 'See v2.0 now.' ) );
		$this->assertSame( array(), Sentences::split( ' … ' ) );
	}

	public function test_syllables(): void {
		$expected = array(
			'cat'         => 1,
			'make'        => 1,
			'table'       => 2,
			'jumped'      => 1,
			'wanted'      => 2,
			'boxes'       => 2,
			'makes'       => 1,
			'yellow'      => 2,
			'happy'       => 2,
			'readability' => 5,
			'beautiful'   => 3,
			'the'         => 1,
		);
		foreach ( $expected as $word => $count ) {
			$this->assertSame( $count, Sentences::syllables( $word ), $word );
		}
		$this->assertSame( 0, Sentences::syllables( '123' ) );
	}

	public function test_passive_and_transition_detection(): void {
		$this->assertTrue( English::is_passive( 'The shoes were tested by our team.' ) );
		$this->assertTrue( English::is_passive( 'The report is not written yet.' ) );
		$this->assertTrue( English::is_passive( 'It was quickly eaten.' ) );
		$this->assertTrue( English::is_passive( 'He got fired.' ) );
		$this->assertFalse( English::is_passive( 'We tested the shoes.' ) );
		$this->assertFalse( English::is_passive( 'I am tired.' ), 'Common -ed adjective.' );
		$this->assertFalse( English::is_passive( 'She is running.' ) );

		$this->assertTrue( English::has_transition( 'However, it rained.' ) );
		$this->assertTrue( English::has_transition( 'It rained. As a result, we stayed.' ) );
		$this->assertFalse( English::has_transition( 'Thenceforth it rained.' ), 'Whole words only.' );

		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $words ) {
				return 'dumpseo_transition_words' === $hook ? array( 'thenceforth', 42 ) : $words;
			}
		);
		English::reset();
		$this->assertTrue( English::has_transition( 'Thenceforth it rained.' ) );
		$this->assertFalse( English::has_transition( 'However, it rained.' ) );
	}

	public function test_flesch_reading_ease(): void {
		// 6 words, 1 sentence, 6 syllables: 206.835 − 1.015 × 6 − 84.6 × 1.
		$this->assertSame( 116.1, ReadingEase::score( array( 'The cat sat on the mat.' ) ) );
		$this->assertNull( ReadingEase::score( array() ) );

		$easy = $this->analyse( str_repeat( '<p>The cat sat on the mat. It was a big cat. The dog ran to it.</p>', 8 ) );
		$this->assertSame( Result::PASS, $easy['reading_ease']['status'] );

		$hard = $this->analyse( str_repeat( '<p>Institutional interoperability necessitates comprehensive organizational standardization initiatives.</p>', 20 ) );
		$this->assertSame( Result::WARNING, $hard['reading_ease']['status'] );
		$this->assertSame( 'very difficult', $hard['reading_ease']['metadata']['label'] );
	}

	public function test_sentence_length_bands(): void {
		$short = $this->analyse( self::paragraph( 10, 10 ) );
		$this->assertSame( Result::PASS, $short['sentence_length']['status'] );

		$mixed = $this->analyse( self::paragraph( 7, 10 ) . self::paragraph( 3, 25 ) );
		$this->assertSame( Result::WARNING, $mixed['sentence_length']['status'] );
		$this->assertSame( 30.0, $mixed['sentence_length']['metadata']['percent'] );

		$long = $this->analyse( self::paragraph( 5, 25 ) );
		$this->assertSame( Result::ERROR, $long['sentence_length']['status'] );
	}

	public function test_paragraphs_and_subheadings(): void {
		$results = $this->analyse( self::paragraph( 20, 10 ) );
		$this->assertSame( Result::WARNING, $results['paragraph_length']['status'] );
		$this->assertSame( 200, $results['paragraph_length']['metadata']['longest'] );
		$this->assertArrayNotHasKey( 'subheading_distribution', $results, 'Only for texts over 300 words.' );

		$results = $this->analyse( str_repeat( self::paragraph( 10, 10 ), 4 ) );
		$this->assertSame( Result::PASS, $results['paragraph_length']['status'] );
		$this->assertSame( 0, $results['subheading_distribution']['metadata']['subheadings'] );
		$this->assertSame( Result::WARNING, $results['subheading_distribution']['status'] );

		$results = $this->analyse( self::paragraph( 10, 10 ) . '<h2>Part</h2>' . self::paragraph( 10, 10 ) . '<h2>Part</h2>' . self::paragraph( 10, 10 ) . '<h3>Part</h3>' . self::paragraph( 10, 10 ) );
		$this->assertSame( Result::PASS, $results['subheading_distribution']['status'] );
		$this->assertSame( 3, $results['subheading_distribution']['metadata']['subheadings'] );

		$results = $this->analyse( '<h2>Intro</h2>' . str_repeat( self::paragraph( 10, 10 ), 4 ) );
		$this->assertSame( 1, $results['subheading_distribution']['metadata']['long'] );
	}

	public function test_passive_and_transition_rules(): void {
		$passive = $this->analyse( self::paragraph( 5, 6, 'The shoes were tested and' ) . self::paragraph( 5, 6 ) );
		$this->assertSame( Result::WARNING, $passive['passive_voice']['status'] );
		$this->assertSame( 50.0, $passive['passive_voice']['metadata']['percent'] );

		$flowing = $this->analyse( self::paragraph( 5, 6, 'However' ) . self::paragraph( 5, 6 ) );
		$this->assertSame( Result::PASS, $flowing['transition_words']['status'] );
		$this->assertSame( Result::PASS, $flowing['passive_voice']['status'] );

		$choppy = $this->analyse( self::paragraph( 10, 6 ) );
		$this->assertSame( Result::WARNING, $choppy['transition_words']['status'] );
		$this->assertArrayNotHasKey( 'transition_words', $this->analyse( self::paragraph( 4, 6 ) ), 'Needs 5 sentences.' );
	}

	public function test_language_specific_rules_only_run_for_english(): void {
		$results = $this->analyse( str_repeat( self::paragraph( 10, 10 ), 4 ), 'de' );

		$this->assertArrayHasKey( 'sentence_length', $results );
		$this->assertArrayHasKey( 'paragraph_length', $results );
		$this->assertArrayHasKey( 'subheading_distribution', $results );
		$this->assertArrayNotHasKey( 'passive_voice', $results );
		$this->assertArrayNotHasKey( 'transition_words', $results );
		$this->assertArrayNotHasKey( 'reading_ease', $results );
	}

	public function test_list_items_count_as_sentences_and_headings_do_not(): void {
		$input = new Input( array( 'content' => '<h2>Heading words here</h2><p>One. Two.</p><ul><li>Item one</li><li>Item two.</li></ul>' ) );
		$this->assertSame( array( 'One.', 'Two.', 'Item one', 'Item two.' ), $input->sentences() );
	}
}
