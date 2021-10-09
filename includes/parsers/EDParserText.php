<?php
/**
 * Class for plain text parser.
 *
 * @author Alexander Mashin
 */

class EDParserText extends EDParserBase {
	/** @var bool $keepExternalVarsCase Whether external variables' names are case-sensitive for this format. */
	public $keepExternalVarsCase = true;

	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 */
	public function __invoke( $text ) {
		$values = parent::__invoke( $text );
		$values['__text'] = [ $text ];
		return $values;
	}
}
