<?php
/**
 * Class for GFF parser.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */

class EDParserGFF extends EDParserBase {
	/** @const string NAME The name of this format. */
	public const NAME = 'GFF';
	/** @const array EXT The usual file extensions of this format. */
	protected const EXT = [ 'gff' ];
	/** @const int GENERICITY The greater, the more this format is likely to succeed on a random input. */
	public const GENERICITY = 10;

	/** @var array $columns Pre-defined names of GFF columns. */
	private static $columns = [ 'seqid', 'source', 'type', 'start', 'end', 'score', 'strand', 'phase', 'attributes' ];

	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 * @param string|null $path URL or filesystem path that may be relevant to the parser.
	 * @return array A two-dimensional column-based array of the parsed values.
	 * @throws EDParserException
	 */
	public function __invoke( $text, $path = null ): array {
		$table = array_map( static function ( $line ) {
			$cells = str_getcsv( $line, "\t" );
			// Require at least eight columns.
			if ( count( $cells ) < 8 ) {
				throw new EDParserException( 'externaldata-invalid-format', self::NAME, 'At least 8 columns required' );
			}
			if ( isset( $cells[8] ) ) {
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal
				$attributes = $cells[8] ? explode( ';', $cells[8] ) : [];
				foreach ( $attributes as $attribute ) {
					if ( strpos( $attribute, '=' ) !== false ) {
						[ $key, $value ] = explode( '=', $attribute, 2 );
						$cells[strtolower( $key )] = $value;
					}
				}
			}
			return $cells;
		// Filter out empty lines after splitting the text by various newlines.
		}, array_filter( preg_split( "/\r\n|\n|\r/", $text ), static function ( $line ) {
			return trim( $line ) !== '';
		} ) );

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
