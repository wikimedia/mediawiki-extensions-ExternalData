<?php
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
	/** @var string $command The expanded command. */
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
			if ( isset( $args['ExeParams'] ) && is_array( $args['ExeParams'] ) ) {
				foreach ( $args['ExeParams'] as $key => $val ) {
					$param = is_numeric( $key ) ? $val : $key;
					$default = is_numeric( $key ) ? null : $val;
					$value = isset( $args[$param] ) ? $args[$param] : $default;
					if ( $value ) {
						if (
							// no filter.
							!isset( $this->paramFilters[$param] )
							// filter is a function.
							|| is_callable( $this->paramFilters[$param] )
							&& $this->paramFilters[$param]( $value )
							// filter is a regular expression.
							|| is_string( $this->paramFilters[$param] )
							&& preg_match( $this->paramFilters[$param], $value )
						) {
							$command = preg_replace( '/\\$' . preg_quote( $param, '/' ) . '\\$/', $value, $command );
						} else {
							$this->error( 'externaldata-exe-illegal-parameter', $this->program, $param, $value );
						}
					} else {
						$this->error( 'externaldata-no-param-specified', $param );
					}
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

			$this->command = $command;

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
	 * Actually connect to the external data source (run program).
	 * It is presumed that there are no errors in parameters and wiki settings.
	 * Set $this->values and $this->errors.
	 *
	 * @return bool True on success, false if errors were encountered.
	 */
	public function run() {
		$output = $this->callCached( function ( $command, $input, array $environment ) use( &$exit_code, &$error ) {
			$result = Shell::command( explode( ' ', $command ) ) // Shell class demands an array of words.
				->input( $input )
				->environment( $environment )
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
		return preg_replace_callback( '/\\[\\[([^|<>\\]]+)]]\\s*(?:\\[([^][]+)])?/', static function ( array $m ) {
			$props = isset( $m[2] ) ? $m[2] : '';
			return '"' . $m[1] . '"[URL = "' . CoreParserFunctions::localurl( null, $m[1] ) . '"; ' . $props . ']';
		}, $str );
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
