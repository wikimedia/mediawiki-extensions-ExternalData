<?php
/**
 * Class for key: value sequences.
 *
 * @author Alexander Mashin
 */
class EDParserIni extends EDParserBase {
	/** @var bool $keepExternalVarsCase Whether external variables' names are case-sensitive for this format. */
	public $keepExternalVarsCase = true;
	/** @var string $delimiter Assignment mark, separating key and value. */
	private $delimiter;
	/** @var string $commentDelimiter Delimiter that starts line comments. */
	private $commentDelimiter;

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );

		$this->delimiter = isset( $params['delimiter'] ) ? $params['delimiter'] : '=';
		$this->commentDelimiter = array_key_exists( 'comment delimiter', $params ) ? $params['comment delimiter'] : '#';
	}

	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 */
	public function __invoke( $text ) {
		$delimiter = '(?<!\\\\)' . preg_quote( $this->delimiter, '/' ); // delimiter, but not escaped.
		// Comment delimiter, but not escaped.
		$comment = $this->commentDelimiter ? '(?<!\\\\)' . preg_quote( $this->commentDelimiter, '/' ) : '$NeverMatches';

		$regex = <<<REGEX
			/(	# The point of this regex is to make key=value lines and comments of both kinds
				# (whole non-key=value lines and # line comments) separate matches.

				# First alternative: key=value followed by a comment or newline
				^(?<key>((?!$delimiter|$comment).)+?) # key, which cannot contain unescaped [comment] delimiter
				$delimiter # unescaped delimiter
				(?<value>(?!$comment).*?) # value, which cannot contain unescaped comment delimiter
				(?=$|$comment) # value continues until unescaped comment delimiter or line end is met
			|
				# Second alternative: comment following unescaped comment delimiter or newline; and not key=value
				(?<comment>(?<=^|$comment).+)$ # continues until line end
			)/mx
REGEX;
		// PHP 7.2 doesn't allow indented HEREDOC closing identifier, and MW still tests under PHP 7.2.

		$values = [];
		preg_match_all( $regex, $text, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			if ( $match['key'] ) {
				$key = trim( $match['key'] );
				$value = trim( $match['value'] );
			} elseif ( $match['comment'] ) {
				$key = '__comments';
				$value = trim( $match['comment'] );
			}
			if ( $key ) {
				if ( !isset( $values[$key] ) ) {
					$values[$key] = [];
				}
				$values[$key][] = $value;
			}
		}

		return $values;
	}
}
