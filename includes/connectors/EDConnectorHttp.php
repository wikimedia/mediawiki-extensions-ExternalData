<?php
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * Base abstract class for external data connectors that work over HTTP/HTTPS.
 *
 * @author Alexander Mashin
 * @author Yaron Koren
 * @author Zoran Dori (kizule)
 */
abstract class EDConnectorHttp extends EDConnectorBase {
	use EDConnectorParsable; // needs parser.
	use EDConnectorThrottled; // throttles calls.
	use EDConnectorCached; // uses cache.

	/** @const string ID_PARAM What the specific parameter identifying the connection is called. */
	protected const ID_PARAM = 'url';

	/** @const string CONTENT_TYPE_REGEX Structure of Content-Type header. */
	private const CONTENT_TYPE_REGEX
		= '^(?<type>[^\s/]+)(?:/(?<subtype>[^\s;]+))?(?:;\s*charset\s*=\s*(?<charset>[^;\s]+))?';

	/** @var string URL to fetch data from as provided by user. */
	protected $originalUrl;
	/** @var string URL to fetch data from after substitutions. */
	protected $realUrl;
	/** @var string HTTP method. */
	protected static $method = 'GET';
	/** @var array HTTP options. */
	protected $options;
	/** @var array HTTP headers. */
	protected $headers;

	/** @var int $maxTries How many times to try an HTTP request. */
	private $maxTries = 3;
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
		// Parser.
		$this->prepareParser( $args );
		$this->error( $this->parseErrors );

		parent::__construct( $args, $title );

		if ( isset( $args['max tries'] ) ) {
			$this->maxTries = (int)$args['max tries'];
		}

		// HTTP options.
		$this->options = $args['options'] ?? [];
		global $wgHTTPTimeout;
		$this->options['HTTPTimeout'] = $this->options['HTTPTimeout'] ?? $wgHTTPTimeout;
		global $wgHTTPConnectTimeout;
		$this->options['HTTPConnectTimeout'] = $this->options['HTTPConnectTimeout'] ?? $wgHTTPConnectTimeout;
		if ( isset( $args['allow ssl'] ) ) {
			$this->options['sslVerifyCert'] = $this->options['sslVerifyCert'] ?? false;
			$this->options['followRedirects'] = $this->options['followRedirects'] ?? false;
		}

		// Throttling.
		if ( isset( $args['throttle key'] ) && isset( $args['throttle interval'] ) ) {
			$this->setupThrottle( $title, $args['throttle key'], $args['throttle interval'] );
		}

		// Cache.
		// Cache expiration.
		$cache_expires_local = $args['cache seconds'] ?? 0;
		$cache_expires_global = $args['min cache seconds'] ?? 0;
		$cache_expires = max( $cache_expires_local, $cache_expires_global );
		// Allow using stale cache.
		$allow_stale_cache = array_key_exists( 'use stale cache', $args )
			|| array_key_exists( 'always use stale cache', $args );
		$this->setupCache( $cache_expires, $allow_stale_cache );

