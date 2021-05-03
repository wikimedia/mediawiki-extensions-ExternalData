<?php
/**
 * Class for XML parser extracting data using XPath notation.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */

class EDParserXMLwithXPath extends EDParserBase {
	/** @var bool $preserve_external_variables_case Whether external variables' names are case-sensitive for this format. */
	protected static $preserve_external_variables_case = true;

	/** @var string $default_xmlns_prefix Default prefix for xmlns. */
	private $default_xmlns_prefix;

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
		if ( array_key_exists( 'default xmlns prefix', $params ) ) {
			$this->default_xmlns_prefix = $params['default xmlns prefix'];
		}
	}

	/**
	 * Parse the text as XML. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 * @param ?array $defaults The initial values.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 * @throws EDParserException
	 *
	 */
	public function __invoke( $text, $defaults = [] ) {
		try {
			$xml = new SimpleXMLElement( $text );
		} catch ( Exception $e ) {
			throw new EDParserException( 'externaldata-invalid-xml', $e->getMessage() );
		}

		$values = parent::__invoke( $text, $defaults );

		// Set default prefix for unprefixed xmlns's.
		$namespaces = $xml->getDocNamespaces( true );
		foreach ( $namespaces as $prefix => $namespace ) {
			if ( !$prefix && $this->default_xmlns_prefix ) {
				$namespaces[$this->default_xmlns_prefix] = $namespace;
				$xml->registerXPathNamespace( $this->default_xmlns_prefix, $namespace );
			}
		}

		foreach ( $this->external as $xpath ) {
			// Register any necessary XML namespaces, if not yet, to
			// avoid "Undefined namespace prefix" errors.
			// It's just a dirty hack.
			if ( preg_match_all( '/[\/\@]([a-zA-Z0-9]*):/', $xpath, $matches ) ) {
				foreach ( $matches[1] as $prefix ) {
					if ( !array_key_exists( $prefix, $namespaces ) ) {
						$namespaces[$prefix] = $prefix;
						$xml->registerXPathNamespace( $prefix, $prefix );
					}
				}
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
		return $values;
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
		return array_filter( $nodes, static function ( $node ) {
			return trim( $node[0] ) !== '';
		} );
	}
}
