<?php
/**
 * Abstract class for JSON parsers.
 *
 * @author Alexander Mashin
 */

abstract class EDParserJSON extends EDParserBase {
	/** @const string NAME The name of this format. */
	public const NAME = 'JSON';
	/** @const array EXT The usual file extensions of this format. */
	protected const EXT = [ 'json' ];

	/** @var int Optional length of the ignored prefix. */
	protected $prefixLength;
	/** @var bool $allowTrailingComma Allow non-standard trailing comma in JSON. */
	private $allowTrailingComma;

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 */
	protected function __construct( array $params ) {
		parent::__construct( $params );

		if ( !function_exists( 'json_decode' ) ) {
			// PECL json extension is required.
			throw new EDParserException( 'externaldata-format-unavailable-absolute', 'PECL JSON', 'json or yaml' );
		}

		$this->prefixLength = isset( $params['json offset'] ) ? (int)$params['json offset'] : 0;
		$this->allowTrailingComma = array_key_exists( 'allow trailing commas', $params );
	}

	/**
	 * Add newlines before closing and after opening braces and brackets to help cutting out fragments, if ordered.
	 *
	 * @param string $json JSON to add newlines to.
	 * @param bool $new_lines Whether to add new lines.
	 *
	 * @return string JSON with newlines added.
	 */
	public function addNewlines( $json, $new_lines ) {
		return $new_lines
			? preg_replace(
				[ '/(?<!^|\s)[ \t]*([}\]])/m', '/([{[])[ \t]*(?!$|\s)/m' ],
				[ PHP_EOL . '$1', '$1' . PHP_EOL ],
				$json
			)
			: $json;
	}

	/**
	 * Remove trailing comma, if $this->allowTrailingComma is set.
	 *
	 * @param string $json JSON, possibly with trailing commas.
	 * @return string JSON without trailing commas, if $this->allowTrailingComma is set.
	 */
	public function removeTrailingComma( $json ) {
		return $this->allowTrailingComma ? preg_replace( '/,(?=\s*[}\]])/s', '', $json ) : $json;
	}
}
