<?php
/**
 * Tests for the SEO analysis engine.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Tests\Unit;

use Brain\Monkey\Functions;
use DumpSEO\Analysis\Document;
use DumpSEO\Analysis\Engine;
use DumpSEO\Analysis\Input;
use DumpSEO\Analysis\Keyphrase;
use DumpSEO\Analysis\Result;

/**
 * Covers content parsing, keyphrase matching and every built-in rule.
 *
 * @covers \DumpSEO\Analysis\Document
 * @covers \DumpSEO\Analysis\Keyphrase
 * @covers \DumpSEO\Analysis\Engine
 * @covers \DumpSEO\Analysis\Input
 * @covers \DumpSEO\Analysis\Result
 * @covers \DumpSEO\Analysis\Rules\BaseRule
 */
final class AnalysisTest extends TestCase {

	protected function set_up() {
		parent::set_up();
		Functions\stubTranslationFunctions();
	}

	/**
	 * Runs the engine and returns results keyed by rule ID.
	 *
	 * @param array<string, mixed> $values Input values.
	 * @return array<string, array<string, mixed>>
	 */
	private function analyse( array $values ): array {
		$report = Engine::seo()->run( new Input( $values + array( 'host' => 'example.org' ) ) );
		return array_column( $report['results'], null, 'id' );
	}

	/**
	 * N filler words.
	 *
	 * @param int $n Words.
	 */
	private static function words( int $n ): string {
		return trim( str_repeat( 'lorem ', $n ) );
	}

	public function test_document_parsing(): void {
		$doc = new Document(
			'<!-- wp:paragraph --><p></p><p>First <b>para</b>&nbsp;here.</p><!-- /wp:paragraph -->'
			. '<script>var hidden = "words";</script>'
			. '<h1>Main</h1><h2>Sub <em>one</em></h2><h3></h3>'
			. '<p>Line<br>break <a href="/about">in</a> <a href="https://www.example.org/x">site</a> <a href="https://other.com">out</a> <a href="#top">anchor</a> <a href="mailto:a@b.c">mail</a></p>'
			. '<img src="a.jpg" alt="A cat"><img src="b.jpg"><img src="c.jpg" alt=" ">'
		);

		$this->assertSame( 'First para here.', $doc->intro );
		$this->assertStringNotContainsString( 'hidden', $doc->text );
		$this->assertStringContainsString( 'Line break', $doc->text, 'Block tags separate words.' );
		$this->assertSame( array( 'Sub one' ), $doc->subheadings );
		$this->assertSame( 1, $doc->h1_count );
		$this->assertSame( array( '/about', 'https://www.example.org/x', 'https://other.com' ), $doc->links );
		$this->assertSame( array( 'A cat', '', '' ), $doc->image_alts );
		$this->assertSame( 13, $doc->word_count );

		$this->assertTrue( Document::is_internal( '/about', 'example.org' ) );
		$this->assertTrue( Document::is_internal( 'relative/page', 'example.org' ) );
		$this->assertTrue( Document::is_internal( 'https://www.example.org/x', 'example.org' ) );
		$this->assertFalse( Document::is_internal( '//cdn.other.com/x', 'example.org' ) );
		$this->assertFalse( Document::is_internal( 'https://example.org.evil.com/', 'example.org' ) );
		$this->assertFalse( Document::is_internal( 'ftp://example.com/', 'example.org' ) );
	}

	public function test_document_without_paragraph_tags_uses_first_100_words(): void {
		$doc = new Document( self::words( 150 ) );
		$this->assertSame( 100, Document::count_words( $doc->intro ) );
	}

	public function test_keyphrase_matching(): void {
		$phrase = new Keyphrase( '  Running   Shoes ' );

		$this->assertSame( 'running shoes', $phrase->normalized() );
		$this->assertSame( 2, $phrase->word_count() );
		$this->assertSame( 3, $phrase->count_in( 'Running shoes, running-shoes and RUNNING  SHOES.' ) );
		$this->assertFalse( $phrase->in( 'overrunning shoeshine' ), 'Whole words only.' );
		$this->assertSame( 5, $phrase->position_in( 'Best running shoes' ) );
		$this->assertSame( -1, $phrase->position_in( 'Boots' ) );
		$this->assertTrue( $phrase->in_slug( 'best-running-shoes-2026' ) );
		$this->assertFalse( $phrase->in_slug( 'best-running-boots' ) );

		$this->assertTrue( ( new Keyphrase( "don't panic" ) )->in( 'Don’t panic!' ), 'Curly apostrophes match.' );
		$this->assertTrue( ( new Keyphrase( "don't panic" ) )->in_slug( 'dont-panic' ) );
		$this->assertTrue( ( new Keyphrase( 'Café Zürich' ) )->in_slug( 'caf%c3%a9-z%c3%bcrich' ), 'Percent-encoded slugs.' );
		$this->assertTrue( ( new Keyphrase( 'c++ (tips)' ) )->in( 'Some c++ tips' ), 'Regex characters are literal.' );
		$this->assertTrue( ( new Keyphrase( ' - ' ) )->is_empty() );
	}

	public function test_no_keyphrase_runs_only_general_rules(): void {
		$results = $this->analyse( array( 'title' => 'Hello' ) );

		$this->assertSame( Result::INFO, $results['keyphrase_set']['status'] );
		foreach ( array_keys( $results ) as $id ) {
			$this->assertTrue( 'keyphrase_set' === $id || 0 !== strpos( $id, 'keyphrase_' ), "{$id} needs a keyphrase." );
		}
	}

