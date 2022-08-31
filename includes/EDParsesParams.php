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
	 * Check if the passed parameters fit a 'pattern'
	 * set in $wgExternalDataConnectors or $wgExternalDataParsers:
	 *
	 * @param array $params Parameters to be checked.
	 * @param array $pattern Parameters 'pattern'.
	 * @return bool
	 */
	private static function paramsFit( array $params, array $pattern ) {
		foreach ( $pattern as $key => $value ) {
			if ( $key === '__exists' ) { // this part of the 'pattern' is a dependency.
				if ( class_exists( $value ) || function_exists( $value ) ) { // and it is met.
					continue; // continue with this 'pattern'.
				} else {
					return false; // dependency is not met, and the 'pattern' fails.
				}
			}
			$parameter_present = array_key_exists( $key, $params );
			if ( $value === true ) { // parameter is required.
				if ( $parameter_present ) { // and is present.
					continue; // this parameter needs no further checks.
				} else {
					return false; // parameter is absent, and the 'pattern' fails.
				}
			}
			if ( $value === false ) { // parameter is forbidden.
				if ( !$parameter_present ) { // and is absent.
					continue; // this parameter needs no further checks.
				} else {
					return false; // parameter is present, and the 'pattern' fails.
				}
			}
			if ( !$parameter_present ) {
				return false; // at this point, parameter ought to be set.
			}
			if ( self::isRegex( $value ) ) { // parameter is a regular expression.
				if ( preg_match( $value, $params[$key] ) ) { // and it matches.
					continue; // this parameter needs no further checks.
				} else {
					return false; // does not match, and the 'pattern' fails.
				}
			}
			// At this point, only exact (case insensitive) match will do.
			if ( strtolower( $params[$key] ) !== strtolower( $value ) ) {
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
		if ( !preg_match( '/^(?:
			# Same delimiter character at the start and the end
			([^\s\w\\\\]).+\\1
			|
			# Pairs of brackets can also be used as delimiters
			\(.+\) | \{.+} | \[.+] | <.+>
		)[imsxADSUXJu]*$/x', $str ) ) {
			return false;
		}
		self::suppressWarnings(); // for preg_match() on regular strings.
		try {
			// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			$is_regex = @preg_match( $str, '' ) !== false;
		} catch ( Exception $e ) {
			return false;
		} finally {
			self::restoreWarnings();
		}
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
			$key_value_pairs = preg_split( $pattern, $arg );
			$split_array = [];
			$counter = 0;
			foreach ( $key_value_pairs as $key_value_pair ) {
				if ( $key_value_pair === '' ) {
					// Ignore.
				} elseif ( strpos( $key_value_pair, '=' ) !== false ) {
					[ $key, $value ] = explode( '=', $key_value_pair, 2 );
					$split_array[trim( $key )] = trim( $value );
				} elseif ( $numeric ) {
					$split_array[$counter++] = trim( $key_value_pair );
				} else {
					$split_array[trim( $key_value_pair )] = trim( $key_value_pair );
				}
			}
		} else {
			// It's already an array.
			$split_array = $arg;
		}
		// Set the letter case as required.
		$case_converted_array = [];
		foreach ( $split_array as $key => $value ) {
			$new_key = trim( $lowercaseKeys ? strtolower( $key ) : $key );
			if ( is_string( $value ) ) {
				$new_value = trim( $lowercaseValues ? strtolower( $value ) : $value );
			} else {
				$new_value = $value;
			}
			$case_converted_array[$new_key] = $new_value;
		}
		return $case_converted_array;
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
		foreach ( $params as $key => $param ) {
			$param_parts = preg_split( '/\s*=\s*/', $param, 2 );
			if ( count( $param_parts ) < 2 ) {
				$args[$param_parts[0]] = null;
				// Also, keep the numbered parameter.
				$args[$key] = $param;
			} else {
				[ $name, $value ] = $param_parts;
				$args[$name] = $value;
			}
		}
		return $args;
	}

	/**
	 * Suppress warnings absolutely.
	 */
	protected static function suppressWarnings() {
		AtEase::suppressWarnings();
	}

	/**
	 *  Restore warnings.
	 */
	protected static function restoreWarnings() {
		AtEase::restoreWarnings();
	}

	/**
	 * Instead of producing a warning, throw an exception.
	 */
	protected static function throwWarnings() {
		// @phan-suppress-next-line PhanTypeMismatchArgumentInternal, PhanPluginNeverReturnFunction
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
