<?php
/**
 * Utility functions for External Data
 */

class EDUtils {

	private const STATUS_OK = 0;
	private const STATUS_POST_FAILED = 1;
	private const STATUS_STALE = 2;
	private const STATUS_URL_NO_DATA = 4;
	private const STATUS_CACHE_HIT = 8;

	// how many times to try an HTTP request
	private static $http_number_of_tries = 3;

	private static $ampersandReplacement = "THIS IS A LONG STRING USED AS A REPLACEMENT FOR AMPERSANDS 55555555";

	/**
	 * Wraps error message in a span with the "error" class, for better
	 * display, and so that it can be handled correctly by #iferror and
	 * possibly others.
	 */
	static function formatErrorMessage( $msg ) {
		return '<span class="error">' . $msg . '</span>';
	}

	/**
	 * This method and endElement() below it are both based on code found at
	 * http://us.php.net/xml_set_element_handler
	 */
	static function startElement( $parser, $name, $attrs ) {
		global $edgCurrentXMLTag, $edgCurrentValue, $edgXMLValues;

		// Set to all lowercase to avoid casing issues.
		$edgCurrentXMLTag = strtolower( $name );
		$edgCurrentValue = '';
		foreach ( $attrs as $attr => $value ) {
			$attr = strtolower( $attr );
			$value = str_replace( self::$ampersandReplacement, '&amp;', $value );
			if ( array_key_exists( $attr, $edgXMLValues ) ) {
				$edgXMLValues[$attr][] = $value;
			} else {
				$edgXMLValues[$attr] = [ $value ];
			}
		}
	}

	static function endElement( $parser, $name ) {
		global $edgCurrentXMLTag, $edgCurrentValue, $edgXMLValues;

		if ( array_key_exists( $edgCurrentXMLTag, $edgXMLValues ) ) {
			$edgXMLValues[$edgCurrentXMLTag][] = $edgCurrentValue;
		} else {
			$edgXMLValues[$edgCurrentXMLTag] = [ $edgCurrentValue ];
		}
		// Clear the value both here and in startElement(), in case this
		// is an embedded tag.
		$edgCurrentValue = '';
	}

	/**
	 * Due to the strange way xml_set_character_data_handler() runs,
	 * getContent() may get called multiple times, once for each fragment
	 * of the text, for very long XML values. Given that, we keep a global
	 * variable with the current value and add to it.
	 */
	static function getContent( $parser, $content ) {
		global $edgCurrentValue;

		// Replace ampersands, to avoid the XML getting split up
		// around them.
		// Note that this is *escaped* ampersands being replaced -
		// this is unrelated to the fact that bare ampersands aren't
		// allowed in XML.
		$content = str_replace( self::$ampersandReplacement, '&amp;', $content );
		$edgCurrentValue .= $content;
	}

