<?php
/**
 * Class implementing {{#get_web_data:}} and mw.ext.externalData.getWebData
 * sending POST web request.
 *
 * @author Alexander Mashin
 * @author Yaron Koren
 *
 */
class EDConnectorPost extends EDConnectorHttp {
	use EDConnectorThrottled; // throttles calls.

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		$this->options['postData']
			= isset( $args['post data'] ) ? $args['post data']
			: ( isset( $this->options['postData'] ) ? $this->options['postData'] : null );
	}

	/**
	 * Actually connect to the external data source.
	 * It is presumed that there are no errors in parameters and wiki settings.
	 * Set $this->values and $this->errors.
	 *
	 * @return bool True on success, false if error were encountered.
	 */
	public function run() {
		$contents = $this->callThrottled( function ( $url, array $options ) /* $this is bound */ {
			// Allow extensions or LocalSettings.php to alter HTTP options.
			Hooks::run( 'ExternalDataBeforeWebCall', [ 'post', $url, $options ] );
			[ $contents, $this->headers, $errors ] = EDHttpWithHeaders::post( $url, $options );
			if ( !$contents ) {
				if ( is_array( $errors ) ) {
					$errors = implode( ',', $errors );
				}
				$this->error( 'externaldata-post-failed', $this->originalUrl, $errors );
				return false;
			}

			// Encoding needs to be detected from HTTP headers this early and not later,
			// during text parsing, so that the converted text may be cached.
			// Try HTTP headers.
			if ( !$this->encoding ) {
				$this->encoding = EDEncodingConverter::fromHeaders( $this->headers );
			}
			return EDEncodingConverter::toUTF8( $contents, $this->encoding );
		}, $this->realUrl, $this->options );
		if ( $contents ) {
			// Parse.
			$this->values = $this->parse( $contents, [
				'__time' => [ time() ],
				'__stale' => [ false ],
				'__tries' => [ 1 ]
			] );
			$this->error( $this->parseErrors );
		} else {
			// Nothing to serve.
			if ( $this->waitTill ) {
				// It was throttled.
				$this->error( 'externaldata-throttled', $this->originalUrl, (int)ceil( $this->waitTill ) );
			}
		}

		return !$this->errors();
	}
}
