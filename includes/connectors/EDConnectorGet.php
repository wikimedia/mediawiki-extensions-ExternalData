<?php
use MediaWiki\MediaWikiServices;

/**
 * Base abstract class for connectors that send a GET request:
 * EDConnectorWeb and EDConnectorSoap. Both are cached.
 *
 * @author Alexander Mashin
 * @author Yaron Koren
 *
 */
abstract class EDConnectorGet extends EDConnectorHttp {
	use EDConnectorCached; // uses cache.

	/** @var int $maxTries How many times to try an HTTP request. */
	private static $maxTries = 3;
	/** @var int $tries How many tries have actually happened. */
	private $tries = 0;
	/** @var int Timestamp of when the result was fetched. */
	private $time;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		// Cache.
		// Cache expiration.
		$cache_expires_local = array_key_exists( 'cache seconds', $args ) ? $args['cache seconds'] : 0;
		$cache_expires_global = array_key_exists( 'min cache seconds', $args ) ? $args['min cache seconds'] : 0;
		$cache_expires = max( $cache_expires_local, $cache_expires_global );
		// Allow using stale cache.
		$allow_stale_cache = array_key_exists( 'use stale cache', $args )
			|| array_key_exists( 'always use stale cache', $args );
		$this->setupCache( $cache_expires, $allow_stale_cache );
	}

	/**
	 * Actually connect to the external data source.
	 * It is presumed that there are no errors in parameters and wiki settings.
	 * Set $this->values and $this->errors.
	 *
	 * @return bool True on success, false if errors were encountered.
	 */
	public function run() {
		$contents = $this->callCached( function ( $url, array $options ) /* $this is bound. */ {
			return $this->callThrottled( function ( $url, array $options ) /* $this is bound again */ {
				// Allow extensions or LocalSettings.php to alter HTTP options.
				$hook_name = 'ExternalDataBeforeWebCall';
				$errors = [];
				if ( class_exists( '\MediaWiki\HookContainer\HookContainer' ) ) {
					// MW 1.35+
					$hook_container = MediaWikiServices::getInstance()->getHookContainer();
					$hook_result = $hook_container->run( $hook_name, [ 'get', &$url, &$options, &$errors ], [] );
				} else {
					$hook_result = Hooks::run( $hook_name, [ 'get', &$url, &$options, &$errors ] );
				}
				if ( $hook_result === false ) {
					$this->error( 'externaldata-url-hooks-aborted', $hook_name, implode( ', ', $errors ) );
					return false;
				}
				do {
					// Actually send a request.
					// Late binding; fetcher() is pure virtual. Also sets $this->headers.
					$contents = $this->fetcher( $url, $options );
				} while ( !$contents && ++$this->tries <= self::$maxTries );
				// Encoding needs to be detected from HTTP headers this early and not later,
				// during text parsing, so that the converted text may be cached.
				// HTTP headers are not cached, therefore, they are not available,
				// if the text is fetched from the cache.
				return $this->convert2Utf8( $contents );
			}, $url, $options );
		}, $this->realUrl, $this->options );

		if ( $contents ) {
			$this->add( [
				'__time' => [ $this->time ],
				'__cached' => [ $this->cached ],
				'__stale' => [ !$this->cacheFresh ],
				'__tries' => [ $this->tries ]
			] );
			if ( $this->waitTill ) {
				// Throttled, but there was a cached value.
				$this->add( [ '__throttled_till' => [ $this->waitTill ] ] );
			}
			$this->add( $this->parse( $contents, $this->encoding ) );
			$this->error( $this->parseErrors );
			return !$this->errors();
		} else {
			// Nothing to serve.
			if ( $this->waitTill ) {
				// It was throttled, and there was no cached value.
				$this->error( 'externaldata-throttled', $this->originalUrl, (int)ceil( $this->waitTill ) );
			} else {
				// It wasn't throttled; just could not get it.
				$this->error( 'externaldata-db-could-not-get-url', $this->originalUrl, self::$maxTries );
			}
			return false;
		}
	}

	/**
	 * This method must be reloaded in EDConnectorWeb and EDConnectorSoap.
	 * It should return $this->text and set $this->headers.
	 *
	 * @param string $url URL to fetch.
	 * @param array $options HTTP options.
	 * @return string Fetched text.
	 */
	abstract protected function fetcher( $url, array $options );
}
