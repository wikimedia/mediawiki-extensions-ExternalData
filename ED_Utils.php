<?php
/**
 * Utility functions for External Data
 */

class EDUtils {
	// how many times to try an HTTP request
	private static $http_number_of_tries = 3;

	private static $ampersandReplacement = "THIS IS A LONG STRING USED AS A REPLACEMENT FOR AMPERSANDS 55555555";

	// XML-handling functions based on code found at
	// http://us.php.net/xml_set_element_handler
	static function startElement( $parser, $name, $attrs ) {
		global $edgCurrentXMLTag, $edgXMLValues;
		// set to all lowercase to avoid casing issues
		$edgCurrentXMLTag = strtolower( $name );
		foreach ( $attrs as $attr => $value ) {
			$attr = strtolower( $attr );
			if ( array_key_exists( $attr, $edgXMLValues ) ) {
				$edgXMLValues[$attr][] = $value;
			} else {
				$edgXMLValues[$attr] = array( $value );
			}
		}
	}

	static function endElement( $parser, $name ) {
		global $edgCurrentXMLTag;
		$edgCurrentXMLTag = "";
	}

	static function getContent( $parser, $content ) {
		global $edgCurrentXMLTag, $edgXMLValues;

		// Replace ampersands, to avoid the XML getting split up
		// around them.
		// Note that this is *escaped* ampersands being replaced -
		// this is unrelated to the fact that bare ampersands aren't
		// allowed in XML.
		$content = str_replace( self::$ampersandReplacement, '&amp;', $content );
		if ( array_key_exists( $edgCurrentXMLTag, $edgXMLValues ) )
			$edgXMLValues[$edgCurrentXMLTag][] = $content;
		else
			$edgXMLValues[$edgCurrentXMLTag] = array( $content );
	}

