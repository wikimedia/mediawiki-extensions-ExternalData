<?php
/**
 * Base abstract class for external data connectors that work over HTTP/HTTPS.
 *
 * @author Alexander Mashin
 * @author Yaron Koren
 *
 */
abstract class EDConnectorHttp extends EDConnectorBase {
	/** @var bool $needs_parser True, if the connector needs one of EDParser* objects. */
	protected static $needs_parser = true;
	/** @var string URL to fetch data from as provided by user. */
	protected $original_url;
	/** @var string URL to fetch data from after substitutions. */
	protected $real_url;
	/** @var array HTTP options. */
	protected $options;
	/** @var array HTTP headers. */
	protected $headers;
	/** @var int When the result was actually retched. */
	private $time;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array $args An array of arguments for parser/Lua function.
	 *
	 * @todo handle HTTP options per site.
	 *
	 */
	protected function __construct( array $args ) {
		parent::__construct( $args );

		// Form URL.
		if ( isset( $args['url'] ) ) {
			$url = $args['url'];
			$url = str_replace( ' ', '%20', $url ); // -- do some minor URL-encoding.
			$this->original_url = $url;
			// If the URL isn't allowed (based on a whitelist), exit.
			if ( self::isURLAllowed( $url ) ) {
				// Do any special variable replacements in the URLs, for secret API keys and the like.
				global $edgStringReplacements;
				foreach ( $edgStringReplacements as $key => $value ) {
					$url = str_replace( $key, $value, $url );
				}
				$this->real_url = $url;
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
		$text = false;
		if ( empty( $data_from ) ) {
			return true;
		} elseif ( is_array( $data_from ) ) {
			foreach ( $data_from as $match ) {
				if ( strpos( $url, $match ) === 0 ) {
					return true;
				}
			}
			return false;
		} else {
			if ( strpos( $url, $data_from ) === 0 ) {
				return true;
			} else {
				return false;
			}
		}
	}
}
