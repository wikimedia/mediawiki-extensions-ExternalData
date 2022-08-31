<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for databases managed by RDBMS if database type is PostgreSQL.
 *
 * @author Alexander Mashin
 *
 */
class EDConnectorPostgresql extends EDConnectorRdbms {
	/** @var bool $keepExternalVarsCase External variables' case need not be preserved. */
	public $keepExternalVarsCase = true;

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
	 * Surround each element with quotes.
	 * @param array $fields
	 * @return array
	 */
	private function quote( array $fields ): array {
		return array_map( function ( $string ) {
			return $this->database->addIdentifierQuotes( $string );
		}, $fields );
	}

	/**
	 * Establish connection the database server.
	 *
	 * @return bool
	 */
	protected function connect() {
		$connected = parent::connect();
		if ( $connected ) {
			// Surround PostgreSQL column names with quotes.
			$this->columns = $this->quote( $this->columns );
			if ( is_array( $this->sqlOptions['GROUP BY'] ) ) {
				$this->sqlOptions['GROUP BY'] = $this->quote( $this->sqlOptions['GROUP BY'] );
			}
			if ( is_array( $this->sqlOptions['ORDER BY'] ) ) {
				$this->sqlOptions['ORDER BY'] = $this->quote( $this->sqlOptions['ORDER BY'] );
			}
		}
		return $connected;
	}
}
