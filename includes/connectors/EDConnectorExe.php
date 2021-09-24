<?php
use MediaWiki\MediaWikiServices;
use MediaWiki\Shell\Shell;

/**
 * Class implementing {{#get_program_data:}} and mw.ext.externalData.getProgramData
 * for executing programs server-side.
 *
 * @author Alexander Mashin
 *
 */
class EDConnectorExe extends EDConnectorBase {
	use EDConnectorCached; // uses cache.
	use EDConnectorParsable; // needs parser.

	/** @var string $program Program ID. */
	private $program;
	/** @var array $environment An array of environment variables. */
	private $environment = [];
	/** @var array $limits Limits override for shell commands. */
	private $limits;
	/** @var array $command The expanded command, as an array. */
	private $command;
	/** @var array $params Parameters to $command. */
	private $params;
	/** @var array $paramFilters An associative array of regular expression filters for parameters. */
	private $paramFilters;
	/** @var string $input This will be fed to program's standard input. */
	private $input;
	/** @var ?string $tempFile The program will send its output to a temporary file rather than stdout. */
	private $tempFile;
	/** @var bool $ignoreWarnings Ignore program warnings in stderr if exit code is 0. */
	private $ignoreWarnings = false;
	/** @var ?callable $preprocessor A preprocessor for program input. */
	private $preprocessor;
	/** @var ?callable $postprocessor A postprocessor for program output. */
	private $postprocessor;

	/** @const int VERSION_TTL Number of seconds that software version is to be cached. */
	private const VERSION_TTL = 3600; // one hour.

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 */
	protected function __construct( array &$args ) {
		// Parser.
		$this->prepareParser( $args );
		$this->error( $this->parseErrors );

		parent::__construct( $args );

		if ( Shell::isDisabled() ) {
			$this->error( 'externaldata-exe-shell-disabled' );
		}

		// Specific parameters.
		$command = null;
		if ( !isset( $args['program'] ) ) {
			$this->error( 'externaldata-no-param-specified', 'program' );
		} else {
			$this->program = $args['program'];
			// The command, stored as a secret in LocalSettings.php.
			if ( isset( $args['ExeCommand'] ) ) {
				$command = $args['ExeCommand'];
			} else {
				$this->error( 'externaldata-exe-incomplete-information', $this->program, 'edgExeCommand' );
			}

			// Limits override.
			$this->limits = self::limits( $args['program'] );

			// Environment variables.
			if ( isset( $args['ExeEnvironment'] ) && is_array( $args['ExeEnvironment'] ) ) {
				$this->environment = $args['ExeEnvironment'];
			}

			// Parameter filters.
			if ( isset( $args['ExeParamFilters'] ) && is_array( $args['ExeParamFilters'] ) ) {
				$this->paramFilters = $args['ExeParamFilters'];
			}

			// Parameters.
			// The required parameters are stored as a secret in LocalSettings.php.
			$validated_params = null;
			if ( isset( $args['ExeParams'] ) && is_array( $args['ExeParams'] ) ) {
				$validated_params = $this->validateParams( $args, $args['ExeParams'], $this->paramFilters );
			}

			if ( $validated_params ) {
				// Substitute parameters in the shell command:
				$command = $this->substitute( $command, $validated_params );
				// And in the environment variables.
				foreach ( $this->environment as $var => &$value ) {
					$value = $this->substitute( $value, $validated_params );
				}
			}

			// Ignore warnings in stderr, if the exit code is 0.
			if ( isset( $args['ExeIgnoreWarnings'] ) ) {
				$this->ignoreWarnings = $args['ExeIgnoreWarnings'];
			}

			// Postprocessor:
			if ( isset( $args['ExePreprocess'] ) && is_callable( $args['ExePreprocess'] ) ) {
				$this->preprocessor = $args['ExePreprocess'];
			}

			// stdin.
			if ( isset( $args['ExeInput'] ) ) {
				$input = $args['ExeInput'];
				if ( isset( $args[$input] ) ) {
					$this->input = $args[$input];
					// Preprocess, if required.
					$preprocessor = $this->preprocessor;
					if ( $preprocessor ) {
						$this->input = $preprocessor( $this->input );
					}
				} else {
					$this->error( 'externaldata-no-param-specified', $input );
				}
			}

			// Get program's output from a temporary file rather than standard output.
			if ( isset( $args['ExeTempFile'] ) && is_string( $args['ExeTempFile'] ) ) {
				$hash = hash( 'fnv1a64', $this->input );
				global $wgTmpDirectory;
				$this->tempFile = preg_replace( '/\\$tmp\\$/', "$wgTmpDirectory/$hash", $args['ExeTempFile'] );
				$command = preg_replace( '/\\$tmp\\$/', "$wgTmpDirectory/$hash", $command );
			}

			$this->command = is_array( $command ) ? $command : explode( ' ', $command );

			// Postprocessor:
			if ( isset( $args['ExePostprocess'] ) ) {
				$this->postprocessor = $args['ExePostprocess'];
			}
		}

		// Cache setting may be per PF call, prorgam and the extension. More aggressive have the priority.
		// Cache expiration.
		global $edgCacheExpireTime;
		$cache_expires_local = array_key_exists( 'cache seconds', $args ) ? $args['cache seconds'] : 0;
		$cache_expires_per_program = array_key_exists( 'ExeCacheSeconds', $args ) ? $args['ExeCacheSeconds'] : 0;
		$cache_expires = max( $cache_expires_local, $cache_expires_per_program, $edgCacheExpireTime );
		// Allow to use stale cache.
		global $edgAlwaysAllowStaleCache;
		$allow_stale_cache = array_key_exists( 'use stale cache', $args )
							|| array_key_exists( 'ExeUseStaleCache', $args )
							|| $edgAlwaysAllowStaleCache;
		$this->setupCache( $cache_expires, $allow_stale_cache );
	}

