<?php
/**
 * Basis abstract class for text parsers.
 * Includes a factory method that analyses parameters and instantiates needed descendant class.
 *
 * @var bool $preserve_external_variables_case Whether external variables' names are case-sensitive for this format.
 * @var ?string $encoding Text encoding.
 * @var array $mappings An associative array mapping internal variables to external.
 * @var array $filters Data filters.
 *
 * @author Alexander Mashin
 *
 */

abstract class EDParserBase {
	// Whether external variables' names are case-sensitive for this format.
	protected static $preserve_external_variables_case = false;

	protected $encoding = null;
	protected $mappings = [];		// mappings from internal variables to external.
	protected $filters = [];		// data filters.

	/**
	 * Instantiate needed parser object according to $params.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 * @return EDParserBase|string An instance of one of the registered descendant classes or an error message.
	 *
	 */
	public static function getParser( array $params ) {
		global $edgParsers;
		foreach ( $edgParsers as list( $pattern, $class ) ) {
			if ( self::paramsFit( $params, $pattern ) ) {
				// Now check, if it can actually run.
				$reason_for_unavailability = $class::reasonForUnavailability();
				if ( $reason_for_unavailability === null ) {
					// Parser has not given us any excuse not to be instantiated.
					try {
						$parser = new $class( $params );
					} catch ( Exception $e ) {
						// Some constructors can throw exceptions.
						$parser = $e->getMessage();
					}
				} else {
					// Under present configuration the chosen format is unavailable.
					$parser = $reason_for_unavailability;
				}
				return $parser;
			}
		}
		// No fitting parser found.
		return wfMessage( 'externaldata-web-invalid-format', $params['format'] )->text();
	}

	/**
	 * Check, if the passed parameters fit a 'pattern':
	 *
	 * @param array $params Parameters to be checked.
	 * @param array $pattern Parametes 'pattern'.
	 *
	 * @return bool
	 */
	private static function paramsFit( array $params, array $pattern ) {
		foreach ( $pattern as $key => $value ) {
			if ( $value === true ) {
				// | use xpath, | use jsonpath, etc.
				if ( !array_key_exists( $key, $params ) ) {
					return false;
				}
			} elseif ( strtolower( $params[$key] ) !== strtolower( $value ) ) {
				// | format = xml, etc.
				return false;
			}
		}
		return true;
	}

	/**
	 * Return null, if MediaWiki and PHP environment allows to use this format;
	 * an error message otherwise.
	 * Reload if parser class availability is subject to any constraints.
	 *
	 * @return string|null An error message, is any; null on success.
	 */
	public static function reasonForUnavailability() {
		return null;
	}

	/**
	 * Constructor.
	 *
	 * @param array $params An associative array of parameters.
	 *
	 */
	protected function __construct( array $params ) {
		// Encoding.
		$this->encoding = array_key_exists( 'encoding', $params ) && $params['encoding'] ? $params['encoding'] : null;

		// Data mappings.
		if ( array_key_exists( 'data', $params ) ) {
			// data may be a string, or already be an array, if so passed from Lua.
			$this->mappings = self::paramToArray( $params['data'], false, !static::$preserve_external_variables_case );
		} else {
			throw new MWException( wfMessage( 'externaldata-no-param-specified', 'data' )->parse() );
		}

		// Filters.
		$this->filters = array_key_exists( 'filters', $params ) && $params['filters']
					   ? self::paramToArray( $params['filters'], true, false )
					   : [];
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
		// Encoding has not been detected earlier by EDUtils::fetch(). So, detect now.
		self::detectEncodingAndConvertToUTF8( $text );
		return $defaults;
	}

	/**
	 * Returns the assumed encoding.
	 *
	 * @return string Encoding.
	 *
	 */
	public function encoding() {
		return $this->encoding;
	}

	/**
	 * Detect encoding based on tags in the $text and $this->encoding override.
	 * Convert $text to UTF-8.
	 *
	 * @param string &$text Text to convert.
	 */
	private function detectEncodingAndConvertToUTF8( &$text ) {
		$this->encoding = EDUtils::detectEncodingAndConvertToUTF8( $text, $this->encoding );
	}

	/**
	 * A helper function.
	 *
	 * @param array $external_values Values to filter and map.
	 *
	 * @return array Filtered and mapped values.
	 */
	protected function mapAndFilter( array $external_values ) {
		return EDUtils::mapAndFilterValues( $external_values, $this->filters, $this->mappings );
	}

	/**
	 * A helper function. Parses an argument of the form "a=b,c=d,..." into an array. If it is already an array, only converts the case.
	 *
	 * @param string|array $arg Values to parse.
	 * @param bool $lowercaseKeys bring keys to lower case.
	 * @param bool $lowercaseValues bring values to lower case.
	 *
	 * @return array Parsed parameter.
	 *
	 */
	protected static function paramToArray( $arg, $lowercaseKeys = false, $lowercaseValues = false ) {
		return EDUtils::paramToArray( $arg, $lowercaseKeys, $lowercaseValues );
	}
}
