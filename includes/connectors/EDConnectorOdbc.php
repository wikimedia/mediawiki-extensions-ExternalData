<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for an ODBC connection.
 *
 * @author Alexander Mashin
 *
 */

class EDConnectorOdbc extends EDConnectorComposed {
	/** @const array ILLEGAL Strings disallowed anywhere in the query. */
	protected const ILLEGAL = [
		';', '--', '/*', '#', '@', '<?', 'grant', 'drop', 'delete', 'create'
	];
	/** @const string TEMPLATE SQL query template. */
	protected const TEMPLATE = 'SELECT $columns $from $where $group $having $order $limit;';
	/** @var bool $keepExternalVarsCase Whether external variables' names are case-sensitive for this format. */
	public $keepExternalVarsCase = true;
	/** @var resource $odbcConnection The ODBC connection resource. */
	private $odbcConnection;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		// Whether the odbc extension is installed and enabled.
		if ( !function_exists( 'odbc_pconnect' ) ) {
			$this->error(
				'externaldata-missing-library',
				'odbc',
				'#get_db_data (type = odbc)',
				'mw.ext.getExternalData.getDbData (type = odbc)'
			);
		}

		// Check for possible SQL injections.
		$this->checkParam( $this->tables, 'from' );
		$this->checkParam( $this->columns, 'data' );
		$this->checkParam( $this->conditions, 'where' );
		$this->checkParam( $this->sqlOptions['ORDER BY'], 'order by' );
		$this->checkParam( $this->sqlOptions['HAVING'], 'having' );
		$this->checkParam( $this->sqlOptions['LIMIT'], 'limit' );
	}

	/**
	 * Screen quoted strings.
	 * @param string $str String to screen quoted string in.
	 * @return string String with quoted strings screened.
	 */
	protected static function screenQuoted( string $str ) {
		return preg_replace( [ "/'([^']|'')*'/", '/"([^"]|"")*"/' ], '', $str );
	}

	/**
	 * Check query components for illegal sequences (possible injections).
	 * Record errors if illegal sequences are found.
	 * @param string $str The string to check.
	 * @param string $context The name of the offending context for the error message.
	 */
	private function checkString( $str, $context ) {
		// Screen quoted strings.
		$str = static::screenQuoted( $str );    // late binding.
		foreach ( static::ILLEGAL /* late binding */ as $illegal ) {
			if ( stripos( $str, $illegal ) !== false ) {
				$this->error( 'externaldata-db-odbc-illegal', $illegal, $context );
			}
		}
	}

	/**
	 * Check parameters (both key and value) for illegal sequences.
	 * @param array|string $param The parameter to check.
	 * @param string $context The name of the offending context for the error message.
	 */
	private function checkParam( $param, $context ) {
		if ( is_string( $param ) ) {
			$this->checkString( $param, $context );
		} elseif ( is_array( $param ) ) {
			foreach ( $param as $key => $value ) {
				$this->checkParam( $key, $context );
				if ( $key !== $value ) {
					$this->checkParam( $value, $context );
				}
			}
		}
	}

	/**
	 * Set credentials settings for database from $this->dbId.
	 * Called by the constructor.
	 *
	 * @param array $params Supplemented parameters.
	 */
	protected function setCredentials( array $params ) {
		parent::setCredentials( $params );
		// Database credentials.
		if ( isset( $params['driver'] ) ) {
			$this->credentials['driver'] = $params['driver'];
		} else {
			$this->error( 'externaldata-db-incomplete-information', $this->dbId, 'driver' );
		}
		if ( isset( $params['server'] ) ) {
			$this->credentials['host'] = $params['server'];
		} else {
			$this->error( 'externaldata-db-incomplete-information', $this->dbId, 'server' );
		}
	}

	/**
	 * Connect to the database server via ODBC.
	 *
	 * @return bool
	 */
	protected function connect() {
		$driver = $this->credentials['driver'];
		$server = $this->credentials['host'];
		$database = $this->credentials['dbname'];
		// Throw exceptions instead of warnings.
		self::throwWarnings();
		try {
			$this->odbcConnection = odbc_pconnect(
				"Driver={$driver};Server=$server;Database=$database;",
				$this->credentials['user'],
				$this->credentials['password']
			);
		} catch ( Exception $e ) {
			$this->error( 'externaldata-db-could-not-connect', $e->getMessage() );
			return false;
		} finally {
			self::stopThrowingWarnings();
		}
		if ( !$this->odbcConnection ) {
			$this->error( 'externaldata-db-could-not-connect' );
			return false;
		}
		return true;
	}

	/**
	 * Extract the table name from a field identifier.
	 * @param string $field The dot-separated field identifier. Table ID is penultimate.
	 * @return ?string The field ID.
	 */
	private static function getTable( $field ) {
		if ( preg_match( '/[^.]+(?=\.[^.]+$)/', $field, $matches ) ) {
			return $matches[0];
		}
	}

	/**
	 * Get a list of columns.
	 * @param array $columns An array of columns.
	 * @return string A list of columns.
	 */
	protected static function listColumns( array $columns ) {
		return implode( ', ', $columns );
	}

	/**
	 * Get the FROM clause.
	 * @param array $tables An associative array of tables ['alias' => 'table'].
	 * @param array $joins An associative array of JOIN conditions ['table1.field1' => 'table2.field2'].
	 * @return string The FROM clause.
	 */
	protected static function from( array $tables, array $joins ) {
		$from = '';
		$listed = [];
		$first = true;
		foreach ( $joins as $field1 => $field2 ) {
			$alias1 = static::getTable( $field1 );  // late binding.
			$alias2 = static::getTable( $field2 );  // late binding.
			// First table AS alias in the first join.
			if ( $first && $alias1 ) {
				$from .= "{$tables[$alias1]} AS $alias1";
				$listed[$alias1] = true;
				$first = false;
			}
			if ( $alias2 ) {
				$from .= " JOIN {$tables[$alias2]} ON $field1 = $field2";
				$listed[$alias2] = true;
			}
		}

		// Table not mentioned in JOIN conditions will be comma-separated.
		foreach ( $tables as $alias => $table ) {
			if ( !isset( $listed[$alias] ) ) {
				if ( $first ) {
					$from = "$table AS $alias" . $from;
					$first = false;
				} else {
					$from .= ", $table AS $alias";
				}
			}
		}
		return "FROM $from";
	}

	/**
	 * Get the LIMIT/TOP clause.
	 * @param int $limit The number of rows to return
	 * @return string The TOP/LIMIT clause.
	 */
	protected static function limit( $limit ) {
		// @todo: non-MS SQL (LIMIT ...).
		return $limit ? 'LIMIT ' . (string)$limit : '';
	}

	/**
	 * Get query text.
	 * @return string
	 */
	protected function getQuery() {
		return strtr( static::TEMPLATE /* late binding */, [
			'$columns' => static::listColumns( $this->columns ),   // late binding
			'$from' => static::from( $this->tables, $this->joins ),    // late binding
			'$where' => $this->conditions ? "\nWHERE {$this->conditions}" : '',
			'$group' => $this->sqlOptions['GROUP BY'] ? "\nGROUP BY {$this->sqlOptions['GROUP BY']}" : '',
			'$having' => $this->sqlOptions['HAVING'] ? "\nHAVING {$this->sqlOptions['HAVING']}" : '',
			'$order' => $this->sqlOptions['ORDER BY'] ? "\nORDER BY {$this->sqlOptions['ORDER BY']}" : '',
			'$limit' => static::limit( $this->sqlOptions['LIMIT'] )   // late binding
		] );
	}

	/**
	 * Get query result as a two-dimensional array.
	 * @return ?array
	 */
	protected function fetch(): ?array {
		$query = $this->getQuery(); // late binding.
		// Throw exceptions instead of warnings.
		self::throwWarnings();
		try {
			$rowset = odbc_exec( $this->odbcConnection, $query );
		} catch ( Exception $e ) {
			$this->error( 'externaldata-db-invalid-query', $query, $e->getMessage() );
			return null;
		} finally {
			self::stopThrowingWarnings();
		}
		if ( !$rowset ) {
			$this->error( 'externaldata-db-invalid-query', $query );
			return null;
		}
		if ( odbc_num_rows( $rowset ) <= 0 ) {
			$this->error( 'externaldata-db-empty-rowset' );
			return null;
		}
		$result = [];
		// phpcs:ignore MediaWiki.ControlStructures.AssignmentInControlStructures.AssignmentInControlStructures
		while (	$row = odbc_fetch_object( $rowset ) ) {
			$result[] = $row;
		}
		odbc_free_result( $rowset );
		return $result;
	}

	/**
	 * Disconnect from DB server.
	 */
	protected function disconnect() {
		// Do nothing.
	}
}