	/**
	 * Validate parameters. Log an error if a required parameter is not supplied or a parameter has an illegal value.
	 *
	 * @param array $parameters User-supplied parameters.
	 * @param array $defaults An array of parameter defaults. A numeric key means that the value is a required param.
	 * @param array $filters An array of parameter filters (callables or regexes).
	 *
	 * @return array The validated parameters.
	 */
	private function validateParams( array $parameters, array $defaults, array $filters ): array {
		$validated = [];
		foreach ( $defaults as $key => $val ) {
			$param = is_numeric( $key ) ? $val : $key;
			$default = is_numeric( $key ) ? null : $val;
			$value = isset( $parameters[$param] ) ? $parameters[$param] : $default;
			if ( $value ) {
				if (
					// no filter.
					!isset( $filters[$param] )
					// filter is a function.
					|| is_callable( $filters[$param] ) && $filters[$param]( $value )
					// filter is a regular expression.
					|| is_string( $filters[$param] ) && preg_match( $filters[$param], $value )
				) {
					$validated[$param] = $value;
				} else {
					$this->error( 'externaldata-exe-illegal-parameter', $this->program, $param, $value );
				}
			} else {
				$this->error( 'externaldata-no-param-specified', $param );
			}
		}
		return $validated;
	}

	/**
	 * Substitute parameters into a string (command or environment variable).
	 *
	 * @param string|array $template The string(s) in which parameters are to be substituted.
	 * @param array $parameters Validated parameters.
	 *
	 * @return string|array The string(s) with substituted parameters.
	 */
	private function substitute( $template, array $parameters ) {
		foreach ( $parameters as $name => $value ) {
			$template = preg_replace( '/\\$' . preg_quote( $name, '/' ) . '\\$/', $value, $template );
		}
		return $template;
	}

	/**
	 * Make an array of resource limits for given shell command.
	 *
	 * @param string $program The program ID.
	 *
	 * @return array An array of limits.
	 */
	private static function limits( $program ): array {
		global $wgMaxShellTime, $wgMaxShellWallClockTime, $wgMaxShellMemory, $wgMaxShellFileSize, $edgExeLimits;
		$default_limits = [
			'time'      => $wgMaxShellTime,
			'walltime'  => $wgMaxShellWallClockTime,
			'memory'    => $wgMaxShellMemory,
			'filesize'  => $wgMaxShellFileSize
		];
		$explicit_limits = isset( $edgExeLimits[$program] ) && is_array( $edgExeLimits[$program] )
			? $edgExeLimits[$program] : [];
		return array_merge( $default_limits, $explicit_limits );
	}

	/**
	 * Actually connect to the external data source (run program).
	 * It is presumed that there are no errors in parameters and wiki settings.
	 * Set $this->values and $this->errors.
	 *
	 * @return bool True on success, false if errors were encountered.
	 */
	public function run() {
		$output = $this->callCached( function (
			array $command,
			$input,
			array $environment
		) use( &$exit_code, &$error ) {
			$result = Shell::command( $command ) // Shell class demands an array of words.
				->input( $input )
				->environment( $environment )
				->limits( $this->limits )
				->execute();
			$exit_code = $result->getExitCode();
			$output = $this->tempFile ? file_get_contents( $this->tempFile ) : $result->getStdout();
			$error = $result->getStderr();

			if ( $exit_code === 0 && !( $error && !$this->ignoreWarnings ) ) {
				$postprocessor = $this->postprocessor;
				if ( $output && is_callable( $postprocessor ) ) {
					$output = $postprocessor( $output );
				}
				return $output;
			} else {
				$error = $error ?: $output; // Some programs send errors only to stdout.
				return false;
			}
		}, $this->command, $this->input, $this->environment );

		if ( $output ) {
			// Fill standard external variables.
			$standard_vars = [
				'__time' => [ $this->time ],
				'__stale' => [ !$this->cacheFresh ]
			];
			if ( $error ) {
				// Let's save the ignored warning.
				$standard_vars['__warning'] = $error;
			}
			$this->values = $this->parse( $output, $standard_vars );
			$this->error( $this->parseErrors );
			return true;
		} else {
			$this->error( 'externaldata-exe-error', $this->program, $exit_code, $error );
			return false;
		}
	}

