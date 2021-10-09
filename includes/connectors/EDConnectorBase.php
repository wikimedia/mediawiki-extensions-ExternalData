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
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		// Bring keys to lowercase:
		$args = self::paramToArray( $args, true, false );
		// Add secrets from wiki settings:
		$args = self::supplementParams( $args );

		// Data mappings. May be handled by the parser or by self.
		if ( array_key_exists( 'data', $args ) ) {
			// Whether to bring the external variables to lower case. It depends on the parser, if any.
			$this->mappings = self::paramToArray( $args['data'], false, !$this->keepExternalVarsCase );
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
	 * @return array [ array External data, array 'Safe to embed raw' flags ].
	 */
	public function result() {
		return $this->filteredAndMappedValues();
	}

	/**
	 * Add new values.
	 *
	 * @param array|null $values A new set of columns.
	 */
	protected function add( $values ) {
		if ( !$values ) {
			return;
		}
		// Find maximum height of columns to be built on.
		$maximum_height = 0;
		foreach ( $values as $variable => $_ ) {
			// Create new columns if necessary.
			if ( !array_key_exists( $variable, $this->values ) ) {
				$this->values[$variable] = [];
			}
			$maximum_height = count( $this->values[$variable] ) > $maximum_height
				? count( $this->values[$variable] )
				: $maximum_height;
		}
		foreach ( $values as $variable => $column ) {
			// Stretch out columns if they are to be built on.
			for ( $counter = count( $this->values[$variable] ); $counter < $maximum_height; $counter++ ) {
				$this->values[$variable][$counter] = null;
			}
			// Superimpose column from $values on column from $this->>values.
			$this->values[$variable] = array_merge( $this->values[$variable], $column );
		}
	}

	/**
	 * A factory method that chooses and instantiates the proper EDConnector* class.
	 *
	 * @param string $name Parser function name.
	 * @param array $args Its parameters.
	 * @param Title $title A title object.
	 *
	 * @return EDConnectorBase An EDConnector* object.
	 */
	public static function getConnector( $name, array $args, Title $title ): EDConnectorBase {
		$args['__pf'] = $name;
		$args['__mongo'] = class_exists( 'MongoDB\Client' ) ? 'MongoDB\Client'
					   : ( class_exists( 'MongoClient' ) ? 'MongoClient' : null );
		if ( isset( $args['file name'] ) && strpbrk( $args['file name'], '*?[]' ) ) {
			$args['file pattern'] = $args['file name'];
		}
		global $edgConnectors;
		$class = self::getMatch( $args, $edgConnectors );
		// Instantiate the connector. If $class is empty, either this extension or $edgConnectors is broken.
		return new $class( $args, $title );
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
				self::setInternal(
					$result,
					$local_var,
					$external_values[$external_var]
				);
			}
		}

		// Special case: __all in data argument. Need to map all external variables to internal ones.
		if ( isset( $this->mappings['__all'] ) ) {
			foreach ( $external_values as $external_var => $external_value ) {
				self::setInternal( $result, $external_var, $external_value );
			}
		}

		return $result;
	}

	/**
	 * Set internal variable.
	 *
	 * @param array &$values Array to set value in.
	 * @param string $name Variable name.
	 * @param mixed $value Variable value(s).
	 */
	private static function setInternal( array &$values, $name, $value ) {
		if ( is_array( $value ) ) {
			$values[$name] = array_values( $value );
		} else {
			// @todo Check, if this code is ever reached.
			$values[$name][] = $value;
		}
	}

	/**
	 * Register an error.
	 *
	 * @param array|string $code Error message key or array of errors.
	 * @param string $params,... Message parameters.
	 */
	protected function error( $code, ...$params ) {
		if ( !$this->errors ) {
			$this->errors = [];
		}
		if ( is_array( $code ) ) {
			foreach ( $code as $error ) {
				$this->error( $error[0], $error[1] );
			}
		} else {
			if ( isset( $params[0] ) && is_array( $params[0] ) ) {
				// Overwrapped $params.
				$params = $params[0];
			}
			$this->errors[] = wfMessage( $code, $params )->inContentLanguage()->text();
		}
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
