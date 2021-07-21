<?php
/**
 * Base abstract class for external data connectors.
 *
 * @author Alexander Mashin
 *
 */
abstract class EDConnectorBase {
	use EDParsesParams;	// Needs paramToArray().

	/** @var array|null An array of errors. */
	private $errors;

	/** @var bool Whether error messages are to be suppressed in wikitext. */
	private $suppressError = false;

	/** @var bool True, if the connector needs one of EDParser* objects. */
	protected static $needsParser = false;
	/** @var EDParserBase A Parser. */
	private $parser;
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

	/** @var array An associative array mapping internal variables to external. */
	protected $mappings = [];
	/** @var array Data filters. */
	protected $filters = [];
	/** @var array Fetched data before filtering and mapping. */
	protected $values = [];

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 */
	protected function __construct( array &$args ) {
		// Bring keys to lowercase:
		$args = self::paramToArray( $args, true, false );
		// Add secrets from wiki settings:
		$args = self::supplementParams( $args );

		// Text parser, if needed.
		if ( static::$needsParser ) {	// late binding.
			// Encoding override supplied by wiki user may also be needed.
			$this->encoding = isset( $args['encoding'] ) && $args['encoding'] ? $args['encoding'] : null;
			try {
				$this->parser = EDParserBase::getParser( $args );
			} catch ( EDParserException $e ) {
				$this->error( $e->code(), $e->params() );
			}

			// Also, set start and end lines.
			$this->setLine( $args, 'offset', 0 );
			$this->setLine( $args, 'limit', 1 );
		}

		// Data mappings. May be handled by the parser or by self.
		if ( array_key_exists( 'data', $args ) ) {
			// Whether to bring the external variables to lower case. It depends on the parser, if any.
			$lower = !( $this->parser ?: $this )->preservesCase();	// late binding in both.
			$this->mappings = self::paramToArray( $args['data'], false, $lower );
		} else {
			$this->error( 'externaldata-no-param-specified', 'data' );
		}

		// Filters.
		$this->filters = array_key_exists( 'filters', $args ) && $args['filters']
					   ? self::paramToArray( $args['filters'], true, false )
					   : [];

		// Whether to suppress error messages.
		if ( array_key_exists( 'suppress error', $args ) ) {
			$this->suppressError = true;
		}
	}

	/**
	 * Set start ond end line.
	 *
	 * @param array $args An array of parameters.
	 * @param string $name 'offset' or 'end'.
	 * @param float $default The default value.
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
	private static function parseAbsoluteOrPercent( $arg ): array {
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

	/**
	 * Actually connect to the external data source.
	 * It is presumed that there are no errors in parameters and wiki settings.
	 * Set $this->values and $this->errors.
	 *
	 * @return bool True on success, false if error were encountered.
	 */
	abstract public function run();

	/**
	 * Return external data, already filtered and mapped.
	 *
	 * @return array External data.
	 */
	public function result() {
		return $this->filteredAndMappedValues();
	}

	/**
	 * A factory method that chooses and instantiates the proper EDConnector* class.
	 *
	 * @param string $name Parser function name.
	 * @param array $args Its parameters.
	 *
	 * @return EDConnectorBase An EDConnector* object.
	 */
	public static function getConnector( $name, array $args ): EDConnectorBase {
		$args['__pf'] = $name;
		$args['__mongo'] = class_exists( 'MongoDB\Client' ) ? 'MongoDB\Client'
					   : ( class_exists( 'MongoClient' ) ? 'MongoClient' : null );
		if ( isset( $args['file name'] ) && strpbrk( $args['file name'], '*?[]' ) ) {
			$args['file pattern'] = $args['file name'];
		}
		global $edgConnectors;
		$class = self::getMatch( $args, $edgConnectors );
		// Instantiate the connector. If $class is empty, either this extension or $edgConnectors is broken.
		return new $class( $args );
	}

