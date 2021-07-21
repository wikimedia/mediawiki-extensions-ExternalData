<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for sqlite database type.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */
class EDConnectorSqlite extends EDConnectorRdbms {
	/** @var bool $keepExternalVarsCase External variables' case ought to be preserved. */
	protected static $keepExternalVarsCase = true;

	/**
	 * Form credentials settings for database from $this->dbId.
	 *
	 * @param array $params Supplemented parameters.
	 */
	protected function setCredentials( array $params ) {
		parent::setCredentials( $params );

		if ( isset( $params['DBDirectory'] ) ) {
			$this->credentials['dbDirectory'] = $params['DBDirectory'];
		} else {
			$this->error( 'externaldata-db-incomplete-information', 'sqlite directory' );
		}
	}
}
