<?php
/**
 * Abstract class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for MongoDB database type.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */
abstract class EDConnectorMongodb extends EDConnectorComposed {
	/** @var bool $keepExternalVarsCase Whether external variables' names are case-sensitive for this format. */
	public $keepExternalVarsCase = true;

	/** @var string $regexClass Class that stores MongoDB regular expressions. */
	protected static $regexClass;

	/** @var string MondoDB connection string. */
	protected $connectString;
	/** @var array MongoDB aggregate. */
	protected $aggregate = [];
	/** @var array MongoDB find query. */
	private $find = [];
	/** @var array MongoDB sort. */
	private $sort = [];
	/** @var string|null A key for Memcached/APC. */
	private $cacheKey;
	/** @var float $cacheSeconds Cache for so many seconds. */
	private $cacheSeconds = 0;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		// This connector needs an explicit set of fields.
		if ( !array_key_exists( 'data', $args ) ) {
			$this->error( 'externaldata-no-param-specified', 'data' );
		}

		// Was an aggregation pipeline command issued?
		if ( isset( $args['aggregate'] ) ) {
			// The 'aggregate' parameter should be an array of
			// aggregation JSON pipeline commands.
			// Note to users: be sure to use spaces between curly
			// brackets in the 'aggregate' JSON so as not to trip up the
			// MW parser.
			$this->aggregate = json_decode( $args['aggregate'], true );
			if ( !$this->aggregate ) {
				$this->error( 'externaldata-invalid-format', 'JSON' );
			}
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
			if ( is_array( $this->conditions ) ) {
				$where_elements = $this->conditions;
			} else {
				$where = str_ireplace( ' and ', ' AND ', $this->conditions );
				$where_elements = explode( ' AND ', $where );
			}
			foreach ( $where_elements as $key => $where_element ) {
				$where_element = str_ireplace( ' like ', ' LIKE ', $where_element );
				if ( strpos( $where_element, '>=' ) ) {
					[ $field_name, $value ] = explode( '>=', $where_element );
					$this->find[trim( $field_name )] = [ '$gte' => trim( $value ) ];
				} elseif ( strpos( $where_element, '>' ) ) {
					[ $field_name, $value ] = explode( '>', $where_element );
					$this->find[trim( $field_name )] = [ '$gt' => trim( $value ) ];
				} elseif ( strpos( $where_element, '<=' ) ) {
					[ $field_name, $value ] = explode( '<=', $where_element );
					$this->find[trim( $field_name )] = [ '$lte' => trim( $value ) ];
				} elseif ( strpos( $where_element, '<' ) ) {
					[ $field_name, $value ] = explode( '<', $where_element );
					$this->find[trim( $field_name )] = [ '$lt' => trim( $value ) ];
				} elseif ( strpos( $where_element, ' LIKE ' ) ) {
					[ $field_name, $value ] = explode( ' LIKE ', $where_element );
					$value = trim( $value );
					$regex_class = static::$regexClass; // late binding.
					$this->find[trim( $field_name )] = new $regex_class( "/$value/i" );
				} elseif ( strpos( $where_element, '=' ) ) {
					[ $field_name, $value ] = explode( '=', $where_element );
					$this->find[trim( $field_name )] = trim( $value );
				} elseif ( is_string( $key ) ) {
					$this->find[$key] = $where_element;
				}
			}
		}

		// Do the same for the "order=" parameter as the "where=" parameter
		if ( $this->sqlOptions['ORDER BY'] ) {
			$sort_slements = explode( ',', $this->sqlOptions['ORDER BY'] );
			foreach ( $sort_slements as $sort_element ) {
				$ordering_num = 1;
				if ( strpos( $sort_element, ' ' ) !== false ) {
					[ $field_name, $order ] = explode( ' ', $sort_element, 2 );
					$ordering_num = 1;
					if ( $order && strtolower( trim( $order ) ) === 'desc' ) {
						$ordering_num = -1;
					}
				} else {
					$field_name = $sort_element;
				}
				$this->sort[trim( $field_name )] = $ordering_num;
			}
		}

		if ( $this->aggregate && count( $this->aggregate ) > 0 ) {
			if ( isset( $this->sqlOptions['ORDER BY'] ) ) {
				$this->aggregate[] = [ '$sort' => $this->sort ];
			}
			if ( isset( $this->sqlOptions['LIMIT'] ) ) {
				$this->aggregate[] = [ '$limit' => intval( $this->sqlOptions['LIMIT'] ) ];
			}
		}

