<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for MongoDB database type.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */
class EDConnectorMongodb extends EDConnectorDb {
	/** @var string $mongo_client_class The name of relevant class for MongoDB client. */
	private $mongo_client_class;
	/** @var string $connect_string MondoDB connection string. */
	private $connect_string;
	/** @var array $aggregate MongoDB aggregate. */
	private $aggregate = [];
	/** @var array $find MongoDB find query. */
	private $find = [];
	/** @var array $sort MongoDB sort. */
	private $sort = [];
	/** @var string|null $cache_key A key for Memcached/APC. */
	private $cache_key = null;

	/**
	 * Constructor.
	 *
	 * @param array $args An array of arguments for parser/Lua function.
	 */
	protected function __construct( array $args ) {
		parent::__construct( $args );

		// TODO: check if MongoDB is at all available.
		// PHP 7: MongoDB.
		// PHP 5: mongo.
		$mongo_regex_class = class_exists( 'MongoDB\BSON\Regex' ) ? 'MongoDB\BSON\Regex' : 'MongoRegex';
		$this->mongo_client_class = class_exists( 'MongoDB\Driver\Manager' ) ? 'MongoDB\Driver\Manager' : 'MongoClient';

		// Was an aggregation pipeline command issued?
		if ( isset( $args['aggregate'] ) ) {
			// The 'aggregate' parameter should be an array of
			// aggregation JSON pipeline commands.
			// Note to users: be sure to use spaces between curly
			// brackets in the 'aggregate' JSON so as not to trip up the
			// MW parser.
			$this->aggregate = json_decode( $args['aggregate'], true );
		} elseif ( isset( $args['find query'] ) ) {
			// Otherwise, was a direct MongoDB "find" query JSON string provided?
			// If so, use that. As with 'aggregate' JSON, use spaces
			// between curly brackets
			$this->find = json_decode( $args['find query'], true );
		} elseif ( $this->conditions ) {
			// If not, turn the SQL of the "where=" parameter into
			// a "find" array for MongoDB. Note that this approach
			// is only appropriate for simple find queries, that
			// use the operators OR, AND, >=, >, <=, < and LIKE
			// - and NO NUMERIC LITERALS.
			$where = str_ireplace( ' and ', ' AND ', $this->conditions );
			$where = str_ireplace( ' like ', ' LIKE ', $where );
			$whereElements = explode( ' AND ', $where );
			foreach ( $whereElements as $whereElement ) {
				if ( strpos( $whereElement, '>=' ) ) {
					list( $fieldName, $value ) = explode( '>=', $whereElement );
					$this->find[trim( $fieldName )] = [ '$gte' => trim( $value ) ];
				} elseif ( strpos( $whereElement, '>' ) ) {
					list( $fieldName, $value ) = explode( '>', $whereElement );
					$this->find[trim( $fieldName )] = [ '$gt' => trim( $value ) ];
				} elseif ( strpos( $whereElement, '<=' ) ) {
					list( $fieldName, $value ) = explode( '<=', $whereElement );
					$this->find[trim( $fieldName )] = [ '$lte' => trim( $value ) ];
				} elseif ( strpos( $whereElement, '<' ) ) {
					list( $fieldName, $value ) = explode( '<', $whereElement );
					$this->find[trim( $fieldName )] = [ '$lt' => trim( $value ) ];
				} elseif ( strpos( $whereElement, ' LIKE ' ) ) {
					list( $fieldName, $value ) = explode( ' LIKE ', $whereElement );
					$value = trim( $value );
					$this->find[trim( $fieldName )] = new $mongo_regex_class( "/$value/i" );
				} else {
					list( $fieldName, $value ) = explode( '=', $whereElement );
					$this->find[trim( $fieldName )] = trim( $value );
				}
			}
		}

		// Do the same for the "order=" parameter as the "where=" parameter
		if ( $this->sql_options['ORDER BY'] ) {
			$sortElements = explode( ',', $this->sql_options['ORDER BY'] );
			foreach ( $sortElements as $sortElement ) {
				$parts = explode( ' ', $sortElement );
				$fieldName = $parts[0];
				$orderingNum = 1;
				if ( count( $parts ) > 1 ) {
					if ( strtolower( $parts[1] ) === 'desc' ) {
						$orderingNum = -1;
					}
				}
				$this->sort[$fieldName] = $orderingNum;
			}
		}

		if ( count( $this->aggregate ) > 0 ) {
			if ( $this->sql_options['ORDER BY'] ) {
				$this->aggregate[] = [ '$sort' => $this->sort ];
			}
			if ( $this->sql_options['LIMIT'] ) {
				$this->aggregate[] = [ '$limit' => intval( $this->sql_options['LIMIT'] ) ];
			}
		}

		// Make a key for Memcached/APC.
		global $wgMainCacheType, $edgMemCachedMongoDBSeconds;
		if ( ( $wgMainCacheType === CACHE_MEMCACHED || $wgMainCacheType === CACHE_ACCEL ) && $edgMemCachedMongoDBSeconds > 0 ) {
			$this->cache_key = wfMemcKey( 'mongodb', $this->from, md5(
				json_encode( $this->aggregate ) .
				json_encode( $this->find ) .
				json_encode( $this->sort ) .
				json_encode( $this->columns ) .
				$this->conditions .
				json_encode( $this->sql_options ) .
				$this->connection['db_name'] .
				$this->connection['host']
			) );
		}
	}

