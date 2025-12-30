<?php
/**
 * Base abstract class for external data connectors.
 *
 * @author Alexander Mashin
 *
 */
use ExternalData\Presets\Base;
use ExternalData\Presets\Media;
use MediaWiki\Title\Title;

abstract class EDConnectorBase {
	use EDParsesParams; // Needs paramToArray().

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

	/** @var string $input This will be fed to program's standard input. */
	protected $input;
	/** @var array $params Parameters to command or to URL. */
	protected $params;
	/** @var ?callable $preprocessor A preprocessor for program input. */
	private $preprocessor;
	/** @var ?callable $postprocessor A postprocessor for program output. */
	protected $postprocessor;

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
		$args = self::paramToArray( $args, true );

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

		// Preprocessor:
		$this->preprocessor = $this->processor( $args['preprocess'] ?? null );

		// stdin.
		if ( isset( $args['input'] ) ) {
			$input = $args['input'];
			if ( isset( $args[$input] ) ) {
				$this->input = $args[$input];
				// Preprocess, if required.
				$preprocessor = $this->preprocessor;
				if ( $preprocessor ) {
					$this->input = $preprocessor( $this->input, $args );
				}
			} else {
				$this->error( 'externaldata-no-param-specified', $input );
			}
		}

		// Postprocessor.
		$this->postprocessor = $this->processor( $args['postprocess'] ?? null );

		// Copy of the params.
		$this->params = $args;

