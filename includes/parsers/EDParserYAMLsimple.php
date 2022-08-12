<?php
/**
 * Class for YAML parser with simple access.
 *
 * @author Alexander Mashin
 */
class EDParserYAMLsimple extends EDParserJSONsimple {
	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 * @throws EDParserException.
	 *
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );
		if ( !function_exists( 'yaml_parse' ) ) {
			// PECL yaml extension is required.
			throw new EDParserException( 'externaldata-format-unavailable-absolute', 'PECL YAML', 'yaml' );
		}
	}

	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 * @throws EDParserException
	 */
	public function __invoke( $text ) {
		$values = [];
		try {
			$yaml_tree = yaml_parse( $text );
		} catch ( Exception $e ) {
			throw new EDParserException( 'externaldata-invalid-yaml' );
		}
		if ( $yaml_tree === null ) {
			// It's probably invalid JSON.
			throw new EDParserException( 'externaldata-invalid-yaml' );
		}
		// Save the whole YAML tree for Lua.
		$values['__yaml'] = [ $yaml_tree ];
		if ( is_array( $yaml_tree ) ) {
			self::parseTree( $yaml_tree, $values );
		}
		return $values;
	}
}
