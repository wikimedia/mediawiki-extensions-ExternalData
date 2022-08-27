<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for database connections to mySQL servers with prepared statements.
 *
 * @author Alexander Mashin
 *
 */
class EDConnectorPreparedMysql extends EDConnectorPrepared {
	/** @var mysqli $mysqli Connection to mySQL server. */
	private $mysqli;
	/** @var mysqli_stmt $prepared The prepared query. */
	protected $prepared;

	/**
	 * Establish connection the database server.
	 * @return bool
	 */
	protected function connect() {
		// Throw exceptions instead of warnings.
		self::throwWarnings();
		try {
			$this->mysqli = new mysqli(
				$this->credentials['host'],
				$this->credentials['user'],
				$this->credentials['password'],
				$this->credentials['dbname']
			);
		} catch ( Exception $e ) {
			$this->error( 'externaldata-db-could-not-connect', $e->getMessage() );
			self::stopThrowingWarnings();
			return false;
		} finally {
			self::stopThrowingWarnings();
		}
		if ( $this->mysqli->connect_error ) {
			// Could not create Database object.
			$this->error( 'externaldata-db-could-not-connect', $this->mysqli->connect_error );
			return false;
		}
		return true;
	}

	/**
	 * Get query result as a two-dimensional array.
	 * @return string[][]|void
	 */
	protected function fetch() {
		// Prepared statement.
		$this->prepared = $this->mysqli->prepare( $this->query );
		if ( !$this->prepared ) {
			$this->error( 'externaldata-db-invalid-query', $this->query );
		}

		// Bind parameters.
		if ( count( $this->parameters ) > 0 ) {
			$this->prepared->bind_param( $this->types, ...$this->parameters );
		}

		// Execute query.
		$this->prepared->execute();

		// Get values.
		$result = $this->prepared->get_result();
		if ( $result !== false ) {
			$rows = $result->fetch_all( MYSQLI_ASSOC );
			$this->prepared->close(); // late binding.
			return $rows;
		} else {
			$this->error( 'externaldata-db-no-return-values' );
		}
	}

	/**
	 * Disconnect from DB server.
	 */
	protected function disconnect() {
		$this->mysqli->close();
	}
}
