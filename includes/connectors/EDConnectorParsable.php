<?php
/**
 * A trait used by connectors that receive external data as text need to parse it.
 */
trait EDConnectorParsable {

	/** @var EDParserBase A Parser. */
	private $parser;
	/** @var bool $parserKeepsCase Keep letter case? */
	protected $parserKeepsCase;
	/** @var string $encoding */
	protected $encoding;
	/** @var string $offsetAbsolute Start from this line (absolute, zero-based). */
	private $offsetAbsolute;
	/** @var string $limitAbsolute End with this line (absolute, zero-based). */
	private $limitAbsolute;
	/** @var string $offsetPercent Start from this line (percents). */
	private $offsetPercent;
	/** @var string $limitPercent End with this line (percents). */
	private $limitPercent;
	/** @var array $parseErrors An 2d array of parse errors. */
	protected $parseErrors = [];

	/**
	 * Prepare the parser.
	 * Call in EDConnector*::__construct() before parent::__construct().
	 *
	 * @param array $args Arguments to the parser function.
	 */
	protected function prepareParser( array $args ) {
		// Encoding override supplied by wiki user may also be needed.
		$this->encoding = isset( $args['encoding'] ) && $args['encoding'] ? $args['encoding'] : null;
		try {
			$this->parser = EDParserBase::getParser( $args );
			// Whether to keep letter case in variables.
			$this->parserKeepsCase = $this->parser->preservesCase();
		} catch ( EDParserException $e ) {
			$this->parseErrors[] = [ $e->code(), $e->params() ];
		}

		// Also, set start and end lines.
		$this->setLine( $args, 'offset', 0 );
		$this->setLine( $args, 'limit', 1 );
	}

	/**
	 * Parse text, if any parser is set.
	 *
	 * @param string $text Text to parse.
	 * @param array $defaults Default values.
	 *
	 * @return ?array Parsed values.
	 */
	protected function parse( $text, $defaults ): ?array {
		$parser = $this->parser;
		// Trimming.
		$split = explode( PHP_EOL, $text );
		$total = count( $split );
		$start = $this->offsetAbsolute !== null
			? $this->offsetAbsolute
			: (int)round( $this->offsetPercent * $total );
		$lines = $this->limitAbsolute !== null
			? $this->limitAbsolute
			: (int)round( $this->limitPercent * $total );
		if ( $start < 0 ) {
			$start = $total + $start - 1;
		}
		if ( $lines < 0 ) {
			$lines = $total + $lines - $start;
		}
		$text = implode( PHP_EOL, array_slice( $split, $start, $lines ) );
		$defaults['__start'] = [ $start ];
		$defaults['__lines'] = [ $lines ];
		$defaults['__end'] = [ $start + $lines - 1 ];
		$defaults['__total'] = [ $total ];

		// Parsing itself.
		try {
			$parsed = $parser( $text, $defaults );
		} catch ( EDParserException $e ) {
			$parsed = null;
			$this->parseErrors[] = [ $e->code(), $e->params() ];
		}
		return $parsed;
	}

	/**
	 * Set start ot end line.
	 *
	 * @param array $args An array of parameters.
	 * @param string $name 'start' or 'end'.
	 * @param numeric $default The default value.
	 */
	private function setLine( array $args, $name, $default ) {
		$attr_absolute = "{$name}Absolute";
		$attr_percent = "{$name}Percent";
		if ( isset( $args[$name] ) && $args[$name] ) {
			[ $this->$attr_absolute, $this->$attr_percent ] = self::parseAbsoluteOrPercent( $args[$name] );
			if ( $this->$attr_absolute === null && $this->$attr_percent === null ) {
				$this->error( 'externaldata-param-type-error', $name, 'integer or percent' );
			}
		}
		if ( !$this->$attr_absolute && !$this->$attr_percent ) {
			$this->$attr_percent = $default;
		}
	}

	/**
	 * Get integer or percent value from string.
	 *
	 * @param string $arg
	 * @return array [absolute|null, percent|null].
	 */
	private static function parseAbsoluteOrPercent( $arg ) {
		$absolute = null;
		$percent = null;
		if ( is_int( $arg ) ) {
			// An absolute value.
			$absolute = (int)$arg;
		} elseif ( preg_match( '/^(?<percent>-?100(\.0+)?|\d{1,2}(\.\d+)?)\s*%$/', $arg, $matches ) ) {
			$percent = (float)$matches['percent'] / 100;
		}
		return [ $absolute, $percent ];
	}
}