		// Form URL.
		$url = $args[self::ID_PARAM] ?? null;
		if ( !$url ) {
			return; // further work is impossible without a URL.
		}
		$url = str_replace( ' ', '%20', $url ); // -- do some minor URL-encoding.
		$this->originalUrl = $url;
		// If the URL isn't allowed (based on a whitelist), exit.
		$allowed_urls = $args['allowed urls'] ?? null;
		if ( self::isURLAllowed( $url, $allowed_urls ) ) {
			// Do any special variable replacements in the URLs, for secret API keys and the like.
			$this->realUrl = self::realUrl( $url, $args['replacements'] ?? null );
		} else {
			// URL not allowed.
			$this->error( 'externaldata-url-not-allowed', $url );
		}
	}

	/**
	 * Convert visible URL to real one.
	 * @param string $url
	 * @param array|null $replacements
	 * @return string
	 */
	protected static function realUrl( string $url, ?array $replacements ): string {
		// Do any special variable replacements in the URLs, for secret API keys and the like.
		if ( $replacements ) {
			foreach ( $replacements as $key => $value ) {
				$url = str_replace( $key, $value, $url );
			}
		}
		return $url;
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
				$hook_container = MediaWikiServices::getInstance()->getHookContainer();
				$hook_result = $hook_container->run(
					$hook_name,
					[ static::$method, &$url, &$options, &$errors ],
					[]
				);
				if ( !$hook_result ) {
					$this->error( 'externaldata-url-hooks-aborted', $hook_name, implode( ', ', $errors ) );
					return false;
				}
				do {
					// Actually send a request.
					// Late binding; fetcher() is pure virtual. Also sets $this->headers.
					$contents = $this->fetcher( $url, $options );
				} while ( !$contents && ++$this->tries <= $this->maxTries );
				// Encoding needs to be detected from HTTP headers this early and not later,
				// during text parsing, so that the converted text may be cached.
				// HTTP headers are not cached, therefore, they are not available,
				// if the text is fetched from the cache.
				return $contents ? $this->applyEncodingFromHeaders( $contents ) : $contents;
			}, $url, $options );
		}, $this->realUrl, $this->options );

		if ( $contents ) {
			$contents = $this->convert2Utf8( $contents );
			$postprocessor = $this->postprocessor;
			if ( $postprocessor ) {
				$contents = $postprocessor( $contents, $this->params, $this->options['postData'] );
			}
			$this->add( $this->parse( $contents, parse_url( $this->realUrl, PHP_URL_PATH ) ) );
			// Fill standard external variables.
			$this->addSpecial( '__time', $this->time );
			$this->addSpecial( '__cached', $this->cached );
			$this->addSpecial( '__stale', !$this->cacheFresh );
			$this->addSpecial( '__tries', $this->tries );
			if ( $this->waitTill ) {
				// Throttled, but there was a cached value.
				$this->addSpecial( '__throttled_till', $this->waitTill );
			}
			$this->error( $this->parseErrors );
			return !$this->errors();
		} else {
			// Nothing to serve.
			if ( $this->waitTill ) {
				// It was throttled, and there was no cached value.
				$this->error( 'externaldata-throttled', $this->originalUrl, (string)(int)ceil( $this->waitTill ) );
			} else {
				// It wasn't throttled; just could not get it.
				$this->error( 'externaldata-db-could-not-get-url', $this->originalUrl, (string)$this->maxTries );
			}
			return false;
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
	private static function isURLAllowed( string $url, $allowed ) {
		// this code is based on Parser::maybeMakeExternalImage().
		if ( !$allowed ) {
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
	 * @see MediaWiki\Http\HttpRequestFactory::request() /
	 * Only difference - $options variable and also have value 'headers', and would append to request before sending.
	 *
	 * @param string $method HTTP request method: 'GET' or 'POST'.
	 * @param string $url URL to fetch.
	 * @param array $options HTTP options.
	 * @param bool $suppress Whether to suppress error details sent by server.
	 * @param string $caller Calling function.
	 *
	 * @return array [ Fetched text, HTTP response headers, [ Errors ] ].
	 *
	 * @todo Also return error report.
	 */
	protected static function request(
		string $method,
		string $url,
		array $options,
		bool $suppress,
		string $caller = __METHOD__
	): array {
		wfDebug( "HTTP: $method: $url\n" );

		$options['method'] = strtoupper( $method );

		// Create an HTTP request object.
		$factory = MediaWikiServices::getInstance()->getHttpRequestFactory();
		$req = $factory->create( $url, $options, $caller );

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
			if ( !$suppress && $req->getContent() ) {
				// Add additional information about the error that the server may have provided.
				$errors[] = '<pre>' . $req->getContent() . '</pre>';
			}
			$logger = LoggerFactory::getInstance( 'http' );
			$logger->warning( Status::wrap( $status )->getWikiText( false, false, 'en' ),
				[ 'error' => $errors, 'caller' => $caller, 'content' => $req->getContent() ] );
			return [ null, null, $errors ];
		}
	}

	/**
	 * Return content type, subtype and encoding based on HTTP headers.
	 * @param array $headers HTTP headers.
	 * @return array [ content type, subtype, encoding ].
	 */
	private static function encodingFromHeaders( array $headers ): array {
		if ( $headers && isset( $headers['content-type'] ) ) {
			$header = strtolower( is_array( $headers['content-type'] )
				? implode( ',', $headers['content-type'] )
				: $headers['content-type'] );
			if ( preg_match( '~' . self::CONTENT_TYPE_REGEX . '~', $header, $match, PREG_UNMATCHED_AS_NULL ) ) {
				return [ $match['type'], $match['subtype'], $match['charset'] ];
			}
		}
		return [ null, null, null ];
	}

	/**
	 * Convert encoding to HTTP, using charset info from the HTTP header 'Content-type';
	 * but only if there is no <meta> tag with charset, and if this is a text.
	 * @param string $text Test to convert to UTF-8.
	 * @return string Text converted to UTF-8.
	 */
	private function applyEncodingFromHeaders( string $text ): string {
		[ $type, $subtype, $charset ] = self::encodingFromHeaders( $this->headers );
		if ( // There is charset in the headers but not in the <html>.
			$charset &&
			!preg_match( '%<meta\s+charset\s*=\s*"(?<charset>[^"]+)"\s*/?>%i', $text ) &&
			!preg_match(
				'~<meta\s+http-equiv\s*=\s*"Content-Type"\s*content\s*=\s*"' . self::CONTENT_TYPE_REGEX . '"\s*/>~i',
				$text
			)
		) {
			// Recoding ought to be done now, for there will be no data for it in the cached value.
			$text = mb_convert_encoding( $text, 'UTF-8', $charset );
		}
		return $text;
	}

	/**
	 * Convert encoding to HTTP, using charset info from the text.
	 * @param string $text Test to convert.
	 * @return string Converted text.
	 */
	protected function convert2Utf8( $text ): string {
		return $text && $this->encoding
			? $this->toUTF8( $text, $this->encoding )
			: $text;
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
