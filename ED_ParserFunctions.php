<?php
 
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This file is a MediaWiki extension; it is not a valid entry point' );
}

/**
 * Class for handling the parser functions for External Data
 *
 * @author Yaron Koren
 * @author Michael Dale
 */
class EDParserFunctions {
	// how many times to try an HTTP request
	private $http_number_of_tries=3;

	// XML-handling functions based on code found at
	// http://us.php.net/xml_set_element_handler
	static function startElement( $parser, $name, $attrs ) {
		global $edgCurrentXMLTag;
		// set to all lowercase to avoid casing issues
		$edgCurrentXMLTag = strtolower($name);
	}

	static function endElement( $parser, $name ) {
		global $edgCurrentXMLTag;
		$edgCurrentXMLTag = "";
	}

	static function getContent ( $parser, $content ) {
		global $edgCurrentXMLTag, $edgXMLValues;
		$edgXMLValues[$edgCurrentXMLTag] = $content;
	}

	static function getXMLData ( $xml ) {
		global $edgXMLValues;
		$edgXMLValues = array();

		$xml_parser = xml_parser_create();
		xml_set_element_handler( $xml_parser, array( 'EDParserFunctions', 'startElement' ), array( 'EDParserFunctions', 'endElement' ) );
		xml_set_character_data_handler( $xml_parser, array( 'EDParserFunctions', 'getContent' ) );
		if (!xml_parse($xml_parser, $xml, true)) {
			echo(sprintf("XML error: %s at line %d",
			xml_error_string(xml_get_error_code($xml_parser)),
			xml_get_current_line_number($xml_parser)));
		}
		xml_parser_free( $xml_parser );
		return $edgXMLValues;
	}

	static function getCSVData( $csv ) {
		// regular expression copied from http://us.php.net/fgetcsv
		$csv_vals = preg_split('/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/', $csv); 
		// start with a null value so that the real values start with
		// an index of 1 instead of 0
		$values = array( null );
		foreach ( $csv_vals as $csv_val ) {
			$values[] = trim( $csv_val, '"' );
		}
		return $values;
	}

	/**
	 * Recursive function for use by getJSONData()
	 */
	static function parseTree( $tree, &$retrieved_values ) {
		foreach ($tree as $key => $val) {
			if (is_array( $val )) {
				self::parseTree( $val, $retrieved_values );
			} else {
				$retrieved_values[$key] = $val;
			}
		}
	}

	static function getJSONData( $json ) {
		// escape if json_decode() isn't supported
		if ( ! function_exists( 'json_decode' ) ) {
			echo( "Error: json_decode() is not supported in this version of PHP" );
			return array();
		}
		$json_tree = json_decode($json, true);
		$values = array();
		if ( is_array( $json_tree ) ) {
			self::parseTree( $json_tree, $values );
		}
		return $values;
	}
 
	/**
	 * Render the #get_external_data parser function
	 */
	static function doGetExternalData( &$parser ) {
		global $edgValues;
		$params = func_get_args();
		array_shift( $params ); // we already know the $parser ...
		$url = array_shift( $params );
		
		$url_contents = EDParserFunctions::doRequest( $url );
		
		$format = array_shift( $params );
		$external_values = array();
		if ($format == 'xml') {
			$external_values = self::getXMLData( $url_contents );
		} elseif ($format == 'csv') {
			$external_values = self::getCSVData( $url_contents );
		} elseif ($format == 'json') {
			$external_values = self::getJSONData( $url_contents );
		}
		// for each external variable name specified in the function
		// call, get its value (if one exists), and attach it to the
		// local variable name
		foreach ($params as $param) {
			list( $local_var, $external_var ) = explode( '=', $param );
			// set to all lowercase to avoid casing issues
			$external_var = strtolower( $external_var );
			if ( array_key_exists( $external_var, $external_values ) )
				$edgValues[$local_var] = $external_values[$external_var];
		}

		return '';
	}
 
	/**
	 * Render the #external_value parser function
	 */
	static function doExternalValue( &$parser, $local_var = '' ) {
		global $edgValues;
		if ( array_key_exists( $local_var, $edgValues) )
			return $edgValues[$local_var];
		else
			return '';
	}
	
	static function doRequest( $url, $post_vars = array(), $get_fresh=false, $try_count=1 ) {
		$dbr = wfGetDB( DB_SLAVE );		
		global $edgStringReplacements, $edgCacheTable;

		// do any special variable replacements in the URLs, for
		// secret API keys and the like
		foreach ( $edgStringReplacements as $key => $value ) {
			$url = str_replace( $key, $value, $url );
		}

		if( !isset( $edgCacheTable ) || is_null( $edgCacheTable ) )
			return @file_get_contents( $url );

		// check the cache (only the first 254 chars of the url) 
		$res = $dbr->select( $edgCacheTable, '*', array( 'url' => substr($url,0,254) ), 'EDParserFunctions::doRequest' );
		// @@todo check date
		if ( $res->numRows() == 0 || $get_fresh) {
			$page = Http::get( $url );
			if ( $page === false ) {
				sleep( 1 );
				if( $try_count >= $this->http_number_of_tries ){
					echo "could not get URL after {$this->http_number_of_tries} tries.\n\n";
					return '';
				}				
				$try_count++;
				return $this->doRequest( $url, $post_vars, $get_fresh, $try_count );
			}
			if ( $page != '' ) {
				$dbw = wfGetDB( DB_MASTER );
				// insert contents into the cache table
				$dbw->insert( $edgCacheTable, array( 'url' => substr($url,0,254), 'result' => $page, 'req_time' => time() ) );
				return $page;
			}
		} else {
			$row = $dbr->fetchObject( $res );
			return $row->result;
		}
	}
}
