<?php
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
	 */
	protected function __construct( array &$args ) {
		parent::__construct( $args );

		// Cache.
		// Cache expiration.
		global $edgCacheExpireTime;
		$cache_expires_local = array_key_exists( 'cache seconds', $args ) ? $args['cache seconds'] : 0;
		$cache_expires = max( $cache_expires_local, $edgCacheExpireTime );
		// Allow to use stale cache.
		global $edgAlwaysAllowStaleCache;
		$allow_stale_cache = array_key_exists( 'use stale cache', $args ) || $edgAlwaysAllowStaleCache;
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
		$contents = $this->callCached( function ( $url ) /* $this is bound. */ {
			// Allow extensions or LocalSettings.php to alter HTTP options.
			Hooks::run( 'ExternalDataBeforeWebCall', [ 'get', $url, $this->options ] );
			do {
				// Actually send a request.
				$contents = $this->fetcher(); // Late binding; fetcher() is pure virtual. Also sets $this->headers.
			} while ( !$contents && ++$this->tries <= self::$maxTries );
			if ( $contents ) {
				// Encoding needs to be detected from HTTP headers this early and not later,
				// during text parsing, so that the converted text may be cached.
				// Try HTTP headers.
				if ( !$this->encoding ) {
					$this->encoding = EDEncodingConverter::fromHeaders( $this->headers );
				}
				$contents = EDEncodingConverter::toUTF8( $contents, $this->encoding );
			}
			return $contents;
		}, $this->realUrl );

		if ( $contents ) {
			$this->values = $this->parse( $contents, [
				'__time' => [ $this->time ],
				'__stale' => [ !$this->cacheFresh ],
				'__tries' => [ $this->tries ]
			] );
			$this->error( $this->parseErrors );
			return !$this->errors();
		} else {
			// Nothing to serve.
			$this->error( 'externaldata-db-could-not-get-url', $this->originalUrl, self::$maxTries );
			return false;
		}
	}

	/**
	 * This method must be reloaded in EDConnectorWeb and EDConnectorSoap.
	 * It should return $this->text and set $this->headers.
	 *
	 * @return string Fetched text.
	 */
	abstract protected function fetcher();
}
