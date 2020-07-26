<?php
/**
 * Utility functions for External Data
 *
 * @author Yaron Koren
 */

class EDUtils {

	private const STATUS_OK = 0;
	private const STATUS_POST_FAILED = 1;
	private const STATUS_STALE = 2;
	private const STATUS_URL_NO_DATA = 4;
	private const STATUS_CACHE_HIT = 8;

	// how many times to try an HTTP request
	private static $http_number_of_tries = 3;

	/**
	 * Wraps error message in a span with the "error" class, for better
	 * display, and so that it can be handled correctly by #iferror and
	 * possibly others.
	 *
	 * @param string $msg Error message.
	 *
	 * @return string Wrapped error message.
	 */
	public static function formatErrorMessage( $msg ) {
		return '<span class="error">' . $msg . '</span>';
	}

	/**
	 * A helper function, called by EDParserFunctions::saveMappedAndFilteredValues and by Lua functions in Scribunto_ExternalData.
	 */
	public static function mapAndFilterValues( array $external_values, array $filters, array $mappings ) {
		foreach ( $filters as $filter_var => $filter_value ) {
			// Find the entry of $external_values that matches
			// the filter variable; if none exists, just ignore
			// the filter.
			if ( array_key_exists( $filter_var, $external_values ) ) {
				if ( is_array( $external_values[$filter_var] ) ) {
					$column_values = $external_values[$filter_var];
					foreach ( $column_values as $i => $single_value ) {
						// if a value doesn't match
						// the filter value, remove
						// the value from this row for
						// all columns
						if ( trim( $single_value ) !== trim( $filter_value ) ) {
							foreach ( $external_values as $external_var => $external_value ) {
								unset( $external_values[$external_var][$i] );
							}
						}
					}
				} else {
					// if we have only one row of values,
					// and the filter doesn't match, just
					// keep the results array blank and
					// return
					if ( $external_values[$filter_var] != $filter_value ) {
						return;
					}
				}
			}
		}
		// for each external variable name specified in the function
		// call, get its value or values (if any exist), and attach it
		// or them to the local variable name
		$result = [];
		foreach ( $mappings as $local_var => $external_var ) {
			if ( array_key_exists( $external_var, $external_values ) ) {
				if ( is_array( $external_values[$external_var] ) ) {
					// array_values() restores regular
					// 1, 2, 3 indexes to array, after unset()
					// in filtering may have removed some
					$result[$local_var] = array_values( $external_values[$external_var] );
				} else {
					$result[$local_var][] = $external_values[$external_var];
				}
			}
		}
		return $result;
	}

