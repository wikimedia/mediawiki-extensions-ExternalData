<?php
use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * Class for HTML parser extracting data using CSS selectors with slightly extended syntax.
 *
 * @author Alexander Mashin
 *
 */
class EDParserHTMLwithCSS extends EDParserHTMLwithXPath {
	/** @const array EXT The usual file extensions of this format. */
	protected const EXT = [ 'htm', 'html' ];

	/** @var array Mappings of CSS selectors to XPaths. */
	private $cssToXpath = [];

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

		if ( !class_exists( 'Symfony\Component\CssSelector\CssSelectorConverter' ) ) {
			// Addressing DOM nodes with CSS/jQuery-like selectors requires symfony/css-selector.
			throw new EDParserException(
				'externaldata-format-unavailable',
				'symfony/css-selector',
				'HTML',
				'use xpath'
			);
		}

		// This is important for the right choice of format, if it is "auto".
		if ( array_key_exists( 'use xpath', $params ) ) {
				throw new EDParserException( 'dummy message' );
		}

		// Convert CSS selectors to XPaths and record them in $mappings.
		// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
		$converter = new CssSelectorConverter();
		$selector_regex = '/(?<selector>.+?)(\.\s*attr\s*\(\s*(?<quote>["\']?)(?<attr>.+?)\k<quote>\s*\))?$/i';
		foreach ( $this->external as &$selector ) {
			if ( !preg_match( $selector_regex, $selector, $matches ) ) {
				throw new EDParserException( 'externaldata-invalid-format-explicit', $selector, 'CSS selector' );
			}
			try {
				// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
				$xpath = $converter->toXPath( $matches['selector'] );
			} catch ( Exception $e ) {
				throw new EDParserException(
					'externaldata-error-converting-css-to-xpath',
					$selector,
					$e->getMessage()
				);
			}
			$xpath = '/' . strtr( $xpath, [
				'descendant-or-self::*' => '',
				'descendant-or-self::' => '/'
			] );
			// CSS selector syntax extension: .attr(href).
			$xpath .= isset( $matches['attr'] ) ? '/@' . $matches['attr'] : '';
			$this->cssToXpath[$selector] = $xpath;
			$selector = $xpath;
		}
	}

	/**
	 * Parse the text as HTML. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 * @param string|null $path URL or filesystem path that may be relevant to the parser.
	 * @return array A two-dimensional column-based array of the parsed values.
	 * @throws EDParserException
	 */
	public function __invoke( $text, $path = null ): array {
		$xpath_values = parent::__invoke( $text );
		foreach ( $this->cssToXpath as $css => $xpath ) {
			$xpath_values[$css] = $xpath_values[$xpath];
		}
		return $xpath_values;
	}
}
