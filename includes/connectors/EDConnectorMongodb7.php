<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for MongoDB database type under PHP 7 using mongodb library.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */
class EDConnectorMongodb7 extends EDConnectorMongodb {
	/** @var string $regex_class Class that stores MongoDB regular expressions. */
	protected static $regex_class = 'MongoDB\BSON\Regex';

	/**
	 * Create a MongoDB connection.
	 *
	 * @return MongoDB\Client|null
	 */
	protected function connect() {
		// Use try/catch to suppress error messages, which would show
		// the MongoDB connect string, which may have sensitive
		// information.
		try {
			return new MongoDB\Client( $this->connect_string );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Get the MongoDB collection $name provided the connection is established.
	 *
	 * @param string $collection The collection name.
	 *
	 * @return MongoDB\Collection|null MongoDB collection.
	 */
	protected function getCollection( $collection ) {
		$connection = $this->connect();
		if ( !$connection ) {
			$this->error( 'externaldata-db-could-not-connect' );
			return null;
		}
		return $connection->selectCollection( $this->connection['dbname'], $collection );
	}

	/**
	 * Run a query against MongoDB $collection.
	 *
	 * @param MongoDB\Collection $collection
	 * @param array $filter
	 * @param array $columns
	 * @param array $sort
	 * @param int $limit
	 *
	 * @return array MongoDB\Driver\Cursor
	 */
	protected function find( $collection, array $filter, array $columns, array $sort, $limit ) {
		try {
			$found = $collection->find( $filter, [ 'sort' => $sort, 'limit' => $limit ] )->toArray();
		} catch ( Exception $e ) {
			$this->error( 'externaldata-db-could-not-connect' );
			return null;
		}
		return $found;
	}

	/**
	 * Run a aggregation query against MongoDB $collection.
	 *
	 * @param MongoDB\Collection $collection
	 * @param array $aggregate
	 *
	 * @return array
	 */
	protected function aggregate( $collection, array $aggregate ) {
		try {
			return $collection->aggregate( $aggregate, [ 'useCursor' => true ] )->toArray();
		} catch ( Exception $e ) {
			$this->error( 'externaldata-mongodb-aggregation-failed', $e->getMessage() );
			return null;
		}
	}
}
