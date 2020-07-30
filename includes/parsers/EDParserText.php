<?php
/**
 * Class for plain text parser.
 *
 * @author Alexander Mashin
 */

class EDParserText extends EDParserBase {
	/** @var bool $preserve_external_variables_case Whether external variables' names are case-sensitive for this format. */
	protected static $preserve_external_variables_case = true;

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
	 * @param ?array $defaults The intial values.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 */
	public function __invoke( $text, $defaults = [] ) {
		$values = parent::__invoke( $text, $defaults );
		$values['text'] = [ $text ];
		return $values;
	}
}
