<?php
/**
 * Class for comma separated text (with a header line or without) parser.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */

class EDParserCSV extends EDParserBase {
	/** @const string NAME The name of this format. */
	public const NAME = 'CSV';
	/** @const array EXT The usual file extensions of this format. */
	protected const EXT = [ 'csv' ];
	/** @const int GENERICITY The greater, the more this format is likely to succeed on a random input. */
	public const GENERICITY = 15;

	/** @const int NO_HEADER There is no header line. */
	private const NO_HEADER = 0;
	/** @const int HEADER There is a header line. */
	private const HEADER = 1;
	/** @const int DETECT_HEADER Detect, whether there is a header line. */
	private const DETECT_HEADER = 2;
	/** @var int $header Whether the header is present, not, or has to be autodetected. */
	private $header;

	/** @var string[] $delimiters Possible column delimiters. */
	private $delimiters = [ ';', ',', "\t", '|' ];

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );

		// Is there a header line?
		if (
			strtolower( $params['format'] ) === 'csv with header' ||
			array_key_exists( 'with header', $params ) ||
			array_key_exists( 'header', $params ) && $params['header'] === 'yes'
		) {
			$this->header = self::HEADER;
		} elseif ( array_key_exists( 'header', $params ) && (
			$params['header'] === 'auto' || $params['header'] === 'detect' || $params['header'] === 'autodetect'
		) ) {
			$this->header = self::DETECT_HEADER;
		} else {
			$this->header = self::NO_HEADER;
		}

		// Also, analyse the 'data' parameter: whether it is numeric or not.
		if ( $this->header !== self::HEADER ) {
			foreach ( $this->external as $column ) {
				if ( !is_numeric( $column ) && substr( $column, 0, 2 ) !== '__' ) {
					$this->header = self::HEADER;
					break;
				}
			}
		}

		if ( array_key_exists( 'delimiter', $params ) && $params['delimiter'] !== 'auto' ) {
			// Wrap single param in an array.
			$params['delimiter'] = is_array( $params['delimiter'] ) ? $params['delimiter'] : [ $params['delimiter'] ];
			// Allow for tab delimiters, using \t.
			$this->delimiters = array_map( static function ( $str ) {
				return str_replace( '\t', "\t", $str );
			}, $params['delimiter'] );
		}
	}

	/**
	 * Parse the comma-separated text. Called as $parser( $text, $defaults ) as syntactic sugar.
	 *
	 * Reload the method in descendant classes, calling parent::__invoke() in the beginning.
	 * Apply mapAndFilter() in the end.
	 *
	 * @param string $text The text to be parsed.
	 * @param string|null $path URL or filesystem path that may be relevant to the parser.
	 * @return array A two-dimensional column-based array of the parsed values.
	 * @throws EDParserException
	 */
	public function __invoke( $text, $path = null ): array {
		$table = $this->parseCSV( $text, $this->delimiters );

		// Since $text is already converted to UTF-8, is it necessary to keep the code below?

		// Get rid of the "byte order mark", if it's there - it could
		// be one of a variety of options, depending on the encoding.
		// Code copied in part from:
		// http://artur.ejsmont.org/blog/content/annoying-utf-byte-order-marks
		$sets = [
			"\xFE",
			"\xFF",
			"\xFE\xFF",
			"\xFF\xFE",
			"\xEF\xBB\xBF",
			"\x2B\x2F\x76",
			"\xF7\x64\x4C",
			"\x0E\xFE\xFF",
			"\xFB\xEE\x28",
			"\x00\x00\xFE\xFF",
			"\xDD\x73\x66\x73",
		];
		$decoded_first_cell = mb_convert_encoding( $table[0][0], 'ISO-8859-1', 'UTF-8' );
		foreach ( $sets as $set ) {
			if ( strncmp( $decoded_first_cell, $set, strlen( $set ) ) === 0 ) {
				$table[0][0] = substr( $decoded_first_cell, strlen( $set ) + 1 );
				break;
			}
		}

		// Another "byte order mark" test, this one copied from the
		// Data Transfer extension - somehow the first one doesn't work
		// in all cases.
		$byte_order_mark = pack( "CCC", 0xef, 0xbb, 0xbf );
		if ( strncmp( $table[0][0], $byte_order_mark, 3 ) === 0 ) {
			$table[0][0] = substr( $table[0][0], 3 );
		}

		// Get header values, if this is 'csv with header'
		$header = $this->header === self::HEADER ||
				$this->header === self::DETECT_HEADER &&
				self::headerDetected( $table[0], isset( $table[1] ) ? $table[1] : null );
		$header_vals = null;
		if ( $header ) {
			$header_vals = array_shift( $table );
			// On the off chance that there are one or more blank
			// lines at the beginning, cycle through.
			while ( count( $header_vals ) === 0 ) {
				$header_vals = array_shift( $table );
			}

			// Unfortunately, some subpar CSV generators don't include
			// trailing commas, so that a line that should look like
			// "A,B,,," instead is just printed as "A,B".
			// To get around this, we first figure out the correct number
			// of columns in this table - which depends on whether the
			// CSV has a header or not.
			$num_columns = count( $header_vals );
		} else {
			$num_columns = 0;
			foreach ( $table as $line ) {
				$num_columns = max( $num_columns, count( $line ) );
			}
		}

		$values = parent::__invoke( $text );

		// Now "flip" the data, turning it into a column-by-column
		// array, instead of row-by-row.
		foreach ( $table as $line ) {
			for ( $i = 0; $i < $num_columns; $i++ ) {
				// This check is needed in case it's an
				// uneven CSV file (see above).
				if ( array_key_exists( $i, $line ) ) {
					$row_val = trim( $line[$i] );
				} else {
					$row_val = '';
				}
				if ( $header_vals ) {
					$column = strtolower( trim( $header_vals[$i] ) );
				} else {
					// start with an index of 1 instead of 0
					$column = $i + 1;
				}
				if ( !array_key_exists( $column, $values ) ) {
					$values[$column] = [];
				}
				$values[$column][] = $row_val;
			}
		}

		$values['__text'] = [ $text ]; // CSV succeeds too often; this helps plain text format.

		return $values;
	}

	/**
	 * Detect header line in the table.
	 * @param array $first The first line that may be a header line.
	 * @param ?array $second The second line -- a specimen of data.
	 * @return bool True, if the first line is likely to be a header line.
	 */
	private static function headerDetected( array $first, ?array $second ) {
		// A column header is not likely to be a float number.
		foreach ( $first as $cell ) {
			if ( preg_match( '/^\s*[+-]?\d*[.,]\d+/', $cell ) ) {
				return false;
			}
		}
		// Compare data types in the first and the second lines.
		if ( $second ) {
			foreach ( $first as $column => $cell ) {
				if ( is_numeric( $cell ) !== is_numeric( $second[$column] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Parse $text to CSV, trying $delimiters one by one.
	 * @param string $text Text to parse to CSV.
	 * @param array $delimiters An array of possible delimiters.
	 * @return string[][] 2D line-based array of values.
	 */
	private function parseCSV( $text, array $delimiters ): array {
		// Filter out empty lines after splitting the text by various newlines.
		$lines = array_filter( preg_split( "/\r\n|\n|\r/", $text ), static function ( $line ) {
			return trim( $line ) !== '';
		} );
		$any_columns = 0;
		$any_csv = [];
		$good_columns = 0;
		$good_csv = null;
		foreach ( $delimiters as $delimiter ) {
			$table = array_map( static function ( $line ) use ( $delimiter ) {
				return array_map( static function ( $cell ) {
					// Get rid of \0's sometimes showing up for certain encodings, presumably, after splitting.
					return str_replace( chr( 0 ), '', $cell );
				}, str_getcsv( $line, $delimiter ) );
			}, $lines );
			// Rate the success of this delimiter.
			$lengths = array_map( 'count', $table ); // number of fields in each line.
			$max_lengths = max( $lengths );
			if ( $max_lengths > 1 && $max_lengths > $good_columns && min( $lengths ) === $max_lengths ) {
				// The current delimiter makes the widest well-formed CSV so far.
				$good_columns = $max_lengths;
				$good_csv = $table;
			}
			if ( $max_lengths > $any_columns ) {
				// The current delimiter has managed to split at least one line into a greater number of fields.
				// But this CSV can be badly formed, i.e. uneven.
				$any_csv = $table;
				$any_columns = $max_lengths;
			}
		}
		return $good_csv ?: $any_csv; // return the widest well-formed CSV; if none, then the widest CSV.
	}
}
