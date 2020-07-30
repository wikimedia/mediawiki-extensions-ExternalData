<?php
/**
 * Base abstract class for external data connectors.
 *
 * @author Alexander Mashin
 *
 */
abstract class EDConnectorBase {
	use EDParsesParams;	// Needs paramToArray().

	/** @var array|null $errors An array of errors. */
	private $errors = null;

	/** @var bool $need_parser True, if the connector needs one of EDParser* objects. */
	protected static $needs_parser = false;
	/** @var EDParserBase $parser A Parser. */
	private $parser = null;

	/** @var array $mappings An associative array mapping internal variables to external. */
	protected $mappings = [];
	/** @var array $filters Data filters. */
	protected $filters = [];
	/** @var array $values Fetched data before filtering and mapping. */
	protected $values = [];

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 */
	protected function __construct( array &$args ) {
		// Add secrets from wiki settings:
		$args = self::supplementParams( $args );
		// Bring keys to lowercase:
		$params = self::paramToArray( $args, true, false );

		// Text parser, if needed.
		if ( static::$needs_parser ) {	// late binding.
			// Encoding override supplied by wiki user may also be needed.
			$this->encoding = isset( $params['encoding'] ) && $params['encoding'] ? $params['encoding'] : null;
			try {
				$this->parser = EDParserBase::getParser( $params );
			} catch ( EDParserException $e ) {
				$this->error( $e->code(), $e->params() );
			}
		}

		// Data mappings. May be handled by the parser or by self.
		if ( array_key_exists( 'data', $params ) ) {
			// Whether to bring the external variables to lower case. It depends on the parser, if any.
			$lower = !( $this->parser ? $this->parser : $this )->preservesCase();	// late binding in both.
			$this->mappings = self::paramToArray( $params['data'], false, $lower );
		} else {
			$this->error( 'externaldata-no-param-specified', 'data' );
		}

		// Filters.
		$this->filters = array_key_exists( 'filters', $params ) && $params['filters']
					   ? self::paramToArray( $params['filters'], true, false )
					   : [];
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
	 * @param array $params Its parameters.
	 *
	 * @return EDConnectorBase An EDConnector* object.
	 */
	public static function getConnector( $name, array $params ) {
		$params['__pf'] = $name;
		global $edgConnectors;
		$class = self::getMatch( $params, $edgConnectors );

		// Instantiate the connector. If $class is empty, either this extension or $edgConnectors is broken.
		return new $class( $params );
	}

	/**
	 * Parse text, if any parser is set.
	 *
	 * @param string $text Text to parse.
	 * @param array $defaults Default values.
	 *
	 * @return array Parsed values.
	 */
	protected function parse( $text, $defaults ) {
		$parser = $this->parser;
		if ( $parser ) {
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
			return null;
		}
		foreach ( $this->filters as $filter_var => $filter_value ) {
			// Find the entry of $external_values that matches
			// the filter variable; if none exists, just ignore
			// the filter.
			if ( array_key_exists( $filter_var, $external_values ) ) {
				if ( is_array( $external_values[$filter_var] ) ) {
					$column_values = $external_values[$filter_var];
					foreach ( $column_values as $i => $single_value ) {
						// if a value doesn't match
						// the filter value, remove
						// the value from this row for
						// all columns
						if ( trim( $single_value ) !== trim( $filter_value ) ) {
							foreach ( $external_values as $external_var => $external_value ) {
								unset( $external_values[$external_var][$i] );
							}
						}
					}
				} else {
					// if we have only one row of values,
					// and the filter doesn't match, just
					// keep the results array blank and
					// return
					if ( $external_values[$filter_var] != $filter_value ) {
						return;
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
		if ( is_array( $params[0] ) ) {
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
}
