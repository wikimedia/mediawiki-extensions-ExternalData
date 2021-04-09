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
				self::paramToArray( $params['data'], false, !static::$preserve_external_variables_case )
			);
		} else {
			throw new EDParserException( 'externaldata-no-param-specified', 'data' );
		}
	}

	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * Reload the method in descendant classes, calling parent::__invoke() in the beginning.
	 * Apply mapAndFilter() in the end.
	 *
	 * @param string $text The text to be parsed.
	 * @param array $defaults The intial values.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 */
	public function __invoke( $text, $defaults = [] ) {
		return $defaults;
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
			throw new EDParserException( 'externaldata-no-param-specified', 'format' );
		}
		global $edgParsers;
		$class = self::getMatch( $params, $edgParsers );
		if ( $class ) {
			return new $class( $params );	// let exception from EDParser* constructor fall through.
		}
		// No fitting parser found.
		throw new EDParserException( 'externaldata-web-invalid-format', $params['format'] );
	}
}
