<?php
/**
 * Base abstract class for text parsers.
 * Includes a factory method that analyses parameters and instantiates needed descendant class.
 *
 * @author Alexander Mashin
 *
 */
abstract class EDParserBase {
	use EDParsesParams;			// Needs paramToArray().

	/** @const string NAME The name of this format. */
	public const NAME = '';
	/** @const array EXT The usual file extensions of this format. */
	protected const EXT = [];
	/** @const int GENERICITY The greater, the more this format is likely to succeed on a random input. */
	public const GENERICITY = 0;

	/** @var bool $addNewlines Add newlines to facilitate cutting out fragments. */
	protected $addNewlines;

	/** @var array $mappings A a list of external variables, possibly converted to lowercase. */
	protected $external = [];

	/**
	 * Constructor.
	 *
	 * @param array $params An associative array of parameters.
	 * @param array $headers An optional array of HTTP headers.
	 *
	 * @throws EDParserException
	 */
	protected function __construct( array $params, array $headers = [] ) {
		// Data mappings.
		if ( array_key_exists( 'data', $params ) ) {
			// Data may be a string, or already be an array, if so passed from Lua.
			// We need only external variables.
			// For some parsers, they may be brought lo lower case.
			$this->external = array_values(
				self::paramToArray( $params['data'], false, !$this->keepExternalVarsCase )
			);
		}

		// Whether to add newlines to help cutting out fragments.
		$this->addNewlines = array_key_exists( 'add newlines', $params );
	}

	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * Reload the method in descendant classes, calling parent::__invoke() in the beginning.
	 *
	 * @param string $text The text to be parsed.
	 * @param string|null $path URL or filesystem path that may be relevant to the parser.
	 * @return array A two-dimensional column-based array of the parsed values.
	 * @throws EDParserException
	 */
	public function __invoke( $text, $path = null ): array {
		return [];
	}

	/**
	 * Instantiate needed parser object according to $params.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 * @return EDParserBase An instance of one of the registered descendant classes.
	 *
	 * @throws EDParserException
	 */
	public static function getParser( array $params ) {
		if ( !isset( $params['format'] ) || !$params['format'] ) {
			$params['format'] = 'auto';
		}
		$class = self::getMatch( $params, self::setting( 'Parsers' ) );
		if ( $class ) {
			return new $class( $params );	// let exception from EDParser* constructor fall through.
		}
		// No fitting parser found.
		throw new EDParserException( 'externaldata-web-invalid-format', $params['format'] );
	}

	/**
	 * Add newlines to facilitate cutting out fragments, if ordered. To be overloaded in JSON and XML parsers.
	 *
	 * @param string $text Text to add newlines to.
	 * @param bool $new_lines Whether to add new lines.
	 *
	 * @return string Text with newlines added.
	 */
	public function addNewlines( $text, $new_lines ) {
		return $text;
	}

	/**
	 * Return the file extensions associated with this format.
	 * @return array
	 */
	public static function extensions(): array {
		return static::EXT;
	}
}
