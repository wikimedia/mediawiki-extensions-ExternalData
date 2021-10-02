<?php
/**
 * Base abstract class for external data connectors that work over HTTP/HTTPS.
 *
 * @author Alexander Mashin
 * @author Yaron Koren
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
			if ( self::isURLAllowed( $url ) ) {
				// Do any special variable replacements in the URLs, for secret API keys and the like.
				global $edgStringReplacements;
				foreach ( $edgStringReplacements as $key => $value ) {
					$url = str_replace( $key, $value, $url );
				}
				$this->realUrl = $url;
			} else {
				// URL not allowed.
				$this->error( 'externaldata-url-not-allowed', $url );
			}
		} else {
			// URL not provided.
			$this->error( 'externaldata-no-param-specified', 'url' );
		}

		// HTTP options.
		global $edgHTTPOptions;
		// TODO: handle HTTP options per site.
		$this->options = $edgHTTPOptions;
		global $edgAllowSSL;
		if ( $edgAllowSSL ) {
			$this->options['sslVerifyCert'] = isset( $this->options['sslVerifyCert'] )
											? $this->options['sslVerifyCert']
											: false;
			$this->options['followRedirects'] = isset( $this->options['followRedirects'] )
											  ? $this->options['followRedirects']
											  : false;
		}

		// Throttling.
		// Throttle key.
		$components = parse_url( $this->realUrl );
		// Second-level domain is likely to be both a throttle key and an index to find a throttle key or interval.
		if ( preg_match( '/(?<=^|\.)\w+\.\w+$/', $components['host'], $matches ) ) {
			$components['2nd_lvl_domain'] = $matches[0];
		} else {
			$components['2nd_lvl_domain'] = $components['host'];
		}
		$components += $this->options;
		if ( $this->headers ) {
			$components += $this->headers;
		}
		global $edgThrottleKey;
		$template = $this->firstSet( $edgThrottleKey,
			$components['host'],
			$components['2nd_lvl_domain'],
			'*',
			0
		);
		// Throttle interval.
		global $edgThrottleInterval;
		$interval = $this->firstSet( $edgThrottleInterval,
			$components['host'],
			$components['2nd_lvl_domain'],
			'*',
			0
		);
		if ( $template && $interval ) {
			$key = $this->substitute( $template, $components );
			$this->setupThrottle( $title, $key, $interval );
		}
	}

	/**
	 * Checks whether this URL is allowed, based on the
	 * $edgAllowExternalDataFrom whitelist
	 *
	 * @param string $url URL to check.
	 *
	 * @return bool True, if allowed; false otherwise.
	 *
	 */
	private static function isURLAllowed( $url ) {
		// this code is based on Parser::maybeMakeExternalImage().
		global $edgAllowExternalDataFrom;
		$data_from = $edgAllowExternalDataFrom;
		if ( empty( $data_from ) ) {
			return true;
		}
		if ( is_array( $data_from ) ) {
			foreach ( $data_from as $match ) {
				if ( strpos( $url, $match ) === 0 ) {
					return true;
				}
			}
			return false;
		} else {
			return strpos( $url, $data_from ) === 0;
		}
	}
}
