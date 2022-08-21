<?php
/**
 * Class for parsing JSON addressed with JSONPath.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */

class EDParserJSONwithJSONPath extends EDParserJSON {
	/** @var bool $keepExternalVarsCase Whether external variables' names are case-sensitive for this format. */
	public $keepExternalVarsCase = true;

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );

		// This connector needs an explicit set of fields.
		if ( !array_key_exists( 'data', $params ) ) {
			throw new EDParserException( 'externaldata-no-param-specified', 'data' );
		}
	}

	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 * @param string|null $path URL or filesystem path that may be relevant to the parser.
	 * @return array A two-dimensional column-based array of the parsed values.
	 * @throws EDParserException
	 */
	public function __invoke( $text, $path = null ): array {
		$text = $this->removeTrailingComma( substr( $text, $this->prefixLength ) );
		try {
			$json = new EDJsonObject( $text );
		} catch ( Exception $e ) {
			throw new EDParserException( 'externaldata-invalid-format', self::NAME );
		}
		$values = $this->extractJsonPaths( $json );
		// Save the whole JSON tree for Lua.
		$values['__json'] = [ $json->complete() ];
		return $values;
	}

	/**
	 * Extract only the required JSONpaths.
	 * @param EDJsonObject $json
	 * @return array
	 * @throws EDParserException
	 */
	protected function extractJsonPaths( EDJsonObject $json ): array {
		$values = [];
		foreach ( $this->external as $jsonpath ) {
			if ( substr( $jsonpath, 0, 2 ) !== '__' && !array_key_exists( $jsonpath, $values ) ) {
				// variable has not been set yet.
				try {
					$json_values = $json->get( $jsonpath );
				} catch ( MWException $e ) {
					throw new EDParserException( 'externaldata-jsonpath-error', $jsonpath );
				}
				// EDJsonObject::get() returns false if values are not found, array otherwise.
				if ( $json_values !== false ) {
					$values[$jsonpath] = $json_values;
				}
			}
		}
		return $values;
	}
}
