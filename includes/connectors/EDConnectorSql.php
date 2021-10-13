<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for relational databases except SQLite and MS SQL Server.
 *
 * @author Alexander Mashin
 *
 */
class EDConnectorSql extends EDConnectorRdbms {
	/** @var bool $keepExternalVarsCase External variables' case need not be preserved. */
	public $keepExternalVarsCase = false;

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
}
