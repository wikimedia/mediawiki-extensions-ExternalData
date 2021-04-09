<?php
/**
 * Class implementing {{#get_soap_data:}} and mw.ext.externalData.getSoapData.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */
class EDConnectorSoap extends EDConnectorGet {
	/** @var bool $preserve_external_variables_case External variables' case ought to be preserved. */
	protected static $preserve_external_variables_case = true;

	/** @var string SOAP request name. */
	private $request_name;
	/** @var array SOAP request data. */
	private $request_data;
	/** @var string SOAP response name. */
	private $response_name;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array $args An array of arguments for parser/Lua function.
	 *
	 */
	public function __construct( array $args ) {
		parent::__construct( $args );

		// Check for SOAP-specific errors.
		if ( !class_exists( 'SoapClient' ) ) {
			$this->error(
				'externaldata-missing-library',
				'SOAP',
				'{{#get_soap_data:}}',
				'mw.ext.getExternalData.getSoapData'
			);
		}
		if ( array_key_exists( 'request', $args ) ) {
			$this->request_name = $args['request'];
		} else {
			$this->error( 'externaldata-no-param-specified', 'request' );
		}
		$this->request_data = array_key_exists( 'requestData', $args )
							? self::paramToArray( $args['requestData'] )
							: [];
		if ( array_key_exists( 'response', $args ) ) {
			$this->response_name = $args['response'];
		} else {
			$this->error( 'externaldata-no-param-specified', 'response' );
		}
	}

	/**
	 * Fetch the SOAP data.
	 *
	 * @return string|null Text content fetched.
	 */
	protected function fetcher() {
		// We do not want to repeat error messages self::$tries times.
		static $log_errors_client = true;
		static $log_errors_request = true;
		// Suppress warnings.
		if ( method_exists( \Wikimedia\AtEase\AtEase::class, 'suppressWarnings' ) ) {
			// MW >= 1.33
			\Wikimedia\AtEase\AtEase::suppressWarnings();
		} else {
			\MediaWiki\suppressWarnings();
		}
		try {
			$client = new SoapClient( $this->real_url, [ 'trace' => true ] );
		} catch ( Exception $e ) {
			if ( $log_errors_client ) {
				$this->error( 'externaldata-caught-exception-soap', $e->getMessage() );
			}
			$log_errors_client = false;	// once is enough.
			return null;
		}
		// Restore warnings.
		if ( method_exists( \Wikimedia\AtEase\AtEase::class, 'restoreWarnings' ) ) {
			// MW >= 1.33
			\Wikimedia\AtEase\AtEase::restoreWarnings();
		} else {
			\MediaWiki\restoreWarnings();
		}
		$request = $this->request_name;
		try {
			$result = $client->$request( $this->request_data );
		} catch ( Exception $e ) {
			if ( $log_errors_request ) {
				$this->error( 'externaldata-caught-exception-soap', $e->getMessage() );
			}
			$log_errors_request = false; // once is enough.
			return null;
		}
		if ( $result ) {
			$this->headers = self::headers( $client->__getLastResponseHeaders() );
			$response = $this->response_name;
			return $result->$response;
		}
	}

	/**
	 * Parse the string of HTTP headers.
	 *
	 * @param string $str A string of headers.
	 *
	 * @return array Parsed headers.
	 */
	private static function headers( $str ) {
		preg_match_all( '/^(?<header>.+?):\s(?<value>.+)$/m', $str, $matches, PREG_SET_ORDER );
		$headers = [];
		foreach ( $matches as $match ) {
			$headers[strtolower( $match['header'] )] = $match['value'];
		}
		return $headers;
	}
}
