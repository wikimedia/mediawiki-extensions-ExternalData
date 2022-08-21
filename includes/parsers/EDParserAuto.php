<?php
/**
 * Text parser that auto-detects real text format and invokes the necessary parser.
 *
 * @author Alexander Mashin
 *
 */
class EDParserAuto extends EDParserBase {
	use EDConnectorParsable;

	/** @var array $parsers A list of all available non-abstract EDParser* classes. */
	private static $parsers;

	/** @var array $args A copy of all parameters and settings. */
	private $args;

	/**
	 * Constructor.
	 *
	 * @param array $params An associative array of parameters.
	 * @param array $headers An optional array of HTTP headers.
	 *
	 * @throws EDParserException
	 */
	protected function __construct( array $params, array $headers = [] ) {
		parent::__construct( $params, $headers );

		// Get a list of all available non-abstract EDParser* classes.
		self::$parsers = array_filter( array_unique( array_map( static function ( array $record ) {
			return $record[1];
		}, self::setting( 'Parsers' ) ) ), static function ( $name ) {
			// We don't need EDParserAuto.
			return $name !== __CLASS__;
		} );
		// Too successful formats will be tried last.
		usort( self::$parsers, static function ( $a, $b ) {
			return $a::GENERICITY <=> $b::GENERICITY;
		} );

		// We need a copy of all parameters and setting, since we don't know which we will need.
		$this->args = $params;
		// CSV will try to detect a header line.
		if ( !isset( $this->args['header'] ) ) {
			$this->args['header'] = 'auto';
		}
	}

	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 * @param string|null $path URL or filesystem path that may be relevant to the parser.
	 * @return array A two-dimensional column-based array of the parsed values.
	 * @throws EDParserException
	 */
	public function __invoke( $text, $path = null ): array {
		$extension = $path ? pathinfo( $path, PATHINFO_EXTENSION ) : null;

		// First, go over the classes that prefer the given extension.
		$failed = [];
		if ( $extension ) {
			foreach ( self::$parsers as $parser ) {
				$class_extensions = $parser::extensions();
				if ( in_array( $extension, $class_extensions, true ) ) {
					$values = $this->tryFormat( $parser, $text );
					if ( $values ) {
						$this->keepExternalVarsCase = $this->parser->keepExternalVarsCase;
						return $values;
					}
					$failed[$parser] = true;
				}
			}
		}

		// Second, go over all classes that we have not tried.
		foreach ( self::$parsers as $parser ) {
			if ( !isset( $failed[$parser] ) ) {
				$values = $this->tryFormat( $parser, $text );
				if ( $values ) {
					$this->keepExternalVarsCase = $this->parser->keepExternalVarsCase;
					return $values;
				}
			}
		}
	}

	/**
	 * Try a format.
	 * @param string $class EDParser class name.
	 * @param string $text Text to parse.
	 * @return array|null Parsed values or null, if parsing failed.
	 */
	private function tryFormat( $class, $text ) {
		try {
			$this->prepareParser( $this->args, $class );
		} catch ( EDParserException $e ) {
			return null;
		}
		if ( !$this->parser ) {
			return null;
		}
		try {
			$values = $this->parse( $text );
		} catch ( EDParserException $e ) {
			return null;
		}
		if ( is_array( $values ) && self::count( $values ) ) {
			$values['__format'] = [ $class::NAME ];
			return $values;
		}
		return null;
	}

	/**
	 * Count actual number of records extracted.
	 * @param array $values Values to count.
	 * @return int Number of values.
	 */
	private static function count( array $values ) {
		$count = 0;
		foreach ( $values as $column => $cells ) {
			if ( substr( $column, 0, 2 ) !== '__' ) {
				$count = max( $count, count( $cells ) );
			}
		}
		return $count;
	}
}
