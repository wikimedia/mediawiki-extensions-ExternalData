<?php
/**
 * Class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData
 * for relational databases.
 *
 * @author Alexander Mashin
 * @author Yaron Koren
 *
 */
abstract class EDConnectorRelational extends EDConnectorComposed {
	/** @var array Tables to query. */
	protected $tables = [];
	/** @var array JOIN conditions. */
	protected $joins = [];

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		// Specific parameters.
		// The format of $from can be just "TableName", or the more
		// complex "Table1=Alias1,Table2=Alias2,...".
		$this->tables = array_flip( self::paramToArray( $this->from ) );
		$this->joins = isset( $args['join on'] ) ? self::paramToArray( $args['join on'] ) : null;

		// Column aliases: the correspondence $external_variable => $column_name_in_query_result.
		foreach ( $this->columns as $column ) {
			// Deal with AS in external names.
			$chunks = preg_split( '/\bas\s+/i', $column, 2 );
			$alias = isset( $chunks[1] ) ? trim( $chunks[1] ) : $column;
			// Deal with table prefixes in column names (internal_var=tbl1.col1).
			if ( preg_match( '/[^.]+$/', $alias, $matches ) ) {
				$alias = $matches[0];
			}
			$this->aliases[$column] = $alias;
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
		$this->credentials['flags'] = isset( $params['flags'] ) ? $params['flags'] : DBO_DEFAULT;
		$this->credentials['prefix'] = isset( $params['prefix'] ) ? $params['prefix'] : '';
	}

	/**
	 * Actually connect to the external data source.
	 * It is presumed that there are no errors in parameters and wiki settings.
	 * Set $this->values and $this->errors.
	 *
	 * @return bool True on success, false if error were encountered.
	 */
	public function run() {
		if ( !$this->connect() /* late binding. */ ) {
			return false;
		}
		$rows = $this->fetch(); // late binding.
		if ( !$rows ) {
			return false;
		}
		$this->add( $this->processRows( $rows, $this->aliases ) ); // late binding.
		$this->disconnect(); // late binding.
		return true;
	}
}
