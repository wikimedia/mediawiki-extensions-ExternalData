<?php
/**
 * Class implementing {{#get_web_data:}} and mw.ext.externalData.getWebData.
 *
 * @author Alexander Mashin
 * @author Yaron Koren
 *
 */
class EDConnectorWeb extends EDConnectorHttp {
	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	public function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		$this->options['postData']
			= isset( $args['post data'] ) ? $args['post data']
			: ( isset( $this->options['postData'] ) ? $this->options['postData'] : null );

		self::$method = $this->options['postData'] ? 'POST' : 'GET';
	}

	/**
	 * Fetch the web data. Sets HTTP headers.
	 *
	 * @param string $url URL to fetch.
	 * @param array $options HTTP options.
	 * @return string|null Fetched text.
	 */
	protected function fetcher( $url, array $options ) {
		// We do not want to repeat error messages self::$tries times.
		static $log_errors = true;
		[ $result, $this->headers, $errors ] = self::request( static::$method, $url, $options, __METHOD__ );
		if ( $errors && $log_errors ) {
			$this->error( 'externaldata-url-not-fetched', $this->originalUrl );
			foreach ( $errors as $error ) {
				if ( is_array( $error ) && isset( $error['message'] ) && isset( $error['params'] ) ) {
					$this->error( $error['message'], $error['params'] ); // -- MW message.
				} else {
					$this->error( 'externaldata-url-not-fetched', (string)$error ); // -- plain string.
				}
			}
			$log_errors = false; // once is enough.
		}
		return trim( $result );
	}
}