	public function test_well_optimised_post_passes(): void {
		$body    = '<p>Running shoes matter. ' . self::words( 60 ) . '</p>'
			. '<h2>Choosing running shoes</h2><p>' . self::words( 150 ) . ' running shoes ' . self::words( 100 ) . '</p>'
			. '<p><a href="/guides/">Guides</a> and <a href="https://example.com/study">a study</a>.</p><img src="x.jpg" alt="Shoes">';
		$results = $this->analyse(
			array(
				'keyphrase'   => 'running shoes',
				'title'       => 'Running shoes: how to choose the right pair – Acme',
				'description' => 'Running shoes explained: what to look for in cushioning, fit and grip, and how to find a pair that suits the way you run every week.',
				'slug'        => 'running-shoes',
				'content'     => $body,
			)
		);

		foreach ( $results as $id => $result ) {
			$this->assertSame( Result::PASS, $result['status'], "{$id}: {$result['message']}" );
		}
		$this->assertSame( 0, $results['keyphrase_in_title']['metadata']['position'] );
	}

	public function test_problems_are_reported_worst_first(): void {
		$report  = Engine::seo()->run(
			new Input(
				array(
					'keyphrase' => 'boots',
					'title'     => '',
					'content'   => '<h1>Big</h1><p>' . self::words( 50 ) . '</p><img src="a.jpg">',
					'host'      => 'example.org',
					'noindex'   => true,
				)
			)
		);
		$results = array_column( $report['results'], null, 'id' );

		$this->assertSame( Result::ERROR, $report['status'] );
		$this->assertSame( Result::ERROR, $report['results'][0]['status'] );
		$this->assertSame( Result::ERROR, $results['title_length']['status'] );
		$this->assertSame( Result::ERROR, $results['keyphrase_in_title']['status'] );
		$this->assertSame( Result::ERROR, $results['content_length']['status'] );
		$this->assertSame( Result::WARNING, $results['description_length']['status'] );
		$this->assertSame( Result::WARNING, $results['keyphrase_in_intro']['status'] );
		$this->assertSame( Result::WARNING, $results['internal_links']['status'] );
		$this->assertSame( Result::INFO, $results['outbound_links']['status'] );
		$this->assertSame( 1, $results['image_alt']['metadata']['missing'] );
		$this->assertSame( Result::WARNING, $results['content_h1']['status'] );
		$this->assertSame( Result::INFO, $results['indexable']['status'] );
		$this->assertArrayNotHasKey( 'keyphrase_density', $results, 'Under 100 words.' );
		$this->assertArrayNotHasKey( 'keyphrase_in_description', $results, 'No description to check.' );

		$statuses = array_column( $report['results'], 'status' );
		$order    = array_flip( array( Result::ERROR, Result::WARNING, Result::INFO, Result::PASS ) );
		$ranks    = array_map(
			static function ( string $status ) use ( $order ): int {
				return $order[ $status ];
			},
			$statuses
		);
		$sorted   = $ranks;
		sort( $sorted );
		$this->assertSame( $sorted, $ranks );
		$this->assertSame( count( $report['results'] ), array_sum( $report['counts'] ) );
	}

	/**
	 * Density bands.
	 *
	 * @dataProvider density_cases
	 *
	 * @param int    $uses   Keyphrase uses in 200 words.
	 * @param string $status Expected status.
	 */
	public function test_keyphrase_density( int $uses, string $status ): void {
		$content = trim( str_repeat( 'boots ', $uses ) . self::words( 200 - $uses ) );
		$result  = $this->analyse(
			array(
				'keyphrase' => 'boots',
				'content'   => $content,
			)
		)['keyphrase_density'];
		$this->assertSame( $status, $result['status'] );
		$this->assertSame( $uses, $result['metadata']['count'] );
	}

	/**
	 * Uses per 200 words → status.
	 *
	 * @return array<string, array{int, string}>
	 */
	public function density_cases(): array {
		return array(
			'none'    => array( 0, Result::WARNING ),
			'one'     => array( 1, Result::PASS ),
			'good'    => array( 4, Result::PASS ),
			'high'    => array( 8, Result::WARNING ),
			'stuffed' => array( 12, Result::ERROR ),
		);
	}

	public function test_title_position_duplicates_and_page_length(): void {
		$results = $this->analyse(
			array(
				'keyphrase'         => 'boots',
				'title'             => 'Everything you ever wanted to know about boots',
				'keyphrase_used_by' => array( 7, 9 ),
				'post_type'         => 'page',
				'content'           => '<p>Contact us about boots.</p>',
			)
		);
		$this->assertSame( Result::WARNING, $results['keyphrase_in_title']['status'] );
		$this->assertSame( Result::WARNING, $results['keyphrase_unique']['status'] );
		$this->assertSame( '7,9', $results['keyphrase_unique']['metadata']['post_ids'] );
		$this->assertSame( Result::INFO, $results['content_length']['status'], 'Short pages are fine.' );
	}

	public function test_filter_can_add_and_remove_rules(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $rules ) {
				unset( $rules['title_length'] );
				$rules['bad'] = 'not a rule';
				return $rules;
			}
		);
		$results = $this->analyse( array( 'title' => 'x' ) );
		$this->assertArrayNotHasKey( 'title_length', $results );
		$this->assertArrayHasKey( 'description_length', $results );
	}

	public function test_result_normalises_unknown_values(): void {
		$result = new Result( 'x', 'great', 'urgent', 'msg' );
		$this->assertSame( Result::INFO, $result->status );
		$this->assertSame( Result::LOW, $result->severity );
	}
}
