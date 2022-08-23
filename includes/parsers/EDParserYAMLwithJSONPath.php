<?php
/**
 * Class for parsing YAML addressed with JSONPath.
 *
 * @author Alexander Mashin
 */
class EDParserYAMLwithJSONPath extends EDParserJSONwithJSONPath {
	/** @const string NAME The name of this format. */
	public const NAME = 'YAML';
	/** @const int GENERICITY The greater, the more this format is likely to succeed on a random input. */
	public const GENERICITY = 5;

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 * @throws EDParserException
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
	 * @param string|null $path URL or filesystem path that may be relevant to the parser.
	 * @return array A two-dimensional column-based array of the parsed values.
	 * @throws EDParserException
	 */
	public function __invoke( $text, $path = null ): array {
		try {
			$yaml_tree = yaml_parse( $text );
		} catch ( Exception $e ) {
			throw new EDParserException( 'externaldata-invalid-format', self::NAME, $e->getMessage() );
		}
		if ( $yaml_tree === null ) {
			// It's probably invalid JSON.
			throw new EDParserException( 'externaldata-invalid-format', self::NAME );
		}
		try {
			$json = new EDJsonObject( $yaml_tree );
		} catch ( MWException $e ) {
			throw new EDParserException( 'externaldata-invalid-format', self::NAME, $e->getMessage() );
		}
		$values = $this->extractJsonPaths( $json );
		// Save the whole YAML tree for Lua.
		$values['__yaml'] = [ $yaml_tree ];
		return $values;
	}
}
