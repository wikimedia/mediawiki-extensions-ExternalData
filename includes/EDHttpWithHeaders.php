<?php
use MediaWiki\Logger\LoggerFactory;

class EDHttpWithHeaders extends Http {
	/**
	 * @see Http::request()
	 * Only diffrence - $options variable an also have value 'headers', and would append to request before sending
	 *
	 * @param string $method HTTP reuest method.
	 * @param string $url URL to fetch.
	 * @param array $options HTTP options.
	 * @param string $caller Calling function.
	 *
	 * @return array [ Fetched text, HTTP response headers, [ Errors ] ].
	 *
	 * @todo Also return error report.
	 */
	public static function request( $method, $url, $options = [], $caller = __METHOD__ ) {
		wfDebug( "HTTP: $method: $url\n" );

		$options['method'] = strtoupper( $method );

		if ( !isset( $options['timeout'] ) ) {
			$options['timeout'] = 'default';
		}
		if ( !isset( $options['connectTimeout'] ) ) {
			$options['connectTimeout'] = 'default';
		}

		$req = MWHttpRequest::factory( $url, $options, $caller );
		if ( isset( $options['headers'] ) ) {
			foreach ( $options['headers'] as $headerName => $headerValue ) {
				$req->setHeader( $headerName, $headerValue );
			}
		}
		try {
			$status = $req->execute();
		} catch ( Exception $e ) {
			$logger = LoggerFactory::getInstance( 'http' );
			$logger->warning( 'Exception from ' . $caller . ' (' . $e->getMessage() . ')',
				[ 'error' => $e->getMessage(), 'caller' => $caller, 'content' => $req->getContent() ] );
			return [ null, null, [ 'Exception: ' . $e->getMessage() ] ];
		}

		if ( $status->isOK() ) {
			return [ $req->getContent(), $req->getResponseHeaders(), null ];
		} else {
			$errors = $status->getErrorsByType( 'error' );
			$logger = LoggerFactory::getInstance( 'http' );
			$logger->warning( Status::wrap( $status )->getWikiText( false, false, 'en' ),
				[ 'error' => $errors, 'caller' => $caller, 'content' => $req->getContent() ] );
			return [ null, null, $errors ];
		}
	}

	/**
	 * Simple wrapper for Http::request( 'POST' )
	 * this is copy of Http::post, the only reason to redeclare it is because Http calls Http::request
	 * instead of self::request
	 * @see Http::request()
	 *
	 * @param string $url
	 * @param array $options
	 * @param string $caller The method making this request, for profiling
	 * @return array [ Fetched text, HTTP response headers, [ Errors ] ].
	 */
	public static function post( $url, $options = [], $caller = __METHOD__ ) {
		return self::request( 'POST', $url, $options, $caller );
	}

	/**
	 * Simple wrapper for Http::request( 'GET' )
	 * this is copy of Http::get, the only reason to redeclare it is because Http calls Http::request
	 * instead of self::request
	 * @see Http::request()
	 * @since 1.25 Second parameter $timeout removed. Second parameter
	 * is now $options which can be given a 'timeout'
	 *
	 * @param string $url
	 * @param array $options
	 * @param string $caller The method making this request, for profiling
	 * @return array [ Fetched text, HTTP response headers, [ Errors ] ].
	 */
	public static function get( $url, $options = [], $caller = __METHOD__ ) {
		$args = func_get_args();
		if ( isset( $args[1] ) && ( is_string( $args[1] ) || is_numeric( $args[1] ) ) ) {
			// Second was used to be the timeout
			// And third parameter used to be $options
			wfWarn( "Second parameter should not be a timeout.", 2 );
			$options = isset( $args[2] ) && is_array( $args[2] ) ?
				$args[2] : [];
			$options['timeout'] = $args[1];
			$caller = __METHOD__;
		}
		return self::request( 'GET', $url, $options, $caller );
	}
}
