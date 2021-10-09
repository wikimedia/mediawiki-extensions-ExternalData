<?php
/**
 * Class for comma separated text (with a header line or without) parser.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */

class EDParserCSV extends EDParserBase {
	/** @var bool The processed text contains a header line. */
	private $header;
	/** @var string Column delimiter. */
	private $delimiter = ',';

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );

		$this->header = strtolower( $params['format'] ) === 'csv with header'
					 || strtolower( $params['format'] ) === 'csv' && array_key_exists( 'with header', $params );
		if ( array_key_exists( 'delimiter', $params ) ) {
			// Allow for tab delimiters, using \t.
			$this->delimiter = str_replace( '\t', "\t", $params['delimiter'] );
		}
	}

	/**
	 * Parse the comma-separated text. Called as $parser( $text, $defaults ) as syntactic sugar.
	 *
	 * Reload the method in descendant classes, calling parent::__invoke() in the beginning.
	 * Apply mapAndFilter() in the end.
	 *
	 * @param string $text The text to be parsed.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 */
	public function __invoke( $text ) {
		$values = parent::__invoke( $text );
		// from http://us.php.net/manual/en/function.str-getcsv.php#88311
		// str_getcsv() is a function that was only added in PHP 5.3.0,
		// so use the much older fgetcsv() if it's not there

		// actually, for now, always use fgetcsv(), since this call to
		// str_getcsv() doesn't work, and I can't test/debug it at the
		// moment
		//if ( function_exists( 'str_getcsv' ) ) {
		//	$table = str_getcsv( $csv );
		//} else {
			$fiveMBs = 5 * 1024 * 1024;
			$fp = fopen( "php://temp/maxmemory:$fiveMBs", 'r+' );
			fwrite( $fp, $text );
			rewind( $fp );
			$table = [];
			// phpcs:ignore MediaWiki.ControlStructures.AssignmentInControlStructures.AssignmentInControlStructures
			while ( $line = fgetcsv( $fp, 0, $this->delimiter ) ) {
				$table[] = $line;
			}
			fclose( $fp );
		// }

		// Get rid of blank characters - these sometimes show up
		// for certain encodings.
		foreach ( $table as $i => $row ) {
			foreach ( $row as $j => $cell ) {
				$table[$i][$j] = str_replace( chr( 0 ), '', $cell );
			}
		}

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
		$decodedFirstCell = utf8_decode( $table[0][0] );
		foreach ( $sets as $set ) {
			if ( strncmp( $decodedFirstCell, $set, strlen( $set ) ) === 0 ) {
				$table[0][0] = substr( $decodedFirstCell, strlen( $set ) + 1 );
				break;
			}
		}

		// Another "byte order mark" test, this one copied from the
		// Data Transfer extension - somehow the first one doesn't work
		// in all cases.
		$byteOrderMark = pack( "CCC", 0xef, 0xbb, 0xbf );
		if ( strncmp( $table[0][0], $byteOrderMark, 3 ) === 0 ) {
			$table[0][0] = substr( $table[0][0], 3 );
		}

		// Get header values, if this is 'csv with header'
		if ( $this->header ) {
			$header_vals = array_shift( $table );
			// On the off chance that there are one or more blank
			// lines at the beginning, cycle through.
			while ( count( $header_vals ) === 0 ) {
				$header_vals = array_shift( $table );
			}
		}

		// Unfortunately, some subpar CSV generators don't include
		// trailing commas, so that a line that should look like
		// "A,B,,," instead is just printed as "A,B".
		// To get around this, we first figure out the correct number
		// of columns in this table - which depends on whether the
		// CSV has a header or not.
		if ( $this->header ) {
			$num_columns = count( $header_vals );
		} else {
			$num_columns = 0;
			foreach ( $table as $line ) {
				$num_columns = max( $num_columns, count( $line ) );
			}
		}

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
				if ( $this->header ) {
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
		return $values;
	}
}
