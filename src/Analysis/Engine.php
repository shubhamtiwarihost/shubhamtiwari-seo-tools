<?php
/**
 * Runs the analysis rules.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Runs every applicable rule of one rule set and summarises the results.
 * The SEO checks and the readability checks are two engines with different rules.
 *
 * There is deliberately no numeric score: the checks are editorial guidance,
 * and a score would suggest a ranking effect nobody can promise. The summary
 * counts results per status instead.
 */
class Engine {

	/**
	 * Status order, worst first.
	 */
	private const ORDER = array( Result::ERROR, Result::WARNING, Result::INFO, Result::PASS );

	/**
	 * Built-in rules keyed by ID.
	 *
	 * @var array<string, Rule>
	 */
	private $rules;

	/**
	 * Filter that lets extensions change the rules.
	 *
	 * @var string
	 */
	private $filter;

	/**
	 * Constructor.
	 *
	 * @param Rule[] $rules  Built-in rules.
	 * @param string $filter Filter name for extensions, e.g. "dumpseo_analysis_rules".
	 */
	public function __construct( array $rules, string $filter ) {
		$this->rules = array();
		foreach ( $rules as $rule ) {
			$this->rules[ $rule->id() ] = $rule;
		}
		$this->filter = $filter;
	}

	/**
	 * The SEO checks.
	 */
	public static function seo(): self {
		return new self(
			array(
				new Rules\KeyphraseSet(),
				new Rules\KeyphraseInTitle(),
				new Rules\KeyphraseInDescription(),
				new Rules\KeyphraseInSlug(),
				new Rules\KeyphraseInIntro(),
				new Rules\KeyphraseDensity(),
				new Rules\KeyphraseInSubheadings(),
				new Rules\KeyphraseUnique(),
				new Rules\TitleLength(),
				new Rules\DescriptionLength(),
				new Rules\ContentLength(),
				new Rules\InternalLinks(),
				new Rules\OutboundLinks(),
				new Rules\ImageAlt(),
				new Rules\SingleH1(),
				new Rules\Indexable(),
			),
			'dumpseo_analysis_rules'
		);
	}

	/**
	 * The readability checks.
	 */
	public static function readability(): self {
		return new self(
			array(
				new \DumpSEO\Readability\Rules\SentenceLength(),
				new \DumpSEO\Readability\Rules\ParagraphLength(),
				new \DumpSEO\Readability\Rules\SubheadingDistribution(),
				new \DumpSEO\Readability\Rules\PassiveVoice(),
				new \DumpSEO\Readability\Rules\TransitionWords(),
				new \DumpSEO\Readability\Rules\ReadingEase(),
			),
			'dumpseo_readability_rules'
		);
	}

	/**
	 * Analyses an input.
	 *
	 * @param Input $input Analysis input.
	 * @return array{status: string, counts: array<string, int>, results: array<int, array{id: string, status: string, severity: string, message: string, recommendation: string, metadata: array<string, int|float|string|bool>}>}
	 */
	public function run( Input $input ): array {
		/**
		 * Filters the rules of a rule set. Entries that are not Rule instances are ignored.
		 *
		 * Hook names: `dumpseo_analysis_rules` (SEO checks), `dumpseo_readability_rules`.
		 *
		 * @param array<string, mixed> $rules Rules keyed by ID.
		 * @param Input                $input Analysis input.
		 */
		$rules = apply_filters( $this->filter, $this->rules, $input ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Always one of the two dumpseo_* names above.

		$results = array();
		foreach ( (array) $rules as $rule ) {
			if ( $rule instanceof Rule && $rule->applies( $input ) ) {
				$results[] = $rule->check( $input );
			}
		}

		// Worst first, then most important first; stable for equal keys.
		$severity = array( Result::HIGH, Result::MEDIUM, Result::LOW );
		$keyed    = array();
		foreach ( $results as $index => $result ) {
			$keyed[] = array( array_search( $result->status, self::ORDER, true ), array_search( $result->severity, $severity, true ), $index, $result );
		}
		sort( $keyed );

		$counts = array_fill_keys( self::ORDER, 0 );
		$sorted = array();
		foreach ( $keyed as $entry ) {
			$result = $entry[3];
			++$counts[ $result->status ];
			$sorted[] = $result->to_array();
		}

		$status = Result::PASS;
		foreach ( self::ORDER as $candidate ) {
			if ( Result::INFO !== $candidate && $counts[ $candidate ] > 0 ) {
				$status = $candidate;
				break;
			}
		}

		return array(
			'status'  => $status,
			'counts'  => $counts,
			'results' => $sorted,
		);
	}
}
