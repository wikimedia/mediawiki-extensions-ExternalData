<?php
/**
 * Class for JSON parser.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */

class EDParserJSON extends EDParserBase {
	/** @var int Optional length of the ignored prefix. */
	private $prefix_length = 0;

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );
		$this->prefix_length = isset( $params['json offset'] ) ? intval( $params['json offset'] ) : 0;
	}

	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 * @param ?array $defaults
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 * @throws EDParserException
	 */
	public function __invoke( $text, $defaults = [] ) {
		$json = substr( $text, $this->prefix_length );
		// FormatJson class is provided by MediaWiki.
		$json_tree = FormatJson::decode( $json, true );
		if ( $json_tree === null ) {
			// It's probably invalid JSON.
			throw new EDParserException( 'externaldata-invalid-json' );
		}
		$values = parent::__invoke( $text, $defaults );
		if ( is_array( $json_tree ) ) {
			self::parseTree( $json_tree, $values );
		}
		return $values;
	}

	/**
	 * Recursive JSON-parsing function for use by __invoke().
	 *
	 * @param array $tree Parsed JSON as returned by FormatJson::decode().
	 *
	 * @param array &$retrieved_values An array with retrieved values.
	 *
	 */
	private static function parseTree( array $tree, array &$retrieved_values ) {
		foreach ( $tree as $key => $val ) {
			// TODO - this logic could probably be a little nicer.
			if ( is_array( $val ) && self::holdsSimpleList( $val ) ) {
				// If it just holds a simple list, turn the
				// array into a comma-separated list, then
				// pass it back in in order to do the final
				// processing.
				$val = [ $key => implode( ', ', $val ) ];
				self::parseTree( $val, $retrieved_values );
			} elseif ( is_array( $val ) && count( $val ) > 1 ) {
				self::parseTree( $val, $retrieved_values );
			} elseif ( is_array( $val ) && count( $val ) == 1 && is_array( current( $val ) ) ) {
				self::parseTree( current( $val ), $retrieved_values );
			} else {
				// If it's an array with just one element,
				// treat it like a regular value.
				// (Why is the null check necessary?)
				if ( $val != null && is_array( $val ) ) {
					$val = current( $val );
				}
				$key = strtolower( $key );
				if ( !array_key_exists( $key, $retrieved_values ) ) {
					$retrieved_values[$key] = [];
				}
				$retrieved_values[$key][] = $val;
			}
		}
	}

	/**
	 * Helper function that determines whether an array holds a simple
	 * list of scalar values, with no keys (i.e., not an associative
	 * array).
	 *
	 * @param array $arr The array to be checked.
	 *
	 * @return bool True, if $arr is a flat numbered array without gaps.
	 */
	private static function holdsSimpleList( array $arr ) {
		$expectedKey = 0;
		foreach ( $arr as $key => $val ) {
			if ( is_array( $val ) || $key !== $expectedKey++ ) {
				return false;
			}
		}
		return true;
	}
}
