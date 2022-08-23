<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for MongoDB database type under PHP 7 using mongodb library.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */
class EDConnectorMongodb7 extends EDConnectorMongodb {
	/** @var ?MongoDB\Client $mongoClient MongoDB client. */
	private $mongoClient;
	/** @var string $regexClass Class that stores MongoDB regular expressions. */
	protected static $regexClass = 'MongoDB\BSON\Regex';

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
			// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
			return new MongoDB\Client( $this->connectString );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Get the MongoDB collection $name provided the connection is established.
	 *
	 * @return MongoDB\Collection|null MongoDB collection.
	 */
	protected function fetch() {
		$this->mongoClient = $this->connect();
		if ( !$this->mongoClient ) {
			$this->error( 'externaldata-db-could-not-connect' );
			return null;
		}
		// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
		return $this->mongoClient->selectCollection( $this->credentials['dbname'], $this->from );
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
	 * @return array|null MongoDB\Driver\Cursor
	 */
	protected function find( $collection, array $filter, array $columns, array $sort, $limit ) {
		try {
			// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
			$found = $collection->find( $filter, [ 'sort' => $sort, 'limit' => $limit ] )->toArray();
		} catch ( Exception $e ) {
			$this->error( 'externaldata-db-could-not-connect', $e->getMessage() );
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
	 * @return array|null
	 */
	protected function aggregate( $collection, array $aggregate ) {
		try {
			// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
			return $collection->aggregate( $aggregate, [ 'useCursor' => true ] )->toArray();
		} catch ( Exception $e ) {
			$this->error( 'externaldata-mongodb-aggregation-failed', $e->getMessage() );
			return null;
		}
	}

	/**
	 * Disconnect from MongoDB.
	 */
	protected function disconnect() {
		// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
		$this->mongoClient->close();
	}
}
