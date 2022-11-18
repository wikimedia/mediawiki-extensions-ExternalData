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

					$query = $args['prepared'][$args['query']];
					if ( is_array( $query ) && isset( $query['query'] ) && isset( $query['types'] ) ) {
						$this->query = $query['query'];
						$this->types = $query['types'];
					} elseif ( is_string( $query ) ) {
						$this->query = $query;
					} else {
						$this->error( 'externaldata-db-prepared-config-wrong-type', $this->dbId, $args['query'] );
					}
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
		if ( !isset( $this->types ) ) {
			$paramCount = count( $this->parameters );
			if ( isset( $args['types'] ) ) {
				// We just set $this->types to $args['types'],
				// but we also make sure the string is exactly $paramCount long.
				$this->types = str_pad(
					substr(
						$args['types'],
						0,
						$paramCount
					),
					$paramCount,
					's'
				);
			} else {
				$this->types = str_repeat( 's', $paramCount );
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
