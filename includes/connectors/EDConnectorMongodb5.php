<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for MongoDB database type for PHP 5.*.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */
class EDConnectorMongodb5 extends EDConnectorMongodb {
	/** @var ?MongoClient $mongoClient MongoDB client. */
	private $mongoClient;
	/** @var string $regexClass Class that stores MongoDB regular expressions. */
	protected static $regexClass = 'MongoRegex';

	/**
	 * Create a MongoDB connection.
	 *
	 * @return MongoClient|null
	 */
	protected function connect() {
		// Use try/catch to suppress error messages, which would show
		// the MongoDB connect string, which may have sensitive
		// information.
		try {
			// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
			return new MongoClient( $this->connectString );
		} catch ( Exception $e ) {
			$this->error( 'externaldata-db-could-not-connect', $e->getMessage() );
			return null;
		}
	}

	/**
	 * Get the MongoDB collection $name provided the connection is established.
	 *
	 * @return MongoCollection|null MongoDB collection.
	 */
	protected function fetch() {
		$this->mongoClient = $this->connect();
		if ( !$this->mongoClient ) {
			return null;
		}
		// @phan-suppress-next-line PhanUndeclaredClassMethod optional extension.
		$db = $this->mongoClient->SelectDb( $this->credentials['dbname'] );
		if ( !$db ) {
			$this->error( 'externaldata-db-unknown-database', $this->dbId );
			return null;
		}
		// Check if collection exists.
		if ( !in_array( $this->from, $db->getCollectionNames(), true ) ) {
			// Not $this->credentials['dbname']!
			$this->error( 'externaldata-mongodb-unknown-collection', $this->dbId . ':' . $this->from );
			return null;
		}
		try {
			// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
			$collection = new MongoCollection( $db, $this->from );
		} catch ( Exception $e ) {
			$this->error( 'externaldata-mongodb-unknown-collection', $this->dbId . ':' . $this->from );
			$collection = false;
		}
		return $collection;
	}

	/**
	 * Run a query against MongoDB $collection.
	 *
	 * @param MongoCollection $collection
	 * @param array $filter
	 * @param array $columns
	 * @param array $sort
	 * @param int $limit
	 *
	 * @return array MongoCursor
	 */
	protected function find( $collection, array $filter, array $columns, array $sort, $limit ) {
		// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
		return iterator_to_array( $collection->find( $filter, $columns )->sort( $sort )->limit( $limit ) );
	}

	/**
	 * Run a aggregation query against MongoDB $collection.
	 *
	 * @param MongoCollection $collection
	 * @param array $aggregate
	 *
	 * @return array|null
	 */
	protected function aggregate( $collection, array $aggregate ) {
		// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
		$aggregate_result = $collection->aggregate( $this->aggregate );
		if ( $aggregate_result['ok'] ) {
			return $aggregate_result['result'];
		} else {
			$this->error( 'externaldata-mongodb-aggregation-failed', $aggregate_result['errmsg'] );
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
