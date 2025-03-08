<?php

use MediaWiki\Title\Title;

/**
 * Base abstract class implementing {{#get_db_data:}} and mw.ext.externalData.getDbData.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */
abstract class EDConnectorDb extends EDConnectorBase {
	/** @const string ID_PARAM What the specific parameter identifying the connection is called. */
	protected const ID_PARAM = 'db';

	/** @var string|null Database ID. */
	protected $dbId = null; // Database ID.

	/** @var string Database type. */
	protected $type;
	/** @var array Connection settings. */
	protected $credentials = [];

	// SQL query components.
	/** @var array Columns to query. */
	protected $columns;
	/** @var array $aliases Column aliases. */
	protected $aliases = [];

	/** @const string IDENTIFIER */
	private const IDENTIFIER = <<<'ID'
		/(?<=\.|^)(?:
			(?<identifier>[\w$\x{0080}-\x{FFFF}]+) #Unquoted
			| (?<quote>`|") (?<identifier> #Quoted
				(?:(?!(?P=quote)).
					| (?P=quote){2}
				)+) (?P=quote)
		)$/uJx
	ID;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		// Specific parameters.
		if ( isset( $args[self::ID_PARAM] ) ) {
			$this->dbId = $args[self::ID_PARAM];
		}

		// Query parts.
		$mappings = $this->mappings();
		if ( count( $mappings ) === 0 || isset( $mappings['__all'] ) ) {
			$this->columns = [ '*' ];
		} else {
			$this->columns = array_values( $mappings );
		}
		// Column aliases: the correspondence $column_name_in_query_result => $external_variable.
		foreach ( $this->columns as $external ) {
			$column = $external;
			if ( preg_match( '/^(?<column>.+)\s+as\s+(?<alias>.+)$/i', $column, $matches ) ) {
				// Deal with AS in external names.
				$alias = $matches['alias'];
				$column = $matches['column'];
			} else {
				$alias = $column;
			}
			// Deal with table prefixes in column names (internal_var=tbl1.col1).
			if ( $alias === $column && preg_match( self::IDENTIFIER, $column, $matches ) ) {
				$alias = $matches['identifier'];
			}
			$this->aliases[trim( $alias )] = $external;
		}
		if ( !$this->dbId ) {
			return; // further checks and initialisations are impossible.
		}
		if ( isset( $args['type'] ) ) {
			$this->type = strtolower( $args['type'] );
		} else {
			$this->error( 'externaldata-db-incomplete-information', $this->dbId, 'type' );
		}
		// Database credentials.
		$this->setCredentials( $args ); // late binding.
	}

	/**
	 * Set credentials settings for database from $this->dbId.
	 * Should be overloaded, with a call to parent::setCredentials().
	 *
	 * @param array $params Supplemented parameters.
	 */
	protected function setCredentials( array $params ) {
		$this->credentials['user'] = isset( $params['user file'] ) && file_exists( $params['user file'] )
				? trim( file_get_contents( $params['user file'] ) )
				: $params['user' ] ?? null;
		if ( $this->credentials['user'] === null ) {
			$this->error( 'externaldata-db-incomplete-information', $this->dbId, 'user/user file' );
		}
		$this->credentials['password'] = isset( $params['password file'] ) && file_exists( $params['password file'] )
			? trim( file_get_contents( $params['password file'] ) )
			: $params['password' ] ?? null;
		if ( isset( $params[ 'name' ] ) ) {
			$this->credentials['dbname'] = $params['name'];
		} else {
			$this->error( 'externaldata-db-incomplete-information', $this->dbId, 'name' );
		}
	}

	/**
	 * Actually connect to the external data source.
	 * It is presumed that there are no errors in parameters and wiki settings.
	 * Set $this->values and $this->errors.
	 *
	 * @return bool True on success, false if error were encountered.
	 */
	public function run(): bool {
		if ( !$this->connect() /* late binding. */ ) {
			return false;
		}
		$rows = $this->fetch(); // late binding.
		if ( !$rows ) {
			return false;
		}
		$this->add( $this->processRows( $rows, $this->aliases ) );
		$this->disconnect(); // late binding.
		return true;
	}

	/**
	 * Establish connection the database server.
	 */
	abstract protected function connect();

	/**
	 * Get query text.
	 * @return string
	 */
	abstract protected function getQuery(): string;

	/**
	 * Get query result as a two-dimensional array.
	 * @return mixed
	 */
	abstract protected function fetch();

	/**
	 * Postprocess query result.
	 * @param mixed $rows A two-dimensional array or result wrapper containing query results.
	 * @param array $aliases An optional associative array of column aliases.
	 * @return array A two-dimensional array containing post-processed query results
	 */
	protected function processRows( $rows, array $aliases = [] ): array {
		$result = [];
		foreach ( $rows as $row ) {
			foreach ( $row as $column => $_ ) {
				$external = $aliases[$column] ?? $column;
				$result[$external] = $result[$external] ?? [];
				// Can be both array and object.
				$result[$external][] = self::processField( ( (array)$row )[$column] );
			}
		}
		return $result;
	}

	/**
	 * Process field value.
	 *
	 * @param string|DateTime|null $value
	 * @return string
	 */
	protected static function processField( $value ): string {
		if ( $value === null ) {
			return '';
		}
		// This can happen with MSSQL.
		if ( $value instanceof DateTime ) {
			$value = $value->format( 'Y-m-d H:i:s' );
		}
		// Convert the encoding to UTF-8 if necessary.
		$encoding = mb_detect_encoding( $value, 'UTF-8', true ) ?: 'UTF-8';
		return $encoding === 'UTF-8' ? $value : mb_convert_encoding( $value, 'UTF-8', $encoding );
	}

	/**
	 * Disconnect from DB server.
	 */
	abstract protected function disconnect();

	/**
	 * Return the version of the relevant software to be used at Special:Version.
	 * @param array $config
	 * @return array [ 'name', 'version' ]
	 */
	public static function version( array $config ): array {
		[ $name, $_ ] = parent::version( $config );
		return [ $name, false ]; // We do not want connected databases to appear on Special:Version.
	}
}
