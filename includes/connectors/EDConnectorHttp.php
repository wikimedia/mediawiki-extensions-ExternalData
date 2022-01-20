<?php
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

/**
 * Base abstract class for external data connectors that work over HTTP/HTTPS.
 *
 * @author Alexander Mashin
 * @author Yaron Koren
 * @author Zoran Dori (kizule)
 *
 */
abstract class EDConnectorHttp extends EDConnectorBase {
	use EDConnectorParsable; // needs parser.
	use EDConnectorThrottled; // throttles calls.

	/** @var string URL to fetch data from as provided by user. */
	protected $originalUrl;
	/** @var string URL to fetch data from after substitutions. */
	protected $realUrl;
	/** @var array HTTP options. */
	protected $options;
	/** @var array HTTP headers. */
	protected $headers;

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

		// Form URL.
		if ( isset( $args['url'] ) ) {
			$url = $args['url'];
			$url = str_replace( ' ', '%20', $url ); // -- do some minor URL-encoding.
			$this->originalUrl = $url;
			// If the URL isn't allowed (based on a whitelist), exit.
			$allowed_urls = isset( $args['allowed urls'] ) ? $args['allowed urls'] : null;
			if ( self::isURLAllowed( $url, $allowed_urls ) ) {
				// Do any special variable replacements in the URLs, for secret API keys and the like.
				if ( isset( $args['replacements'] ) ) {
					foreach ( $args['replacements'] as $key => $value ) {
						$url = str_replace( $key, $value, $url );
					}
				}
				$this->realUrl = $url;
			} else {
				// URL not allowed.
				$this->error( 'externaldata-url-not-allowed', $url );
			}
		} else {
			// URL not provided.
			$this->error( 'externaldata-no-param-specified', 'url' );
			return; // no need to continue.
		}

		// HTTP options.
		$this->options = isset( $args['options'] ) ? $args['options'] : [];
		// @TODO inject into data sources.
		global $wgHTTPTimeout;
		$this->options['HTTPTimeout'] = isset( $this->options['HTTPTimeout'] )
			? $this->options['HTTPTimeout']
			: $wgHTTPTimeout;
		global $wgHTTPConnectTimeout;
		$this->options['HTTPConnectTimeout'] = isset( $this->options['HTTPConnectTimeout'] )
			? $this->options['HTTPConnectTimeout']
			: $wgHTTPConnectTimeout;
		if ( isset( $args['allow ssl'] ) ) {
			$this->options['sslVerifyCert'] = isset( $this->options['sslVerifyCert'] )
											? $this->options['sslVerifyCert']
											: false;
			$this->options['followRedirects'] = isset( $this->options['followRedirects'] )
											  ? $this->options['followRedirects']
											  : false;
		}

		// Throttling.
		$template = $args['throttle key'];
		$interval = $args['throttle interval'];
		if ( $template && $interval ) {
			$key = $this->substitute( $template, $args['components'] );
			$this->setupThrottle( $title, $key, $interval );
		}
	}

	/**
	 * Checks whether this URL is allowed, based on the 'allowed urls' whitelist
	 *
	 * @param string $url URL to check.
	 * @param string[]|string $allowed An array of allowed URLs.
	 *
	 * @return bool True, if allowed; false otherwise.
	 *
	 * @todo Rethink.
	 */
	private static function isURLAllowed( $url, $allowed ) {
		// this code is based on Parser::maybeMakeExternalImage().
		if ( empty( $allowed ) ) {
			return true;
		}
		if ( is_array( $allowed ) ) {
			foreach ( $allowed as $match ) {
				if ( strpos( $url, $match ) === 0 ) {
					return true;
				}
			}
			return false;
		} else {
			return strpos( $url, $allowed ) === 0;
		}
	}

	/**
	 * @see Http::request() /
	 * Only difference - $options variable and also have value 'headers', and would append to request before sending.
	 *
	 * @param string $method HTTP request method: 'GET' or 'POST'.
	 * @param string $url URL to fetch.
	 * @param array $options HTTP options.
	 * @param string $caller Calling function.
	 *
	 * @return array [ Fetched text, HTTP response headers, [ Errors ] ].
	 *
	 * @todo Also return error report.
	 */
	protected static function request( $method, $url, array $options, $caller = __METHOD__ ): array {
		wfDebug( "HTTP: $method: $url\n" );

		$options['method'] = strtoupper( $method );

		// Create an HTTP request object.
		if ( class_exists( '\MediaWiki\Http\HttpRequestFactory' ) ) {
			// MW 1.31+
			$factory = MediaWikiServices::getInstance()->getHttpRequestFactory();
			$req = $factory->create( $url, $options, $caller );
		} elseif ( class_exists( 'MWHttpRequest' ) ) {
			$req = MWHttpRequest::factory( $url, $options, $caller );
		}

		if ( isset( $options['headers'] ) ) {
			foreach ( $options['headers'] as $name => $value ) {
				$req->setHeader( $name, $value );
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
	 * Return content type, subtype and encoding based on HTTP headers.
	 *
	 * @param array $headers HTTP headers.
	 *
	 * @return array [ content type, encoding ].
	 */
	private static function fromHeaders( array $headers ): array {
		if ( $headers && isset( $headers['content-type'] ) ) {
			$header = strtolower( is_array( $headers['content-type'] )
				? implode( ',', $headers['content-type'] )
				: $headers['content-type'] );
			$regex = '~^(?<type>[^\s/]+)(?:/(?<subtype>[^\s;]+))?(?:;\s*charset\s*=\s*(?<charset>[^;\s]+))?~';

			if ( preg_match( $regex, $header, $match, PREG_UNMATCHED_AS_NULL ) ) {
				return [ $match['type'], $match['subtype'], $match['charset'] ];
			}
		}
		return [ null, null, null ];
	}

	/**
	 * Convert encoding to HTTP, using charset info from the HTTP header 'Content-type', but only if this is a text.
	 *
	 * @param string $text Test to convert.
	 *
	 * @return string Converted text.
	 */
	protected function convert2Utf8( $text ) {
		$type = 'text';
		// Try HTTP headers.
		if ( $text && !$this->encoding && $this->headers ) {
			[ $type, $subtype, $this->encoding ] = self::fromHeaders( $this->headers );
		}
		return $text && $type === 'text' && $this->encoding
			? $this->toUTF8( $text, $this->encoding )
			: $text;
	}
}