		// Make a key for Memcached/APC.
		global $wgMainCacheType;
		if ( ( $wgMainCacheType === CACHE_MEMCACHED || $wgMainCacheType === CACHE_ACCEL )
			&& isset( $args['cache seconds'] ) && $args['cache seconds'] > 0
		) {
			$this->cacheKey = ObjectCache::getLocalClusterInstance()->makeKey( 'mongodb', $this->from, md5(
				$this->getQuery() .
				$this->credentials['dbname'] .
				$this->credentials['host']
			) );
			$this->cacheSeconds = $args['cache seconds'];
		}
	}

	/**
	 * Form credentials for database from $this->dbId.
	 *
	 * @param array $params Supplemented parameters.
	 */
	protected function setCredentials( array $params ) {
		parent::setCredentials( $params );
		$this->credentials['host'] = isset( $params['server'] ) ? $params['server'] : 'localhost:27017';

		// MongoDB login is done using a single string.
		// When specifying extra connect string options (e.g. replicasets,timeout, etc.),
		// use $wgExternalDataSources[$this->dbId] to pass these values
		// see http://docs.mongodb.org/manual/reference/connection-string
		$this->connectString = "mongodb://";
		if ( $this->credentials['user'] ) {
			$this->connectString .= $this->credentials['user'] . ':' . $this->credentials['password'] . '@';
		}
		$this->connectString .= $this->credentials['host'];
	}

	/**
	 * Get the MongoDB collection $name provided the connection is established.
	 *
	 * @return MongoCollection|MongoDB\Collection|null MongoDB collection.
	 */
	abstract protected function fetch();

	/**
	 * Run a query against MongoDB $collection.
	 *
	 * @param MongoCollection|MongoDB\Collection $collection
	 * @param array $filter
	 * @param array $columns
	 * @param array $sort
	 * @param int $limit
	 *
	 * @return array MongoCursor|MongoDB\Driver\Cursor
	 */
	abstract protected function find( $collection, array $filter, array $columns, array $sort, $limit );

	/**
	 * Run a aggregation query against MongoDB $collection.
	 *
	 * @param MongoCollection|MongoDB\Collection $collection
	 * @param array $aggregate
	 *
	 * @return array
	 */
	abstract protected function aggregate( $collection, array $aggregate );

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

		$collection = $this->fetch(); // late binding.
		if ( !$collection ) {
			return false;
		}

		// Get the data!
		if ( count( $this->aggregate ) > 0 ) {
			$results = $this->aggregate( $collection, $this->aggregate ); // late binding.
		} else {
			$results = $this->find(
				$collection,
				$this->find,
				$this->columns,
				$this->sort,
				$this->sqlOptions['LIMIT']
			); // late binding.
		}

		// Handle failure:
		if ( !$results ) {
			return false;
		}

		// Arrange values returned by MongoDB in a column-based array.
		$values = $this->processRows( $results, $this->aliases );
		$this->add( $values );

		// Cache, if so configured.
		if ( count( $values ) > 0 ) {
			$this->cache( $values );
		}

		return true;
	}

	/**
	 * Arrange values returned by MongoDB in a column-based array.
	 *
	 * @param array $rows Results from MongoDB.
	 * @param array $aliases Stub.
	 *
	 * @return array $values Column-based array of values.
	 */
	protected function processRows( $rows, array $aliases = [] ): array {
		$values = [];
		foreach ( $rows as $doc ) {
			foreach ( $this->columns as $column ) {
				if ( strstr( $column, "." ) ) {
					// If the exact path of the value was
					// specified using dots (e.g., "a.b.c"),
					// get the value that way.
					$values[$column][] = self::getValueFromJSONArray( $doc, $column );
				} elseif ( isset( $doc[$column] )
						&& ( ( is_array( $doc[$column] )
							|| is_a( $doc[$column], 'MongoDB\Model\BSONArray' )
							|| is_a( $doc[$column], 'MongoDB\Model\BSONDocument' ) ) ) ) {
					// If MongoDB returns an array for a column,
					// but the exact location of the value wasn't specified,
					// do some extra processing.
					if ( $column === 'geometry' && array_key_exists( 'coordinates', $doc['geometry'] ) ) {
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
					$values[$column][] = ( isset( $doc[$column] ) ? (string)$doc[$column] : null );
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
				return [ $default ];
			}
			$current = $current[$token];
			$token = strtok( '.' );
		}
		return $current;
	}

	/**
	 * Pseudo-query in text form for diagnostics. Also used to from cache key.
	 *
	 * @return string
	 */
	protected function getQuery() {
		return json_encode( $this->aggregate ) .
			json_encode( $this->find ) .
			json_encode( $this->sort ) .
			json_encode( $this->columns ) .
			$this->conditions .
			json_encode( $this->sqlOptions );
	}

	/**
	 * Look up Memcached/APC, if set up and configured to cache MongoDB queries.
	 *
	 * @return array|null Stored values or null, if no storage is configured.
	 */
	private function cached() {
		if ( $this->cacheKey ) {
			// Check if cache entry exists.
			return ObjectCache::getLocalClusterInstance()->get( $this->cacheKey );
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
		if ( $this->cacheKey ) {
			ObjectCache::getLocalClusterInstance()->set( $this->cacheKey, $values, $this->cacheSeconds );
		}
	}
}
