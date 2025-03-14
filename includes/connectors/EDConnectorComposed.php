<?php

use MediaWiki\Title\Title;

/**
 * Base abstract class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for composed, i.e., not prepared, SQL statements.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */
abstract class EDConnectorComposed extends EDConnectorDb {
	// SQL query components.
	/** @var string FROM clause as a string. */
	protected $from;
	/** @var array Tables to query. */
	protected $tables = [];
	/** @var array JOIN conditions. */
	protected $joins = [];
	/** @var string Select conditions. */
	protected $conditions;
	/** @var array LIMIT, ORDER BY and GROUP BY clauses. */
	protected $sqlOptions;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );
		// Query parts.
		$this->from = $args['from'] ?? [];
		// @todo Allow Lua tables rather than comma-separated strings for the below parametres.
		// The format of $from can be just "TableName", or the more
		// complex "Table1=Alias1,Table2=Alias2,...".
		$this->tables = array_flip( self::paramToArray( $this->from ) );
		$this->joins = [];
		$tables = []; // tables mentioned in JOIN clauses.
		foreach ( isset( $args['join on'] ) ? self::paramToArray( $args['join on'] ) : [] as $left => $right ) {
			$left_refers_table = preg_match( '/^(?<table>.+)\.(?<field>[^.]+)$/', $left, $left_parsed );
			$right_refers_table = preg_match( '/^(?<table>.+)\.(?<field>[^.]+)$/', $right, $right_parsed );
			if ( $right_refers_table && !isset( $this->joins[$right_parsed['table']] ) ) {
				$joined = $right_parsed['table'];
				$tables[$joined] = $joined;
				if ( $left_parsed['table'] ) {
					$tables[$left_parsed['table']] = $left_parsed['table'];
				}
			} elseif ( $left_refers_table && !isset( $this->joins[$left_parsed['table']] ) ) {
				$joined = $left_parsed['table'];
				$tables[$joined] = $joined;
				if ( $right_parsed['table'] ) {
					$tables[$right_parsed['table']] = $right_parsed['table'];
				}
			} else {
				$joined = null;
			}
			if ( $joined ) {
				// Only INNER JOIN.
				$this->joins[$joined] = [ 'JOIN', "$left = $right" ];
			}
		}
		// Add tables mentioned in join on clause but missing from tables and joins arrays.
		foreach ( $tables as $alias => $table ) {
			if ( !isset( $this->tables[$alias] ) ) {
				$this->tables[$alias] = $table;
			}
		}
		$this->conditions = array_key_exists( 'where', $args ) ? $args['where'] : null;
		$this->sqlOptions = [
			'ORDER BY' => array_key_exists( 'order by', $args ) ? $args['order by'] : null,
			'GROUP BY' => array_key_exists( 'group by', $args ) ? $args['group by'] : null,
			'HAVING' => array_key_exists( 'having', $args ) ? $args['having'] : null
		];
		// @TODO: consider extracting table names from WHERE, ORDER BY, GROUP BY and HAVING clauses.
		if ( count( $this->tables ) === 0 ) {
			$this->error( 'externaldata-no-param-specified', 'from / join on' );
		}
		if ( isset( $args['limit'] ) ) {
			if ( is_numeric( $args['limit'] ) ) {
				$this->sqlOptions['LIMIT'] = (int)$args['limit'];
			} else {
				$this->error( 'externaldata-param-type-error', 'limit', 'integer' );
			}
		}
	}
}
