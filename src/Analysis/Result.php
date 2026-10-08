<?php
/**
 * Outcome of one analysis rule.
 *
 * @package DumpSEO
 */

namespace DumpSEO\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * What a rule found. Status says how the page did; severity says how much the
 * rule matters. Status is always stated in words too (message), never only by
 * color, so the UI stays accessible.
 */
final class Result {

	public const PASS    = 'pass';
	public const WARNING = 'warning';
	public const ERROR   = 'error';
	public const INFO    = 'info';

	public const HIGH   = 'high';
	public const MEDIUM = 'medium';
	public const LOW    = 'low';

	/**
	 * Rule ID.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * One of PASS, WARNING, ERROR, INFO.
	 *
	 * @var string
	 */
	public $status;

	/**
	 * One of HIGH, MEDIUM, LOW.
	 *
	 * @var string
	 */
	public $severity;

	/**
	 * What was found (plain text).
	 *
	 * @var string
	 */
	public $message;

	/**
	 * What to do about it (plain text; '' when nothing).
	 *
	 * @var string
	 */
	public $recommendation;

	/**
	 * Measured values, e.g. length or count.
	 *
	 * @var array<string, int|float|string|bool>
	 */
	public $metadata;

	/**
	 * Constructor.
	 *
	 * @param string                               $id             Rule ID.
	 * @param string                               $status         Status constant.
	 * @param string                               $severity       Severity constant.
	 * @param string                               $message        What was found.
	 * @param string                               $recommendation What to do.
	 * @param array<string, int|float|string|bool> $metadata       Measured values.
	 */
	public function __construct( string $id, string $status, string $severity, string $message, string $recommendation = '', array $metadata = array() ) {
		$this->id             = $id;
		$this->status         = in_array( $status, array( self::PASS, self::WARNING, self::ERROR, self::INFO ), true ) ? $status : self::INFO;
		$this->severity       = in_array( $severity, array( self::HIGH, self::MEDIUM, self::LOW ), true ) ? $severity : self::LOW;
		$this->message        = $message;
		$this->recommendation = $recommendation;
		$this->metadata       = $metadata;
	}

	/**
	 * Plain array for REST responses.
	 *
	 * @return array{id: string, status: string, severity: string, message: string, recommendation: string, metadata: array<string, int|float|string|bool>}
	 */
	public function to_array(): array {
		return array(
			'id'             => $this->id,
			'status'         => $this->status,
			'severity'       => $this->severity,
			'message'        => $this->message,
			'recommendation' => $this->recommendation,
			'metadata'       => $this->metadata,
		);
	}
}
