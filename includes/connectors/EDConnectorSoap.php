<?php

/**
 * Class implementing {{#get_soap_data:}} and mw.ext.externalData.getSoapData.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */
class EDConnectorSoap extends EDConnectorHttp {
	/** @var bool $keepExternalVarsCase External variables' case ought to be preserved. */
	public $keepExternalVarsCase = true;

	/** @var string SOAP request name. */
	private $requestName;
	/** @var array SOAP request data. */
	private $requestData;
	/** @var string SOAP response name. */
	private $responseName;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		// Check for SOAP-specific errors.
		if ( !class_exists( 'SoapClient' ) ) {
			$this->error(
				'externaldata-missing-library',
				'SOAP',
				'#get_soap_data',
				'mw.ext.getExternalData.getSoapData'
			);
		}
		if ( array_key_exists( 'request', $args ) ) {
			$this->requestName = $args['request'];
		} else {
			$this->error( 'externaldata-no-param-specified', 'request' );
		}
		$this->requestData = array_key_exists( 'requestData', $args )
							? self::paramToArray( $args['requestData'] )
							: [];
		if ( array_key_exists( 'response', $args ) ) {
			$this->responseName = $args['response'];
		} else {
			$this->error( 'externaldata-no-param-specified', 'response' );
		}
	}

	/**
	 * Fetch the SOAP data.
	 *
	 * @param string $url URL to fetch.
	 * @param array $options HTTP options (unused).
	 * @return string|null Text content fetched.
	 */
	protected function fetcher( $url, array $options ) {
		// We do not want to repeat error messages self::$tries times.
		static $log_errors_client = true;
		static $log_errors_request = true;
		// Suppress warnings.
		self::suppressWarnings();
		try {
			// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension
			$client = new SoapClient( $url, [ 'trace' => true ] );
		} catch ( Exception $e ) {
			if ( $log_errors_client ) {
				$this->error( 'externaldata-caught-exception-soap', $e->getMessage() );
			}
			$log_errors_client = false;	// once is enough.
			return null;
		}
		// Restore warnings.
		self::restoreWarnings();
		$request = $this->requestName;
		try {
			$result = $client->$request( $this->requestData );
		} catch ( Exception $e ) {
			if ( $log_errors_request ) {
				$this->error( 'externaldata-caught-exception-soap', $e->getMessage() );
			}
			$log_errors_request = false; // once is enough.
			return null;
		}
		if ( $result ) {
			// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension
			$this->headers = self::headers( $client->__getLastResponseHeaders() );
			$response = $this->responseName;
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