	static function parseParams( $params ) {
		$args = array();
		foreach ( $params as $param ) {
			$param = preg_replace ( "/\s\s+/", ' ', $param ); // whitespace
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
	 * Parses an argument of the form "a=b,c=d,..." into an array
	 */
	static function paramToArray( $arg, $lowercaseKeys = false, $lowercaseValues = false ) {
		$arg = preg_replace ( "/\s\s+/", ' ', $arg ); // whitespace

		// Split text on commas, except for commas found within quotes
		// and parentheses. Regular expression based on:
		// http://stackoverflow.com/questions/1373735/regexp-split-string-by-commas-and-spaces-but-ignore-the-inside-quotes-and-parent#1381895
		// ...with modifications by Nick Lindridge, ionCube Ltd.
		$pattern = <<<END
        /
	[,]
	(?=(?:(?:[^"]*"){2})*[^"]*$)
	(?=(?:(?:[^']*'){2})*[^']*$)
	(?=(?:[[:alnum:]]+=))
	/x
END;
		// " - fix for color highlighting in vi :)
		$keyValuePairs = preg_split( $pattern, $arg );

		$returnArray = array();
		foreach ( $keyValuePairs as $keyValuePair ) {
			$keyAndValue = explode( '=', $keyValuePair, 2 );
			if ( count( $keyAndValue ) == 2 ) {
				$key = trim( $keyAndValue[0] );
				if ( $lowercaseKeys ) {
					$key = strtolower( $key );
				}
				$value = trim( $keyAndValue[1] );
				if ( $lowercaseValues ) {
					$value = strtolower( $value );
				}
				$returnArray[$key] = $value;
			}
		}
		return $returnArray;
	}

	static function getLDAPData( $filter, $domain, $params ) {
		global $edgLDAPServer;
		global $edgLDAPUser;
		global $edgLDAPPass;

		$ds = self::connectLDAP( $edgLDAPServer[$domain], $edgLDAPUser[$domain], $edgLDAPPass[$domain] );
		$results = self::searchLDAP( $ds, $domain, $filter, $params );

		return $results;
	}

	static function connectLDAP( $server, $username, $password ) {
		$ds = ldap_connect( $server );
		if ( $ds ) {
			// these options for Active Directory only?
			ldap_set_option( $ds, LDAP_OPT_PROTOCOL_VERSION, 3 );
			ldap_set_option( $ds, LDAP_OPT_REFERRALS, 0 );

			if ( $username ) {
				$r = ldap_bind( $ds, $username, $password );
			} else {
				# no username, so do anonymous bind
				$r = ldap_bind( $ds );
			}

			# should check the result of the bind here
			return $ds;
		} else {
			echo wfMessage( "externaldata-ldap-unable-to-connect", $server )->text();
		}
	}

	static function searchLDAP( $ds, $domain, $filter, $attributes ) {
		global $edgLDAPBaseDN;

		$sr = ldap_search( $ds, $edgLDAPBaseDN[$domain], $filter, $attributes );
		$results = ldap_get_entries( $ds, $sr );
		return $results;
	}

	static function getArrayValue( $arrayName, $key ) {
		if ( array_key_exists( $key, $arrayName ) ) {
			return $arrayName[$key];
		} else {
			return null;
		}
	}

	static function getDBData( $dbID, $from, $columns, $where, $sqlOptions, $otherParams ) {
		global $edgDBServerType;
		global $edgDBServer;
		global $edgDBDirectory;
		global $edgDBName;
		global $edgDBUser;
		global $edgDBPass;
		global $edgDBFlags;
		global $edgDBTablePrefix;

		// Get all possible parameters
		$db_type = self::getArrayValue( $edgDBServerType, $dbID );
		$db_server = self::getArrayValue( $edgDBServer, $dbID );
		$db_directory = self::getArrayValue( $edgDBDirectory, $dbID );
		$db_name = self::getArrayValue( $edgDBName, $dbID );
		$db_username = self::getArrayValue( $edgDBUser, $dbID );
		$db_password = self::getArrayValue( $edgDBPass, $dbID );
		$db_flags = self::getArrayValue( $edgDBFlags, $dbID );
		$db_tableprefix = self::getArrayValue( $edgDBTablePrefix, $dbID );

		// MongoDB has entirely different handling from the rest.
		if ( $db_type == 'mongodb' ) {
			if ( $db_name == '' ) {
				return wfMessage( "externaldata-db-incomplete-information" )->text();
			}
			return self::getMongoDBData( $db_server, $db_username, $db_password, $db_name, $from, $columns, $where, $sqlOptions, $otherParams );
		}

		// Validate parameters
		if ( $db_type == '' ) {
			return wfMessage( "externaldata-db-incomplete-information" )->text();
		} elseif ( $db_type == 'sqlite' ) {
			if ( $db_directory == '' || $db_name == '' ) {
				return wfMessage( "externaldata-db-incomplete-information" )->text();
			}
		} else {
			if ( $db_server == '' || $db_name == '' ||
				$db_username == '' || $db_password == '' ) {
				return wfMessage( "externaldata-db-incomplete-information" )->text();
			}
		}

		// Additional settings
		if ( $db_type == 'sqlite' ) {
			global $wgSQLiteDataDir;
			$oldDataDir = $wgSQLiteDataDir;
			$wgSQLiteDataDir = $db_directory;
		}
		if ( $db_flags == '' ) {
			$db_flags = DBO_DEFAULT;
		}

		// DatabaseBase::newFromType() was added in MW 1.17 - it was
		// then replaced by DatabaseBase::factory() in MW 1.18
		$factoryFunction = array( 'DatabaseBase', 'factory' );
		//$newFromTypeFunction = array( 'DatabaseBase', 'newFromType' );
		if ( is_callable( $factoryFunction ) ) {
			$db = DatabaseBase::factory( $db_type,
				array(
					'host' => $db_server,
					'user' => $db_username,
					'password' => $db_password,
					// Both 'dbname' and 'dbName' have been
					// used in different versions.
					'dbname' => $db_name,
					'dbName' => $db_name,
					'flags' => $db_flags,
					'tablePrefix' => $db_tableprefix,
				)
			);
		} else { //if ( is_callable( $newFromTypeFunction ) ) {
			$db = DatabaseBase::newFromType( $db_type,
				array(
					'host' => $db_server,
					'user' => $db_username,
					'password' => $db_password,
					'dbname' => $db_name,
					'flags' => $db_flags,
					'tableprefix' => $db_tableprefix,
				)
			);
		}

		if ( $db == null ) {
			return wfMessage( "externaldata-db-unknown-type" )->text();
		}

		if ( ! $db->isOpen() ) {
			return wfMessage( "externaldata-db-could-not-connect" )->text();
		}

		if ( count( $columns ) == 0 ) {
			return wfMessage( "externaldata-db-no-return-values" )->text();
		}

		$rows = self::searchDB( $db, $from, $columns, $where, $sqlOptions );
		$db->close();

		if ( !is_array( $rows ) ) {
			// It's an error message.
			return $rows;
		}

		if ( $db_type == 'sqlite' ) {
			// Reset global variable back to its original value.
			global $wgSQLiteDataDir;
			$wgSQLiteDataDir = $oldDataDir;
		}

		$values = array();
		foreach ( $rows as $row ) {
			foreach ( $columns as $column ) {
				$values[$column][] = $row[$column];
			}
		}

		return $values;
	}


	static function dotresolve(array $arrayName, $path, $default = null)
	{
	  $current = $arrayName;
	  $token = strtok($path, '.');

	  while ($token !== false) {
	    if (!isset($current[$token])) {
	      return $default;
	    }
	    $current = $current[$token];
	    $token = strtok('.');
	  }

	  return $current;
	}


	/**
	 * Handles #get_db_data for the non-relational database system
	 * MongoDB.
	 */
	static function getMongoDBData( $db_server, $db_username, $db_password, $db_name, $from, $columns, $where, $sqlOptions, $otherParams ) {
		
		// construct connect string
		$connect_string = "mongodb://";
		if ( $db_username != '' ) {
			$connect_string .= $db_username . ':' . $db_password . '@';
		}
		if ( $db_server != '') {
			$connect_string .= $db_server;
		} else {
			$connect_string .= 'localhost:27017';
		}

		$m = new MongoClient($connect_string);
		$db = $m->selectDB( $db_name );

		// MongoDB doesn't seem to have a way to check whether either
		// a database or a collection exists, so instead we'll use
		// getCollectionNames() to check for both.
		$collectionNames = $db->getCollectionNames();
		if ( count( $collectionNames ) == 0 ) {
			return wfMessage( "externaldata-db-could-not-connect" )->text();
		}

		if ( !in_array( $from, $collectionNames ) ) {
			return wfMessage( "externaldata-db-unknown-collection" )->text();
		}

		$collection = new MongoCollection( $db, $from );

		$findArray = array();
		$aggregateArray = array();
		// Was a direct MongoDB "find" query JSON string provided?
		// If so, use that.
		if ( array_key_exists( 'find query', $otherParams ) ) {
			// Note to users: be sure to use spaces between curly
			// brackets in the 'find' JSON so as not to trip up the
			// MW parser.
			$findArray = json_decode ($otherParams['find query'], true);
		} elseif ( $where != '' ) {
			// If not, turn the SQL of the "where=" parameter into
			// a "find" array for MongoDB. Note that this approach
			// is only appropriate for simple find queries, that
			// use the operators OR, AND, >=, >, <=, < and LIKE
			// - and NO NUMERIC LITERALS.
			$where = str_ireplace( ' and ', ' AND ', $where );
			$where = str_ireplace( ' like ', ' LIKE ', $where );
			$whereElements = explode( ' AND ', $where );
			foreach ( $whereElements as $whereElement ) {
				if ( strpos( $whereElement, '>=' ) ) {
					list( $fieldName, $value ) = explode( '>=', $whereElement );
					$findArray[trim( $fieldName )] = array( '$gte' => trim( $value ) );
				} elseif ( strpos( $whereElement, '>' ) ) {
					list( $fieldName, $value ) = explode( '>', $whereElement );
					$findArray[trim( $fieldName )] = array( '$gt' => trim( $value ) );
				} elseif ( strpos( $whereElement, '<=' ) ) {
					list( $fieldName, $value ) = explode( '<=', $whereElement );
					$findArray[trim( $fieldName )] = array( '$lte' => trim( $value ) );
				} elseif ( strpos( $whereElement, '<' ) ) {
					list( $fieldName, $value ) = explode( '<', $whereElement );
					$findArray[trim( $fieldName )] = array( '$lt' => trim( $value ) );
				} elseif ( strpos( $whereElement, ' LIKE ' ) ) {
					list( $fieldName, $value ) = explode( ' LIKE ', $whereElement );
					$value = trim( $value );
					$regex = new MongoRegex( "/$value/i" );
					$findArray[trim( $fieldName )] = $regex;
				} else {
					list( $fieldName, $value ) = explode( '=', $whereElement );
					$findArray[trim( $fieldName )] = trim( $value );
				}
			}
		}

		// Do the same for the "order=" parameter.
		$sortArray = array();
		if ( $sqlOptions['ORDER BY'] != '' ) {
			$sortElements = explode( ',', $sqlOptions['ORDER BY'] );
			foreach ( $sortElements as $sortElement ) {
				$parts = explode( ' ', $sortElement );
				$fieldName = $parts[0];
				$orderingNum = 1;
				if ( count( $parts ) > 1 ) {
					if ( strtolower( $parts[1] ) == 'desc' ) {
						$orderingNum = -1;
					}
				}
				$sortArray[$fieldName] = $orderingNum;
			}
		}

		// Get the data!
		if ( array_key_exists( 'aggregate', $otherParams ) ) {
			$resultsCursor = $collection->aggregate( $aggregateArray );
		} else {
			$resultsCursor = $collection->find( $findArray, $columns )->sort( $sortArray )->limit( $sqlOptions['LIMIT'] );
		}

		$values = array();
		foreach ( $resultsCursor as $doc ) {
			foreach ( $columns as $column ) {
				if ( strstr($column, ".") ) {
					// If the user specified dot notation to retrieve values from the MongoDB result array
				 	$values[$column][] = self::dotresolve($doc, $column);
				} elseif ( is_array( $doc[$column] ) ) {
					// If MongoDB returns an array for a column, but the user didnt specify dot notation
					// do some extra processing.
					if ( $column == 'geometry' && array_key_exists( 'coordinates', $doc['geometry'] ) ) {
						// Check if it's GeoJSON geometry:
						// http://www.geojson.org/geojson-spec.html#geometry-objects 
						// If so, return it in a format that
						// the Maps extension can understand.
						$coordinates = $doc['geometry']['coordinates'][0];
						$coordinateStrings = array();
						foreach ( $coordinates as $coordinate ) {
							$coordinateStrings[] = $coordinate[1] . ',' . $coordinate[0];
						}
						$values[$column][] =  implode( ':', $coordinateStrings );
					} else {
						// Just return it as JSON, the
						// lingua franca of MongoDB.
						$values[$column][] = json_encode( $doc[$column] );
					}
				} else {
					// It's a simple literal.
					$values[$column][] = $doc[$column];
				}
			}
		}

		return $values;
	}

	static function searchDB( $db, $table, $vars, $conds, $sqlOptions ) {
		// Add on a space at the beginning of $table so that
		// $db->select() will treat it as a literal, instead of
		// putting quotes around it or otherwise trying to parse it.
		$table = ' ' . $table;
		$result = $db->select( $table, $vars, $conds, 'EDUtils::searchDB', $sqlOptions );
		if ( !$result ) {
			return wfMessage( "externaldata-db-invalid-query" )->text();
		}

		$rows = array();
		while ( $row = $db->fetchRow( $result ) ) {
			// Create a new row object, that uses the passed-in
			// column names as keys, so that there's always an
			// exact match between what's in the query and what's
			// in the return value (so that "a.b", for instance,
			// doesn't get chopped off to just "b").
			$new_row = array();
			foreach ( $vars as $i => $column_name ) {
				// Convert the encoding to UTF-8
				// if necessary - based on code at
				// http://www.php.net/manual/en/function.mb-detect-encoding.php#102510
				$dbField = $row[$i];
				if ( !function_exists( 'mb_detect_encoding' ) ||
					mb_detect_encoding( $dbField, 'UTF-8', true ) == 'UTF-8' ) {
					$new_row[$column_name] = $dbField;
				} else {
					$new_row[$column_name] = utf8_encode( $dbField );
				}
			}
			$rows[] = $new_row;
		}
		return $rows;
	}

	static function getXMLData( $xml ) {
		global $edgXMLValues;
		$edgXMLValues = array();

		// Remove comments from XML - for some reason, xml_parse()
		// can't handle them.
		$xml = preg_replace( '/<!--.*?-->/s', '', $xml );

		// Also, re-insert ampersands, after they were removed to
		// avoid parsing problems.
		$xml = str_replace( '&amp;', self::$ampersandReplacement, $xml );

		$xml_parser = xml_parser_create();
		xml_set_element_handler( $xml_parser, array( 'EDUtils', 'startElement' ), array( 'EDUtils', 'endElement' ) );
		xml_set_character_data_handler( $xml_parser, array( 'EDUtils', 'getContent' ) );
		if ( !xml_parse( $xml_parser, $xml, true ) ) {
			return wfMessage( 'externaldata-xml-error',
			xml_error_string( xml_get_error_code( $xml_parser ) ),
			xml_get_current_line_number( $xml_parser ) )->text();
		}
		xml_parser_free( $xml_parser );
		return $edgXMLValues;
	}

	static function isNodeNotEmpty( $node ) {
		return trim( $node[0] ) != '';
	}

	static function filterEmptyNodes( $nodes ) {
		if ( !is_array( $nodes ) ) return $nodes;
		return array_filter( $nodes, array( 'EDUtils', 'isNodeNotEmpty' ) );
	}

	static function getXPathData( $xml, $mappings, $url ) {
		global $edgXMLValues;

		$edgXMLValues = array();
		$sxml = new SimpleXMLElement( $xml );

		foreach ( $mappings as $local_var => $xpath ) {
			// First, register any necessary XML namespaces, to
			// avoid "Undefined namespace prefix" errors.
			$matches = array();
			preg_match_all( '/[\/\@]([a-zA-Z0-9]*):/', $xpath, $matches );
			foreach ( $matches[1] as $namespace ) {
				$sxml->registerXPathNamespace( $namespace, $url );
			}

			// Now, get all the matching values, and remove any
			// empty results.
			$nodes = self::filterEmptyNodes( $sxml->xpath( $xpath ) );
			if ( !$nodes ) {
				continue;
			}
			if ( array_key_exists( $xpath, $edgXMLValues ) ) {
				// At the moment, this code will never get
				// called, because duplicate values in
				// $mappings will have been removed already.
				$edgXMLValues[$xpath] = array_merge( $edgXMLValues[$xpath], (array)$nodes );
			} else {
				$edgXMLValues[$xpath] = (array)$nodes;
			}
		}
		return $edgXMLValues;
	}

	static function getValuesFromCSVLine( $csv_line ) {
		// regular expression copied from http://us.php.net/fgetcsv
		$vals = preg_split( '/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/', $csv_line );
		$vals2 = array();
		foreach ( $vals as $val ) {
			$vals2[] = trim( $val, '"' );
		}
		return $vals2;
	}

	static function getCSVData( $csv, $has_header ) {
		// from http://us.php.net/manual/en/function.str-getcsv.php#88311
		// str_getcsv() is a function that was only added in PHP 5.3.0,
		// so use the much older fgetcsv() if it's not there

		// actually, for now, always use fgetcsv(), since this call to
		// str_getcsv() doesn't work, and I can't test/debug it at the
		// moment
		//if ( function_exists( 'str_getcsv' ) ) {
		//	$table = str_getcsv( $csv );
		//} else {
			$fiveMBs = 5 * 1024 * 1024;
			$fp = fopen( "php://temp/maxmemory:$fiveMBs", 'r+' );
			fputs( $fp, $csv );
			rewind( $fp );
			$table = array();
			while ( $line = fgetcsv( $fp ) ) {
				array_push( $table, $line );
			}
			fclose( $fp );
		//}
		// Get header values, if this is 'csv with header'
		if ( $has_header ) {
			$header_vals = array_shift( $table );
			// On the off chance that there are one or more blank
			// lines at the beginning, cycle through.
			while ( count( $header_vals ) == 0 ) {
				$header_vals = array_shift( $table );
			}
		}
		// Now "flip" the data, turning it into a column-by-column
		// array, instead of row-by-row.
		$values = array();
		foreach ( $table as $line ) {
			foreach ( $line as $i => $row_val ) {
				if ( $has_header ) {
					if ( array_key_exists( $i, $header_vals ) ) {
						$column = strtolower( trim( $header_vals[$i] ) );
					} else {
						$column = '';
						wfDebug( "External Data: number of values per line appears to be inconsistent in CSV file." );
					}
				} else {
					// start with an index of 1 instead of 0
					$column = $i + 1;
				}
				$row_val = trim( $row_val );
				if ( array_key_exists( $column, $values ) )
					$values[$column][] = $row_val;
				else
					$values[$column] = array( $row_val );
			}
		}
		return $values;
	}

	/**
	 * This function handles version 3 of the genomic-data format GFF,
	 * defined here:
	 * http://www.sequenceontology.org/gff3.shtml
	 */
	static function getGFFData( $gff ) {
		// use an fgetcsv() call, similar to the one in getCSVData()
		// (fgetcsv() can handle delimiters other than commas, in this
		// case a tab)
		$fiveMBs = 5 * 1024 * 1024;
		$fp = fopen( "php://temp/maxmemory:$fiveMBs", 'r+' );
		fputs( $fp, $gff );
		rewind( $fp );
		$table = array();
		while ( $line = fgetcsv( $fp, null, "\t" ) ) {
			// ignore comment lines
			if ( strpos( $line[0], '##' ) !== 0 ) {
				// special handling for final 'attributes' column
				if ( array_key_exists( 8, $line ) ) {
					$attributes = explode( ';', $line[8] );
					foreach ( $attributes as $attribute ) {
						$keyAndValue = explode( '=', $attribute, 2 );
						if ( count( $keyAndValue ) == 2 ) {
							$key = strtolower( $keyAndValue[0] );
							$value = $keyAndValue[1];
							$line[$key] = $value;
						}
					}
				}
				array_push( $table, $line );
			}
		}
		fclose( $fp );
		// now "flip" the data, turning it into a column-by-column
		// array, instead of row-by-row
		if ( $has_header ) {
			$header_vals = array_shift( $table );
		}
		$values = array();
		foreach ( $table as $line ) {
			foreach ( $line as $i => $row_val ) {
				// each of the columns in GFF have a
				// pre-defined name - even the last column
				// has its own name, "attributes"
				if ( $i === 0 ) {
					$column = 'seqid';
				} elseif ( $i == 1 ) {
					$column = 'source';
				} elseif ( $i == 2 ) {
					$column = 'type';
				} elseif ( $i == 3 ) {
					$column = 'start';
				} elseif ( $i == 4 ) {
					$column = 'end';
				} elseif ( $i == 5 ) {
					$column = 'score';
				} elseif ( $i == 6 ) {
					$column = 'strand';
				} elseif ( $i == 7 ) {
					$column = 'phase';
				} elseif ( $i == 8 ) {
					$column = 'attributes';
				} else {
					// this is hopefully an attribute key
					$column = $i;
				}
				if ( array_key_exists( $column, $values ) )
					$values[$column][] = $row_val;
				else
					$values[$column] = array( $row_val );
			}
		}
		return $values;
	}

	/**
	 * Recursive JSON-parsing function for use by getJSONData().
	 */
	static function parseTree( $tree, &$retrieved_values ) {
		foreach ( $tree as $key => $val ) {
			// TODO - this logic could probably be a little nicer.
			if ( is_array( $val ) && count( $val ) > 1 ) {
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
				if ( array_key_exists( $key, $retrieved_values ) ) {
					$retrieved_values[$key][] = $val;
				} else {
					$retrieved_values[$key] = array( $val );
				}
			}
		}
	}

	static function getJSONData( $json ) {
		$json_tree = FormatJson::decode( $json, true );
		$values = array();
		if ( is_array( $json_tree ) ) {
			self::parseTree( $json_tree, $values );
		}
		return $values;
	}

	static function fetchURL( $url, $post_vars = array(), $get_fresh = false, $try_count = 1 ) {
		$dbr = wfGetDB( DB_SLAVE );
		global $edgStringReplacements, $edgCacheTable,
			$edgCacheExpireTime, $edgAllowSSL;

		if ( $post_vars ) {
			return Http::post( $url, array( 'postData' => $post_vars ) );
		}

		// do any special variable replacements in the URLs, for
		// secret API keys and the like
		foreach ( $edgStringReplacements as $key => $value ) {
			$url = str_replace( $key, $value, $url );
		}

		if ( !isset( $edgCacheTable ) || is_null( $edgCacheTable ) ) {
			if ( $edgAllowSSL ) {
				$contents = Http::get( $url, 'default', array( 'sslVerifyCert' => false, 'followRedirects' => false ) );
			} else {
				$contents = Http::get( $url );
			}
			// Handle non-UTF-8 encodings.
			// Copied from http://www.php.net/manual/en/function.file-get-contents.php#85008
			return mb_convert_encoding( $contents, 'UTF-8',
				mb_detect_encoding( $contents, 'UTF-8, ISO-8859-1', true ) );
		}

		// check the cache (only the first 254 chars of the url)
		$row = $dbr->selectRow( $edgCacheTable, '*', array( 'url' => substr( $url, 0, 254 ) ), 'EDUtils::fetchURL' );

		if ( $row && ( ( time() - $row->req_time ) > $edgCacheExpireTime ) ) {
			$get_fresh = true;
		}

		if ( !$row || $get_fresh ) {
			if ( $edgAllowSSL ) {
				$page = Http::get( $url, 'default', array( CURLOPT_SSL_VERIFYPEER => false ) );
			} else {
				$page = Http::get( $url );
			}
			if ( $page === false ) {
				sleep( 1 );
				if ( $try_count >= self::$http_number_of_tries ) {
					echo wfMessage( 'externaldata-db-could-not-get-url', self::$http_number_of_tries )->text();
					return '';
				}
				$try_count++;
				return self::fetchURL( $url, $post_vars, $get_fresh, $try_count );
			}
			if ( $page != '' ) {
				$dbw = wfGetDB( DB_MASTER );
				// Delete the old entry, if one exists.
				$dbw->delete( $edgCacheTable, array( 'url' => substr( $url, 0, 254 )));
				// Insert contents into the cache table.
				$dbw->insert( $edgCacheTable, array( 'url' => substr( $url, 0, 254 ), 'result' => $page, 'req_time' => time() ) );
				return $page;
			}
		} else {
			return $row->result;
		}
	}

	/**
	 * Checks whether this URL is allowed, based on the
	 * $edgAllowExternalDataFrom whitelist
	 */
	static public function isURLAllowed( $url ) {
		// this code is based on Parser::maybeMakeExternalImage()
		global $edgAllowExternalDataFrom;
		$data_from = $edgAllowExternalDataFrom;
		$text = false;
		if ( empty( $data_from ) ) {
			return true;
		} elseif ( is_array( $data_from ) ) {
			foreach ( $data_from as $match ) {
				if ( strpos( $url, $match ) === 0 ) {
					return true;
				}
			}
			return false;
		} else {
			if ( strpos( $url, $data_from ) === 0 ) {
				return true;
			} else {
				return false;
			}
		}
	}

	static public function getDataFromURL( $url, $format, $mappings, $postData = null ) {
		$url_contents = self::fetchURL( $url, $postData );
		// exit if there's nothing there
		if ( empty( $url_contents ) )
			return array();

		if ( $format == 'xml' ) {
			return self::getXMLData( $url_contents );
		} elseif ( $format == 'xml with xpath' ) {
			return self::getXPathData( $url_contents, $mappings, $url );
		} elseif ( $format == 'csv' ) {
			return self::getCSVData( $url_contents, false );
		} elseif ( $format == 'csv with header' ) {
			return self::getCSVData( $url_contents, true );
		} elseif ( $format == 'json' ) {
			return self::getJSONData( $url_contents );
		} elseif ( $format == 'gff' ) {
			return self::getGFFData( $url_contents );
		} else {
			return wfMessage( 'externaldata-web-invalid-format', $format )->text();
		}
		return array();
	}

}