	/**
	 * Parse text, if any parser is set.
	 *
	 * @param string $text Text to parse.
	 * @param array $defaults Default values.
	 *
	 * @return array Parsed values.
	 */
	protected function parse( $text, $defaults ): array {
		$parser = $this->parser;
		if ( $parser ) {

			// Trimming.
			$split = explode( PHP_EOL, $text );
			$total = count( $split );
			$offset = $this->offsetAbsolute !== null
					? $this->offsetAbsolute
					: (int)round( $this->offsetPercent * $total );
			$lines = $this->limitAbsolute !== null
					? $this->limitAbsolute
					: (int)round( $this->limitPercent * $total );
			if ( $offset < 0 ) {
				$offset = $total + $offset - 1;
			}
			if ( $lines < 0 ) {
				$lines = $total + $lines - $offset;
			}
			$text = implode( PHP_EOL, array_slice( $split, $offset, $lines ) );
			$defaults['__start'] = [ $offset ];
			$defaults['__lines'] = [ $lines ];
			$defaults['__end'] = [ $offset + $lines - 1 ];
			$defaults['__total'] = [ $total ];

			// Parsing itself.
			try {
				$parsed = $parser( $text, $defaults );
			} catch ( EDParserException $e ) {
				$parsed = null;
				$this->error( $e->code(), $e->params() );
			}
			return $parsed;
		}
	}

	/**
	 * A helper function that filters external values and maps them to internal ones.
	 *
	 * @return array Filtered and mapped values.
	 */
	private function filteredAndMappedValues() {
		$external_values = $this->values;
		if ( !$external_values ) {
			return [];
		}
		foreach ( $this->filters as $filter_var => $filter_value ) {
			// Find the entry of $external_values that matches
			// the filter variable; if none exists, just ignore
			// the filter.
			if ( array_key_exists( $filter_var, $external_values ) ) {
				if ( is_array( $external_values[$filter_var] ) ) {
					$column_values = $external_values[$filter_var];
					foreach ( $column_values as $i => $single_value ) {
						// if a value doesn't match the filter value, remove
						// the value from this row for all columns
						if ( trim( $single_value ) !== trim( $filter_value ) ) {
							foreach ( $external_values as $external_var => $external_value ) {
								unset( $external_values[$external_var][$i] );
							}
						}
					}
				} else {
					// if we have only one row of values, and the filter doesn't match,
					// just keep the results array blank and return
					if ( $external_values[$filter_var] != $filter_value ) {
						return [];
					}
				}
			}
		}
		// for each external variable name specified in the function
		// call, get its value or values (if any exist), and attach it
		// or them to the local variable name
		$result = [];
		foreach ( $this->mappings as $local_var => $external_var ) {
			if ( array_key_exists( $external_var, $external_values ) ) {
				if ( is_array( $external_values[$external_var] ) ) {
					// array_values() restores regular
					// 1, 2, 3 indexes to array, after unset()
					// in filtering may have removed some
					$result[$local_var] = array_values( $external_values[$external_var] );
				} else {
					$result[$local_var][] = $external_values[$external_var];
				}
			}
		}
		return $result;
	}

	/**
	 * Register an error.
	 *
	 * @param string $code Error message key.
	 * @param string $params,... Message parameters.
	 */
	protected function error( $code, ...$params ) {
		if ( !$this->errors ) {
			$this->errors = [];
		}
		if ( isset( $params[0] ) && is_array( $params[0] ) ) {
			// Overwrapped $params.
			$params = $params[0];
		}
		$this->errors[] = wfMessage( $code, $params )->inContentLanguage()->text();
	}

	/**
	 * Return a list of error messages.
	 *
	 * @return array An array of messages.
	 */
	public function errors() {
		return $this->errors;
	}

	/**
	 * Whether to suppress error messages.
	 *
	 * @return bool The message.
	 */
	public function suppressError() {
		return $this->suppressError;
	}
}
