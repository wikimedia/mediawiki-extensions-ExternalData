<?php
use MediaWiki\MediaWikiServices;

/**
 * A job that reparses a wiki page, if time is come.
 *
 * @author Alexander Mashin
 *
 */
class EDReparseJob extends Job {
	/**
	 * @param string $command Command string
	 * @param array $params
	 */
	public function __construct( $command, array $params ) {
		parent::__construct( $command, $params );
		$this->removeDuplicates = true;
	}

	/**
	 * @see Job::run
	 *
	 * @return bool True on success, false if something is very wrong.
	 */
	public function run() {
		$ready = $this->getReleaseTimestamp() ?: $this->params['when'];
		$now = (int)ceil( microtime( true ) );
		$title = $this->getTitle();
		if ( $ready <= $now && $title ) {
			if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
				// MW 1.36+
				// @phan-suppress-next-line PhanUndeclaredMethod Not necessarily existing in the current version.
				$success = MediaWikiServices::getInstance()->getWikiPageFactory()
					->newFromTitle( $title )->doPurge();
			} else {
				$success = WikiPage::factory( $title )->doPurge();
			}
		} else {
			// This should only be executed, if the job queue does not support delayed jobs.
			// All we can do in this situation is to purge caches.
			$this->getTitle()->invalidateCache( $ready );
			$success = true;
		}
		return $success;
	}
}
