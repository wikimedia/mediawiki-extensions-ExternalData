<?php
/**
 * Class for plain text parser.
 *
 * @author Alexander Mashin
 */

class EDParserText extends EDParserBase {
	/** @const string NAME The name of this format. */
	public const NAME = 'TEXT';
	/** @const int GENERICITY The greater, the more this format is likely to succeed on a random input. */
	public const GENERICITY = 20;

	/** @var bool $keepExternalVarsCase Whether external variables' names are case-sensitive for this format. */
	public $keepExternalVarsCase = true;

	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 * @param string|null $path URL or filesystem path that may be relevant to the parser.
	 * @return array A two-dimensional column-based array of the parsed values.
	 * @throws EDParserException
	 */
	public function __invoke( $text, $path = null ): array {
		$values = parent::__invoke( $text );
		$values['__text'] = [ $text ];
		return $values;
	}
}
