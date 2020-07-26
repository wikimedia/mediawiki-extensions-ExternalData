<?php
/**
 * Class for HTML parser extracting data using CSS selectors with slightly extended syntax.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */

class EDParserHTMLwithCSS extends EDParserHTMLwithXPath {

	/**
	 * Return the reason why this format cannot be used.
	 *
	 * @return string|null Reason for unavailability, or null if this parser is available.
	 *
	 */
	public static function reasonForUnavailability() {
		if ( !class_exists( 'Symfony\Component\CssSelector\CssSelectorConverter' ) ) {
			// Addressing DOM nodes with CSS/jQuery-like selectors requires symfony/css-selector.
			return wfMessage( 'externaldata-format-unavailable', 'symfony/css-selector', 'HTML', 'use xpath' )->parse();
		}
		return null;
	}

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 * @throws MWException.
	 *
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );

		// Convert CSS selectors to XPaths and record them in $mappings.
		$converter = new Symfony\Component\CssSelector\CssSelectorConverter();
		foreach ( $this->mappings as $local_var => &$selector ) {
			preg_match( '/(?<selector>.+?)(\.\s*attr\s*\(\s*(?<quote>["\']?)(?<attr>.+?)\k<quote>\s*\))?$/i', $selector, $matches );
			try {
				$xpath = $converter->toXPath( $matches ['selector'] );
			} catch ( Exception $e ) {
				throw new MWException( wfMessage( 'externaldata-error-converting-css-to-xpath', $selector, $e->getMessage() )->text() );
			}
			$xpath = '/' . strtr( $xpath, [
				'descendant-or-self::*' => '',
				'descendant-or-self::' => '/'
			] );
			// CSS selector syntax extension: .attr(href).
			$xpath .= isset( $matches ['attr'] ) ? '/@' . $matches ['attr'] : '';
			$selector = $xpath;
		}
	}
}
