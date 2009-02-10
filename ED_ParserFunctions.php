<?php
/**
 * Class for handling the parser functions for External Data
 */
 
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is a MediaWiki extension; it is not a valid entry point' );
}

class EDParserFunctions {
 
	/**
	 * Render the #get_external_data parser function
	 */
	static function doGetExternalData( &$parser ) {
		global $edgValues;
		$params = func_get_args();
		array_shift( $params ); // we already know the $parser ...
		$url = array_shift( $params );
		
		$url_contents = EDUtils::fetchURL( $url );
		
		$format = array_shift( $params );
		$external_values = array();
		if ( $format == 'xml' ) {
			$external_values = EDUtils::getXMLData( $url_contents );
		} elseif ( $format == 'csv' ) {
			$external_values = EDUtils::getCSVData( $url_contents );
		} elseif ( $format == 'json' ) {
			$external_values = EDUtils::getJSONData( $url_contents );
		}
		// for each external variable name specified in the function
		// call, get its value (if one exists), and attach it to the
		// local variable name
		foreach ( $params as $param ) {
			list( $local_var, $external_var ) = explode( '=', $param );
			// set to all lowercase to avoid casing issues
			$external_var = strtolower( $external_var );
			if ( array_key_exists( $external_var, $external_values ) )
				if ( is_array( $external_values[$external_var] ) )
					$edgValues[$local_var] = $external_values[$external_var];
				else
					$edgValues[$local_var][] = $external_values[$external_var];
		}

		return '';
	}

	/**
	 * Get the specified index of the array for the specified local
	 * variable retrieved by #get_external_data
	 */
	static function getIndexedValue( $var, $i ) {
		global $edgValues;
		if ( array_key_exists( $var, $edgValues ) && count( $edgValues[$var] > $i ) )
			return $edgValues[$var][$i];
		else
			return '';
	}
 
	/**
	 * Render the #external_value parser function
	 */
	static function doExternalValue( &$parser, $local_var = '' ) {
		global $edgValues;
		if ( ! array_key_exists( $local_var, $edgValues ) )
			return '';
		elseif ( is_array( $edgValues[$local_var] ) )
			return $edgValues[$local_var][0];
		else
			return $edgValues[$local_var];
	}
 
	/**
	 * Render the #for_external_table parser function
	 */
	static function doForExternalTable( &$parser, $expression = '' ) {
		global $edgValues;

		// get the variables used in this expression, get the number
		// of values for each, and loop through 
		$matches = array();
		preg_match_all( '/{{{([^}]*)}}}/', $expression, $matches );
		$variables = $matches[1];
		$num_loops = 0;
		foreach ($variables as $variable) {
			if ( array_key_exists( $variable, $edgValues ) ) {
				$num_loops = max( $num_loops, count( $edgValues[$variable] ) );
			}
		}
		$text = "";
		for ($i = 0; $i < $num_loops; $i++) {
			$cur_expression = $expression;
			foreach ($variables as $variable) {
				$cur_expression = str_replace( '{{{' . $variable . '}}}', self::getIndexedValue( $variable , $i ), $cur_expression );
			}
			$text .= $cur_expression;
		}
		return $text;
	}
}
