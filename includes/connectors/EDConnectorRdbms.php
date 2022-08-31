<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for databases connectable with Wikimedia\Rdbms\Database.
 *
 * @author Alexander Mashin
 * @author Yaron Koren
 *
 */
use Wikimedia\Rdbms\Database;

abstract class EDConnectorRdbms extends EDConnectorComposed {
	/** @var Database The database object. */
	protected $database;

	/**
	 * Set credentials settings for database from $this->dbId.
	 * Called by the constructor.
	 *
	 * @param array $params Supplemented parameters.
	 */
	protected function setCredentials( array $params ) {
		parent::setCredentials( $params );
		$this->credentials['flags'] = isset( $params['flags'] ) ? $params['flags'] : DBO_DEFAULT;
		$this->credentials['prefix'] = isset( $params['prefix'] ) ? $params['prefix'] : '';
	}

	/**
	 * Establish connection the database server.
	 *
	 * @return bool
	 */
	protected function connect() {
		try {
			$this->database = Database::factory( $this->type, $this->credentials );
		} catch ( Exception $e ) {
			$this->error( 'externaldata-db-could-not-connect', $e->getMessage() );
			return false;
		}
		if ( !$this->database ) {
			// Could not create Database object.
			$this->error( 'externaldata-db-unknown-type', $this->type );
			return false;
		}
		if ( !$this->database->isOpen() ) {
			$this->error( 'externaldata-db-could-not-connect' );
			return false;
		}
		return true;
	}

	/**
	 * Get query text.
	 * @return string
	 */
	protected function getQuery() {
		return $this->database->selectSQLText(
			$this->tables,
			$this->columns,
			$this->conditions,
			__METHOD__,
			$this->sqlOptions,
			$this->joins
		);
	}

	/**
	 * Get query result as a two-dimensional array.
	 * @return \Wikimedia\Rdbms\IResultWrapper|null
	 */
	protected function fetch() {
		try {
			$rows = $this->database->select(
				$this->tables,
				$this->columns,
				$this->conditions,
				__METHOD__,
				$this->sqlOptions,
				$this->joins
			);
		} catch ( Exception $e ) {
			// No result.
			$this->error(
				'externaldata-db-invalid-query',
				$this->getQuery(),
				$e->getMessage()
			);
			return null;
		}
		if ( $rows ) {
			return $rows;
		} else {
			// No result.
			$this->error(
				'externaldata-db-invalid-query',
				$this->getQuery(),
				$this->database->lastError()
			);
			return null;
		}
	}

	/**
	 * Disconnect from DB server.
	 */
	protected function disconnect() {
		$this->database->close();
	}
}