	/**
	 * Preprocess parameter needed to make a web connection.
	 */
	private static function getWebParams( array $args ) {
		if ( array_key_exists( 'url', $args ) ) {
			$url = $args['url'];
		} else {
			return [ false, self::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'url' )->parse() ) ];
		}
		$url = str_replace( ' ', '%20', $url ); // -- do some minor URL-encoding.
		// If the URL isn't allowed (based on a whitelist), exit.
		if ( !self::isURLAllowed( $url ) ) {
			return [ false, self::formatErrorMessage( 'URL is not allowed' ) ];
		}

		$postData = array_key_exists( 'post data', $args ) ? $args['post data'] : null;

		// Cache expiration.
		global $edgCacheExpireTime;
		$cacheExpireTime = array_key_exists( 'cache seconds', $args ) ? max( $args['cache seconds'], $edgCacheExpireTime ) : $edgCacheExpireTime;

		// Allow to use stale cache.
		global $edgAlwaysAllowStaleCache;
		$useStaleCache = array_key_exists( 'use stale cache', $args ) || $edgAlwaysAllowStaleCache;

		return [ true, $url, $postData, $cacheExpireTime, $useStaleCache ];
	}

	/**
	 * Core of the {{#get_web_data:}} parser function and mw.ext.getExternalData.getWebData.
	 */
	public static function doGetWebData( array $params ) {
		$parser = EDParserBase::getParser( self::paramToArray( $params, true, false ) );
		if ( !is_a( $parser, 'EDParserBase' ) ) {
			// It's an error message.
			return $parser;
		}

		// URL and other connection settings.
		list( $success, $url, $postData, $cacheExpireTime, $useStaleCache ) = self::getWebParams( $params );
		if ( !$success ) {
			// URL is not supplied, valid or allowed. $url is an error message.
			return $url;
		}

		// Actually fetch data from URL.
		$method = __METHOD__;
		$external_values = self::getDataFromURL( function ( $url, array $options ) use( $method ) {
			// This function actually handles HTTP request.
			return EDHttpWithHeaders::get( $url, $options, $method );
		}, $url, $parser, $postData, $cacheExpireTime, $useStaleCache );

		if ( is_string( $external_values ) ) {
			// It's an error message - display it on the screen.
			return self::formatErrorMessage( $external_values );
		}
		if ( count( $external_values ) === 0 ) {
			return;
		}

		return $external_values;
	}

	/**
	 * Core of the {{#get_file_data:}} parser function and mw.ext.getExternalData.getFileData.
	 */
	public static function doGetFileData( array $params ) {
		$parser = EDParserBase::getParser( self::paramToArray( $params, true, false ) );
		if ( !is_a( $parser, 'EDParserBase' ) ) {
			// It's an error message.
			return $parser;
		}

		// Parameters specific to {{#get_file_data:}} / mw.ext.externalData.getFileData.
		$file = null;
		$directory = null;
		$fileName = null;
		if ( array_key_exists( 'file', $params ) ) {
			$file = $params['file'];
		} elseif ( array_key_exists( 'directory', $params ) ) {
			$directory = $params['directory'];
			if ( array_key_exists( 'file name', $params ) ) {
				$fileName = $params['file name'];
			} else {
				return self::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'file name' )->parse() );
			}
		} else {
			return self::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'file|directory' )->parse() );
		}

		// Actually fetch data from file/directory.
		if ( $file ) {
			$external_values = self::getDataFromFile( $file, $parser );
		} else {
			$external_values = self::getDataFromDirectory( $directory, $fileName, $parser );
		}

		if ( is_string( $external_values ) ) {
			// It's an error message - display it on the screen.
			return self::formatErrorMessage( $external_values );
		}
		if ( count( $external_values ) === 0 ) {
			return;
		}

		return $external_values;
	}

	/**
	 * Core of the {{#get_soap_data:}} parser function and mw.ext.getExternalData.getSoapData.
	 */
	public static function doGetSoapData( array $params ) {
		if ( !class_exists( 'SoapClient' ) ) {
			return self::formatErrorMessage(
				wfMessage( 'externaldata-missing-library', 'SOAP', '{{#get_soap_data:}}', 'mw.ext.getExternalData.getSoapData' )->text()
			);
		}
		$args = self::paramToArray( $params, true, false );

		// URL and other connection settings.
		list( $success, $url, $postData, $cacheExpireTime, $useStaleCache ) = self::getWebParams( $args );
		if ( !$success ) {
			// URL is not supplied, valid or allowed. $url is an error message.
			return $url;
		}

		if ( array_key_exists( 'request', $args ) ) {
			$requestName = $args['request'];
		} else {
			return self::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'request' )->parse() );
		}

		$requestData = array_key_exists( 'requestData', $args ) ? self::paramToArray( $args['requestData'] ) : null;

		if ( array_key_exists( 'response', $args ) ) {
			$responseName = $args['response'];
		} else {
			return self::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'response' )->parse() );
		}

		if ( !isset( $args['format'] ) ) {
			$args['format'] = 'json';
		}
		$parser = EDParserBase::getParser( self::paramToArray( $args, true, false ) );

		if ( !is_a( $parser, 'EDParserBase' ) ) {
			// It's an error message.
			return $parser;
		}

		// Actually fetch SOAP data from URL.
		$external_values = self::getDataFromURL( function ( $url, array $options ) use ( $requestName, $requestData, $responseName ) {
			// This function actually handles SOAP request.
			$client = new SoapClient( $url, [ 'trace' => true ] );
			try {
				$result = $client->$requestName( $requestData );
			} catch ( Exception $e ) {
				$result = null;
			}
			if ( $result ) {
				$contents = $result->$responseName;
			}
			return [ $contents, $client->__getLastResponseHeaders() ];
		}, $url, $parser, $postData, $cacheExpireTime, $useStaleCache );

		if ( is_string( $external_values ) ) {
			// It's an error message - display it on the screen.
			return self::formatErrorMessage( $external_values );
		}

		return $external_values;
	}

	/**
	 * A helper function. Parses an argument of the form "a=b,c=d,..." into an array. If it is already an array, only converts the case.
	 *
	 * @param string|array $arg Values to parse.
	 * @param bool $lowercaseKeys bring keys to lower case.
	 * @param bool $lowercaseValues bring values to lower case.
	 *
	 * @return array Parsed parameter.
	 */
	public static function paramToArray( $arg, $lowercaseKeys = false, $lowercaseValues = false ) {
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
				$keyAndValue = explode( '=', $keyValuePair, 2 );
				if ( count( $keyAndValue ) === 2 ) {
					$splitArray[trim( $keyAndValue[0] )] = trim( $keyAndValue[1] );
				}
			}
		} else {
			// It's already an array.
			$splitArray = $arg;
		}
		// Set the letter case as required.
		$caseConvertedArray = [];
		foreach ( $splitArray as $key => $value ) {
			$caseConvertedArray[$lowercaseKeys ? strtolower( $key ) : $key] = $lowercaseValues ? strtolower( $value ) : $value;
		}
		return $caseConvertedArray;
	}

	/**
	 * Called by both {{#get_ldap_data:}} and mw.ext.externalData.getLdapData.
	 */
	public static function doGetLDAPData( array $params ) {
		if ( !function_exists( 'ldap_connect' ) ) {
			return self::formatErrorMessage(
				wfMessage( 'externaldata-missing-library', 'LDAP', '{{#get_ldap_data:}}', 'mw.ext.externalData.getLdapData' )->text()
			);
		}
		$args = self::paramToArray( $params, true, false ); // parse params into name-value pairs
		if ( array_key_exists( 'data', $args ) ) {
			$mappings = self::paramToArray( $args['data'] ); // parse the data arg into mappings
		} else {
			return self::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'data' )->parse() );
		}
		if ( !array_key_exists( 'filter', $args ) ) {
			return self::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'filter' )->parse() );
		}
		$all = array_key_exists( 'all', $args );
		$external_values = self::getLDAPData( $args['filter'], $args['domain'], array_values( $mappings ) );
		if ( !is_array( $external_values ) ) {
			// This is an error message.
			return self::formatErrorMessage( $external_values );
		}
		$result = [];
		foreach ( $external_values as $i => $row ) {
			if ( !is_array( $row ) ) {
				continue;
			}
			foreach ( $mappings as $local_var => $external_var ) {
				if ( !array_key_exists( $local_var, $result ) ) {
					$result[$local_var] = [];
				}
				if ( array_key_exists( $external_var, $row ) ) {
					if ( $all ) {
						foreach ( $row[$external_var] as $j => $value ) {
							if ( $j !== 'count' ) {
								$result[$local_var][] = $value;
							}
						}
					} else {
						$result[$local_var][] = $row[$external_var][0];
					}
				} else {
					$result[$local_var][] = '';
				}
			}
		}
		return $result;
	}

	private static function getLDAPData( $filter, $domain, $params ) {
		global $edgLDAPServer, $edgLDAPUser, $edgLDAPPass;

		$ds = self::connectLDAP( $edgLDAPServer[$domain], $edgLDAPUser[$domain], $edgLDAPPass[$domain] );
		if ( !is_resource( $ds ) ) {
			// This is an error message.
			return $ds;
		}
		$results = self::searchLDAP( $ds, $domain, $filter, $params );

		return $results;
	}

	private static function connectLDAP( $server, $username, $password ) {
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

	private static function searchLDAP( $ds, $domain, $filter, $attributes ) {
		global $edgLDAPBaseDN;

		$sr = ldap_search( $ds, $edgLDAPBaseDN[$domain], $filter, $attributes );
		$results = ldap_get_entries( $ds, $sr );
		return $results;
	}

	private static function getArrayValue( $arrayName, $key ) {
		if ( array_key_exists( $key, $arrayName ) ) {
			return $arrayName[$key];
		} else {
			return null;
		}
	}

	/**
	 * Called by both {{#get_db_data:}} and mw.ext.getExternalData.getDbData.
	 */
	public static function doGetDBData( array $args ) {
		$data = ( array_key_exists( 'data', $args ) ) ? $args['data'] : null;
		if ( array_key_exists( 'db', $args ) ) {
			$dbID = $args['db'];
		} elseif ( array_key_exists( 'server', $args ) ) {
			// For backwards-compatibility - 'db' parameter was
			// added in External Data version 1.3.
			$dbID = $args['server'];
		} else {
			return self::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'db' )->parse() );
		}
		if ( array_key_exists( 'from', $args ) ) {
			$from = $args['from'];
		} else {
			return self::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'from' )->parse() );
		}
		$conds = ( array_key_exists( 'where', $args ) ) ? $args['where'] : null;
		$limit = ( array_key_exists( 'limit', $args ) ) ? $args['limit'] : null;
		$orderBy = ( array_key_exists( 'order by', $args ) ) ? $args['order by'] : null;
		$groupBy = ( array_key_exists( 'group by', $args ) ) ? $args['group by'] : null;
		$sqlOptions = [ 'LIMIT' => $limit, 'ORDER BY' => $orderBy, 'GROUP BY' => $groupBy ];
		$joinOn = ( array_key_exists( 'join on', $args ) ) ? $args['join on'] : null;
		$otherParams = [];
		if ( array_key_exists( 'aggregate', $args ) ) {
			$otherParams['aggregate'] = $args['aggregate'];
		} elseif ( array_key_exists( 'find query', $args ) ) {
			$otherParams['find query'] = $args['find query'];
		}
		$mappings = self::paramToArray( $data ); // parse the data arg into mappings

		$external_values = self::getDBData( $dbID, $from, array_values( $mappings ), $conds, $sqlOptions, $joinOn, $otherParams );

		// Handle error cases.
		if ( !is_array( $external_values ) ) {
			return self::formatErrorMessage( $external_values );
		}

		// Map, filter and return external values.
		return self::mapAndFilterValues( $external_values, [], $mappings );
	}

	/**
	 * Actually query the database.
	 */
	private static function getDBData( $dbID, $from, $columns, $where, $sqlOptions, $joinOn, $otherParams ) {
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
			if ( !$db_name ) {
				return wfMessage( "externaldata-db-incomplete-information" )->text();
			}
			return self::getMongoDBData( $db_server, $db_username, $db_password, $db_name, $from, $columns, $where, $sqlOptions, $otherParams );
		}

		// Validate parameters
		if ( !$db_type ) {
			return wfMessage( "externaldata-db-incomplete-information" )->text();
		} elseif ( $db_type === 'sqlite' ) {
			if ( !$db_directory || !$db_name ) {
				return wfMessage( "externaldata-db-incomplete-information" )->text();
			}
		} else {
			// We don't check the username or password because they
			// could legitimately be blank or null.
			if ( !$db_server || !$db_name ) {
				return wfMessage( "externaldata-db-incomplete-information" )->text();
			}
		}

		if ( !$db_flags ) {
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
		if ( !$db ) {
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

	private static function getValueFromJSONArray( array $origArray, $path, $default = null ) {
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
	private static function getMongoDBData( $db_server, $db_username, $db_password, $db_name, $from, $columns, $where, $sqlOptions, $otherParams ) {
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

	private static function searchDB( $db, $from, $vars, $conds, $sqlOptions, $joinOn ) {
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

	/**
	 * Detect encoding based on tags in the $text, $headers and $encoding override.
	 * Convert $text to UTF-8.
	 *
	 * @param string &$text Text to convert.
	 * @param string|null $encoding Encoding override.
	 * @param array|null $headers HTTP headers.
	 *
	 * @return string The detected encoding.
	 */
	public static function detectEncodingAndConvertToUTF8( &$text, $encoding = null, $headers = null ) {
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

	/**
	 * Common code for fetching URL and sending SOAP request. Handles caching.
	 * @param callable $fetcher should get two arguments: URL and an array of HTTP options.
	 */
	private static function fetch( callable $fetcher, $url, $post_vars, $cacheExpireTime, $useStaleCache, $encoding_override ) {
		// Do any special variable replacements in the URLs, for
		// secret API keys and the like.
		global $edgStringReplacements;
		foreach ( $edgStringReplacements as $key => $value ) {
			$url = str_replace( $key, $value, $url );
		}
		global $edgHTTPOptions;
		// TODO: handle HTTP options per site.
		$options = $edgHTTPOptions;

		// We do not cache POST requests.
		if ( $post_vars ) {
			$post_options = array_merge( isset( $options['postData'] ) ? $options['postData'] : [], $post_vars );
			Hooks::run( 'ExternalDataBeforeWebCall', [ 'post', $url, $post_options ] );
			list( $contents, $headers ) = EDHttpWithHeaders::post( $url,  $post_options );
			$encoding =	self::detectEncodingAndConvertToUTF8( $contents, $encoding_override, $headers );
			return [ $contents ? self::STATUS_OK : self::STATUS_POST_FAILED, $contents, time(), false, 0, $encoding ];
		}

		// Initialize some caching variables.
		$cache_present = false;
		$cached = false;
		$cached_time = null;
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
			do {
				// Actually send a request.
				list( $contents, $headers ) = call_user_func( $fetcher, $url, $options );
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

	/**
	 * Checks whether this URL is allowed, based on the
	 * $edgAllowExternalDataFrom whitelist
	 */
	private static function isURLAllowed( $url ) {
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

	/**
	 * This function gets and processes data from both HTTP GET and POST requests and SOAP requests.
	 *
	 * @param callable $fetcher is the function that implements either HTTP or SOAP.
	 * @param string $url is the URL to fetch.
	 * @param EDParserBase $parser is the text parser.
	 *
	 * @return array A column based two-dimensional array of parsed values.
	 *
	 */
	private static function getDataFromURL( callable $fetcher, $url, EDParserBase $parser, $postData, $cacheExpireTime, $useStaleCache ) {
		// We need encoding this early, because we want to cache text converted to UTF-8.
		list( $status, $contents, $time, $stale, $tries, $encoding )
			= self::fetch( $fetcher, $url, $postData, $cacheExpireTime, $useStaleCache, $parser->encoding() );
		switch ( $status ) {
			case self::STATUS_OK:
			case self::STATUS_CACHE_HIT:
			case self::STATUS_STALE:
				$parsed = $parser( $contents, [
					'__time' => [ $time ],
					'__stale' => [ $stale ],
					'__tries' => [ $tries ]
				] );
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

	/**
	 * Get data from absolute filepath.
	 *
	 * @param string $path Filepath.
	 * @param EDParserBase $parser Text parser.
	 *
	 * @return array An column based two-dimensional array of values.
	 *
	 */
	private static function getDataFromPath( $path, EDParserBase $parser ) {
		if ( !file_exists( $path ) ) {
			return 'Error: No file found.';
		}
		$file_contents = file_get_contents( $path );
		// Show an error message if there's nothing there.
		if ( empty( $file_contents ) ) {
			return "Error: Unable to get file contents.";
		}

		return $parser( $file_contents, [
			'__time' => [ time() ]
		] );
	}

	/**
	 * Get data from file in a directory.
	 *
	 * @param string $file File alias.
	 * @param EDParserBase $parser Text parser.
	 *
	 * @return array An column based two-dimensional array of values.
	 *
	 */
	private static function getDataFromFile( $file, EDParserBase $parser ) {
		global $edgFilePath;

		if ( array_key_exists( $file, $edgFilePath ) ) {
			return self::getDataFromPath( $edgFilePath[$file], $parser );
		} else {
			// TODO: message.
			return "Error: No file is set for ID \"$file\".";
		}
	}

	/**
	 * Get data from file in an aliased directory.
	 *
	 * @param string $directory Directory alias.
	 * @param string $fileName Local file name in the directory.
	 * @param EDParserBase $parser Text parser.
	 *
	 * @return array An column based two-dimensional array of values.
	 *
	 */
	private static function getDataFromDirectory( $directory, $fileName, EDParserBase $parser ) {
		global $edgDirectoryPath;

		if ( array_key_exists( $directory, $edgDirectoryPath ) ) {
			$directoryPath = $edgDirectoryPath[$directory];
			$path = realpath( $directoryPath . $fileName );
			if ( $path !== false && strpos( $path, $directoryPath ) === 0 ) {
				return self::getDataFromPath( $path, $parser );
			} else {
				// TODO: message.
				return "Error: File name \"$fileName\" is not allowed for directory ID \"$directory\".";
			}
		} else {
			// TODO: message.
			return "Error: No directory is set for ID \"$directory\".";
		}
	}
}