	/**
	 * Actually connect to the external data source.
	 * It is presumed that there are no errors in parameters and wiki settings.
	 * Set $this->values and $this->errors.
	 *
	 * @return bool True on success, false if error were encountered.
	 */
	public function run() {
		// Use Memcached if configured to cache mongodb queries.
		$cached = $this->cached();
		if ( $cached ) {
			$this->values = $cached;
			return true;
		}

		// Use try/catch to suppress error messages, which would show
		// the MongoDB connect string, which may have sensitive
		// information.
		$class = $this->mongo_client_class;
		try {
			$m = new $class( $this->connect_string );
		} catch ( Exception $e ) {
			$this->error( 'externaldata-db-could-not-connect' );
			return false;
		}

		$db = $m->selectDB( $this->connection['dbname'] );

		// Check if collection exists.
		if ( !in_array( $this->from, $db->getCollectionNames(), true ) ) {
			$this->error( 'externaldata-db-unknown-collection', $this->db_id . ':' . $this->from ); // Not $this->connection['dbname']!
			return false;
		}

		$collection = new MongoCollection( $db, $this->from );

		// Get the data!
		if ( count( $this->aggregate ) > 0 ) {
			$aggregateResult = $collection->aggregate( $this->aggregate );
			if ( !$aggregateResult['ok'] ) {
				$this->error( 'externaldata-db-aggregation-failed', $aggregateResult['errmsg'] );
				return false;
			}
			$resultsCursor = $aggregateResult['result'];
		} else {
			$resultsCursor = $collection->find( $this->find, $this->columns )->sort( $this->sort )->limit( $this->sql_options['LIMIT'] );
		}

		// Arrange values returned by MongoDB in a column-based array.
		$this->values = $this->arrange( $resultsCursor );

		// Cache, is so configured.
		$this->cache( $this->values );

		return true;
	}

	/**
	 * Arrange values returned by MongoDB in a column-based array.
	 *
	 * @param array $results Results from MongoDB.
	 *
	 * @return array $values Column-based array of values.
	 */
	private function arrange( array $results ) {
		$values = [];
		foreach ( $results as $doc ) {
			foreach ( $this->columns as $column ) {
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
		return $values;
	}

	/**
	 * A helper function to get values from JSON.
	 *
	 * @param array $origArray A multi-dimentional array of values.
	 * @param string $path A dot-separated path to value.
	 * @param string|null $default Default value.
	 *
	 * @return array
	 */
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
	 * Form connection settings for database from $this->db_id.
	 *
	 * @param array $params Supplemented parameters.
	 */
	protected function setConnection( array $params ) {
		parent::setConnection( $params );
		$this->connection['host'] = isset( $params['DBServer'] ) ? $params['DBServer'] : 'localhost:27017';

		// MongoDB login is done using a single string.
		// When specifying extra connect string options (e.g. replicasets,timeout, etc.),
		// use $edgDBServer[$this->db_id] to pass these values
		// see http://docs.mongodb.org/manual/reference/connection-string
		$this->connect_string = "mongodb://";
		if ( $this->connection['user'] ) {
			$this->connect_string .= $this->connection['user'] . ':' . $this->connection['password'] . '@';
		}
		$this->connect_string .= $this->connection['host'];
	}

	/**
	 * Look up Memcached/APC, if set up and configured to cache MongoDB queries.
	 *
	 * @return array|null Stored values or null, if no storage is configured.
	 */
	private function cached() {
		if ( $this->cache_key ) {
			// Check if cache entry exists.
			return wfGetMainCache()->get( $this->cache_key );
		} else {
			return null;
		}
	}

	/**
	 * Save $values in Memcached/APC, if set up and so configured.
	 *
	 * @param array $values Values to store.
	 */
	private function cache( array $values ) {
		if ( $this->cache_key ) {
			global $edgMemCachedMongoDBSeconds;
			wfGetMainCache()->set( $this->cache_key, $values, $edgMemCachedMongoDBSeconds );
		}
	}
}
