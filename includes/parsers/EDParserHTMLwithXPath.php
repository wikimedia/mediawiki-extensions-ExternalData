<?php
/**
 * Class for HTML parser extracting data using XPath notation.
 *
 * @author Alexander Mashin
 */

class EDParserHTMLwithXPath extends EDParserXMLwithXPath {
	/** @const string NAME The name of this format. */
	public const NAME = 'HTML';
	/** @const array EXT The usual file extensions of this format. */
	protected const EXT = [ 'htm', 'html' ];

	/** @const int GENERICITY The greater, the more this format is likely to succeed on a random input. */
	public const GENERICITY = 5;

	/**
	 * Parse the text as HTML. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 * @param string|null $path URL or filesystem path that may be relevant to the parser.
	 * @return array A two-dimensional column-based array of the parsed values.
	 * @throws EDParserException
	 */
	public function __invoke( $text, $path = null ): array {
		$doc = new DOMDocument( '1.0', 'UTF-8' );
		// Remove whitespaces.
		$doc->preserveWhiteSpace = false;

		// Otherwise, the encoding will be broken. Yes, it's an abstraction leak.
		$text = preg_replace( '/<\?xml[^?]+\?>/i', '', $text );
		// <? fix for color highlighting in vi
		$text = '<?xml version="1.0" encoding="UTF-8" ?>' . $text;

		// Try to recover really crappy HTML.
		$html = preg_replace( [ '/\\\\"/' ], [ '&quot;' ], $text );
		// Relax HTML strictness, @see https://stackoverflow.com/a/7386650.
		$doc->recover = true;
		$doc->strictErrorChecking = false;

		// Give the log a rest. See https://stackoverflow.com/a/10482622.
		$internalErrors = libxml_use_internal_errors( true ); // -- remember.
		// Try to parse HTML.
		try {
			if ( !$doc->loadHTML( $html ) ) {
				$errors = libxml_get_errors();
				throw new EDParserException(
					'externaldata-parsing-html-failed',
					$this->xmlParseErrors( $errors, $html )
				);
			}
		} catch ( Exception $e ) {
			throw new EDParserException( 'externaldata-caught-exception-parsing-html', $e->getMessage() );
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $internalErrors ); // -- restore.
		}
		$values = EDParserBase::__invoke( $text );
		$domxpath = new DOMXPath( $doc );
		$internalErrors = libxml_use_internal_errors( true ); // -- remember.
		foreach ( $this->external as $xpath ) {
			// Try to select nodes with XPath:
			$nodes_array = [];
			try {
				$entries = $domxpath->evaluate( $xpath );
				$errors = $this->xmlParseErrors( libxml_get_errors(), $html );
				libxml_clear_errors();
				if ( $errors ) {
					throw new EDParserException(
						'externaldata-invalid-format-explicit',
						$xpath,
						'XPath',
						$errors
					);
				}
			} catch ( Exception $e ) {
				throw new EDParserException(
					'externaldata-invalid-format-explicit',
					$xpath,
					'XPath',
					$e->getMessage()
				);
			}
			if ( $entries === false ) {
				$errors = $this->xmlParseErrors( libxml_get_errors(), $html );
				libxml_clear_errors();
				libxml_use_internal_errors( $internalErrors ); // -- restore.
				throw new EDParserException( 'externaldata-invalid-format-explicit', $xpath, 'XPath', $errors );
			}
			if ( $entries instanceof DOMNodeList ) {
				// It's a list of DOM nodes.
				foreach ( $entries as $entry ) {
					$child_nodes = [];
					foreach ( $entry->childNodes as $node ) {
						$child_nodes[] = $entry->ownerDocument->saveHTML( $node );
					}
					$nodes_array[] = implode( '', $child_nodes );
				}
			} else {
				// It's some calculated value.
				$nodes_array = is_array( $entries ) ? $entries : [ $entries ];
			}
			if ( array_key_exists( $xpath, $values ) ) {
				// At the moment, this code will never get
				// called, because duplicate values in
				// $mappings will have been removed already.
				$values[$xpath] = array_merge( $values[$xpath], $nodes_array );
			} else {
				$values[$xpath] = $nodes_array;
			}
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $internalErrors ); // -- restore.

		return $values;
	}
}
