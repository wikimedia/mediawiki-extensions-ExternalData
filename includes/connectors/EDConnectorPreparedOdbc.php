<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for ODBC connections with prepared statements.
 *
 * @author Alexander Mashin
 *
 */
class EDConnectorPreparedOdbc extends EDConnectorPrepared {
	/** @var resource $odbcConnection The ODBC connection resource. */
	private $odbcConnection;
	/** @var resource|false $prepared The prepared statement. */
	private $prepared;
	/** @var bool $keepExternalVarsCase Whether external variables' names are case-sensitive for this format. */
	public $keepExternalVarsCase = true;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		// Whether the necessary library is enabled.
		if ( !function_exists( 'odbc_pconnect' ) ) {
			$this->error(
				'externaldata-missing-library',
				'odbc',
				'#get_db_data (type = odbc)',
				'mw.ext.getExternalData.getDbData (type = odbc)'
			);
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
		if ( isset( $params['driver'] ) ) {
			$this->credentials['driver'] = $params['driver'];
		} else {
			$this->error( 'externaldata-db-incomplete-information', $this->dbId, 'driver' );
		}
		if ( isset( $params['server'] ) ) {
			$this->credentials['host'] = $params['server'];
		} else {
			$this->error( 'externaldata-db-incomplete-information', $this->dbId, 'server' );
		}
	}

	/**
	 * Connect to the database server via ODBC.
	 *
	 * @return bool
	 */
	protected function connect() {
		$driver = $this->credentials['driver'];
		$server = $this->credentials['host'];
		$database = $this->credentials['dbname'];
		// Throw exceptions instead of warnings.
		self::throwWarnings();
		try {
			$this->odbcConnection = odbc_pconnect(
				"Driver={$driver};Server=$server;Database=$database;",
				$this->credentials['user'],
				$this->credentials['password']
			);
		} catch ( Exception $e ) {
			$this->error( 'externaldata-db-could-not-connect', $e->getMessage() );
			return false;
		} finally {
			self::stopThrowingWarnings();
		}
		if ( !$this->odbcConnection ) {
			$this->error( 'externaldata-db-could-not-connect' );
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
		// Throw exceptions instead of warnings.
		self::throwWarnings();
		try {
			$this->prepared = odbc_prepare( $this->odbcConnection, $this->query );
		} catch ( Exception $e ) {
			$msg = $e->getMessage() ?: odbc_errormsg( $this->odbcConnection );
			$this->error( 'externaldata-db-invalid-query', $this->query, $msg );
			return null;
		} finally {
			self::stopThrowingWarnings();
		}
		if ( !$this->prepared ) {
			$this->error( 'externaldata-db-invalid-query', $this->query, odbc_errormsg( $this->odbcConnection ) );
			return null;
		}

		// Execute query.
		// Throw exceptions instead of warnings.
		self::throwWarnings();
		try {
			$success = odbc_execute( $this->prepared, $this->parameters );
		} catch ( Exception $e ) {
			$msg = $e->getMessage() ?: odbc_errormsg( $this->odbcConnection );
			$this->error( 'externaldata-db-invalid-query', $this->query, $msg );
			return null;
		} finally {
			self::stopThrowingWarnings();
		}

		if ( $success ) {
			// Get values.
			$result = [];
			// phpcs:ignore MediaWiki.ControlStructures.AssignmentInControlStructures.AssignmentInControlStructures
			while (	$row = odbc_fetch_object( $this->prepared ) ) {
				$result[] = $row;
			}
		} else {
			$this->error( 'externaldata-db-no-return-values' );
			return null;
		}

		odbc_free_result( $this->prepared );
		return $result;
	}

	/**
	 * Close ODBC connection.
	 */
	protected function disconnect() {
		odbc_close( $this->odbcConnection );
	}
}
