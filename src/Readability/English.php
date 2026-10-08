<?php
/**
 * English word lists for readability checks.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Readability;

use DumpSEO\Analysis\Keyphrase;

defined( 'ABSPATH' ) || exit;

/**
 * Word lists and detectors for English text. Compiled for DumpSEO from
 * general English grammar; extend with the `dumpseo_transition_words` filter.
 */
final class English {

	/**
	 * Words and phrases that connect a sentence to the previous one.
	 */
	public const TRANSITIONS = array(
		// Adding.
		'also',
		'besides',
		'furthermore',
		'moreover',
		'in addition',
		'additionally',
		'as well as',
		'what is more',
		'not only',
		// Contrasting.
		'however',
		'but',
		'yet',
		'although',
		'though',
		'even though',
		'nevertheless',
		'nonetheless',
		'on the other hand',
		'in contrast',
		'instead',
		'whereas',
		'still',
		'despite',
		'otherwise',
		'rather',
		// Cause and effect.
		'because',
		'since',
		'therefore',
		'thus',
		'hence',
		'consequently',
		'as a result',
		'so that',
		'for this reason',
		'accordingly',
		'due to',
		// Sequence and time.
		'first',
		'firstly',
		'second',
		'secondly',
		'third',
		'next',
		'then',
		'finally',
		'lastly',
		'afterwards',
		'meanwhile',
		'later',
		'before',
		'after',
		'eventually',
		'to begin with',
		'at the same time',
		'until',
		'once',
		// Examples and emphasis.
		'for example',
		'for instance',
		'such as',
		'in particular',
		'especially',
		'namely',
		'indeed',
		'in fact',
		'above all',
		'notably',
		'specifically',
		// Comparing.
		'similarly',
		'likewise',
		'in the same way',
		'equally',
		'compared to',
		'unlike',
		// Summing up.
		'in conclusion',
		'to sum up',
		'in short',
		'overall',
		'in summary',
		'in other words',
		'to summarize',
		'all in all',
		'ultimately',
		// Condition.
		'if',
		'unless',
		'provided that',
		'in case',
		'as long as',
		'even if',
	);

	/**
	 * Forms of "be" (and "get") that introduce a passive.
	 */
	private const AUXILIARIES = array( 'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being', "isn't", "aren't", "wasn't", "weren't", 'get', 'gets', 'got', 'gotten', 'getting' );

	/**
	 * Common irregular past participles.
	 */
	private const IRREGULAR = array(
		'arisen',
		'awoken',
		'beaten',
		'become',
		'begun',
		'bent',
		'bitten',
		'blown',
		'broken',
		'brought',
		'built',
		'bought',
		'caught',
		'chosen',
		'come',
		'cut',
		'dealt',
		'done',
		'drawn',
		'driven',
		'eaten',
		'fallen',
		'fed',
		'felt',
		'fought',
		'found',
		'forbidden',
		'forgotten',
		'forgiven',
		'frozen',
		'given',
		'gone',
		'grown',
		'heard',
		'held',
		'hidden',
		'hit',
		'hung',
		'hurt',
		'kept',
		'known',
		'laid',
		'led',
		'left',
		'lent',
		'let',
		'lost',
		'made',
		'meant',
		'met',
		'paid',
		'put',
		'read',
		'ridden',
		'rung',
		'risen',
		'run',
		'said',
		'seen',
		'sent',
		'set',
		'shaken',
		'shot',
		'shown',
		'shut',
		'sold',
		'sought',
		'spent',
		'spoken',
		'spread',
		'stolen',
		'struck',
		'sung',
		'sunk',
		'taken',
		'taught',
		'thought',
		'thrown',
		'told',
		'torn',
		'understood',
		'woken',
		'won',
		'worn',
		'written',
	);

	/**
	 * Common -ed adjectives that follow "be" without being passive ("I am tired").
	 */
	private const ED_ADJECTIVES = array( 'tired', 'bored', 'excited', 'interested', 'worried', 'pleased', 'surprised', 'scared', 'confused', 'married', 'used', 'supposed', 'concerned', 'related', 'based', 'located', 'involved', 'needed', 'aged', 'talented', 'crowded', 'detailed', 'advanced', 'complicated', 'dedicated', 'experienced', 'limited', 'qualified', 'relaxed', 'satisfied', 'sophisticated', 'balanced' );

	/**
	 * Transition words, after the filter.
	 *
	 * @var Keyphrase[]|null
	 */
	private static $transitions = null;

	/**
	 * Whether a sentence contains a transition word or phrase.
	 *
	 * @param string $sentence Sentence.
	 */
	public static function has_transition( string $sentence ): bool {
		if ( null === self::$transitions ) {
			/**
			 * Filters the English transition words and phrases.
			 *
			 * @param mixed $words Lowercase words and phrases (strings; other entries are ignored).
			 */
			$words             = (array) apply_filters( 'dumpseo_transition_words', self::TRANSITIONS );
			self::$transitions = array();
			foreach ( $words as $word ) {
				if ( is_string( $word ) && '' !== trim( $word ) ) {
					self::$transitions[] = new Keyphrase( $word );
				}
			}
		}
		foreach ( self::$transitions as $phrase ) {
			if ( $phrase->in( $sentence ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a sentence shows a passive-voice pattern: a form of "be"/"get",
	 * optionally one adverb ending in -ly or "not", then a past participle
	 * (-ed word or a common irregular one). This is an indicator, not a
	 * grammatical parse: a few adjectives are excluded, others can still match.
	 *
	 * @param string $sentence Sentence.
	 */
	public static function is_passive( string $sentence ): bool {
		$words = preg_split( '/[^\p{L}\']+/u', Keyphrase::normalize( $sentence ), -1, PREG_SPLIT_NO_EMPTY );
		$words = is_array( $words ) ? $words : array();
		$count = count( $words );

		for ( $i = 0; $i < $count - 1; $i++ ) {
			if ( ! in_array( $words[ $i ], self::AUXILIARIES, true ) ) {
				continue;
			}
			$next = $words[ $i + 1 ];
			if ( ( 'not' === $next || preg_match( '/ly$/', $next ) ) && $i + 2 < $count ) {
				$next = $words[ $i + 2 ];
			}
			if ( self::is_participle( $next ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a word looks like a past participle.
	 *
	 * @param string $word Lowercase word.
	 */
	private static function is_participle( string $word ): bool {
		if ( in_array( $word, self::IRREGULAR, true ) ) {
			return true;
		}
		return strlen( $word ) > 4 && 1 === preg_match( '/ed$/', $word ) && ! in_array( $word, self::ED_ADJECTIVES, true );
	}

	/**
	 * Resets cached lists. For tests.
	 *
	 * @internal
	 */
	public static function reset(): void {
		self::$transitions = null;
	}
}
