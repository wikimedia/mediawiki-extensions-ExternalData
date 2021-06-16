<?php
/**
 * A trait to be used by classes that need to parse a string parameter into an array:
 * EDConnector* and EDParser*.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */
trait EDParsesParams {
	/** @var bool $preserve_external_variables_case Whether external variables' names are case-sensitive for this format. */
	protected static $preserve_external_variables_case = false;

	/**
	 * This method adds secret parameters to user-supplied ones, extracting them from
	 * global configuration variables.
	 *
	 * @param array $params User-supplied parameters.
	 *
	 * @return array Supplemented parameters.
	 */
	protected static function supplementParams( array $params ) {
		global $edgSecrets;
		$prefix = 'edg';
		$supplemented = $params;
		foreach ( $edgSecrets as $key => $globals ) {
			if ( isset( $params[$key] ) ) {
				foreach ( $globals as $global ) {
					$prefixed_global = $prefix . $global;
					global $$prefixed_global; // phpcs:ignore MediaWiki.NamingConventions.ValidGlobalName.allowedPrefix
					if ( isset( $$prefixed_global[$params[$key]] ) ) {
						$supplemented[$global] = $$prefixed_global[$params[$key]];
					}
				}
			}
		}
		return $supplemented;
	}

	/**
	 * Make choice of needed data based on an array of parameters and array of patterns to match.
	 *
	 * @param array $args A named array of parameters passed from parser or Lua function.
	 * @param array $patterns An array of patterns that $params has to match.
	 *
	 * @return string|null ID of matched pattern.
	 *
	 * @throws EDParserException
	 */
	protected static function getMatch( array $args, array $patterns ) {
		// Bring keys to lowercase:
		$args = self::paramToArray( $args, true, false );
		$supplemented_params = self::supplementParams( $args );
		foreach ( $patterns as list( $pattern, $match ) ) {
			if ( self::paramsFit( $supplemented_params, $pattern ) ) {
				return $match;
			}
		}
		// No match found.
		return null;
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
			if ( !array_key_exists( $key, $params ) // | use xpath, etc.
			  || $value !== true && strtolower( $params[$key] ) !== strtolower( $value ) // format = (format), etc.
			) {
				return false;
			}
		}
		return true;
	}

	/**
	 * A helper function. Parses an argument of the form "a=b,c=d,..." into an array
	 * and converts key and value case if required.
	 * If it is already an array, only converts the case.
	 *
	 * @param string|array $arg Values to parse.
	 * @param bool $lowercaseKeys bring keys to lower case.
	 * @param bool $lowercaseValues bring values to lower case.
	 *
	 * @return array Parsed parameter.
	 */
	protected static function paramToArray( $arg, $lowercaseKeys = false, $lowercaseValues = false ) {
		if ( !is_array( $arg ) ) {
			// Not an array. Splitting needed.
			$arg = preg_replace( "/\s\s+/", ' ', $arg ); // whitespace

			// Split text on commas, except for commas found within quotes
			// and parentheses. Regular expression based on:
			// http://stackoverflow.com/questions/1373735/regexp-split-string-by-commas-and-spaces-but-ignore-the-inside-quotes-and-parent#1381895
			// ...with modifications by Nick Lindridge, ionCube Ltd.
			$pattern = <<<END
			/
			[,]
			(?=(?:(?:[^"]*"){2})*[^"]*$)
			(?=(?:(?:[^']*'){2})*[^']*$)
			(?=(?:[^()]*+\([^()]*+\))*+[^()]*+$)
			/x
END;
			// " - fix for color highlighting in vi :)
			$keyValuePairs = preg_split( $pattern, $arg );
			$splitArray = [];
			foreach ( $keyValuePairs as $keyValuePair ) {
				if ( $keyValuePair == '' ) {
					// Ignore.
				} elseif ( strpos( $keyValuePair, '=' ) !== false ) {
					list( $key, $value ) = explode( '=', $keyValuePair, 2 );
					$splitArray[trim( $key )] = trim( $value );
				} else {
					$splitArray[trim( $keyValuePair )] = null;
				}
			}
		} else {
			// It's already an array.
			$splitArray = $arg;
		}
		// Set the letter case as required.
		$caseConvertedArray = [];
		foreach ( $splitArray as $key => $value ) {
			$new_key = trim( $lowercaseKeys ? strtolower( $key ) : $key );
			if ( is_string( $value ) ) {
				$new_value = trim( $lowercaseValues ? strtolower( $value ) : $value );
			} else {
				$new_value = $value;
			}
			$caseConvertedArray[$new_key] = $new_value;
		}
		return $caseConvertedArray;
	}

	/**
	 * Parse numbered params of a parser functions into a named array.
	 *
	 * @param array $params User-supplied parameters.
	 *
	 * @return array Associative array of parameters.
	 */
	protected static function parseParams( $params ) {
		$args = [];
		foreach ( $params as $param ) {
			$param = preg_replace( "/\s\s+/", ' ', $param ); // whitespace
			$param_parts = explode( "=", $param, 2 );
			if ( count( $param_parts ) < 2 ) {
				$args[$param_parts[0]] = null;
			} else {
				list( $name, $value ) = $param_parts;
				$args[$name] = $value;
			}
		}
		return $args;
	}

	/**
	 * Whether the parser preserves external variable case.
	 *
	 * @return bool False, is external variables' names are brought to lowercase, true otherwise.
	 */
	public static function preservesCase() {
		return static::$preserve_external_variables_case; // late binding.
	}
}
