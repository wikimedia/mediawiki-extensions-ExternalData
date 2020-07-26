<?php
/**
 * Class for XML parser extracting data using XPath notation.
 *
 * @var bool $preserve_external_variables_case Whether external variables' names are case-sensitive for this format.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */

class EDParserXMLwithXPath extends EDParserBase {

	// Whether external variables' names are case-sensitive for this format.
	protected static $preserve_external_variables_case = true;

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
	}

	/**
	 * Parse the text as XML. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 * @param ?array $defaults The intial values.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 */
	public function __invoke( $text, $defaults = [] ) {
		try {
			$xml = new SimpleXMLElement( $text );
		} catch ( Exception $e ) {
			return wfMessage( 'externaldata-invalid-xml', $e->getMessage() )->text();
		}
		$values = parent::__invoke( $text, $defaults );

		foreach ( $this->mappings as $local_var => $xpath ) {
			// First, register any necessary XML namespaces, to
			// avoid "Undefined namespace prefix" errors.
			$matches = [];
			preg_match_all( '/[\/\@]([a-zA-Z0-9]*):/', $xpath, $matches );
			foreach ( $matches[1] as $namespace ) {
				$xml->registerXPathNamespace( $namespace, $ns );
			}

			// Now, get all the matching values, and remove any empty results.
			$nodes = self::filterEmptyNodes( $xml->xpath( $xpath ) );
			if ( !$nodes ) {
				continue;
			}

			// Convert from SimpleXMLElement to string.
			$nodesArray = [];
			foreach ( $nodes as $xmlNode ) {
				$nodesArray[] = (string)$xmlNode;
			}

			if ( array_key_exists( $xpath, $values ) ) {
				// At the moment, this code will never get
				// called, because duplicate values in
				// $this->mappings will have been removed already.
				$values[$xpath] = array_merge( $values[$xpath], $nodesArray );
			} else {
				$values[$xpath] = $nodesArray;
			}
		}
		return $this->mapAndFilter( $values );
	}

	/**
	 * Filters out empty $nodes.
	 *
	 * @param mixed $nodes Nodes to filter. TODO: always array?
	 *
	 * @return mixed Filtered nodes, TODO: always array?
	 *
	 */
	protected static function filterEmptyNodes( $nodes ) {
		if ( !is_array( $nodes ) ) {
			return $nodes;
		}
		return array_filter( $nodes, function ( $node ) {
			return trim( $node[0] ) !== '';
		} );
	}
}
