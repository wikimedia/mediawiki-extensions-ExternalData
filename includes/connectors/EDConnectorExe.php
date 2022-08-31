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
	use EDConnectorThrottled; // throttles calls.

	/** @const string ID_PARAM What the specific parameter identifying the connection is called. */
	protected const ID_PARAM = 'program';

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
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		// Parser.
		$this->prepareParser( $args );
		$this->error( $this->parseErrors );

		parent::__construct( $args, $title );

		$this->program = isset( $args[self::ID_PARAM] ) ? $args[self::ID_PARAM] : null;

		$this->params = $args;

		if ( Shell::isDisabled() ) {
			$this->error( 'externaldata-exe-shell-disabled' );
		}

		// Specific parameters.
		$command = null;
		if ( !isset( $args[self::ID_PARAM] ) ) {
			$this->error( 'externaldata-no-param-specified', self::ID_PARAM );
			return; // no need to continue.
		}

		// The command, stored as a secret in LocalSettings.php.
		if ( isset( $args['command'] ) ) {
			$command = $args['command'];
		} else {
			$this->error( 'externaldata-exe-incomplete-information', $this->program, 'command' );
		}

		// Environment variables.
		if ( isset( $args['env'] ) && is_array( $args['env'] ) ) {
			$this->environment = $args['env'];
		}

		// Resource limits.
		$this->limits = self::defaultLimits();
		if ( isset( $args['limits'] ) && is_array( $args['limits'] ) ) {
			$this->limits = array_merge( $this->limits, $args['limits'] );
		}

		// Ignore warnings in stderr, if the exit code is 0.
		if ( isset( $args['ignore warnings'] ) ) {
			$this->ignoreWarnings = $args['ignore warnings'];
		}

		// Postprocessor:
		if ( isset( $args['preprocess'] ) && is_callable( $args['preprocess'] ) ) {
			$this->preprocessor = $args['preprocess'];
		}

		// stdin.
		if ( isset( $args['input'] ) ) {
			$input = $args['input'];
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
		global $wgTmpDirectory;
		if ( $wgTmpDirectory && isset( $args['temp'] ) && is_string( $args['temp'] ) ) {
			$hash = hash( 'fnv1a64', $this->input );
			// @phan-suppress-next-line PhanPossiblyUndeclaredVariable WTF?
			$this->tempFile = str_replace( '$tmp$', "$wgTmpDirectory/$hash", $args['temp'] );
			// @phan-suppress-next-line PhanPossiblyUndeclaredVariable WTF?
			$command = str_replace( '$tmp$', "$wgTmpDirectory/$hash", $command );
		}

		$this->command = is_array( $command ) ? $command : explode( ' ', $command );

		// Postprocessor.
		if ( isset( $args['postprocess'] ) ) {
			$this->postprocessor = $args['postprocess'];
		}

		// Cache setting may be per PF call, program and the extension. More aggressive have the priority.
		// Cache expiration.
		$cache_expires_local = array_key_exists( 'cache seconds', $args ) ? $args['cache seconds'] : 0;
		$cache_expires_per_program = array_key_exists( 'min cache seconds', $args ) ? $args['min cache seconds'] : 0;
		$cache_expires = max( $cache_expires_local, $cache_expires_per_program );
		// Allow to use stale cache.
		$allow_stale_cache = array_key_exists( 'use stale cache', $args )
							|| array_key_exists( 'always use stale cache', $args );
		$this->setupCache( $cache_expires, $allow_stale_cache );

		// Throttle.
		$key = isset( $args['throttle key'] ) ? $args['throttle key'] : null;
		$interval = isset( $args['throttle interval'] ) ? $args['throttle interval'] : null;
		if ( $key && $interval ) {
			$this->setupThrottle( $title, $key, $interval );
		}
	}

	/**
	 * Get default limits from MediaWiki settings.
	 *
	 * @return array
	 */
	private static function defaultLimits(): array {
		global $wgMaxShellTime, $wgMaxShellWallClockTime, $wgMaxShellMemory, $wgMaxShellFileSize;
		return [
			'time' => $wgMaxShellTime,
			'walltime' => $wgMaxShellWallClockTime,
			'memory' => $wgMaxShellMemory,
			'filesize' => $wgMaxShellFileSize
		];
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
		) use( &$exit_code, &$error )  /* $this is bound. */ {
			return $this->callThrottled( function (
				array $command,
				$input,
				array $environment
			) use( &$exit_code, &$error ) /* $this is bound. */ {
				$prepared = Shell::command( $command ) // Shell class demands an array of words.
					->input( $input )
					->environment( $environment )
					->limits( $this->limits );
				try {
					$result = $prepared->execute();
				} catch ( Exception $e ) {
					$this->error( 'externaldata-exe-exception', $e->getMessage() );
					return false;
				}
				$exit_code = $result->getExitCode();
				if ( $this->tempFile ) {
					if ( !file_exists( $this->tempFile ) ) {
						$error = "No temporary file {$this->tempFile}";
						return false;
					}
					$output = file_get_contents( $this->tempFile );
				} else {
					$output = $result->getStdout();
				}
				$error = $result->getStderr();

				if ( $exit_code === 0 && !( $error && !$this->ignoreWarnings ) ) {
					$postprocessor = $this->postprocessor;
					if ( $output && is_callable( $postprocessor ) ) {
						$output = $postprocessor( $output, $this->params );
					}
					return $output;
				} else {
					$error = $error ?: $output; // Some programs send errors only to stdout.
					return false;
				}
			}, $command, $input, $environment );
		}, $this->command, $this->input, $this->environment );

		if ( $output ) {
			// Fill standard external variables.
			$this->add( [
				'__time' => [ $this->time ],
				'__stale' => [ !$this->cacheFresh ]
			] );
			if ( $this->waitTill ) {
				// Throttled, but there was a cached value.
				$this->add( [ '__throttled_till' => [ $this->waitTill ] ] );
			}
			if ( $error ) {
				// Let's save the ignored warning.
				$this->add( [ '__warning' => [ $error ] ] );
			}
			$this->add( $this->parse( $output ) );
			$this->error( $this->parseErrors );
			return true;
		} else {
			// Nothing to serve.
			if ( $this->waitTill ) {
				// It was throttled, and there was no cached value.
				$this->error( 'externaldata-throttled', $this->program, (string)(int)ceil( $this->waitTill ) );
			} else {
				// It wasn't throttled; just could not get it.
				$this->error( 'externaldata-exe-error', $this->program, $exit_code, $error );
			}
			return false;
		}
	}

	/**
	 * Register tags for backward compatibility with other extensions.
	 *
	 * @param Parser $parser
	 */
	public static function registerTags( Parser $parser ) {
		foreach ( self::$sources as $program => $config ) {
			if ( !isset( $config['command'] ) || !isset( $config['tag'] ) ) {
				continue; // not a program or no tag emulation mode for it.
			}
			$parser->setHook(
				$config['tag'],
				static function ( $inner, array $attributes, Parser $parser, PPFrame $_ ) use ( $program, $config ) {
					$params = $attributes;
					$params[$config['input']] = $inner;
					$params['program'] = $program;
					$id = isset( $params['id'] ) ? $params['id'] : 'output';
					$params['data'] = "$id=__text";
					$params['format'] = 'text';
					$params = self::supplementParams( $params );
					$title = $parser->getTitle();
					if ( $title ) {
						// @phan-suppress-next-line SecurityCheck-ReDoS
						$connector = new self( $params, $title );
						if ( !$connector->errors() ) {
							if ( $connector->run() ) {
								$values = $connector->result();
								return [ $values[$id][0], 'markerType' => 'nowiki' ];
							}
						}
						return EDParserFunctions::formatErrorMessages( $connector->errors() );
					}
					return false;
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
		foreach ( self::$sources as $key => $config ) {
			if ( !isset( $config['command'] ) ) {
				continue; // not a program.
			}
			// Get path to the command.
			preg_match(
				'~^[\w/-]+~',
				is_array( $config['command'] ) ? $config['command'][0] : $config['command'],
				$matches
			);
			$path = $matches[0];

			// Get program name.
			if ( isset( $config['name'] ) ) {
				$name = $config['name'];
			} else {
				preg_match( '~[^/]+$~', $path, $matches );
				$name = $matches[0];
			}

			// Program site.
			if ( isset( $config['program url'] ) ) {
				$name = "[{$config['program url']} $name]";
			}

			// Program version.
			$version = null;
			if ( isset( $config['version'] ) ) {
				// Version is hard coded in LocalSettings.php.
				$version = $config['version'];
			} else {
				// Version will be reported by the program itself.
				if ( isset( $config['version command'] ) ) {
					// The command key that reports the version is set in LocalSettings.php,
					$commands_v = [ $config['version command'] ];
				} else {
					// We will try several most common keys that print out version one by one.
					$commands_v = [ "$path -V", "$path -v", "$path --version", "$path -version" ];
				}
				$cache = MediaWikiServices::getInstance()->getLocalServerObjectCache();
				$limits = self::defaultLimits();
				if ( isset( $config['limits'] ) ) {
					$limits += $config['limits'];
				}
				foreach ( $commands_v as $command_v ) {
					$reported_version = $cache->getWithSetCallback(
						$cache->makeGlobalKey( __CLASS__, $command_v ),
						self::VERSION_TTL,
						static function () use ( $command_v, $limits ) {
							$prepared = Shell::command( explode( ' ', $command_v ) )
								->includeStderr()
								->restrict( Shell::RESTRICT_DEFAULT | Shell::NO_NETWORK )
								->limits( $limits );
							try {
								$result = $prepared->execute();
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
	public static function wikilinks4dot( $str ) {
		// Process URL = "[[wikilink]]" in properties.
		$dewikified = preg_replace_callback(
			'/(?<attr>URL|href)\s*=\s*"\[\[(?<url>[^|<>\]]+)]]"/',
			static function ( array $m ) {
				return $m['attr'] . ' = "' . (string)CoreParserFunctions::localurl( null, $m['url'] ) . '"';
			},
			$str
		);
		// Process [[wikilink]] in nodes.
		$dewikified = preg_replace_callback( '/\[\[([^|<>\]]+)]]\s*(?:\[([^][]+)])?/', static function ( array $m ) {
			$props = isset( $m[2] ) ? $m[2] : '';
			return '"' . $m[1]
				 . '"[URL = "' . (string)CoreParserFunctions::localurl( null, $m[1] ) . '"; ' . $props . ']';
		}, $dewikified );
		return $dewikified;
	}

	/**
	 * Convert [[wikilinks]] to UML links.
	 *
	 * @param string $str Text to add wikilinks in UML format.
	 * @return string dot with links.
	 */
	public static function wikilinks4uml( $str ) {
		// Process [[wikilink]] in nodes.
		return preg_replace_callback( '/\[\[([^|\]]+)(?:\|([^]]*))?]]/', static function ( array $m ) {
			$alias = isset( $m[2] ) ? $m[2] : $m[1];
			return '[[' . (string)CoreParserFunctions::localurl( null, $m[1] ) . ' ' . $alias . ']]';
		}, $str );
	}

	/**
	 * Strip SVG from surrounding XML.
	 *
	 * @param string $xml XML to extract SVG from.
	 * @return string The stripped SVG.
	 */
	public static function innerXML( $xml ) {
		$dom = new DOMDocument();
		$dom->loadXML( $xml );
		return $dom->saveHTML();
	}
}
