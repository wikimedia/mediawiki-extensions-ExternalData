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
	/** @var int $tries How many times to try an HTTP request. */
	private static $tries = 3;

	// Cache variables.
	/** @var bool $cache_set_up Is the cache set up? */
	private static $cache_set_up;
	/** @var string|null $cache_table Cache table name. */
	private static $cache_table;

	/** @var int Number of seconds before cache expires. */
	private $cache_expires;
	/** @var bool Whether the data can be fetched from stale cache. */
	private $allow_stale_cache;

	/** @var int When the cache was cached. */
	private $cached_time;
	/** @var bool Is the cache fresh. */
	private $cache_fresh;
	/** @var int Timestamp of when the result was fetched. */
	private $time;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array $args An array of arguments for parser/Lua function.
	 */
	protected function __construct( array $args ) {
		parent::__construct( $args );

		// Cache.
		global $edgCacheTable;
		self::$cache_set_up = (bool)$edgCacheTable;
		self::$cache_table = $edgCacheTable;

		// Cache expiration.
		global $edgCacheExpireTime;
		$this->cache_expires = array_key_exists( 'cache seconds', $args )
			? max( $args['cache seconds'], $edgCacheExpireTime )
			: $edgCacheExpireTime;

		// Allow to use stale cache.
		global $edgAlwaysAllowStaleCache;
		$this->allow_stale_cache = array_key_exists( 'use stale cache', $args ) || $edgAlwaysAllowStaleCache;
	}

	/**
	 * Actually connect to the external data source.
	 * It is presumed that there are no errors in parameters and wiki settings.
	 * Set $this->values and $this->errors.
	 *
	 * @return bool True on success, false if error were encountered.
	 */
	public function run() {
		$this->cache_time = null;
		$this->cache_fresh = false;

		$cached = false;
		// Is the cache set up, present and fresh?
		if ( self::$cache_set_up && ( $this->cache_expires !== 0 || $this->allow_stale_cache ) ) {
			// Cache set up and can be used.
			$cached = $this->cached();
		}

		// If there is no fresh cache, try to get from the web.
		$cache_present = (bool)$cached;
		$tries = 0;
		if ( !self::$cache_set_up || !$cache_present || !$this->cache_fresh || $this->cache_expires === 0 ) {
			// Allow extensions or LocalSettings.php to alter HTTP options.
			Hooks::run( 'ExternalDataBeforeWebCall', [ 'get', $this->real_url, $this->options ] );
			do {
				// Actually send a request.
				$contents = $this->fetcher(); // Late binding; fetcher() is pure virtual. Also sets $this->headers.
			} while ( !$contents && ++$tries <= self::$tries );
			if ( $contents ) {
				// Fetched successfully.
				$this->cache_fresh = true;
				// Encoding needs to be detected from HTTP headers this early and not later,
				// during text parsing, so that the converted text may be cached.
				// Try HTTP headers.
				if ( !$this->encoding ) {
					$this->encoding = EDEncodingConverter::fromHeaders( $this->headers );
				}
				$contents = EDEncodingConverter::toUTF8( $contents, $this->encoding );
				$this->time = time();
				// Update cache, if possible and required.
				if ( self::$cache_set_up && $this->cache_expires !== 0 ) {
					$this->cache( $contents, $cache_present );
				}
			} else {
				// Not fetched.
				if ( $cache_present && $this->allow_stale_cache ) {
					// But can serve stale cache, if any and allowed.
					$contents = $cached;
					$this->cache_fresh = false;
					$this->time = $this->cached_time;
				} else {
					// Nothing to serve.
					$this->error( 'externaldata-db-could-not-get-url', $this->original_url, self::$tries );
					return false;
				}
			}
		} else {
			// We have a fresh cache; so serve it.
			$contents = $cached;
			$this->time = $this->cached_time;
		}

		$this->values = $this->parse( $contents, [
			'__time' => [ $this->time ],
			'__stale' => [ !$this->cache_fresh ],
			'__tries' => [ $tries ]
		] );
		return !$this->errors();
	}

	/**
	 * This method must be reloaded in EDConnectorWeb and EDConnectorSoap.
	 * It should return $this->text and set $this->headers.
	 *
	 * @return string Fetched text.
	 */
	abstract protected function fetcher();

	/**
	 * Get cached value, if any. It is assumed that the cache is set up.
	 * Sets $this->cached_time and $this->cache_fresh.
	 *
	 * @return string|null The cached value; null if none.
	 */
	private function cached() {
		// Check the cache (only the first 254 chars of the url).
		$dbr = wfGetDB( DB_REPLICA );
		$row = $dbr->selectRow( self::$cache_table, '*', [ 'url' => substr( $this->real_url, 0, 254 ) ], __METHOD__ );
		if ( $row ) {
			$this->cached_time = $row->req_time;
			$this->cache_fresh = $this->cache_expires !== 0 && time() - $this->cached_time <= $this->cache_expires;
			return $row->result;
		} else {
			return null;
		}
	}

	/**
	 * Cache text. It is assumed that the cache is set up.
	 *
	 * @param string $contents Text to be cached.
	 * @param bool $old_cache True, if there was an old cache.
	 */
	private function cache( $contents, $old_cache ) {
		$dbw = wfGetDB( DB_MASTER );
		// Delete the old entry, if one exists.
		if ( $old_cache ) {
			$dbw->delete( self::$cache_table, [ 'url' => substr( $this->real_url, 0, 254 ) ] );
		}
		// Insert contents into the cache table.
		$dbw->insert(
			self::$cache_table,
			[ 'url' => substr( $this->real_url, 0, 254 ), 'result' => $contents, 'req_time' => time() ]
		);
	}
}
