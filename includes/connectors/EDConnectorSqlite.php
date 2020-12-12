<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for sqlite database type.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */
class EDConnectorSqlite extends EDConnectorRelational {
	/** @var bool $preserve_external_variables_case External variables' case ought to be preserved. */
	protected static $preserve_external_variables_case = true;

	/** @var string Database directory. */
	private $directory;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array $args An array of arguments for parser/Lua function.
	 */
	protected function __construct( array $args ) {
		parent::__construct( $args );
	}

	/**
	 * Form connection settings for database from $this->db_id.
	 *
	 * @param array $params Supplemented parameters.
	 */
	protected function setConnection( array $params ) {
		parent::setConnection( $params );

		if ( isset( $params['DBDirectory'] ) ) {
			$this->connection['dbDirectory'] = $params['DBDirectory'];
		} else {
			$this->error( 'externaldata-db-incomplete-information', 'sqlite directory' );
		}
	}
}
