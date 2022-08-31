<?php
/**
 * Base abstract class for external data connectors.
 *
 * @author Alexander Mashin
 *
 */
abstract class EDConnectorBase {
	use EDParsesParams;	// Needs paramToArray().

	/** @const string[] ID_PARAMS An array of name of params that will serve as data sources' names. */
	private const ID_PARAMS = [ 'url', 'db', 'server', 'domain', 'program', 'file', 'directory', 'source', '*' ];
	/** @const string ID_PARAM What the specific parameter identifying the connection is called. */
	protected const ID_PARAM = 'source';
	/** @const string[] URL_PARAMS An array of name of params that will serve as data sources' names for URLs. */
	private const URL_PARAMS = [ 'url', 'host', '2nd_lvl_domain' ];

	/** @const string[] GLOBAL_ARRAYS Old configuration settings that are arrays but not indexed by data sources. */
	private const GLOBAL_ARRAYS = [ 'StringReplacements', 'HTTPOptions', 'TryEncodings' ];
	/** @const string[] OLD_CONFIG Mapping between old and new configuration variables. */
	private const OLD_CONFIG = [
		'StringReplacements' => 'replacements', 'AllowExternalDataFrom' => 'allowed urls',
		'TryEncodings' => 'encodings', 'AllowSSL' => 'allow ssl', 'HTTPOptions' => 'options',
		'DBServer' => 'server', 'DBServerType' => 'type', 'DBName' => 'name',
		'DBUser' => 'user', 'DBPass' => 'password',
		'DBDirectory' => 'directory', 'DBFlags' => 'flags', 'DBTablePrefix' => 'prefix',
		'DBDriver' => 'driver', 'DBPrepared' => 'prepared', 'DBTypes' => 'types',
		'MemCachedMongoDBSeconds' => 'cache seconds',
		'DirectoryPath' => 'path', 'DirectoryDepth' => 'depth', 'FilePath' => 'path',
		'LDAPServer' => 'server', 'LDAPUser' => 'user', 'LDAPPass' => 'password', 'LDAPBaseDN' => 'base dn',
		'ExeCommand' => 'command', 'ExeParams' => 'params', 'ExeParamFilters' => 'param filters', 'ExeInput' => 'input',
		'ExeTempFile' => 'temp', 'ExeLimits' => 'limits', 'ExeEnvironment' => 'env',
		'ExeIgnoreWarnings' => 'ignore warnings', 'ExePreprocess' => 'preprocess', 'ExePostprocess' => 'postprocess',
		'ExeCacheSeconds' => 'min cache seconds', 'ExeUseStaleCache' => 'always use stale cache',
		'ExeName' => 'name', 'ExeUrl' => 'program url',
		'ExeVersion' => 'version', 'ExeVersionCommand' => 'version command',
		'ExeThrottleKey' => 'throttle key', 'ExeThrottleInterval' => 'throttle interval', 'ExeTags' => 'tag',
		'AlwaysAllowStaleCache' => 'always use stale cache', 'CacheExpireTime' => 'min cache seconds',
		'ThrottleKey' => 'throttle key', 'ThrottleInterval' => 'throttle interval'
	];

	/** @var array $sources All configured data sources. */
	protected static $sources = [];

	/** @var array|null An array of errors. */
	private $errors;

	/** @var bool Whether error messages are to be suppressed in wikitext. */
	private $suppressError = false;

	/** @var array An associative array mapping internal variables to external. */
	private $mappings = [];
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

		// Check the presence of the identifier parameter ('source', 'url', 'db', etc.).
		if ( !isset( $args[static::ID_PARAM] ) ) {
			if ( isset( $args[self::ID_PARAM] ) ) {
				// 'source' is a universal replacement for 'url', 'db', etc.
				$args[static::ID_PARAM] = $args[self::ID_PARAM];
			} else {
				$this->error( 'externaldata-no-param-specified', static::ID_PARAM );
			}
		}

		// Check the presence of required values.
		if ( isset( $args['params'] ) ) {
			$args = $this->checkPresence( $args, $args['params'] );
		}

		// Validate parameters.
		if ( isset( $args['param filters'] ) ) {
			$args = $this->validateParams( $args, $args['param filters'] );
		}

