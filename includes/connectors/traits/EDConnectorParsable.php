<?php
/**
 * A trait used by connectors that receive external data as text need to parse it.
 *
 */
trait EDConnectorParsable {
	/** @var EDParserBase A Parser. */
	private $parser;
	/** @var string $encoding Current encoding. */
	protected $encoding;
	/** @var string[] $encodings Try these encodings. */
	private $encodings = [];
	/** @var bool $do_not_decode Do not decode archives and suchlike. */
	private $do_not_decode = false;

	/** @var int $startAbsolute Start from this line (absolute, zero-based). */
	private $startAbsolute;
	/** @var int $endAbsolute End with this line (absolute, zero-based). */
	private $endAbsolute;
	/** @var float $startPercent Start from this line (percents). */
	private $startPercent;
	/** @var float $endPercent End with this line (percents). */
	private $endPercent;
	/** @var int $headerLines Always include so many lines from the beginning. */
	private $headerLines;
	/** @var int $footerLines Always include so many lines at the end. */
	private $footerLines;

	/** @var array $parseErrors A 2d array of parse errors. */
	protected $parseErrors = [];

	/**
	 * Prepare the parser.
	 * Call in EDConnector*::__construct() before parent::__construct().
	 *
	 * @param array $args Arguments to the parser function.
	 * @param string|null $parser The optional name ot the EDParser class.
	 */
	protected function prepareParser( array $args, $parser = null ) {
		if ( isset( $args['archive path'] ) ) {
			$this->do_not_decode = true;
		} else {
			// Encoding override supplied by wiki user may also be needed.
			$this->encoding = isset( $args['encoding'] ) && $args['encoding'] ? $args['encoding'] : null;
			// Try these encodings.
			$this->encodings = isset( $args['encodings'] ) && $args['encodings'] ? $args['encodings'] : [];
		}

		try {
			$this->parser = $parser ? new $parser( $args ) : EDParserBase::getParser( $args );
		} catch ( EDParserException $e ) {
			$this->parseErrors[] = [ 'code' => $e->code(), 'params' => $e->params() ];
			return;
		}

		// @phan-suppress-next-line PhanUndeclaredProperty keepExternalVarsCase is declared in EDConnectorBase.
		$this->keepExternalVarsCase = $this->parser->keepExternalVarsCase || $this->keepExternalVarsCase;

		// Set start and end lines.
		$this->setLine( $args, 'start', 0 );
		$this->setLine( $args, 'end', 1 );

		// Set header and footer.
		$this->headerLines = isset( $args['header lines'] ) ? (int)$args['header lines'] : 0;
		$this->footerLines = isset( $args['footer lines'] ) ? (int)$args['footer lines'] : 0;
	}

	/**
	 * Calculate absolute offset and limit.
	 *
	 * @param int $total Total number of lines.
	 *
	 * @return array [['start' => (effective absolute), 'end' => (effective absolute)] (up to three pairs);
	 *      also contains 'start' and 'end' fields].
	 */
	private function ranges( $total ): array {
		$start = $this->startAbsolute !== null ? $this->startAbsolute : (int)round( $this->startPercent * $total );
		$end = $this->endAbsolute !== null ? $this->endAbsolute : (int)round( $this->endPercent * $total );
		if ( $start < 0 ) {
			$start = $total + $start;
		}
		if ( $end < 0 ) {
			$end = $total + $end;
		}
		$ranges = [	[ 'start line' => $start, 'end line' => $end ] ]; // set the main range

		if ( $this->headerLines ) {
			if ( $this->headerLines < $start ) {
				// Need to add a new range.
				array_unshift( $ranges, [ 'start line' => 0, 'end line' => $this->headerLines - 1 ] );
			} elseif ( $this->headerLines < $end ) {
				// Header and the range intersect.
				$ranges[0]['start line'] = 0;
			} else {
				// Header overlaps the whole range.
				$ranges[0]['end line'] = $this->headerLines - 1;
			}
		}
		if ( $this->footerLines ) {
			if ( $this->footerLines > $total - $start ) {
				// Footer overlaps the whole range.
				$ranges[count( $ranges ) - 1]['start line'] = $total - $this->footerLines;
			} elseif ( $this->footerLines > $total - $end ) {
				// Footer and range intersect.
				$ranges[count( $ranges ) - 1]['end line'] = $total - 1;
			} else {
				// Need to add a new range.
				$ranges[] = [ 'start line' => $total - $this->footerLines, 'end line' => $total - 1 ];
			}
		}
		// Set numbers for __start and __lines variables.
		$ranges['start line'] = $start;
		$ranges['end line'] = $end;
		return $ranges;
	}

