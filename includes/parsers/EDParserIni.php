<?php
/**
 * Class for key: value sequences.
 *
 * @author Alexander Mashin
 */
class EDParserIni extends EDParserBase {
	/** @const string NAME The name of this format. */
	public const NAME = 'INI';
	/** @const array EXT The usual file extensions of this format. */
	protected const EXT = [ 'ini' ];

	/** @const int GENERICITY The greater, the more this format is likely to succeed on a random input. */
	public const GENERICITY = 10;

	/** @var bool $keepExternalVarsCase Whether external variables' names are case-sensitive for this format. */
	public $keepExternalVarsCase = true;
	/** @var array $delimiters Assignment mark, separating key and value. */
	private $delimiters = [ '=', ':' ];
	/** @var array $commentDelimiters Possible delimiters that start line comments. */
	private $commentDelimiters = [ '#', ';' ];
	/** @var bool $treatInvalidAsComments Treat lines that are neither settings nor comments as comments. */
	private $treatInvalidAsComments = false;

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );

		if ( isset( $params['delimiter'] ) && $params['delimiter'] !== 'auto' ) {
			$this->delimiters = [ $params['delimiter'] ];
		}
		if ( isset( $params['comment delimiter'] ) && $params['comment delimiter'] !== 'auto' ) {
			$this->commentDelimiters = [ $params['comment delimiter'] ];
		}
		if ( array_key_exists( 'invalid as comments', $params ) ) {
			$this->treatInvalidAsComments = true;
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
		// Filter out empty lines after splitting the text by various newlines.
		$lines = array_filter( preg_split( "/\r\n|\n|\r/", $text ), static function ( $line ) {
			return trim( $line ) !== '';
		} );

		// Comment delimiter, but not escaped.
		$comment_start = $this->commentDelimiters
			? '(?<!\\\\)(?:' . implode( '|', array_map( static function ( $delim ) {
				return preg_quote( $delim, '/' );
			}, $this->commentDelimiters ) ) . ')'
			: '$NeverMatches';

		$max_assignments = 0;
		// Try possible delimiters one by one.
		foreach ( $this->delimiters as $delimiter ) {
			$delimiter = '(?<!\\\\)' . preg_quote( $delimiter, '/' ); // delimiter, but not escaped.
			$regex = <<<REGEX
				/^
				# key = value:
				( (?<key>((?!$delimiter|$comment_start).)+?) # key, which cannot contain unescaped [comment] delimiter
					$delimiter # unescaped delimiter
					(?<value>(?!$comment_start).*?) # value, which cannot contain unescaped comment delimiter
				)?
				# comment:
				\s* ( $comment_start (?<comment>.+) )?
				$/x
REGEX;
			// PHP 7.2 doesn't allow indented HEREDOC closing identifier, and MW still tests under PHP 7.2.

			$assignments_found = 0;
			$assignments = [];
			foreach ( $lines as $line ) {
				$comment = null;
				if ( preg_match( $regex, $line, $match, PREG_UNMATCHED_AS_NULL ) ) {
					if ( isset( $match['key'] ) ) {
						$key = trim( $match['key'] );
						$value = trim( $match['value'] );
						if ( !isset( $assignments[$key] ) ) {
							$assignments[$key] = [];
						}
						$assignments[$key][] = $value;
						$assignments_found++;
					}
					if ( isset( $match['comment'] ) ) {
						$comment = trim( $match['comment'] );
					}
				} elseif ( $this->treatInvalidAsComments ) {
						$comment = $line;
				} else {
						// This delimiter is no good.
						$assignments_found = 0;
						break;
				}
				if ( $comment ) {
					if ( !isset( $assignments['__comments'] ) ) {
						$assignments['__comments'] = [];
					}
					$assignments['__comments'][] = $comment;
				}
			}
			// Rate the success of this delimiter.
			if ( $assignments_found > $max_assignments ) {
				$values = $assignments;
				$max_assignments = $assignments_found;
			}
		}
		if ( $max_assignments === 0 ) {
			// A meaningful INI file cannot consist of comments only.
			throw new EDParserException( 'externaldata-invalid-format', self::NAME );
		}
		$values['__text'] = [ $text ]; // INI succeeds too often; this helps plain text format.
		return $values;
	}
}