	/**
	 * Register tags for backward compatibility with other extensions.
	 *
	 * @param Parser $parser
	 */
	public static function registerTags( Parser $parser ) {
		global $edgExeTags;
		foreach ( $edgExeTags as $program => $tag ) {
			$parser->setHook(
				$tag,
				function ( $inner, array $args, Parser $parser, PPFrame $frame ) use ( $program ) {
					global $edgExeInput;
					$params = self::parseParams( $args );
					$params[$edgExeInput[$program]] = $inner;
					$params['program'] = $program;
					$id = isset( $params['id'] ) ? $params['id'] : 'output';
					$params['data'] = "$id=__text";
					$params['format'] = 'text';

					$connector = new self( $params );
					if ( !$connector->errors() ) {
						if ( $connector->run() ) {
							$values = $connector->result();
							return [ $values[$id][0], 'markerType' => 'nowiki' ];
						}
					}
					return EDParserFunctions::formatErrorMessages( $connector->errors() );
				}
			);
		}
	}

	/**
	 * Register used software for Special:Version.
	 *
	 * @param array &$software
	 */
	public static function addSoftware( array &$software ) {
		global $edgExeCommand;
		foreach ( $edgExeCommand as $key => $command ) {
			preg_match( '~^[\w/-]+~', is_array( $command ) ? $command[0] : $command, $matches );
			$path = $matches[0];
			global $edgExeName;
			if ( array_key_exists( $key, $edgExeName ) ) {
				$name = $edgExeName[$key];
			} else {
				preg_match( '~[^/]+$~', $path, $matches );
				$name = $matches[0];
			}
			global $edgExeUrl;
			if ( array_key_exists( $key, $edgExeUrl ) ) {
				$name = "[$edgExeUrl[$key] $name]";
			}
			$version = null;
			global $edgExeVersion;
			if ( array_key_exists( $key, $edgExeVersion ) ) {
				// Version is hard coded in LocalSettings.php.
				$version = $edgExeVersion[$key];
			} else {
				// Version will be reported by the program itself.
				global $edgExeVersionCommand;
				if ( array_key_exists( $key, $edgExeVersionCommand ) ) {
					// The command key that reports the version is set in LocalSettings.php,
					$commands_v = [ $edgExeVersionCommand[$key] ];
				} else {
					// We will try several most common keys that print out version one by one.
					$commands_v = [ "$path -V", "$path -v", "$path --version", "$path -version" ];
				}
				$cache = MediaWikiServices::getInstance()->getLocalServerObjectCache();
				$limits = self::limits( $key );
				foreach ( $commands_v as $command_v ) {
					$reported_version = $cache->getWithSetCallback(
						$cache->makeGlobalKey( __CLASS__, $command_v ),
						self::VERSION_TTL,
						static function () use ( $command_v, $limits ) {
							try {
								$result = Shell::command( explode( ' ', $command_v ) )
									->includeStderr()
									->restrict( Shell::RESTRICT_DEFAULT | Shell::NO_NETWORK )
									->limits( $limits )
									->execute();
							} catch ( Exception $e ) {
								return null;
							}
							return $result->getExitCode() === 0 ? $result->getStdout() : null;
						}
					);
					if ( $reported_version ) {
						$version = $reported_version;
						break;
					}
				}
			}
			$software[$name] = $version ?: '(unknown)';
		}
	}

	/*
	 * Pre- and postprocessing utilities.
	 */

	/**
	 * Convert [[wikilinks]] to dot links.
	 *
	 * @param string $str Text to add wikilinks in dot format.
	 * @return string dot with links.
	 */
	public static function wikilinks4dot( string $str ) {
		// Process URL = "[[wikilink]]" in properties.
		$dewikified = preg_replace_callback( '/URL\s*=\s*"\[\[([^|<>\]]+)]]"/', static function ( array $m ) {
			return 'URL = "' . CoreParserFunctions::localurl( null, $m[1] ) . '"';
		}, $str );
		// Process [[wikilink]] in nodes.
		$dewikified = preg_replace_callback( '/\[\[([^|<>\]]+)]]\s*(?:\[([^][]+)])?/', static function ( array $m ) {
			$props = isset( $m[2] ) ? $m[2] : '';
			return '"' . $m[1] . '"[URL = "' . CoreParserFunctions::localurl( null, $m[1] ) . '"; ' . $props . ']';
		}, $dewikified );
		return $dewikified;
	}

	/**
	 * Strip SVG from surrounding XML.
	 *
	 * @param string $xml XML to extract SVG from.
	 * @return string The stripped SVG.
	 */
	public static function innerXML( string $xml ): string {
		$dom = new DOMDocument();
		$dom->loadXML( $xml );
		return $dom->saveHTML();
	}
}
