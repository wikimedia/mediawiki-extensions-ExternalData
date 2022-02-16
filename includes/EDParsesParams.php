<?php
/**
 * A trait to be used by classes that need to parse a string parameter into an array:
 * EDConnector* and EDParser*.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */

use Wikimedia\AtEase\AtEase;
use function MediaWiki\restoreWarnings;
use function MediaWiki\suppressWarnings;

trait EDParsesParams {
	/** @var string PREFIX Prefix for the new configuration. */
	public static $prefix = 'wgExternalData';
	/** @var string OLD_PREFIX Prefix for old style configuration. */
	public static $oldPrefix = 'edg';

	/** @var bool $keepExternalVarsCase Whether external variables' names are case-sensitive for this format. */
	public $keepExternalVarsCase = false;

	/**
	 * Get a configuration setting.
	 *
	 * @param string $setting Setting's name.
	 *
	 * @return mixed Setting's value.
	 */
	protected static function setting( $setting ) {
		if ( isset( $GLOBALS[self::$prefix . $setting ] ) ) {
			return $GLOBALS[self::$prefix . $setting ];
		}
		if ( isset( $GLOBALS[self::$oldPrefix . $setting ] ) ) {
			return $GLOBALS[self::$oldPrefix . $setting ];
		}
		// Special case.
		if ( $setting === 'Verbose' ) {
			global $wgExternalValueVerbose;
			return $wgExternalValueVerbose;
		}
	}

	/**
	 * Make choice of needed data based on an array of parameters and array of patterns to match.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 * @param array $patterns An array of patterns that $params has to match.
	 *
	 * @return string|null ID of matched pattern.
	 */
	protected static function getMatch( array $params, array $patterns ) {
		// Bring keys to lowercase, turn comma-separated string to arrays.
		$parsed = self::paramToArray( $params, true, false );
		foreach ( $patterns as [ $pattern, $match ] ) {
			if ( self::paramsFit( $parsed, $pattern ) ) {
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
	 * @param array $pattern Parameters 'pattern'.
	 *
	 * @return bool
	 */
	private static function paramsFit( array $params, array $pattern ) {
		foreach ( $pattern as $key => $value ) {
			if ( $key === '__exists' ) {
				if ( class_exists( $value ) || function_exists( $value ) ) {
					// A necessary class is provided by a library.
					continue;
				} else {
					return false;
				}
			}
			if ( !array_key_exists( $key, $params ) // | use xpath, etc.
			  || !(
					$value === true // argument should be present.
				 || strtolower( $params[$key] ) === strtolower( $value ) // argument should have a certain value.
				 || self::isRegex( $value ) && preg_match( $value, $params[$key] ) // argument should match a regex.
				) // format = (format), etc.
			) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Returns true, if the passed string is a regular expression.
	 *
	 * @param string $str
	 *
	 * @return bool
	 */
	private static function isRegex( $str ) {
		self::suppressWarnings(); // for preg_match() on regular strings.
		try {
			// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			$is_regex = @preg_match( $str, '' ) !== false;
		} catch ( Exception $e ) {
			return false;
		}
		self::restoreWarnings();
		return $is_regex;
	}

	/**
	 * A helper function. Parses an argument of the form "a=b,c=d,..." into an array
	 * and converts key and value case if required.
	 * If it is already an array, only converts the case.
	 *
	 * @param string|array $arg Values to parse.
	 * @param bool $lowercaseKeys bring keys to lower case.
	 * @param bool $lowercaseValues bring values to lower case.
	 * @param bool $numeric Set anonymous parameter's name to a number rather than to itself.
	 *
	 * @return array Parsed parameter.
	 */
	protected static function paramToArray( $arg, $lowercaseKeys = false, $lowercaseValues = false, $numeric = false ) {
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
			$counter = 0;
			foreach ( $keyValuePairs as $keyValuePair ) {
				if ( $keyValuePair === '' ) {
					// Ignore.
				} elseif ( strpos( $keyValuePair, '=' ) !== false ) {
					[ $key, $value ] = explode( '=', $keyValuePair, 2 );
					$splitArray[trim( $key )] = trim( $value );
				} elseif ( $numeric ) {
					$splitArray[$counter++] = trim( $keyValuePair );
				} else {
					$splitArray[trim( $keyValuePair )] = trim( $keyValuePair );
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
			$param_parts = preg_split( '/\s*=\s*/', $param, 2 );
			if ( count( $param_parts ) < 2 ) {
				$args[$param_parts[0]] = null;
			} else {
				[ $name, $value ] = $param_parts;
				$args[$name] = $value;
			}
		}
		return $args;
	}

	/**
	 * Substitute parameters into a string (command, environment variable, etc.).
	 *
	 * @param string|array $template The string(s) in which parameters are to be substituted.
	 * @param array $parameters Validated parameters.
	 *
	 * @return string|array The string(s) with substituted parameters.
	 */
	protected function substitute( $template, array $parameters ) {
		foreach ( $parameters as $name => $value ) {
			$template = preg_replace( '/\\$' . preg_quote( $name, '/' ) . '\\$/', $value, $template );
		}
		return $template;
	}

	/**
	 * Suppress warnings absolutely.
	 */
	protected static function suppressWarnings() {
		if ( method_exists( AtEase::class, 'suppressWarnings' ) ) {
			// MW >= 1.33
			AtEase::suppressWarnings();
		} else {
			suppressWarnings();
		}
	}

	/**
	 *  Restore warnings.
	 */
	protected static function restoreWarnings() {
		if ( method_exists( AtEase::class, 'restoreWarnings' ) ) {
			// MW >= 1.33
			AtEase::restoreWarnings();
		} else {
			restoreWarnings();
		}
	}

	/**
	 * Instead of producing a warning, throw an exception.
	 * @throws Exception
	 */
	protected static function throwWarnings() {
		set_error_handler( static function ( $errno, $errstr ) {
			throw new Exception( $errstr );
		} );
	}

	/**
	 * Resume warnings.
	 */
	protected static function stopThrowingWarnings() {
		restore_error_handler();
	}
}
