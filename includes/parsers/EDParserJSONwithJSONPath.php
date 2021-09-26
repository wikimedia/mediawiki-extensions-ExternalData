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
	 * @param ?array $defaults The initial values.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 */
	public function __invoke( $text, $defaults = [] ) {
		$text = $this->removeTrailingComma( substr( $text, $this->prefixLength ) );
		try {
			$json = new EDJsonObject( $text );
		} catch ( Exception $e ) {
			throw new EDParserException( 'externaldata-invalid-json' );
		}
		// Save the whole JSON tree for Lua.
		$defaults['__json'] = [ $json->complete() ];
		$values = parent::__invoke( $text, $defaults );
		foreach ( $this->external as $jsonpath ) {
			if ( !array_key_exists( $jsonpath, $values ) ) {
				// variable has not been set yet.
				try {
					$values[$jsonpath] = $json->get( $jsonpath );
				} catch ( MWException $e ) {
					throw new EDParserException( 'externaldata-jsonpath-error', $jsonpath );
				}
			}
		}
		return $values;
	}
}
