<?php
/**
 * Base abstract class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */
abstract class EDConnectorDb extends EDConnectorBase {
	/** @var string $db_id Database ID. */
	protected $db_id;	// Database ID.

	/** @var string $type Database type. */
	protected $type;
	/** @var array $connection Connection settings. */
	protected $connection = [];

	// SQL query components.
	/** @var string $from FROM clause as a string. */
	protected $from;
	/** @var array $columns Columns to query. */
	protected $columns;
	/** @var string $conditions Select conditions. */
	protected $conditions;
	/** @var array $sql_options LIMIT, ORDER BY and GROUP BY clauses. */
	protected $sql_options;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args An array of arguments for parser/Lua function.
	 */
	protected function __construct( array &$args ) {
		parent::__construct( $args );

		// Specific parameters.
		if ( isset( $args['db'] ) ) {
			$this->db_id = $args['db'];
		} elseif ( isset( $args['server'] ) ) {
			// For backwards-compatibility - 'db' parameter was
			// added in External Data version 1.3.
			$this->db_id = $args['server'];
		}
		if ( !$this->db_id ) {
			$this->error( 'externaldata-no-param-specified', 'db' );
		}
		if ( isset( $args['DBServerType'] ) ) {
			$this->type = $args['DBServerType'];
		} else {
			$this->error( 'externaldata-db-incomplete-information', $this->db_id, 'edgDBServerType' );
		}
		// Database credentials.
		$this->setConnection( $args );	// late binding.
		// Query parts.
		if ( isset( $args['from'] ) ) {
			$this->from = $args['from'];
		} else {
			$this->error( 'externaldata-no-param-specified', 'from' );
		}
		$this->columns = array_values( $this->mappings );
		$this->conditions = ( array_key_exists( 'where', $args ) ) ? $args['where'] : null;
		$this->sql_options = [
			'LIMIT'		=> ( array_key_exists( 'limit', $args ) ) ? $args['limit'] : null,
			'ORDER BY'	=> ( array_key_exists( 'order by', $args ) ) ? $args['order by'] : null,
			'GROUP BY'	=> ( array_key_exists( 'group by', $args ) ) ? $args['group by'] : null
		];
	}

	/**
	 * Set connection settings for database from $this->db_id.
	 * Should be overloaded, with parent::setConnection().
	 *
	 * @param array $params Supplemented parameters.
	 */
	protected function setConnection( array $params ) {
		$this->connection['user'] = isset( $params[ 'DBUser' ] ) ? $params[ 'DBUser' ] : null;
		$this->connection['password'] = isset( $params[ 'DBPass' ] ) ? $params[ 'DBPass' ] : null;
		if ( isset( $params[ 'DBName' ] ) ) {
			$this->connection['dbname'] = $params['DBName'];
		} else {
			$this->error( 'externaldata-db-incomplete-information', $this->db_id, 'edgDBName' );
		}
	}
}
