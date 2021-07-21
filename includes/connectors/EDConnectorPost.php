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
	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 */
	protected function __construct( array &$args ) {
		parent::__construct( $args );
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
		// Allow extensions or LocalSettings.php to alter HTTP options.
		Hooks::run( 'ExternalDataBeforeWebCall', [ 'post', $this->realUrl, $this->options ] );
		[ $contents, $this->headers, $errors ] = EDHttpWithHeaders::post( $this->realUrl, $this->options );
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
		$contents = EDEncodingConverter::toUTF8( $contents, $this->encoding );
		$this->values = $this->parse( $contents, [
			'__time' => [ time() ],
			'__stale' => [ false ],
			'__tries' => [ 1 ]
		] );
		return !$this->errors();
	}
}
