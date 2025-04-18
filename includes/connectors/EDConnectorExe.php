<?php
use MediaWiki\MediaWikiServices;
use MediaWiki\Shell\Shell;
use MediaWiki\Title\Title;

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
	/** @const int VERSION_TTL Number of seconds that software version is to be cached. */
	private const VERSION_TTL = 3600; // one hour.

	/** @var string $program Program ID. */
	private $program;
	/** @var array $environment An array of environment variables. */
	private $environment = [];
	/** @var array $limits Limits override for shell commands. */
	private $limits;
	/** @var array $command The expanded command, as an array. */
	private $command;
	/** @var ?string $tempFile The program will send its output to a temporary file rather than stdout. */
	private $tempFile;
	/** @var bool $ignoreWarnings Ignore program warnings in stderr if exit code is 0. */
	private $ignoreWarnings = false;

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

		$this->program = $args[self::ID_PARAM] ?? null;

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

		// Get program's output from a temporary file rather than standard output.
		global $wgTmpDirectory;
		if ( $wgTmpDirectory && isset( $args['temp'] ) && is_string( $args['temp'] ) ) {
			$hash = hash( 'fnv1a64', $this->input );
			// @phan-suppress-next-line PhanPossiblyUndeclaredVariable WTF?
			$this->tempFile = str_replace( '$tmp$', "$wgTmpDirectory/$hash", $args['temp'] );
			// @phan-suppress-next-line PhanPossiblyUndeclaredVariable WTF?
			$command = str_replace( '$tmp$', "$wgTmpDirectory/$hash", $command );
		}

		$this->command = is_string( $command ) ? explode( ' ', $command ) : $command;

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
		$key = $args['throttle key'] ?? null;
		$interval = $args['throttle interval'] ?? null;
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
		$output = $this->callCached( function ( array $command, ?string $input, array $environment )
		use ( &$exit_code, &$error ) /* $this is bound. */ {
			return $this->callThrottled( function ( array $command, ?string $input, array $environment )
			use ( &$exit_code, &$error ) /* $this is bound. */ {
				$prepared = Shell::command( $command ) // Shell class demands an array of words.
					->environment( $environment )
					->limits( $this->limits );
				if ( $input !== null ) {
					$prepared = $prepared->input( $input );
				}
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
					if ( $output && $postprocessor ) {
						$output = $postprocessor( $output, $this->params, $input );
					}
					return $output;
				} else {
					$error = $error ?: $output; // Some programs send errors only to stdout.
					return false;
				}
			}, $command, $input, $environment );
		}, $this->command, $this->input, $this->environment );

		if ( $output ) {
			$this->add( $this->parse( $output ) );
			// Fill standard external variables.
			$this->addSpecial( '__time', $this->time );
			$this->addSpecial( '__stale', !$this->cacheFresh );
			if ( $this->waitTill ) {
				// Throttled, but there was a cached value.
				$this->addSpecial( '__throttled_till', $this->waitTill );
			}
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
	 * Return name and version of the relevant software to be used at Special:Version.
	 * @param array $config
	 * @return array [ 'name', 'version' ]
	 */
	public static function version( array $config ): array {
		[ $name, $version ] = parent::version( $config );
		if ( !( $name && $version ) && ( isset( $config['version command'] ) || isset( $config['command'] ) ) ) {
			// Version will be reported by the program itself.
			// Get path to the command.
			preg_match(
				'~^[\w/-]+~',
				is_array( $config['command'] ) ? $config['command'][0] : $config['command'],
				$matches
			);
			$path = $matches[0];
			if ( !$name ) {
				$name = basename( $path );
			}
			if ( isset( $config['version command'] ) ) {
				// The command key that reports the version is set in LocalSettings.php,
				$commands_v = [ $config['version command'] ];
			} else {
				// We will try several most common keys that print out version one by one.
				preg_match( '~[^/]+$~', $path, $dir_matches );
				$commands_v = [ "$path -V", "$path -v", "$path --version", "$path -version" ];
			}
			$cache = MediaWikiServices::getInstance()->getLocalServerObjectCache();
			$limits = self::defaultLimits();
			if ( isset( $config['limits'] ) ) {
				$limits += $config['limits'];
			}
			foreach ( $commands_v as $command_v ) {
				$version = $cache->getWithSetCallback(
					$cache->makeGlobalKey( __CLASS__, $command_v ),
					self::VERSION_TTL,
					static function () use ( $command_v, $limits ): ?string {
						$prepared = Shell::command( explode( ' ', $command_v ) );
						// Keeping backward compatibility with MW 1.35.
						$restrictions = 0;
						foreach ( [
							'disableNetwork' => Shell::NO_NETWORK,
							'privateUserNamespace' => Shell::NO_ROOT,
							'noNewPrivs' => Shell::SECCOMP,
							'privateDev' => Shell::PRIVATE_DEV
						] as $method => $const ) {
							if ( method_exists( $prepared, $method ) ) {
								$prepared = $prepared->{$method}();
							} else {
								$restrictions |= $const;
							}
						}
						if ( $restrictions !== 0 ) {
							$prepared = $prepared->restrict( $restrictions );
						}
						$prepared = $prepared->limits( $limits );
						try {
							$result = $prepared->execute();
						} catch ( Exception $e ) {
							return null;
						}
						// Some programs report their version to stderr, out of pronciple.
						return $result->getExitCode() === 0 ? ( $result->getStdout() ?: $result->getStderr() ) : null;
					}
				);
				if ( $version ) {
					return [ $name, $version ];
				}
			}
		}
		return [ $name, $version ];
	}
}
