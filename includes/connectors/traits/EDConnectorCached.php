<?php
/**
 * A trait to be used by cached connectors.
 *
 * Classes using this trait should call $this->setupCache() in their constructors
 * and can call functions wrapped in $this->callCached().
 *
 * @author Alexander Mashin
 * @author Yaron Koren
 *
 */
trait EDConnectorCached {
	use EDParsesParams;

	// Cache variables.
	/** @var bool $cacheIsUp Is the cache set up? */
	private static $cacheIsUp;
	/** @var \Wikimedia\Rdbms\Database $primaryDB Connection to primary database. */
	private static $primaryDB;
	/** @var \Wikimedia\Rdbms\Database $replicaDB Connection to primary database. */
	private static $replicaDB;
	/** @var string|null $cacheTable Cache table name. */
	private static $cacheTable;
	/** @var int Number of seconds before cache expires. */
	private $cacheExpires;
	/** @var bool Whether the data can be fetched from stale cache. */
	private $allowStaleCache;
	/** @var int When the cache was cached. */
	private $cachedTime;
	/** @var bool $cached Whether the result was fetched from the cache. */
	private $cached;
	/** @var bool Is the cache fresh. */
	private $cacheFresh;
	/** @var int Timestamp of when the result was fetched. */
	private $time;

	/**
	 * Setup cache. Call from the constructor.
	 * @param int $seconds Cache for so many seconds.
	 * @param bool $stale Allow using stale cache.
	 */
	private function setupCache( $seconds, $stale ) {
		// Take into account the obsolete setting $edgCacheTable.
		$cache_table = self::setting( 'CacheTable' ) ?: 'ed_url_cache';
		self::$cacheIsUp = (bool)$cache_table;
		self::$cacheTable = $cache_table;
		$this->cacheExpires = $seconds;
		$this->allowStaleCache = $stale;
		if ( self::$cacheIsUp ) {
			self::$primaryDB = wfGetDB( defined( 'DB_PRIMARY' ) ? DB_PRIMARY : DB_MASTER );
			self::$replicaDB = wfGetDB( DB_REPLICA );
			if ( !self::$replicaDB->tableExists( self::$cacheTable ) ) {
				self::$cacheIsUp = false;
			}
		}
	}

	/**
	 * A function wrapper that caches its results.
	 *
	 * @param callable $func The cached function.
	 * @param mixed ...$args The arguments.
	 * @return mixed Result.
	 */
	private function callCached( callable $func, ...$args ) {
		$cache_key = implode( ':', array_map( static function ( $val ) {
			return is_scalar( $val ) ? (string)$val : var_export( $val, true );
		}, $args ) );
		$this->cacheFresh = false;
		$cached = false;

		// Is the cache set up, present and fresh?
		if ( self::$cacheIsUp && ( $this->cacheExpires !== 0 || $this->allowStaleCache ) ) {
			// Cache set up and can be used.
			$cached = $this->cached( $cache_key );
		}

		// If there is no fresh cache, try to get from the web.
		$this->cached = (bool)$cached;
		// @phan-suppress-next-line PhanSuspiciousValueComparison WTF?
		if ( !self::$cacheIsUp || !$this->cached || !$this->cacheFresh || $this->cacheExpires === 0 ) {
			$result = $func( ...$args ); // actually call the function.
			if ( $result ) {
				// Non-falsy result from $func.
				$this->cacheFresh = true;
				$this->time = time();
				// Update cache, if possible and required.
				$this->cache( $cache_key, $result, $this->cached );
				$this->cached = false;
			} else {
				// No result from $func.
				if ( $this->cached && $this->allowStaleCache ) {
					// But can serve stale cache, if any and allowed.
					$result = $cached;
					$this->cacheFresh = false;
					$this->time = $this->cachedTime;
				} else {
					// Nothing to serve.
					return false;
				}
			}
		} else {
			// We have an acceptably fresh cache; so serve it.
			$result = $cached;
			$this->time = $this->cachedTime;
		}
		return $result;
	}

	/**
	 * Hash a too long key.
	 * @param string $key The key to hash.
	 * @return string The hashed key.
	 */
	private static function hash( $key ) {
		return strlen( $key ) > 254 ? hash( 'fnv1a64', $key ) : $key;
	}

	/**
	 * Get cached value, if any. It is assumed that the cache is set up.
	 * Sets $this->cachedTime and $this->cacheFresh.
	 *
	 * @param string $key Cache key.
	 * @return string|null The cached value; null if none.
	 */
	private function cached( $key ) {
		// Check the cache (only the first 254 chars of the url).
		$row = self::$replicaDB->selectRow(
			self::$cacheTable,
			'*',
			[ 'url' => self::hash( $key ) ],
			__METHOD__
		);
		if ( $row ) {
			$this->cachedTime = $row->req_time;
			$this->cacheFresh = $this->cacheExpires !== 0 && time() - $this->cachedTime <= $this->cacheExpires;
			return $row->result;
		} else {
			return null;
		}
	}

	/**
	 * Cache text. It is assumed that the cache is set up.
	 *
	 * @param string $key Cache key.
	 * @param string $contents Text to be cached.
	 * @param bool $old_cache True, if there was an old cache.
	 */
	private function cache( $key, $contents, $old_cache ) {
		// Update cache, if possible and required.
		if ( self::$cacheIsUp && $this->cacheExpires !== 0 ) {
			$hashed_key = self::hash( $key );
			// Delete the old entry, if one exists.
			// @todo: Upsert?
			if ( $old_cache ) {
				self::$primaryDB->delete( self::$cacheTable, [ 'url' => $hashed_key ] );
			}
			// Insert contents into the cache table.
			self::$primaryDB->insert(
				self::$cacheTable,
				[ 'url' => $hashed_key, 'result' => $contents, 'req_time' => time() ]
			);
		}
	}
}