		// Whether to suppress error messages.
		if ( array_key_exists( 'suppress error', $args ) || array_key_exists( 'hidden', $args ) ) {
			$this->suppressError = true;
		}
	}

	/**
	 * The function analyses and converts 'preprocess' and 'postprocess' params into callables.
	 * @param callable|array $param
	 * @return callable|null
	 */
	private function processor( $param ): ?callable {
		$func = null;
		if ( is_callable( $param ) ) {
			$func = function ( string $input, array $params, ?string $preprocessed = null ) use ( $param ) {
				try {
					$output = $param( $input, $params, $preprocessed );
				} catch ( Exception $e ) {
					$this->error( 'externaldata-processing-exception', $e->getMessage() );
					return false;
				}
				return $output;
			};
		} elseif ( is_array( $param ) ) {
			$callables = array_filter( $param, 'is_callable' );
			$func = function ( string $input, array $params, ?string $preprocessed = null ) use ( $callables ) {
				$output = $input;
				foreach ( $callables as $callable ) {
					try {
						$output = $callable( $output, $params, $preprocessed );
					} catch ( Exception $e ) {
						$this->error( 'externaldata-processing-exception', $e->getMessage() );
						return false;
					}
				}
				return $output;
			};
		}
		return $func;
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
	 * Override user-supplied settings with wiki-wide.
	 * @param array $wiki_wide
	 * @param array $supplemented
	 * @return array
	 */
	protected static function applyWikiWideSettings( array $wiki_wide, array $supplemented ): array {
		foreach ( $wiki_wide as $param => $value ) {
			if ( $value !== null ) {
				$supplemented[$param] = $value;
			} else {
				unset( $supplemented[$param] );
			}
		}
		return $supplemented;
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
		self::pile( $this->values, $values );
	}

	/**
	 * Add a special variable.
	 *
	 * @param string $variable Special variable's name.
	 * @param mixed $value Special variable's value.
	 */
	protected function addSpecial( string $variable, $value ) {
		if ( !isset( $this->values[$variable] ) ) {
			$this->values[$variable] = [];
		}
		$this->values[$variable][] = $value;
	}

	/**
	 * A list of available connectors.
	 * @return array
	 */
	private static function connectors(): array {
		$connectors = self::setting( 'IntegratedConnectors' );
		global $wgExternalDataAllowGetters;
		if ( $wgExternalDataAllowGetters ) {
			$last = array_pop( $connectors );
			$connectors = array_merge( self::setting( 'Connectors' ), $connectors, [ $last ] );
		}
		return $connectors;
	}

	/**
	 * Returns a list of connectors configured in $wgExternalDataConnectors.
	 *
	 * @return array An associative array of the form [ 'get_some_data' => 'getSomeData' ].
	 */
	public static function getConnectors(): array {
		$connectors = [];
		foreach ( self::connectors() as $connector ) {
			$parser_function = $connector[0]['__pf'] ?? null;
			if ( is_string( $parser_function ) && !isset( $connectors[$parser_function] ) ) {
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
	 * @param string|null $name Parser function name.
	 * @param array $args Its parameters.
	 *
	 * @return string Name of a EDConnector* class.
	 */
	protected static function getConnectorClass( ?string $name, array $args ): string {
		$args['__pf'] = $name;
		$connectors = self::connectors();
		return self::getMatch( $args, $connectors );
	}

	/**
	 * A factory method that chooses and instantiates the proper EDConnector* class.
	 *
	 * @param string|null $name Parser function name.
	 * @param array $args Its parameters.
	 * @param Title $title A title object.
	 *
	 * @return EDConnectorBase An EDConnector* object.
	 */
	public static function getConnector( ?string $name, array $args, Title $title ): EDConnectorBase {
		$supplemented = self::supplementParams( $args, $title );
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

		// Load chosen presets and merge them with overrides.
		foreach ( $sources as $source => &$settings ) {
			if ( is_array( $settings ) ) {
				if ( isset( $settings['preset'] ) ) {
					$settings = array_replace_recursive(
						call_user_func(
							[ 'ExternalData\\Presets\\' . ucfirst( $settings['preset'] ), 'source' ],
							$source
						),
						$settings
					);
					unset( $settings['preset'] );
				}
			} elseif ( is_string( $settings ) && in_array( ucfirst( $settings ), [ 'Test', 'Reference', 'Media' ] ) ) {
				$settings = call_user_func( [ 'ExternalData\\Presets\\' . ucfirst( $settings ), 'source' ], $source );
			}
		}

		// Load presets wholesale.
		foreach ( [ 'test', 'reference', 'media' ] as $group ) {
			if ( ( $sources["load $group presets"] ?? false ) || ( $sources['load all presets'] ?? false ) ) {
				$presets = call_user_func( [ 'ExternalData\\Presets\\' . ucfirst( $group ), 'sources' ] );
				$sources = array_replace_recursive( $presets, $sources );
			}
			unset( $sources["load $group presets"] );
		}
		unset( $sources["load all presets"] );

		self::$sources = $sources;
	}

	/**
	 * Substitute default values for the absent parameters.
	 *
	 * @param array $parameters User-supplied parameters.
	 * @param array $defaults An array of parameter defaults. A numeric key means that the value is a required param.
	 * @param Title $title The Title object.
	 * @return array The supplemented parameters.
	 */
	private static function setDefaults( array $parameters, array $defaults, Title $title ): array {
		// Check if the required parameters are present and provide default values for the optional ones.
		// Literal defaults.
		foreach ( $defaults as $key => $value ) {
			if ( !is_callable( $value ) ) { // literal defaults.
				if ( !isset( $parameters[$key] ) ) { // no value provided.
					$parameters[$key] = $value;
				}
			}
		}
		// Functional defaults:
		foreach ( $defaults as $key => $value ) {
			if ( is_callable( $value ) ) { // literal defaults.
				if ( !isset( $parameters[$key] ) ) { // no value provided.
					$parameters[$key] = $value( $parameters, $title );
				}
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
				$this->error( 'externaldata-no-param-specified', $value );
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
			if ( !isset( $filters[$key] ) ) {
				continue;
			}
			if ( is_callable( $filters[$key] ) ) {
				if ( !$filters[$key]( $value, $parameters ) ) {
					$this->error( 'externaldata-illegal-parameter', $key, $value );
				}
				continue;
			}
			if ( is_string( $filters[$key] ) ) {
				if ( !preg_match( $filters[$key], $value ) ) {
					$this->error( 'externaldata-illegal-parameter', $key, $value );
				}
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
	 * Checks, whether $args contains a data source identifier.
	 * @param array $args
	 * @return ?string
	 */
	public static function sourceSet( array $args ): ?string {
		foreach ( self::ID_PARAMS as $arg ) {
			if ( isset( $args[$arg] ) ) {
				return $arg;
			}
		}
		return null;
	}

	/**
	 * This method adds secret parameters to user-supplied ones, extracting them from
	 * global configuration variables.
	 *
	 * @param array $params User-supplied parameters.
	 * @param Title $title Title object.
	 * @return array Supplemented parameters.
	 */
	protected static function supplementParams( array $params, Title $title ): array {
		$supplemented = $params;

		// Allow concise syntax with unnamed first parameter instead of 'source', etc.
		if ( isset( $params[0] ) ) {
			$supplemented[static::ID_PARAM] = $params[0];
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
			if ( isset( $supplemented['host'] ) ) {
				if ( preg_match( '/(?<=^|\.)\w+\.\w+$/', $supplemented['host'], $matches ) ) {
					$supplemented['2nd_lvl_domain'] = $matches[0];
				} else {
					// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset text saying why it was suppressed
					$supplemented['2nd_lvl_domain'] = $supplemented['host'];
				}
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
				if ( isset( static::$sources[$id] ) ) {
					foreach ( static::$sources[$id] as $param => $value ) {
						if ( !isset( $wiki_wide[$param] ) ) { // more specific setting override less specific.
							$wiki_wide[$param] = $value;
						}
					}
				}
			}
		}

		// Set default values.
		if ( isset( $wiki_wide['params'] ) ) {
			$supplemented = self::setDefaults( $supplemented, $wiki_wide['params'], $title );
		}

		// Substitute user-supplied parameters into wiki-wide, where they contain wildcards.
		$wiki_wide = self::substitute( $wiki_wide, self::forStrtr( $supplemented ) );

		// Apply wiki-wide settings. They override user-provided ones.
		$supplemented = self::applyWikiWideSettings( $wiki_wide, $supplemented );

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
					if ( $external_values[$filter_var] !== $filter_value ) {
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
	 * @param array|string ...$params Message parameters.
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

	/**
	 * Get a list of data sources emulating tags.
	 * @return array[]
	 */
	public static function emulatedTags(): array {
		$tags = [];
		foreach ( self::$sources as $source => $config ) {
			if ( isset( $config['tag'] ) ) {
				$tag = $config['tag'];
				$tags[$tag] = self::tagFunction( $config + [ 'source' => $source ] );
				// @todo: is 'program' really necessary?
			}
		}
		return $tags;
	}

	/**
	 * Create a parser function implementing a tag.
	 * @param array $config
	 * @return callable
	 * @throws Exception
	 */
	private static function tagFunction( array $config ): callable {
		return static function (
			?string $inner,
			array $attributes,
			Parser $parser,
			PPFrame $_
		) use ( $config ) {
			$params = $attributes;
			if ( isset( $config['input'] ) ) {
				$params[$config['input']] = $inner;
			}
			$params['source'] = $config['source'];
			$id = $params['id'] ?? 'output';
			$params['data'] ??= "$id=__text";
			$params['format'] = 'text';
			$title = method_exists( $parser, 'getPage' ) ? $parser->getPage() : $parser->getTitle();
			if ( !$title ) {
				throw new MWException( 'No Title object.' ); // unreachable. Just to make the IDE shut up.
			}
			$params = self::supplementParams( $params, $title );
			if ( $title ) {
				// @phan-suppress-next-line SecurityCheck-ReDoS
				$connector = self::getConnector( 'get_external_data', $params, $title );
				if ( !$connector->errors() ) {
					if ( $connector->run() ) {
						$values = $connector->result();
						// @todo: other markerTypes?
						return [ $values[$id][0], 'markerType' => 'nowiki' ];
					}
				}
				return EDParserFunctions::formatErrorMessages( $connector->errors() );
			}
			return false;
		};
	}

	/**
	 * Return the version of the relevant software to be used at Special:Version.
	 * @param array $config
	 * @return array [ 'name', 'version' ]
	 */
	public static function version( array $config ): array {
		$name = $config['name'] ?? null;
		$version = null;
		// Program site.
		if ( isset( $config['program url'] ) ) {
			$name = "[{$config['program url']} $name]";
		}
		if ( isset( $config['version'] ) ) {
			return [ $name, $config['version'] ];
		}
		return [ $name, $version ];
	}

	/**
	 * Register used software for Special:Version.
	 * @param array &$software
	 * @return bool
	 */
	public static function addSoftware( array &$software ): bool {
		foreach ( self::$sources as $id => $config ) {
			$class = self::getConnectorClass( null, $config );
			[ $name, $version ] = call_user_func( [ $class, 'version' ], $config );
			if ( $name && $version !== false ) {
				$software[$name] = $version ?: '(unknown)';
			}
		}
		return true;
	}

	/*
	 * Pre- and postprocessing utilities. Moved to \ExternalData\Presets\Base;
	 * wrappers are preserved here for backward compatibility.
	 */

	/**
	 * Validate XML.
	 * @param string $xml
	 * @return bool
	 */
	public static function validateXml( string $xml ): bool {
		return Base::validateXml( $xml );
	}

	/**
	 * Validate JSON.
	 * @param string|array $json
	 * @param array $params
	 * @return bool
	 */
	public static function validateJson( $json, array $params ): bool {
		return Base::validateJsonOrYaml( $json, $params );
	}

	/**
	 * Convert [[wikilinks]] to dot links, including images and CSS.
	 *
	 * @param string $dot Text to add wikilinks in dot format.
	 * @param array $params
	 * @return string dot with links.
	 */
	public static function wikilinks4dot( string $dot, array $params ): string {
		return Media::wikilinks4dot( $dot, $params );
	}

	/**
	 * Convert [[wikilinks]] to UML links.
	 *
	 * @param string $uml Text to add wikilinks in UML format.
	 * @return string dot with links.
	 */
	public static function wikilinks4uml( string $uml ): string {
		return Media::wikilinks4uml( $uml );
	}

	/**
	 * @param string $script
	 * @return string
	 */
	public static function maskWikilinks( string $script ): string {
		return Media::maskWikilinks( $script );
	}

	/**
	 * @param string $masked
	 * @return string
	 */
	public static function unmaskWikilinks( string $masked ): string {
		return Media::unmaskWikilinks( $masked );
	}

	/**
	 * Strip SVG from surrounding XML.
	 *
	 * @param string $xml XML to extract SVG from.
	 * @param array $params
	 * @return string The stripped SVG.
	 */
	public static function innerXML( string $xml, array $params ): string {
		return Media::innerXml( $xml, $params );
	}

	/**
	 * Strips log messages before and after SVG that could not be stripped otherwise.
	 * @param string $input
	 * @return string
	 */
	public static function onlySvg( string $input ): string {
		return Media::onlySvg( $input );
	}

	/**
	 * Replace local image paths with URLs in SVG.
	 * @param string $svg SVG to process
	 * @return string Prcessed SVG
	 */
	public static function filepathToUrl( string $svg ): string {
		return Media::filepathToUrl( $svg );
	}

	/**
	 * Set SVG size, if not set.
	 * @param string $svg
	 * @param array $params
	 * @return string
	 */
	public static function sizeSVG( string $svg, array $params ): string {
		return Media::sizeSVG( $svg, $params );
	}

	/**
	 * Convert [[…]] in SVG <text> into <a>.
	 * @param string $svg
	 * @return string
	 */
	public static function wikilinksInSvg( string $svg ): string {
		return Media::wikilinksInSvg( $svg );
	}

	/**
	 * Alter links to JavaScripts in SVG.
	 * @param string $svg
	 * @param array $params
	 * @return string
	 */
	public static function jsLinks( string $svg, array $params ): string {
		return Media::jsLinksInSvg( $svg, $params );
	}

	/**
	 * Inject locale object for Vega.
	 * @param array|string $json
	 * @param array $params
	 * @return string
	 * @throws MWException
	 */
	public static function inject3d( $json, array $params ): string {
		return Media::inject3d( $json, $params );
	}

	/**
	 * Make interactive Vega visualisation based on the original JSON, with SVG fallback.
	 * @param string $svg Vega visualisation exported to SVG to be used as fallback.
	 * @param array $params Parameters passed to Vega engine, including the source JSON.
	 * @param string $json Vega JSON config.
	 * @return string HTML code containing the animated Vega with SVG fallback.
	 */
	public static function animateVega( string $svg, array $params, string $json ): string {
		return Media::animateChart( $svg, $params, $json );
	}

	/**
	 * Make interactive ECharts visualisation based on the original JSON, with SVG fallback.
	 * @param string $svg ECharts visualisation exported to SVG to be used as fallback.
	 * @param array $params Parameters passed to ECharts engine, including the source JSON.
	 * @param string $json ECharts JSON config.
	 * @return string HTML code containing the animated ECharts with SVG fallback.
	 */
	public static function animateEcharts( string $svg, array $params, string $json ): string {
		return Media::animateEcharts( $svg, $params, $json );
	}

	/**
	 * Screen colons in wikilinks in a Mermaid diagram.
	 * @param string $mmd
	 * @return string
	 */
	public static function screenColons( string $mmd ): string {
		return Media::screenColons( $mmd );
	}

	/**
	 * Convert wikilinks in XML to proper hyperlinks.
	 * @param string $xml
	 * @return string
	 */
	public static function wikiLinksInXml( string $xml ): string {
		return Media::wikiLinksInXml( $xml );
	}

	/**
	 * Make interactive Mermaid diagram based on the source code, with SVG fallback.
	 * @param string $svg Mermaid diagram converted to SVG server-side to be used as fallback.
	 * @param array $params Parameters to <mermaid> tag including the Mermaid source code.
	 * @param string $mmd Mermaid source code.
	 * @return string The original SVG plus Mermaid source code with scripts to activate it.
	 */
	public static function animateMermaid( string $svg, array $params, string $mmd ): string {
		return Media::animateChart( $svg, $params, $mmd );
	}
}
