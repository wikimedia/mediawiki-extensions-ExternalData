<?php
/**
 * Class for plain text parser.
 *
 * @author Alexander Mashin
 */

class EDParserText extends EDParserBase {
	/** @var bool $keepExternalVarsCase Whether external variables' names are case-sensitive for this format. */
	protected static $keepExternalVarsCase = true;

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
		$values = parent::__invoke( $text, $defaults );
		$values['__text'] = [ $text ];
		return $values;
	}
}
