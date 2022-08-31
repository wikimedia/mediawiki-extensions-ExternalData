<?php
/**
 * Base abstract class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */
abstract class EDConnectorDb extends EDConnectorBase {
	/** @const string ID_PARAM What the specific parameter identifying the connection is called. */
	protected const ID_PARAM = 'db';

	/** @var string|null Database ID. */
	protected $dbId = null;	// Database ID.

	/** @var string Database type. */
	protected $type;
	/** @var array Connection settings. */
	protected $credentials = [];

	// SQL query components.
	/** @var array Columns to query. */
	protected $columns;
	/** @var array $aliases Column aliases. */
	protected $aliases = [];

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		// Specific parameters.
		if ( isset( $args[self::ID_PARAM] ) ) {
			$this->dbId = $args[self::ID_PARAM];
		}

		// Query parts.
		$mappings = $this->mappings();
		if ( count( $mappings ) === 0 || isset( $mappings['__all'] ) ) {
			$this->columns = [ '*' ];
		} else {
			$this->columns = array_values( $mappings );
		}
		// Column aliases: the correspondence $external_variable => $column_name_in_query_result.
		foreach ( $this->columns as $column ) {
			// Deal with AS in external names.
			$chunks = preg_split( '/\bas\s+/i', $column, 2 );
			$alias = isset( $chunks[1] ) ? trim( $chunks[1] ) : $column;
			// Deal with table prefixes in column names (internal_var=tbl1.col1).
			if ( preg_match( '/[^.]+$/', $alias, $matches ) ) {
				$alias = $matches[0];
			}
			$this->aliases[$column] = $alias;
		}
		if ( !$this->dbId ) {
			return; // further checks and initialisations are impossible.
		}
		if ( isset( $args['type'] ) ) {
			$this->type = strtolower( $args['type'] );
		} else {
			$this->error( 'externaldata-db-incomplete-information', $this->dbId, 'type' );
		}
		// Database credentials.
		$this->setCredentials( $args );	// late binding.
	}

	/**
	 * Set credentials settings for database from $this->dbId.
	 * Should be overloaded, with a call to parent::setCredentials().
	 *
	 * @param array $params Supplemented parameters.
	 */
	protected function setCredentials( array $params ) {
		$this->credentials['user'] = isset( $params['user' ] ) ? $params['user' ] : null;
		$this->credentials['password'] = isset( $params['password' ] ) ? $params['password' ] : null;
		if ( isset( $params[ 'name' ] ) ) {
			$this->credentials['dbname'] = $params['name'];
		} else {
			$this->error( 'externaldata-db-incomplete-information', $this->dbId, 'name' );
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
		if ( !$this->connect() /* late binding. */ ) {
			return false;
		}
		$rows = $this->fetch(); // late binding.
		if ( !$rows ) {
			return false;
		}
		$this->add( $this->processRows( $rows, $this->aliases ) );
		// $this->values = $this->processRows( $rows ); // late binding.
		$this->disconnect(); // late binding.
		return true;
	}

	/**
	 * Establish connection the database server.
	 */
	abstract protected function connect();

	/**
	 * Get query text.
	 * @return string
	 */
	abstract protected function getQuery();

	/**
	 * Get query result as a two-dimensional array.
	 * @return mixed
	 */
	abstract protected function fetch();

	/**
	 * Postprocess query result.
	 * @param mixed $rows A two-dimensional array or result wrapper containing query results.
	 * @param array $aliases An optional associative array of column aliases.
	 * @return array A two-dimensional array containing post-processed query results
	 */
	protected function processRows( $rows, array $aliases = [] ): array {
		$result = [];
		foreach ( $rows as $row ) {
			foreach ( $row as $column => $_ ) {
				$alias = isset( $aliases[$column] ) ? $aliases[$column] : $column;
				if ( !isset( $result[$column] ) ) {
					$result[$column] = [];
				}
				// Can be both array and object.
				$result[$column][] = self::processField( ( (array)$row )[$alias] );
			}
		}
		return $result;
	}

	/**
	 * Process field value.
	 *
	 * @param string|DateTime $value
	 * @return string
	 */
	protected static function processField( $value ) {
		// This can happen with MSSQL.
		if ( $value instanceof DateTime ) {
			$value = $value->format( 'Y-m-d H:i:s' );
		}
		// Convert the encoding to UTF-8
		// if necessary - based on code at
		// http://www.php.net/manual/en/function.mb-detect-encoding.php#102510
		return mb_detect_encoding( $value, 'UTF-8', true ) === 'UTF-8'
			? $value
			: utf8_encode( $value );
	}

	/**
	 * Disconnect from DB server.
	 */
	abstract protected function disconnect();
}