		// Data mappings. May be handled by the parser or by self. Delay settings, if format auto-detection is set.
		if ( array_key_exists( 'data', $args ) ) {
			// Whether to bring the external variables to lower case. It depends on the parser, if any.
			$this->mappings = self::paramToArray( $args['data'], false, false );
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
			// Superimpose column from $values on column from $this->values.
			$this->values[$variable] = array_merge( $this->values[$variable], $column );
		}
	}

	/**
	 * Returns a list of connectors configured in $wgExternalDataConnectors.
	 *
	 * @return array An associative array of the form [ 'get_some_data' => 'getSomeData' ].
	 */
	public static function getConnectors(): array {
		$connectors = [];
		foreach ( self::setting( 'Connectors' ) as $connector ) {
			$parser_function = $connector[0]['__pf'];
			if ( !isset( $connectors[$parser_function] ) && is_string( $parser_function ) ) {
				// 'get_some_data' => 'getSomeData'.
				$connectors[$parser_function] = preg_replace_callback( '/_(\w)/', static function ( array $captures ) {
					return strtoupper( $captures[1] );
				}, $parser_function );
			}
		}
		return $connectors;
	}

	/**
	 * Chooses the proper EDConnector* class.
	 *
	 * @param string $name Parser function name.
	 * @param array $args Its parameters.
	 *
	 * @return string Name of a EDConnector* class.
	 */
	protected static function getConnectorClass( $name, array $args ) {
		$args['__pf'] = $name;
		return self::getMatch( $args, self::setting( 'Connectors' ) );
	}

	/**
	 * A factory method that chooses and instantiates the proper EDConnector* class.
	 *
	 * @param string $name Parser function name.
	 * @param array $args Its parameters.
	 * @param Title|null $title A title object.
	 *
	 * @return EDConnectorBase An EDConnector* object.
	 */
	public static function getConnector( $name, array $args, ?Title $title ): EDConnectorBase {
		$supplemented = self::supplementParams( $args );
		$class = self::getConnectorClass( $name, $supplemented );
		// Instantiate the connector. If $class is empty, either this extension or $wgExternalDataConnectors is broken.
		return new $class( $supplemented, $title );
	}

	/**
	 * Supplement $wgExternalDataSources from old $wgExternalData... settings.
	 */
	public static function loadConfig() {
		$sources = self::setting( 'Sources' );

		// Read old style settings.
		$old_prefix = self::$oldPrefix;
		foreach ( self::OLD_CONFIG as $old => $new ) {
			if ( isset( $GLOBALS["$old_prefix$old"] ) ) {
				$global = $GLOBALS["$old_prefix$old"];
				if ( is_array( $global ) && !in_array( $old, self::GLOBAL_ARRAYS ) ) {
					// This $wgExternalData... is per data source.
					foreach ( $global as $source => $value ) {
						if ( !isset( $sources[$source] ) ) {
							$sources[$source] = [];
						}
						$sources[$source][$new] = $value;
					}
				} else {
					// This $wgExternalData... is universal. Save it in the '*' pseudo-source.
					$sources['*'][$new] = $global;
				}
			}
		}

		self::$sources = $sources;
	}

	/**
	 * Substitute default values for the absent parameters. Log an error if a required parameter is not supplied.
	 *
	 * @param array $parameters User-supplied parameters.
	 * @param array $defaults An array of parameter defaults. A numeric key means that the value is a required param.
	 * @return array The supplemented parameters.
	 */
	private static function setDefaults( array $parameters, array $defaults ): array {
		// Check if the required parameters are present and provide default values for the optional ones.
		foreach ( $defaults as $key => $value ) {
			if ( is_string( $key ) && !isset( $parameters[$key] ) ) { // no value provided.
					$parameters[$key] = $value;
			}
		}
		return $parameters;
	}

	/**
	 * Log an error if a required parameter is not supplied.
	 *
	 * @param array $parameters User-supplied parameters.
	 * @param array $defaults An array of parameter defaults. A numeric key means that the value is a required param.
	 * @return array The same $parameters.
	 */
	private function checkPresence( array $parameters, array $defaults ): array {
		// Check if the required parameters are present and provide default values for the optional ones.
		foreach ( $defaults as $key => $value ) {
			if ( is_numeric( $key ) && !isset( $parameters[$value] ) ) {
					$this->error( 'externaldata-no-param-specified', $key );
			}
		}
		return $parameters;
	}

	/**
	 * Validate parameters. Log an error if a parameter has an illegal value.
	 *
	 * @param array $parameters User-supplied parameters.
	 * @param array $filters An array of parameter filters (callables or regexes).
	 * @return array The validated parameters.
	 */
	private function validateParams( array $parameters, array $filters ): array {
		// Validate parameters.
		foreach ( $parameters as $key => $value ) {
			if ( !(
				// no filter for this parameter.
				!isset( $filters[$key] )
				// filter is a function and returns true.
				|| is_callable( $filters[$key] ) && $filters[$key]( $value )
				// filter is a regular expression and parameter matches it.
				|| is_string( $filters[$key] ) && preg_match( $filters[$key], $value )
			) ) {
				$this->error( 'externaldata-illegal-parameter', $key, $value );
			}

		}
		return $parameters;
	}

	/**
	 * Convert an associative array of wildcards into one usable directly by strtr().
	 * @param array $wildcards The wildcards.
	 * @return array The wildcards surrounded by '$...$'.
	 */
	private static function forStrtr( array $wildcards ): array {
		$filtered = array_filter( $wildcards, static function ( $value ) {
			return is_string( $value ) || is_numeric( $value );
		} );
		return array_combine(
			array_map( static function ( $wildcard ) {
				return '$' . (string)$wildcard . '$';
			}, array_keys( $filtered ) ),
			array_values( $filtered )
		);
	}

	/**
	 * Substitute each $parameter$ in $parameters recursively using the $replacements associative array.
	 *
	 * @param string|array $parameters Parameters in which wildcards should be substituted.
	 * @param array $replacements Optional associative array of replacements, $parameters by default.
	 * @return string|array The string(s) with substituted parameters.
	 */
	private static function substitute( $parameters, array $replacements ) {
		if ( is_string( $parameters ) ) {
			$parameters = strtr( $parameters, $replacements );
		} elseif ( is_array( $parameters ) ) {
			foreach ( $parameters as &$value ) {
				$value = self::substitute( $value, self::forStrtr( $parameters ) + $replacements );
			}
		}
		return $parameters;
	}

	/**
	 * This method adds secret parameters to user-supplied ones, extracting them from
	 * global configuration variables.
	 *
	 * @param array $params User-supplied parameters.
	 * @return array Supplemented parameters.
	 */
	protected static function supplementParams( array $params ): array {
		$supplemented = $params;

		// Allow concise syntax with unnamed first parameter instead of 'source', etc.
		if ( isset( $params[0] ) ) {
			$supplemented[self::ID_PARAM] = $params[0];
		}

		// URL passed as 'source'.
		if (
			!isset( $supplemented['url'] ) &&
			isset( $supplemented[self::ID_PARAM] ) &&
			filter_var( $supplemented[self::ID_PARAM], FILTER_VALIDATE_URL )
		) {
			$supplemented['url'] = $supplemented[self::ID_PARAM];
		}

		// A list of fields containing names data sources to read.
		$fields = [];
		if ( isset( $supplemented['url'] ) ) {
			// Get URL components.
			$supplemented['components'] = parse_url( $supplemented['url'] );
			if ( isset( $supplemented['components']['host'] ) ) {
				// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset Make PHAN shut up.
				$supplemented['host'] = $supplemented['components']['host'];
			}
			// Second-level domain is likely to be both a throttle key and an index to find a throttle key or interval.
			// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset text saying why it was suppressed
			if ( $supplemented['host'] && preg_match( '/(?<=^|\.)\w+\.\w+$/', $supplemented['host'], $matches ) ) {
				$supplemented['2nd_lvl_domain'] = $matches[0];
			} else {
				// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset text saying why it was suppressed
				$supplemented['2nd_lvl_domain'] = $supplemented['host'];
			}
			$fields = self::URL_PARAMS;
		}
		// @phan-suppress-next-line PhanSuspiciousBinaryAddLists Shut up.
		$fields += self::ID_PARAMS;
		$supplemented['*'] = '*';

		// 'db' passed as 'server'.
		if ( isset( $supplemented['server'] ) && !isset( $supplemented['db'] ) ) {
			$supplemented['db'] = $supplemented['server'];
		}

		// Read the settings for data source(s).
		$wiki_wide = [];
		foreach ( $fields as $field ) {
			if ( isset( $supplemented[$field] ) ) {
				$id = $supplemented[$field];
				if ( isset( self::$sources[$id] ) ) {
					foreach ( self::$sources[$id] as $param => $value ) {
						if ( !isset( $wiki_wide[$param] ) ) { // more specific setting override less specific.
							$wiki_wide[$param] = $value;
						}
					}
				}
			}
		}

		// Set default values.
		if ( isset( $wiki_wide['params'] ) ) {
			$params = self::setDefaults( $params, $wiki_wide['params'] );
		}

		// Substitute user-supplied parameters into wiki-wide, where they contain wildcards.
		$wiki_wide = self::substitute( $wiki_wide, self::forStrtr( $params ) );

		// Apply wiki-wide settings. They override user-provided ones.
		foreach ( $wiki_wide as $param => $value ) {
			$supplemented[$param] = $value;
		}

		return $supplemented;
	}

	/**
	 * Mappings from internal => external.
	 * @return array
	 */
	protected function mappings(): array {
		return $this->keepExternalVarsCase ? $this->mappings : array_map( 'mb_strtolower', $this->mappings );
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

		// Special case: __all in data argument or no data at all. Need to map all external variables to internal ones.
		$mappings = $this->mappings();
		if ( count( $mappings ) === 0 || isset( $mappings['__all'] ) ) {
			foreach ( $external_values as $external_var => $_ ) {
				$mappings[$external_var] = $external_var;
			}
		}

		// For each external variable name specified in the function
		// call, get its value or values (if any exist), and attach it
		// or them to the local variable name
		$result = [];
		foreach ( $mappings as $local_var => $external_var ) {
			if ( array_key_exists( $external_var, $external_values ) ) {
				self::setInternal(
					$result,
					$local_var,
					$external_values[$external_var]
				);
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
	 * @param string ...$params Message parameters.
	 */
	protected function error( $code, ...$params ) {
		if ( !$this->errors ) {
			$this->errors = [];
		}
		if ( is_array( $code ) ) {
			foreach ( $code as $error ) {
				$this->error( $error['code'], $error['params'] );
			}
		} else {
			if ( isset( $params[0] ) && is_array( $params[0] ) ) {
				// Overwrapped $params.
				$params = $params[0];
			}
			// Guarantee that errors do not repeat:
			$key = hash( 'md5', $code . ':' . var_export( $params, true ) );
			$this->errors[$key] = [ 'code' => $code, 'params' => $params ];
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
