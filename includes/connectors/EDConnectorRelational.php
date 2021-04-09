<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for relational databases.
 *
 * @author Alexander Mashin
 * @author Yaron Koren
 *
 */
abstract class EDConnectorRelational extends EDConnectorDb {
	/** @var Database The database object. */
	private $db;
	/** @var array Tables to query. */
	private $tables = [];
	/** @var array JOIN conditions. */
	private $joins = [];

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args An array of arguments for parser/Lua function.
	 */
	public function __construct( array &$args ) {
		parent::__construct( $args );

		// Specific parameters.
		// The format of $from can be just "TableName", or the more
		// complex "Table1=Alias1,Table2=Alias2,...".
		// TODO: check if self::paramToArray will work here.
		foreach ( explode( ',', $this->from ) as $table_string ) {
			if ( strpos( $table_string, '=' ) !== false ) {
				list( $name, $alias ) = explode( '=', $table_string, 2 );
			} else {
				$name = $alias = $table_string;
			}
			$this->tables[trim( $alias )] = trim( $name );
		}

		// Join conditions.
		$joins = ( array_key_exists( 'join on', $args ) ) ? $args['join on'] : '';
		$join_strings = explode( ',', $joins );
		if ( count( $join_strings ) > count( $this->tables ) ) {
			$this->error(
				'externaldata-db-too-many-joins',
				(string)count( $join_strings ),
				(string)count( $this->tables )
			);
		}
		foreach ( $join_strings as $i => $join_string ) {
			if ( $join_string === '' ) {
				continue;
			}
			if ( strpos( $join_string, '=' ) === false ) {
				$this->error( 'externaldata-db-invalid-join', $join_string );
			}
			$aliases = array_keys( $this->tables );
			$alias = $aliases[$i + 1];
			$this->joins[$alias] = [ 'JOIN', $join_string ];
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
		$this->db = Database::factory( $this->type, $this->connection );
		if ( !$this->db ) {
			// Could not create Database object.
			$this->error( 'externaldata-db-unknown-type', $this->type );
			return false;
		}
		if ( !$this->db->isOpen() ) {
			$this->error( 'externaldata-db-could-not-connect' );
			return false;
		}
		$this->values = $this->searchDB();
		$this->db->close();
		return true;
	}

	/**
	 * Set connection settings for database from $this->db_id.
	 * Called by the constructor.
	 *
	 * @param array $params Supplemented parameters.
	 */
	protected function setConnection( array $params ) {
		parent::setConnection( $params );
		$this->connection['flags'] = isset( $params['DBFlags'] ) ? $params['DBFlags'] : DBO_DEFAULT;
		$this->connection['tablePrefix'] = isset( $params['DBTablePrefix'] ) ? $params['DBTablePrefix'] : '';
	}

	/**
	 * Run a query in an open database.
	 * @return string[][]|void
	 */
	private function searchDB() {
		$rows = $this->db->select(
			$this->tables,
			$this->columns,
			$this->conditions,
			__METHOD__,
			$this->sql_options,
			$this->joins
		);
		if ( $rows ) {
			$result = [];
			foreach ( $rows as $row ) {
				// Create a new row object that uses the passed-in
				// column names as keys, so that there's always an
				// exact match between what's in the query and what's
				// in the return value (so that "a.b", for instance,
				// doesn't get chopped off to just "b").
				foreach ( $this->columns as $column ) {
					$field = $row->$column;
					// This can happen with MSSQL.
					if ( $field instanceof DateTime ) {
						$field = $field->format( 'Y-m-d H:i:s' );
					}
					// Convert the encoding to UTF-8
					// if necessary - based on code at
					// http://www.php.net/manual/en/function.mb-detect-encoding.php#102510
					$field = mb_detect_encoding( $field, 'UTF-8', true ) === 'UTF-8'
						? $field
						: utf8_encode( $field );
					if ( !isset( $result[$column] ) ) {
						$result[$column] = [];
					}
					$result[$column][] = $field;
				}
			}
			return $result;
		} else {
			// No result.
			$this->error(
				'externaldata-db-invalid-query',
				$this->db->selectSQLText(
					$this->tables,
					$this->columns,
					$this->conditions,
					__METHOD__,
					$this->options,
					$this->joins
				)
			);
		}
	}
}
