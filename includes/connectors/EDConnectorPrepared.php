<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for database connections with prepared statements.
 *
 * @author Alexander Mashin
 *
 */
abstract class EDConnectorPrepared extends EDConnectorDb {
	/** @var string $name Name of the prepared statement. */
	protected $name;
	/** @var string $query The parametrised SQL query. */
	protected $query;
	/** @var array $parameters Parameters to the SQL query. */
	protected $parameters = [];
	/** @var string $types Parameter types. */
	protected $types;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		// Specific parameters.
		// SQL statement to prepare.
		if ( is_array( $args['prepared'] ) ) {
			// Several statements for this database connection.
			if ( isset( $args['query'] ) && is_string( $args['query'] ) ) {
				if ( isset( $args['prepared'][$args['query']] ) ) {
					$this->name = $args['query'];
					$this->query = $args['prepared'][$this->name];
				} else {
					$this->error( 'externaldata-db-no-such-prepared', $this->dbId, $args['query'] );
				}
			} else {
				$this->error( 'externaldata-db-prepared-not-specified', $this->dbId );
			}
		} else {
			// Only one statement for this database connection.
			$this->name = $this->dbId;
			$this->query = $args['prepared'];
		}
		if ( isset( $args['parameters'] ) ) {
			$this->parameters = self::paramToArray( $args['parameters'], false, false, true );
		}
		$this->types = isset( $args['types'] ) ? $args['types'] : str_repeat( 's', count( $this->parameters ) );
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
		if ( isset( $params['server'] ) ) {
			$this->credentials['host'] = $params['server'];
		} else {
			$this->error( 'externaldata-db-incomplete-information', $this->dbId, 'server' );
		}
	}

	/**
	 * Get query text.
	 * @return string
	 */
	protected function getQuery() {
		return $this->query;
	}
}
