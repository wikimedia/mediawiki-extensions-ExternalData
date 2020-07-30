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
	/** @var array $post_data POST data to send */
	private $post_data;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array $args An array of arguments for parser/Lua function.
	 *
	 */
	public function __construct( array $args ) {
		parent::__construct( $args );
		$this->post_data = $args['post data'];
		// Merge POST data from $$edgHTTPOptions['postData'] and |post data parameter.
		$post_options = array_merge( isset( $this->options['postData'] ) ? $this->options['postData'] : [], $this->post_data );
		// Allow extensions or LocalSettings.php to alter HTTP options.
		Hooks::run( 'ExternalDataBeforeWebCall', [ 'post', $this->real_url, $post_options ] );
	}

	/**
	 * Actually connect to the external data source.
	 * It is presumed that there are no errors in parameters and wiki settings.
	 * Set $this->values and $this->errors.
	 *
	 * @return bool True on success, false if error were encountered.
	 */
	public function run() {
		list( $contents, $this->headers, $errors ) = EDHttpWithHeaders::post( $url,  $post_options );
		if ( !$contents ) {
			$this->error( 'externaldata-post-failed', $this->original_url, implode( ', ', $errors ) );
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
		return true;
	}
}
