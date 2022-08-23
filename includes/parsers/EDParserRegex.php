<?php

/**
 * Class for text parser based on PERL-compatible regular expressions.
 *
 * @author Alexander Mashin
 */

class EDParserRegex extends EDParserBase {
	/** @const string NAME The name of this format. */
	public const NAME = 'TEXT';
	/** @var string The regular expression. */
	private $regex;

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 * @throws EDParserException
	 *
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );

		if ( isset( $params['regex'] ) ) {
			$regex = $params['regex'];
		} else {
			throw new EDParserException( 'externaldata-no-param-specified', 'regex' );
		}

		// Validate regex.
		self::suppressWarnings();
		// Run regular expression against null and compare results with false.
		// @see https://stackoverflow.com/a/12941133.
		// @phan-suppress-next-line PhanTypeMismatchArgumentInternalReal, PhanTypeMismatchArgumentInternalProbablyReal
		if ( preg_match( $regex, null ) !== false ) {
			// A valid regular expression.
			$this->regex = $regex;
		} else {
			// A broken regular expression.
			throw new EDParserException(
				'externaldata-invalid-format-explicit',
				$regex,
				'PERL-compatible regular expression'
			);
		}
		// Restore warnings.
		self::restoreWarnings();
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
		$matches = [];
		// The regular expression has been validated in the constructor.
		preg_match_all( $this->regex, $text, $matches, PREG_PATTERN_ORDER );
		return $matches;
	}
}
