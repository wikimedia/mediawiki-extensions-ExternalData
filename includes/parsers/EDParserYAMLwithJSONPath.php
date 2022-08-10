<?php
/**
 * Class for parsing YAML addressed with JSONPath.
 *
 * @author Alexander Mashin
 */
class EDParserYAMLwithJSONPath extends EDParserJSONwithJSONPath {
	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 */
	public function __invoke( $text ) {
		try {
			$yaml_tree = yaml_parse( $text );
		} catch ( Exception $e ) {
			throw new EDParserException( 'externaldata-invalid-yaml' );
		}
		if ( $yaml_tree === null ) {
			// It's probably invalid JSON.
			throw new EDParserException( 'externaldata-invalid-yaml' );
		}
		$values = $this->extractJsonPaths( new EDJsonObject( $yaml_tree ) );
		// Save the whole YAML tree for Lua.
		$values['__yaml'] = [ $yaml_tree ];
		return $values;
	}
}
