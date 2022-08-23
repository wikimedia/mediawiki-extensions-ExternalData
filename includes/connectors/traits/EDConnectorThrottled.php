<?php
use MediaWiki\MediaWikiServices;

/**
 * A trait to be used by throttled connectors (all using EDConnectorCached plus EDConnectorPost).
 *
 * Classes that use this trait ought to call $this->setupThrottle() in their constructors
 * and can call functions wrapped in $this->callThrottled().
 *
 * @author Alexander Mashin
 *
 */
trait EDConnectorThrottled {
	/** @var Title $title Title object for the page that calls a throttled service. */
	private $title;
	/** @var string $throttleKey Connections are throttled per this key. */
	protected $throttleKey;
	/** @var BagOStuff $cache Object cache, where service freeing time (throttle status) is stored. */
	private $cache;
	/** @var string $cacheKey Key of the throttle status in the cache. */
	private $cacheKey;
	/** @var float $interval Interval between two calls to a throttled service, in seconds. */
	private $interval;
	/** @var float $wailtTill Time when the service is freed. */
	protected $waitTill;

	/**
	 * Initialise throttle status.
	 *
	 * @param Title $title Title object for the page that calls a throttled service.
	 * @param string $key Throttle key.
	 * @param float $interval Throttle interval, in seconds.
	 */
	protected function setupThrottle( Title $title, $key, $interval ) {
		$this->title = $title;
		$this->throttleKey = $key;
		$this->interval = $interval;
		if ( $this->throttleKey && $this->interval ) {
			// Presumably, all servers in a datacenter have the same external IP address.
			$this->cache = ObjectCache::getLocalClusterInstance();
			$this->cacheKey = $this->cache->makeKey( 'ED throttled connection', $this->throttleKey );
		}
	}

	/**
	 * Call a function, if it is not throttled, or plan it for later.
	 *
	 * @param callable $func The throttled function.
	 * @param mixed ...$args The arguments.
	 *
	 * @return mixed Result.
	 */
	protected function callThrottled( callable $func, ...$args ) {
		$this->waitTill = $this->waitTill();
		$time = microtime( true );
		if ( $time < $this->waitTill ) {
			// Plan page reparsing.
			$this->planPurge( $this->waitTill );
			return false;
		} else {
			// Update service freeing time.
			$this->blockTill( $time + $this->interval );
			return $func( ...$args ); // actually call the function.
		}
	}

	/**
	 * Return the time when it is allowed to connect to a throttled service.
	 *
	 * @return float Unix timestamp.
	 */
	private function waitTill() {
		return $this->throttleKey && $this->interval ? $this->cache->get( $this->cacheKey ) : 0;
	}

	/**
	 * Update the time when it is allowed to connect to a throttled service.
	 *
	 * @param float $time Unix timestamp.
	 */
	private function blockTill( $time ) {
		if ( $this->throttleKey && $this->interval ) {
			$this->cache->set( $this->cacheKey, $time );
		}
	}

	/**
	 * Plan page reparse and therefore, a new call to the throttled function.
	 *
	 * @param float $when When to reparse the page.
	 */
	private function planPurge( $when ) {
		if ( method_exists( MediaWikiServices::class, 'getJobQueueGroup' ) ) {
			// MW 1.37+
			// @phan-suppress-next-line PhanUndeclaredMethod Different MW versions.
			$queue_group = MediaWikiServices::getInstance()->getJobQueueGroup();
		} else {
			$queue_group = JobQueueGroup::singleton();
		}
		$params = [
			'title' => $this->title->getText(),
			'namespace' => $this->title->getNamespace(),
			'when' => (int)ceil( $when )
		];
		// Unfortunately, simple DB-base JobQueue does not support delayed jobs and will not queue them.
		foreach ( $queue_group->getQueueTypes() as $type ) {
			if ( $queue_group->get( $type )->delayedJobsEnabled() ) {
				$params['jobReleaseTimestamp'] = (int)ceil( $when );
				break;
			}
		}
		$queue_group->lazyPush( Job::factory( 'edReparse', $params ) );
	}
}
