<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for relational databases excepm SQLite.
 *
 * @author Alexander Mashin
 *
 */
class EDConnectorSql extends EDConnectorRelational {
	/** @var bool $preserve_external_variables_case External variables' case need not be preserved. */
	protected static $preserve_external_variables_case = false;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args An array of arguments for parser/Lua function.
	 */
	public function __construct( array &$args ) {
		parent::__construct( $args );
	}

	/**
	 * Set connection settings for database from $this->db_id.
	 * Called by the constructor.
	 *
	 * @param array $params Supplemented parameters.
	 */
	protected function setConnection( array $params ) {
		parent::setConnection( $params );

		// Database credentials.
		if ( isset( $params['DBServer'] ) ) {
			$this->connection['host'] = $params['DBServer'];
		} else {
			$this->error( 'externaldata-db-incomplete-information', $this->db_id, 'edgDBServer' );
		}
	}
}
