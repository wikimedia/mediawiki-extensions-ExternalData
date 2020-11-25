<?php
/**
 * Class implementing {{#get_web_data:}} and mw.ext.externalData.getWebData.
 *
 * @author Alexander Mashin
 * @author Yaron Koren
 *
 */
class EDConnectorWeb extends EDConnectorGet {
	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array $args An array of arguments for parser/Lua function.
	 */
	public function __construct( array $args ) {
		parent::__construct( $args );
	}

	/**
	 * Fetch the web data. Sets HTTP headers.
	 *
	 * @return string|null Fetched text.
	 */
	protected function fetcher() {
		// We do not want to repeat error messages self::$tries times.
		static $log_errors = true;
		[ $result, $this->headers, $errors ] = EDHttpWithHeaders::get( $this->real_url, $this->options, __METHOD__ );
		if ( $errors && $log_errors ) {
			$this->error( 'externaldata-url-not-fetched', $this->original_url );
			foreach ( $errors as $error ) {
				if ( is_array( $error ) && isset( $error['message'] ) && isset( $error['params'] ) ) {
					$this->error( $error['message'], $error['params'] );    // -- MW message.
				} else {
					$this->error( 'externaldata-url-not-fetched', $error ); // -- plain string.
				}
			}
			$log_errors = false; // once is enough.
		}
		return $result;
	}
}