	static function parseParams( $params ) {
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
	 * Parses an argument of the form "a=b,c=d,..." into an array
	 */
	static function paramToArray( $arg, $lowercaseKeys = false, $lowercaseValues = false ) {
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

		$returnArray = [];
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
		global $edgLDAPServer, $edgLDAPUser, $edgLDAPPass;

		$ds = self::connectLDAP( $edgLDAPServer[$domain], $edgLDAPUser[$domain], $edgLDAPPass[$domain] );
		$results = self::searchLDAP( $ds, $domain, $filter, $params );

		return $results;
	}

	static function connectLDAP( $server, $username, $password ) {
		// Check that the PHP LDAP library is installed.
		if ( !function_exists( 'ldap_connect' ) ) {
			echo ( "Error: you must have a PHP LDAP library installed in order to call #get_ldap_data." );
		}

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

			// should check the result of the bind here
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

	static function getDBData( $dbID, $from, $columns, $where, $sqlOptions, $joinOn, $otherParams ) {
		global $edgDBServerType, $edgDBServer, $edgDBDirectory, $edgDBName,
			$edgDBUser, $edgDBPass, $edgDBFlags, $edgDBTablePrefix;

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
		if ( $db_type === 'mongodb' ) {
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
			// We don't check the username or password because they
			// could legitimately be blank or null.
			if ( $db_server == '' || $db_name == '' ) {
				return wfMessage( "externaldata-db-incomplete-information" )->text();
			}
		}

		if ( $db_flags == '' ) {
			$db_flags = DBO_DEFAULT;
		}

		$dbConnectionParams = [
			'host' => $db_server,
			'user' => $db_username,
			'password' => $db_password,
			'dbname' => $db_name,
			'flags' => $db_flags,
			'tablePrefix' => $db_tableprefix,
		];
		if ( $db_type === 'sqlite' ) {
			$dbConnectionParams['dbDirectory'] = $db_directory;
		}

		$db = Database::factory( $db_type, $dbConnectionParams );
		if ( $db == null ) {
			return wfMessage( 'externaldata-db-unknown-type' )->text();
		}
		if ( !$db->isOpen() ) {
			return wfMessage( 'externaldata-db-could-not-connect' )->text();
		}

		if ( count( $columns ) === 0 ) {
			return wfMessage( 'externaldata-db-no-return-values' )->text();
		}

		$rows = self::searchDB( $db, $from, $columns, $where, $sqlOptions, $joinOn );
		$db->close();

		if ( !is_array( $rows ) ) {
			// It's an error message.
			return $rows;
		}

		$values = [];
		foreach ( $rows as $row ) {
			foreach ( $columns as $column ) {
				$values[$column][] = $row[$column];
			}
		}

		return $values;
	}

	static function getValueFromJSONArray( array $origArray, $path, $default = null ) {
		$current = $origArray;
		$token = strtok( $path, '.' );

		while ( $token !== false ) {
			if ( !isset( $current[$token] ) ) {
				return $default;
			}
			$current = $current[$token];
			$token = strtok( '.' );
		}
		return $current;
	}

	/**
	 * Handles #get_db_data for the non-relational database system
	 * MongoDB.
	 */
	static function getMongoDBData( $db_server, $db_username, $db_password, $db_name, $from, $columns, $where, $sqlOptions, $otherParams ) {
		global $wgMainCacheType, $wgMemc, $edgMemCachedMongoDBSeconds;

		// Use MEMCACHED if configured to cache mongodb queries.
		if ( $wgMainCacheType === CACHE_MEMCACHED && $edgMemCachedMongoDBSeconds > 0 ) {
			// Check if cache entry exists.
			$mckey = wfMemcKey( 'mongodb', $from, md5( json_encode( $otherParams ) . json_encode( $columns ) . $where . json_encode( $sqlOptions ) . $db_name . $db_server ) );
			$values = $wgMemc->get( $mckey );

			if ( $values !== false ) {
				return $values;
			}
		}

		// MongoDB login is done using a single string.
		// When specifying extra connect string options (e.g. replicasets,timeout, etc.),
		// use $db_server to pass these values
		// see http://docs.mongodb.org/manual/reference/connection-string
		$connect_string = "mongodb://";
		if ( $db_username != '' ) {
			$connect_string .= $db_username . ':' . $db_password . '@';
		}
		if ( $db_server != '' ) {
			$connect_string .= $db_server;
		} else {
			$connect_string .= 'localhost:27017';
		}

		// Use try/catch to suppress error messages, which would show
		// the MongoDB connect string, which may have sensitive
		// information.
		try {
			$m = new MongoClient( $connect_string );
		} catch ( Exception $e ) {
			return wfMessage( "externaldata-db-could-not-connect" )->text();
		}

		$db = $m->selectDB( $db_name );

		// Check if collection exists
		if ( $db->system->namespaces->findOne( [ 'name' => $db_name . "." . $from ] ) === null ) {
			return wfMessage( "externaldata-db-unknown-collection:" )->text() . $db_name . "." . $from;
		}

		$collection = new MongoCollection( $db, $from );

		$findArray = [];
		$aggregateArray = [];
		// Was an aggregation pipeline command issued?
		if ( array_key_exists( 'aggregate', $otherParams ) ) {
			// The 'aggregate' parameter should be an array of
			// aggregation JSON pipeline commands.
			// Note to users: be sure to use spaces between curly
			// brackets in the 'aggregate' JSON so as not to trip up the
			// MW parser.
			$aggregateArray = json_decode( $otherParams['aggregate'], true );
		} elseif ( array_key_exists( 'find query', $otherParams ) ) {
			// Otherwise, was a direct MongoDB "find" query JSON string provided?
			// If so, use that. As with 'aggregate' JSON, use spaces
			// between curly brackets
			$findArray = json_decode( $otherParams['find query'], true );
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
					$findArray[trim( $fieldName )] = [ '$gte' => trim( $value ) ];
				} elseif ( strpos( $whereElement, '>' ) ) {
					list( $fieldName, $value ) = explode( '>', $whereElement );
					$findArray[trim( $fieldName )] = [ '$gt' => trim( $value ) ];
				} elseif ( strpos( $whereElement, '<=' ) ) {
					list( $fieldName, $value ) = explode( '<=', $whereElement );
					$findArray[trim( $fieldName )] = [ '$lte' => trim( $value ) ];
				} elseif ( strpos( $whereElement, '<' ) ) {
					list( $fieldName, $value ) = explode( '<', $whereElement );
					$findArray[trim( $fieldName )] = [ '$lt' => trim( $value ) ];
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

		// Do the same for the "order=" parameter as the "where=" parameter
		$sortArray = [];
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
			if ( $sqlOptions['ORDER BY'] != '' ) {
				$aggregateArray[] = [ '$sort' => $sortArray ];
			}
			if ( $sqlOptions['LIMIT'] != '' ) {
				$aggregateArray[] = [ '$limit' => intval( $sqlOptions['LIMIT'] ) ];
			}
			$aggregateResult = $collection->aggregate( $aggregateArray );
			$resultsCursor = $aggregateResult['result'];
		} else {
			$resultsCursor = $collection->find( $findArray, $columns )->sort( $sortArray )->limit( $sqlOptions['LIMIT'] );
		}

		$values = [];
		foreach ( $resultsCursor as $doc ) {
			foreach ( $columns as $column ) {
				if ( strstr( $column, "." ) ) {
					// If the exact path of the value was
					// specified using dots (e.g., "a.b.c"),
					// get the value that way.
					$values[$column][] = self::getValueFromJSONArray( $doc, $column );
				} elseif ( isset( $doc[$column] ) && is_array( $doc[$column] ) ) {
					// If MongoDB returns an array for a column,
					// but the exact location of the value wasn't specified,
					// do some extra processing.
					if ( $column == 'geometry' && array_key_exists( 'coordinates', $doc['geometry'] ) ) {
						// Check if it's GeoJSON geometry.
						// http://www.geojson.org/geojson-spec.html#geometry-objects
						// If so, return it in a format that
						// the Maps extension can understand.
						$coordinates = $doc['geometry']['coordinates'][0];
						$coordinateStrings = [];
						foreach ( $coordinates as $coordinate ) {
							$coordinateStrings[] = $coordinate[1] . ',' . $coordinate[0];
						}
						$values[$column][] = implode( ':', $coordinateStrings );
					} else {
						// Just return it as JSON, the
						// lingua franca of MongoDB.
						$values[$column][] = json_encode( $doc[$column] );
					}
				} else {
					// It's a simple literal.
					$values[$column][] = ( isset( $doc[$column] ) ? $doc[$column] : null );
				}
			}
		}

		if ( $wgMainCacheType === CACHE_MEMCACHED && $edgMemCachedMongoDBSeconds > 0 ) {
			$wgMemc->set( $mckey, $values, $edgMemCachedMongoDBSeconds );
		}

		return $values;
	}

	static function searchDB( $db, $from, $vars, $conds, $sqlOptions, $joinOn ) {
		// The format of $from can be just "TableName", or the more
		// complex "Table1=Alias1,Table2=Alias2,...".
		$tables = [];
		$tableStrings = explode( ',', $from );
		foreach ( $tableStrings as $tableString ) {
			if ( strpos( $tableString, '=' ) !== false ) {
				$tableStringParts = explode( '=', $tableString, 2 );
				$tableName = trim( $tableStringParts[0] );
				$alias = trim( $tableStringParts[1] );
			} else {
				$tableName = $alias = trim( $tableString );
			}
			$tables[$alias] = $tableName;
		}
		$joinConds = [];
		$joinStrings = explode( ',', $joinOn );
		foreach ( $joinStrings as $i => $joinString ) {
			if ( $joinString == '' ) {
				continue;
			}
			if ( strpos( $joinString, '=' ) === false ) {
				return "Error: every \"join on\" string must contain an \"=\" sign.";
			}
			if ( count( $tables ) <= $i + 1 ) {
				return "Error: too many \"join on\" conditions.";
			}
			$aliases = array_keys( $tables );
			$alias = $aliases[$i + 1];
			$joinConds[$alias] = [ 'JOIN', $joinString ];
		}
		$result = $db->select( $tables, $vars, $conds, 'EDUtils::searchDB', $sqlOptions, $joinConds );
		if ( !$result ) {
			return wfMessage( "externaldata-db-invalid-query" )->text();
		}

		$rows = [];
		while ( $row = $db->fetchRow( $result ) ) {
			// Create a new row object that uses the passed-in
			// column names as keys, so that there's always an
			// exact match between what's in the query and what's
			// in the return value (so that "a.b", for instance,
			// doesn't get chopped off to just "b").
			$new_row = [];
			foreach ( $vars as $i => $column_name ) {
				$dbField = $row[$i];
				// This can happen with MSSQL.
				if ( $dbField instanceof DateTime ) {
					$dbField = $dbField->format( 'Y-m-d H:i:s' );
				}
				// Convert the encoding to UTF-8
				// if necessary - based on code at
				// http://www.php.net/manual/en/function.mb-detect-encoding.php#102510
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
		$edgXMLValues = [];

		// Remove comments from XML - for some reason, xml_parse()
		// can't handle them.
		$xml = preg_replace( '/<!--.*?-->/s', '', $xml );

		// Also, re-insert ampersands, after they were removed to
		// avoid parsing problems.
		$xml = str_replace( '&amp;', self::$ampersandReplacement, $xml );

		$xml_parser = xml_parser_create();
		xml_set_element_handler( $xml_parser, [ __CLASS__, 'startElement' ], [ __CLASS__, 'endElement' ] );
		xml_set_character_data_handler( $xml_parser, [ __CLASS__, 'getContent' ] );
		if ( !xml_parse( $xml_parser, $xml, true ) ) {
			return wfMessage( 'externaldata-xml-error',
			xml_error_string( xml_get_error_code( $xml_parser ) ),
			xml_get_current_line_number( $xml_parser ) )->text();
		}
		xml_parser_free( $xml_parser );
		return $edgXMLValues;
	}

	static function isNodeNotEmpty( $node ) {
		return trim( $node[0] ) !== '';
	}

	static function filterEmptyNodes( $nodes ) {
		if ( !is_array( $nodes ) ) {
			return $nodes;
		}
		return array_filter( $nodes, [ 'EDUtils', 'isNodeNotEmpty' ] );
	}

	static function getXMLXPathData( $xml, $mappings, $ns ) {
		global $edgXMLValues;

		try {
			$sxml = new SimpleXMLElement( $xml );
		} catch ( Exception $e ) {
			return "Caught exception parsing XML: " . $e->getMessage();
		}
		$edgXMLValues = [];

		foreach ( $mappings as $local_var => $xpath ) {
			// First, register any necessary XML namespaces, to
			// avoid "Undefined namespace prefix" errors.
			$matches = [];
			preg_match_all( '/[\/\@]([a-zA-Z0-9]*):/', $xpath, $matches );
			foreach ( $matches[1] as $namespace ) {
				$sxml->registerXPathNamespace( $namespace, $ns );
			}

			// Now, get all the matching values, and remove any
			// empty results.
			$nodes = self::filterEmptyNodes( $sxml->xpath( $xpath ) );
			if ( !$nodes ) {
				continue;
			}

			// Convert from SimpleXMLElement to string.
			$nodesArray = [];
			foreach ( $nodes as $xmlNode ) {
				$nodesArray[] = (string)$xmlNode;
			}

			if ( array_key_exists( $xpath, $edgXMLValues ) ) {
				// At the moment, this code will never get
				// called, because duplicate values in
				// $mappings will have been removed already.
				$edgXMLValues[$xpath] = array_merge( $edgXMLValues[$xpath], $nodesArray );
			} else {
				$edgXMLValues[$xpath] = $nodesArray;
			}
		}
		return $edgXMLValues;
	}

	static function getHTMLData( $html, array $mappings, $css ) {
		$doc = new DOMDocument( '1.0', 'UTF-8' );
		// Remove whitespaces.
		$doc->preserveWhiteSpace = false;

		// Otherwise, the encoding will be broken. Yes, it's an abstraction leak.
		[ $encoding, $_ ] = self::findEncodingInText( $html );
		if ( !$encoding ) {
			$html = preg_replace( '/<\?xml[^?]+\?>/i', '', $html );
			$html = '<?xml version="1.0" encoding="UTF-8" ?>' . $html;
		}

		// Try to recover really crappy HTML.
		$html = preg_replace( [ '/\\\\"/' ], [ '&quot;' ], $html );
		// Relax HTML strictness, see https://stackoverflow.com/a/7386650.
		$doc->recover = true;
		$doc->strictErrorChecking = false;

		// Try to parse HTML.
		try {
			// Give the log a rest. See https://stackoverflow.com/a/10482622.
			$internalErrors = libxml_use_internal_errors( true ); // -- remember.
			if ( !$doc->loadHTML( $html ) ) {
				return wfMessage( 'externaldata-parsing-html-failed' )->text();
			}
			// Report errors.
			foreach ( libxml_get_errors() as $error ) {
				wfDebug( "HTML parsing error {$error->code} in line {$error->line}, column {$error->column}: {$error->message}" );
			}
			libxml_clear_errors();
			libxml_use_internal_errors( $internalErrors ); // -- restore.
		} catch ( Exception $e ) {
			wfDebug( wfMessage( 'externaldata-caught-exception-parsing-html', $e->getMessage() )->text() );
			return wfMessage( 'externaldata-caught-exception-parsing-html', $e->getMessage() )->text();
		}
		global $edgHTMLValues;
		$edgHTMLValues = [];

		$domxpath = new DOMXPath( $doc );
		if ( $css ) {
			$converter = new Symfony\Component\CssSelector\CssSelectorConverter();
		}
		foreach ( $mappings as $local_var => $query ) {
			// Convert CSS selectors to XPaths.
			if ( $css ) {
				preg_match( '/(?<selector>.+?)(\.\s*attr\s*\(\s*(?<quote>["\']?)(?<attr>.+?)\k<quote>\s*\))?$/i', $query, $matches );
				try {
					$xpath = $converter->toXPath( $matches ['selector'] );
				} catch ( Exception $e ) {
					return wfMessage( 'externaldata-error-converting-css-to-xpath', $e->getMessage() )->text();
				}
				$xpath = '/' . strtr( $xpath, [
					'descendant-or-self::*' => '',
					'descendant-or-self::' => '/'
				] );
				$attr = isset( $matches ['attr'] ) ? $matches ['attr'] : null;
			} else {
				$xpath = $query;
				$attr = null;
			}

			// Try to select nodes with XPath:
			$nodesArray	= [];
			try {
				$entries = $domxpath->evaluate( $xpath );
			} catch ( Exception $e ) {
				wfDebug( wfMessage( 'externaldata-xpath-invalid', $xpath, $e->getMessage() )->text() );
				return wfMessage( 'externaldata-xpath-invalid', $xpath, $e->getMessage() )->text();
			}
			if ( is_a( $entries, 'DOMNodeList' ) ) {
				// It's a list of DOM nodes.
				foreach ( $entries as $entry ) {
					$values = $attr ? $entry->attributes[$attr]->nodeValue : $entry->textContent;
					$nodesArray[] = self::filterEmptyNodes( $values );
				}
			} else {
				// It's some calculated value.
				$nodesArray = is_array( $entries ) ? $entries : [ $entries ];
			}

			if ( array_key_exists( $xpath, $edgHTMLValues ) ) {
				// At the moment, this code will never get
				// called, because duplicate values in
				// $mappings will have been removed already.
				$edgHTMLValues[$query] = array_merge( $edgHTMLValues[$xpath], $nodesArray );
			} else {
				$edgHTMLValues[$query] = $nodesArray;
			}
		}
		return $edgHTMLValues;
	}

	static function getValuesFromCSVLine( $csv_line ) {
		// regular expression copied from http://us.php.net/fgetcsv
		$vals = preg_split( '/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/', $csv_line );
		$vals2 = [];
		foreach ( $vals as $val ) {
			$vals2[] = trim( $val, '"' );
		}
		return $vals2;
	}

	static function getCSVData( $csv, $has_header, $delimiter = ',' ) {
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
			$table = [];
			while ( $line = fgetcsv( $fp, 0, $delimiter ) ) {
				array_push( $table, $line );
			}
			fclose( $fp );
		// }

		// Get rid of blank characters - these sometimes show up
		// for certain encodings.
		foreach ( $table as $i => $row ) {
			foreach ( $row as $j => $cell ) {
				$table[$i][$j] = str_replace( chr( 0 ), '', $cell );
			}
		}

		// Get rid of the "byte order mark", if it's there - it could
		// be one of a variety of options, depending on the encoding.
		// Code copied in part from:
		// http://artur.ejsmont.org/blog/content/annoying-utf-byte-order-marks
		$sets = [
			"\xFE",
			"\xFF",
			"\xFE\xFF",
			"\xFF\xFE",
			"\xEF\xBB\xBF",
			"\x2B\x2F\x76",
			"\xF7\x64\x4C",
			"\x0E\xFE\xFF",
			"\xFB\xEE\x28",
			"\x00\x00\xFE\xFF",
			"\xDD\x73\x66\x73",
		];
		$decodedFirstCell = utf8_decode( $table[0][0] );
		foreach ( $sets as $set ) {
			if ( 0 == strncmp( $decodedFirstCell, $set, strlen( $set ) ) ) {
				$table[0][0] = substr( $decodedFirstCell, strlen( $set ) + 1 );
				break;
			}
		}

		// Another "byte order mark" test, this one copied from the
		// Data Transfer extension - somehow the first one doesn't work
		// in all cases.
		$byteOrderMark = pack( "CCC", 0xef, 0xbb, 0xbf );
		if ( 0 == strncmp( $table[0][0], $byteOrderMark, 3 ) ) {
			$table[0][0] = substr( $table[0][0], 3 );
		}

		// Get header values, if this is 'csv with header'
		if ( $has_header ) {
			$header_vals = array_shift( $table );
			// On the off chance that there are one or more blank
			// lines at the beginning, cycle through.
			while ( count( $header_vals ) == 0 ) {
				$header_vals = array_shift( $table );
			}
		}

		// Unfortunately, some subpar CSV generators don't include
		// trailing commas, so that a line that should look like
		// "A,B,,," instead is just printed as "A,B".
		// To get around this, we first figure out the correct number
		// of columns in this table - which depends on whether the
		// CSV has a header or not.
		if ( $has_header ) {
			$num_columns = count( $header_vals );
		} else {
			$num_columns = 0;
			foreach ( $table as $line ) {
				$num_columns = max( $num_columns, count( $line ) );
			}
		}

		// Now "flip" the data, turning it into a column-by-column
		// array, instead of row-by-row.
		$values = [];
		foreach ( $table as $line ) {
			for ( $i = 0; $i < $num_columns; $i++ ) {
				// This check is needed in case it's an
				// uneven CSV file (see above).
				if ( array_key_exists( $i, $line ) ) {
					$row_val = trim( $line[$i] );
				} else {
					$row_val = '';
				}
				if ( $has_header ) {
					$column = strtolower( trim( $header_vals[$i] ) );
				} else {
					// start with an index of 1 instead of 0
					$column = $i + 1;
				}
				if ( array_key_exists( $column, $values ) ) {
					$values[$column][] = $row_val;
				} else {
					$values[$column] = [ $row_val ];
				}
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
		$table = [];
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

		$values = [];
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
				if ( array_key_exists( $column, $values ) ) {
					$values[$column][] = $row_val;
				} else {
					$values[$column] = [ $row_val ];
				}
			}
		}
		return $values;
	}

	/**
	 * Helper function that determines whether an array holds a simple
	 * list of scalar values, with no keys (i.e., not an associative
	 * array).
	 */
	static function holdsSimpleList( $arr ) {
		$expectedKey = 0;
		foreach ( $arr as $key => $val ) {
			if ( is_array( $val ) || $key != $expectedKey++ ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Recursive JSON-parsing function for use by getJSONData().
	 */
	static function parseTree( $tree, &$retrieved_values ) {
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
				if ( array_key_exists( $key, $retrieved_values ) ) {
					$retrieved_values[$key][] = $val;
				} else {
					$retrieved_values[$key] = [ $val ];
				}
			}
		}
	}

	static function getJSONData( $json, $prefixLength ) {
		$json = substr( $json, $prefixLength );
		$json_tree = FormatJson::decode( $json, true );
		if ( $json_tree === null ) {
			// It's probably invalid JSON.
			return wfMessage( 'externaldata-invalid-json' )->text();
		}
		$values = [];
		if ( is_array( $json_tree ) ) {
			self::parseTree( $json_tree, $values );
		}
		return $values;
	}

	static function getJSONPathData( $json, $mappings ) {
		global $edgJSONValues;

		$jsonObject = new EDJsonObject( $json );
		foreach ( $mappings as $jsonpath ) {
			$edgJSONValues[$jsonpath] = $jsonObject->get( $jsonpath );
		}
		return $edgJSONValues;
	}

	private static function findEncodingInText( $text ) {
		$encoding_regexes = [
			// charset must be in the capture #3.
			'/<\?xml([^>]+)encoding\s*=\s*(["\']?)([^"\'>]+)\2[^>]*\?>/i' => '<?xml$1encoding="UTF-8"?>',
			'%<meta([^>]+)(charset)\s*=\s*([^"\'>]+)([^>]*)/?>%i' => '<meta$1charset=UTF-8$4>',
			'%<meta(\s+)charset\s*=\s*(["\']?)([^"\'>]+)\2([^>]*)/?>%i' => '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'
		];
		foreach ( $encoding_regexes as $pattern => $replacement ) {
			if ( preg_match( $pattern, $text, $matches ) ) {
				$new_text = preg_replace( $pattern, $replacement, $text, 1 );
				$encoding = $matches [3];
				return [ $encoding, $new_text ];
			}
		}
		return [ null, $text ];
	}

	private static function detectEncodingAndConvertToUTF8( &$text, $encoding = null, $headers = null ) {
		$encoding_found_in_XML = false;
		if ( !$encoding ) {
			// No encoding is set in parser function call.

			// First, try to find it in the XML/HTML:
			[ $encoding, $text ] = self::findEncodingInText( $text );

			// Secondly, try HTTP headers.
			if ( !$encoding && $headers && isset( $headers['content-type'] ) ) {
				if ( preg_match( '/charset\s*=\s*(?<charset>[^\s;]+)/i', implode( ',', $headers['content-type'] ), $matches ) ) {
					$encoding = $matches['charset'];
				}
			}

			// Finally, try mb_detect_encoding.
			if ( !$encoding ) {
				global $edgTryEncodings;
				$encoding = mb_detect_encoding( $text, $edgTryEncodings, true ); // -- strict.
			}
		}

		// Convert $text:
		// Is it UTF-8 or ISO-8859-1?
		if ( $encoding && strtoupper( $encoding ) !== 'UTF-8' ) {
			$text = mb_convert_encoding( $text, 'UTF-8', $encoding );
		}

		return $encoding;
	}

	private static function fetchURL( $url, $post_vars, $cacheExpireTime, $useStaleCache, $encoding_override ) {
		// Do any special variable replacements in the URLs, for
		// secret API keys and the like.
		global $edgStringReplacements;
		foreach ( $edgStringReplacements as $key => $value ) {
			$url = str_replace( $key, $value, $url );
		}
		global $edgHTTPOptions;
		$options = $edgHTTPOptions;

		// We do not cache POST requests.
		if ( $post_vars ) {
			$post_options = array_merge( isset( $options['postData'] ) ? $options['postData'] : [], $post_vars );
			Hooks::run( 'ExternalDataBeforeWebCall', [ 'post', $url, $post_options ] );
			list( $contents, $headers ) = EDHttpWithHeaders::post( $url,  $post_options );
			$encoding =	self::detectEncodingAndConvertToUTF8( $contents, $encoding_override, $headers );
			return [ $contents ? self::STATUS_OK : self::STATUS_POST_FAILED, $contents, time(), false, 0, $encoding ];
		}

		// TODO: Think of moving caching to EDUtils::getDataFromText() or EDUtils::getDataFromURL().
		// Is the cache set up, present and fresh?
		global $edgCacheTable;
		$cache_set_up = (bool)$edgCacheTable;
		if ( $cache_set_up && ( $cacheExpireTime !== 0 || $useStaleCache ) ) {
			// Cache set up and can be used.
			// check the cache (only the first 254 chars of the url)
			$dbr = wfGetDB( DB_REPLICA );
			$row = $dbr->selectRow( $edgCacheTable, '*', [ 'url' => substr( $url, 0, 254 ) ], __METHOD__ );
			$cache_present = (bool)$row;
			if ( $cache_present ) {
				$cached = $row->result;
				$cached_time = $row->req_time;
				$cache_fresh = $cacheExpireTime !== 0 && time() - $cached_time <= $cacheExpireTime;
			}
		}

		// If there is no fresh cache, try to get from the web.
		$tries = 0;
		$time = null;
		$stale = false;
		$encoding = null;

		if ( !$cache_set_up || !$cache_present || !$cache_fresh || $cacheExpireTime === 0 ) {
			// Continue forming set HTTP request fields.
			global $edgAllowSSL;
			if ( $edgAllowSSL ) {
				$options['sslVerifyCert'] = isset( $options['sslVerifyCert'] ) ? $options['sslVerifyCert'] : false;
				$options['followRedirects'] = isset( $options['followRedirects'] ) ? $options['followRedirects'] : false;
			}
			Hooks::run( 'ExternalDataBeforeWebCall', [ 'get', $url, $options ] );
			// Actually send a request.
			do {
				list( $contents, $headers ) = EDHttpWithHeaders::get( $url, $options, __METHOD__ );
			} while ( !$contents && ++$tries <= self::$http_number_of_tries );
			if ( $contents ) {
				// Fetched successfully.
				$status = self::STATUS_OK;
				// Encoding is detected here and not later in EDUtils::getDataFromText(),
				// so that we can cache the converted text.
				$encoding =	self::detectEncodingAndConvertToUTF8( $contents, $encoding_override, $headers );
				$stale = false;
				$time = time();
				// Update cache, if possible and required.
				if ( $cache_set_up && $cacheExpireTime !== 0 ) {
					$dbw = wfGetDB( DB_MASTER );
					// Delete the old entry, if one exists.
					if ( $cache_present ) {
						$dbw->delete( $edgCacheTable, [ 'url' => substr( $url, 0, 254 ) ] );
					}
					// Insert contents into the cache table.
					$dbw->insert( $edgCacheTable, [ 'url' => substr( $url, 0, 254 ), 'result' => $contents, 'req_time' => time() ] );
				}
			} else {
				// Not fetched.
				if ( $cache_present && $useStaleCache ) {
					// But can serve stale cache, if any and allowed.
					$status = self::STATUS_STALE;
					$contents = $cached;
					$stale = true;
					$time = $cached_time;
				} else {
					// Nothing to serve.
					// In debug, $url with secret parts is OK.
					wfDebug( wfMessage( 'externaldata-db-could-not-get-url', $url, self::$http_number_of_tries )->text() );
					$status = self::STATUS_URL_NO_DATA;
					$contents = '';
				}
			}
		} else {
			// We have a fresh cache; so serve it.
			$status = self::STATUS_CACHE_HIT;
			$contents = $cached;
			$time = $cached_time;
		}
		return [ $status, $contents, $time, $stale, $tries, $encoding ];
	}

	private static function getDataFromText( $contents, $format, $mappings, $source, $prefixLength = 0, $regex = null, $encoding = null ) {
		if ( !$encoding ) {
			// Encoding has not been detected earlier by EDUtils::fetchURL(). So, detect now.
			$encoding = self::detectEncodingAndConvertToUTF8( $contents, $encoding );
		}
		// For now, this is only done for the CSV formats.
		if ( is_array( $format ) ) {
			list( $format, $delimiter ) = $format;
		} else {
			$delimiter = ',';
		}
		switch ( $format ) {
			case 'xml':
				return self::getXMLData( $contents );
			case 'xml with xpath':
				return self::getXMLXPathData( $contents, $mappings, $source );
			case 'html':
				return self::getHTMLData( $contents, $mappings, true );
			case 'html with xpath':
				return self::getHTMLData( $contents, $mappings, false );
			case 'csv':
				return self::getCSVData( $contents, false, $delimiter );
			case 'csv with header':
				return self::getCSVData( $contents, true, $delimiter );
			case 'json':
				return self::getJSONData( $contents, $prefixLength );
			case 'json with jsonpath':
				return self::getJSONPathData( $contents, $mappings );
			case 'gff':
				return self::getGFFData( $contents );
			case 'text':
				return $regex === null ? [ 'text' => $contents ] : self::getRegexData( $contents, $regex );
			default:
				return wfMessage( 'externaldata-web-invalid-format', $format )->text();
		}
	}

	/**
	 * Checks whether this URL is allowed, based on the
	 * $edgAllowExternalDataFrom whitelist
	 */
	public static function isURLAllowed( $url ) {
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

	public static function getDataFromURL( $url, $format, $mappings, $postData, $cacheExpireTime, $useStaleCache, $prefixLength, $regex, $encoding ) {
		// We need encoding this early, because we want to cache text converted to UTF-8.
		list( $status, $url_contents, $time, $stale, $tries, $encoding )
			= self::fetchURL( $url, $postData, $cacheExpireTime, $useStaleCache, $encoding );
		switch ( $status ) {
			case self::STATUS_OK:
			case self::STATUS_CACHE_HIT:
			case self::STATUS_STALE:
				$parsed = self::getDataFromText( $url_contents, $format, $mappings, $url, $prefixLength, $regex, $encoding );
				if ( is_array( $parsed ) ) {
					$parsed['__time'] = [ $time ];
					$parsed['__stale'] = [ $stale ? 'stale' : 'fresh' ];
					$parsed['__tries'] = [ $tries ];
				}
				return $parsed;
			case self::STATUS_URL_NO_DATA:
				return wfMessage( 'externaldata-db-could-not-get-url', $url, self::$http_number_of_tries )->text();
			case self::STATUS_POST_FAILED:
				return wfMessage( 'externaldata-post-failed', $url )->text();
			default:
				// This code should never be reached.
				return wfMessage( 'externaldata-url-unknown-error', $url )->text();
		}
	}

	private static function getDataFromPath( $path, $format, $mappings, $regex, $encoding ) {
		if ( !file_exists( $path ) ) {
			return 'Error: No file found.';
		}
		$file_contents = file_get_contents( $path );
		// Show an error message if there's nothing there.
		if ( empty( $file_contents ) ) {
			return "Error: Unable to get file contents.";
		}

		return self::getDataFromText( $file_contents, $format, $mappings, $path, 0, $regex, $encoding );
	}

	public static function getDataFromFile( $file, $format, $mappings, $regex, $encoding ) {
		global $edgFilePath;

		if ( array_key_exists( $file, $edgFilePath ) ) {
			return self::getDataFromPath( $edgFilePath[$file], $format, $mappings, $regex, $encoding );
		} else {
			return "Error: No file is set for ID \"$file\".";
		}
	}

	public static function getDataFromDirectory( $directory, $fileName, $format, $mappings, $regex, $encoding ) {
		global $edgDirectoryPath;

		if ( array_key_exists( $directory, $edgDirectoryPath ) ) {
			$directoryPath = $edgDirectoryPath[$directory];
			$path = realpath( $directoryPath . $fileName );
			if ( $path !== false && strpos( $path, $directoryPath ) === 0 ) {
				return self::getDataFromPath( $path, $format, $mappings, $regex, $encoding );
			} else {
				return "Error: File name \"$fileName\" is not allowed for directory ID \"$directory\".";
			}
		} else {
			return "Error: No directory is set for ID \"$directory\".";
		}
	}

	/**
	 * Recursive function, used by getSOAPData().
	 */
	public static function getValuesForKeyInTree( $key, $tree ) {
		// The passed-in tree can be either an array or a stdObject -
		// we need it to be an array.
		if ( is_object( $tree ) ) {
			$tree = get_object_vars( $tree );
		}
		$values = [];
		foreach ( $tree as $curKey => $curValue ) {
			if ( is_object( $curValue ) || is_array( $curValue ) ) {
				$additionalValues = self::getValuesForKeyInTree( $key, $curValue );
				$values = array_merge( $values, $additionalValues );
			} elseif ( $curKey == $key ) {
				$values[] = $curValue;
			}
		}
		return $values;
	}

	public static function getSOAPData( $url, $requestName, $requestData, $responseName, $mappings ) {
		$client = new SoapClient( $url );
		try {
			$result = $client->$requestName( $requestData );
		} catch ( Exception $e ) {
			return "Caught exception: " . $e->getMessage();
		}

		$realResultJSON = $result->$responseName;
		if ( $realResultJSON == '' ) {
			return 'Error: no data found for this set of "requestData" fields.';
		}

		$realResult = json_decode( $realResultJSON );
		$errorKey = '#Error:';
		if ( array_key_exists( $errorKey, $realResult ) ) {
			return 'Error: ' . $realResult->$errorKey;
		}

		$values = [];
		foreach ( $mappings as $fieldName ) {
			$values[$fieldName] = self::getValuesForKeyInTree( $fieldName, $realResult );
		}
		return $values;
	}

	public static function getRegexData( $text, $regex ): array {
		$matches = [];

		if ( method_exists( AtEase::class, 'suppressWarnings' ) ) {
			// MW >= 1.33
			AtEase::suppressWarnings();
		} else {
			\MediaWiki\suppressWarnings();
		}
		preg_match_all( $regex, $text, $matches, PREG_PATTERN_ORDER );
		if ( method_exists( AtEase::class, 'restoreWarnings' ) ) {
			// MW >= 1.33
			AtEase::restoreWarnings();
		} else {
			\MediaWiki\restoreWarnings();
		}

		return $matches;
	}

}