	/**
	 * Parse text, if any parser is set.
	 *
	 * @param string $text Text to parse.
	 * @param string|null $path Optional file or URL path that may be relevant to the parser
	 *
	 * @return array|null Parsed values.
	 */
	protected function parse( $text, $path = null ) {
		$parser = $this->parser;

		$special_variables = [];

		// Insert newlines where appropriate.
		$text = $parser->addNewlines( $text, $this->headerLines || $this->footerLines );
		// Trimming.
		$split = explode( PHP_EOL, $text );
		$total = count( $split );
		$special_variables['__total'] = [ $total ];

		// Get really needed absolute line ranges.
		$ranges = $this->ranges( $total );

		// Extract __start, __lines and __end variables.
		$special_variables['__start'] = [ $ranges['start line'] + 1 ]; // zero-based to one-based.
		$special_variables['__end'] = [ $ranges['end line'] + 1 ]; // zero-based to one-based.
		$special_variables['__lines'] = [ $ranges['end line'] - $ranges['start line'] + 1 ];
		unset( $ranges['start line'] );
		unset( $ranges['end line'] );

		// Extract text fragment(s).
		$text = implode( PHP_EOL, array_map( static function ( array $range ) use ( $split ) {
			return implode(
				PHP_EOL,
				array_slice( $split, $range['start line'], $range['end line'] - $range['start line'] + 1 )
			);
		}, $ranges ) );

		// Parsing itself.
		try {
			$parsed = array_merge( $parser( $text, $path ), $special_variables );
			// @phan-suppress-next-line PhanUndeclaredProperty keepExternalVarsCase is declared in EDConnectorBase.
			$this->keepExternalVarsCase = $parser->keepExternalVarsCase; // can be altered by 'auto' format.
		} catch ( EDParserException $e ) {
			$parsed = null;
			$this->parseErrors[] = [ 'code' => $e->code(), 'params' => $e->params() ];
		}

		return $parsed;
	}

	/**
	 * Set start ot end line.
	 *
	 * @param array $args An array of parameters.
	 * @param string $name 'start' or 'end'.
	 * @param float $default The default value.
	 */
	private function setLine( array $args, $name, $default ) {
		$attr_absolute = "{$name}Absolute";
		$attr_percent = "{$name}Percent";
		$index = "$name line";
		if ( isset( $args[$index] ) && $args[$index] ) {
			[ $this->$attr_absolute, $this->$attr_percent ] = self::parseAbsoluteOrPercent( $args[$index] );
			if ( $this->$attr_absolute === null && $this->$attr_percent === null ) {
				// @phan-suppress-next-line PhanUndeclaredMethod error is declared in EDConnectorBase.
				$this->error( 'externaldata-param-type-error', $name, 'integer or percent' );
			}
		}
		if ( $this->$attr_absolute === null && $this->$attr_percent === null ) {
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
		if ( is_numeric( $arg ) ) {
			// An absolute value.
			$absolute = (int)$arg - 1; // one-based to zero-based
		} elseif ( preg_match( '/^(?<percent>-?100(\.0+)?|\d{1,2}(\.\d+)?)\s*%$/', $arg, $matches ) ) {
			$percent = (float)$matches['percent'] / 100;
		}
		return [ $absolute, $percent ];
	}

	/**
	 * Detect encoding based on tags in the $text,
	 *
	 * @param string $text Text to analyse and convert.
	 * @param string|null $encoding_override Encoding from context.
	 *
	 * @return string The converted text.
	 */
	private function toUTF8( $text, $encoding_override = null ) {
		if ( $this->do_not_decode ) {
			return $text;
		}

		$encoding = $encoding_override ?: null;

		// Try to find encoding in the XML/HTML.
		$encoding_regexes = [
			// charset must be in the capture #3.
			'/<\?xml([^>]+)encoding\s*=\s*(["\']?)([^"\'>]+)\2[^>]*\?>/i' => '<?xml$1encoding="UTF-8"?>',
			'%<meta([^>]+)(charset)\s*=\s*([^"\'>]+)([^>]*)/?>%i' => '<meta$1charset=UTF-8$4>',
			'%<meta(\s+)charset\s*=\s*(["\']?)([^"\'>]+)\2([^>]*)/?>%i'
			=> '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'
		];
		foreach ( $encoding_regexes as $pattern => $replacement ) {
			if ( preg_match( $pattern, $text, $matches ) ) {
				// Pretend it's already UTF-8.
				$text = preg_replace( $pattern, $replacement, $text, 1 );
				if ( !$encoding ) {
					$encoding = $matches[3];
				}
				break;
			}
		}

		// Try mb_detect_encoding.
		if ( !$encoding ) {
			$encoding = mb_detect_encoding( $text, $this->encodings, true /* strict */ );
		}

		// Convert $text:
		// Is it UTF-8 or ISO-8859-1?
		if ( $encoding && strtoupper( $encoding ) !== 'UTF-8' ) {
			$this->encoding = 'UTF-8'; // do not convert twice.
			return mb_convert_encoding( $text, 'UTF-8', $encoding );
		} else {
			return $text;
		}
	}
}
