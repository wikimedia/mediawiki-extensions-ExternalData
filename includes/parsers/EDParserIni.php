<?php
/**
 * Class for key: value sequences.
 *
 * @author Alexander Mashin
 */
class EDParserIni extends EDParserBase {
	/** @var bool $keepExternalVarsCase Whether external variables' names are case-sensitive for this format. */
	public $keepExternalVarsCase = true;
	/** @var string $assignment Assignment mark, separating key and value. */
	private $delimiter;

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );

		$this->delimiter = isset( $params['delimiter'] ) ? $params['delimiter'] : '=';
	}

	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 * @param ?array $defaults The initial values.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 */
	public function __invoke( $text, $defaults = [] ) {
		$values = [];
		foreach ( explode( PHP_EOL, $text ) as $line ) {
			if ( !$line ) {
				continue;
			}

			if ( str_contains( $line, $this->delimiter ) ) {
				[ $key, $value ] = explode( $this->delimiter, $line, 2 );
				$key = trim( $key );
				$value = trim( $value );
			} else {
				// Special treatment for lines without a colon: put them in __comments external variable.
				$value = $line;
				$key = '__comments';
			}

			if ( !isset( $values[$key] ) ) {
				$values[$key] = [];
			}
			$values[$key][] = $value;
		}
		return $values;
	}
}
