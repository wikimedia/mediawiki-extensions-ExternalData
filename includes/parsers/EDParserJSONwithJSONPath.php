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
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 */
	public function __invoke( $text ) {
		$text = $this->removeTrailingComma( substr( $text, $this->prefixLength ) );
		try {
			$json = new EDJsonObject( $text );
		} catch ( Exception $e ) {
			throw new EDParserException( 'externaldata-invalid-json' );
		}
		$values = parent::__invoke( $text );
		// Save the whole JSON tree for Lua.
		$values['__json'] = [ $json->complete() ];
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
