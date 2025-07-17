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
use Wikimedia\Rdbms\DatabaseFactory;

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
		$this->credentials['flags'] = $params['flags'] ?? DBO_DEFAULT;
		$this->credentials['prefix'] = $params['prefix'] ?? '';
	}

	/**
	 * Establish connection the database server.
	 *
	 * @return bool
	 */
	protected function connect() {
		try {
			$factory = new DatabaseFactory();
			$this->database = $factory->create( $this->type, $this->credentials );
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
	protected function getQuery(): string {
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
	protected function fetch(): ?\Wikimedia\Rdbms\IResultWrapper {
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
		if ( !$rows ) {
			// No result.
			$this->error(
				'externaldata-db-invalid-query',
				$this->getQuery(),
				$this->database->lastError()
			);
			return null;
		}
		return $rows;
	}

	/**
	 * Disconnect from DB server.
	 */
	protected function disconnect() {
		$this->database->close();
	}
}
