<?php
/**
 * Class for YAML parser with simple access.
 *
 * @author Alexander Mashin
 */
class EDParserYAMLsimple extends EDParserJSONsimple {
	/** @const string NAME The name of this format. */
	public const NAME = 'YAML';
	/** @const array EXT The usual file extensions of this format. */
	protected const EXT = [ 'yaml', 'yml' ];
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
		// This is important for the right choice of format, if it is "auto".
		if ( array_key_exists( 'use jsonpath', $params ) ) {
			throw new EDParserException( 'dummy message' );
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
		$values = [];
		self::throwWarnings();
		try {
			$yaml_tree = yaml_parse( $text );
		} catch ( Exception $e ) {
			throw new EDParserException( 'externaldata-invalid-format', self::NAME );
		} finally {
			self::stopThrowingWarnings();
		}
		if ( $yaml_tree === null ) {
			// It's probably invalid JSON.
			throw new EDParserException( 'externaldata-invalid-format', self::NAME );
		}
		if ( is_array( $yaml_tree ) ) {
			self::parseTree( $yaml_tree, $values );
			// Save the whole YAML tree for Lua.
			$values['__yaml'] = [ $yaml_tree ];
		}
		return $values;
	}
}
