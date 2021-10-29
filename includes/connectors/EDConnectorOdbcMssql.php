<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for an ODBC connection to MS SQL Server.
 *
 * @author Alexander Mashin
 *
 */

class EDConnectorOdbcMssql extends EDConnectorOdbc {
	/** @const string TEMPLATE SQL query template. */
	protected const TEMPLATE = 'SELECT $limit $columns $from $where $group $having $order;';

	/**
	 * Get the TOP clause.
	 * @param int $limit The number of rows to return.
	 * @return string The TOP clause.
	 */
	protected static function limit( $limit ) {
		return $limit ? 'TOP ' . (string)$limit : '';
	}
}
