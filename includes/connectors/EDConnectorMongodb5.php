<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for MongoDB database type for PHP 5.*.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */
class EDConnectorMongodb5 extends EDConnectorMongodb {
	/** @var string $regex_class Class that stores MongoDB regular expressions. */
	protected static $regex_class = 'MongoRegex';

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
			return new MongoClient( $this->connect_string );
		} catch ( Exception $e ) {
			$this->error( 'externaldata-db-could-not-connect' );
			return null;
		}
	}

	/**
	 * Get the MongoDB collection $name provided the connection is established.
	 *
	 * @param string $collection The collection name.
	 *
	 * @return MongoCollection|null MongoDB collection.
	 */
	protected function getCollection( $collection ) {
		$connection = $this->connect();

		if ( !$connection ) {
			return null;
		}
		$db = $connection->SelectDb( $this->connection['dbname'] );
		if ( !$db ) {
			$this->error( 'externaldata-db-unknown-database', $this->db_id );
			return null;
		}
		// Check if collection exists.
		if ( !in_array( $this->from, $db->getCollectionNames(), true ) ) {
			// Not $this->connection['dbname']!
			$this->error( 'externaldata-mongodb-unknown-collection', $this->db_id . ':' . $this->from );
			return null;
		}
		return new MongoCollection( $db, $collection );
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
		return $collection->find( $filter, $columns )->sort( $sort )->limit( $limit )->toArray();
	}

	/**
	 * Run a aggregation query against MongoDB $collection.
	 *
	 * @param MongoCollection $collection
	 * @param array $aggregate
	 *
	 * @return array
	 */
	protected function aggregate( $collection, array $aggregate ) {
		$aggregateResult = $collection->aggregate( $this->aggregate );
		if ( $aggregateResult['ok'] ) {
			return $aggregateResult['result'];
		} else {
			$this->error( 'externaldata-mongodb-aggregation-failed', $aggregateResult['errmsg'] );
			return null;
		}
	}
}
