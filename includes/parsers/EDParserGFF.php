<?php
/**
 * Class for GFF parser.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */

class EDParserGFF extends EDParserBase {
	/** @var array $columns Pre-defined names of GFF columns. */
	private static $columns = [ 'seqid', 'source', 'type', 'start', 'end', 'score', 'strand', 'phase', 'attributes' ];

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );
	}

	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 */
	public function __invoke( $text ) {
		// use an fgetcsv() call, similar to the one in getCSVData()
		// (fgetcsv() can handle delimiters other than commas, in this
		// case a tab)
		$fiveMBs = 5 * 1024 * 1024;
		$fp = fopen( "php://temp/maxmemory:$fiveMBs", 'r+' );
		fwrite( $fp, $text );
		rewind( $fp );
		$table = [];
		// phpcs:ignore MediaWiki.ControlStructures.AssignmentInControlStructures.AssignmentInControlStructures
		while ( $line = fgetcsv( $fp, null, "\t" ) ) {
			// ignore comment lines
			if ( strpos( $line[0], '##' ) !== 0 ) {
				// special handling for final 'attributes' column
				if ( array_key_exists( 8, $line ) ) {
					$attributes = explode( ';', $line[8] );
					foreach ( $attributes as $attribute ) {
						$keyAndValue = explode( '=', $attribute, 2 );
						if ( count( $keyAndValue ) === 2 ) {
							$key = strtolower( $keyAndValue[0] );
							$value = $keyAndValue[1];
							$line[$key] = $value;
						}
					}
				}
				array_push( $table, $line );
			}
		}
		fclose( $fp );

		$values = parent::__invoke( $text );
		foreach ( $table as $line ) {
			foreach ( $line as $i => $row_val ) {
				// each of the columns in GFF have a
				// pre-defined name - even the last column
				// has its own name, "attributes".
				$column = is_numeric( $i ) && isset( self::$columns[(int)$i] ) ? self::$columns[(int)$i] : $i;
				if ( !array_key_exists( $column, $values ) ) {
					$values[$column] = [];
				}
				$values[$column][] = $row_val;
			}
		}
		return $values;
	}
}
