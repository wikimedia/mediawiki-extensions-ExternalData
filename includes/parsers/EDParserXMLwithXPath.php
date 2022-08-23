<?php
/**
 * Class for XML parser extracting data using XPath notation.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */

class EDParserXMLwithXPath extends EDParserXML {
	/** @var bool $keepExternalVarsCase Whether external variables' names are case-sensitive for this format. */
	public $keepExternalVarsCase = true;

	/** @var string $defaultXmlnsPrefix Default prefix for xmlns. */
	private $defaultXmlnsPrefix;

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 * @throws MWException
	 *
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );

		// This connector needs an explicit set of fields.
		if ( !array_key_exists( 'data', $params ) ) {
			throw new EDParserException( 'externaldata-no-param-specified', 'data' );
		}

		if ( array_key_exists( 'default xmlns prefix', $params ) ) {
			$this->defaultXmlnsPrefix = $params['default xmlns prefix'];
		}
	}

	/**
	 * Parse the text as XML. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 * @param string|null $path URL or filesystem path that may be relevant to the parser.
	 * @return array A two-dimensional column-based array of the parsed values.
	 * @throws EDParserException
	 */
	public function __invoke( $text, $path = null ): array {
		self::suppressWarnings();
		try {
			$xml = new SimpleXMLElement( $text );
		} catch ( Exception $e ) {
			throw new EDParserException( 'externaldata-invalid-format', self::NAME, $e->getMessage() );
		}
		self::restoreWarnings();

		$values = parent::__invoke( $text );

		// Set default prefix for unprefixed xmlns's.
		$namespaces = $xml->getDocNamespaces( true );
		foreach ( $namespaces as $prefix => $namespace ) {
			if ( !$prefix && $this->defaultXmlnsPrefix ) {
				$namespaces[$this->defaultXmlnsPrefix] = $namespace;
				$xml->registerXPathNamespace( $this->defaultXmlnsPrefix, $namespace );
			}
		}

		foreach ( $this->external as $xpath ) {
			if ( substr( $xpath, 0, 2 ) === '__' ) {
				// Special variables are not XPaths.
				continue;
			}
			// Register any necessary XML namespaces, if not yet, to
			// avoid "Undefined namespace prefix" errors.
			// It's just a dirty hack.
			if ( preg_match_all( '/[\/@]([a-zA-Z0-9]*):/', $xpath, $matches ) ) {
				foreach ( $matches[1] as $prefix ) {
					if ( !array_key_exists( $prefix, $namespaces ) ) {
						$namespaces[$prefix] = $prefix;
						$xml->registerXPathNamespace( $prefix, $prefix );
					}
				}
			}

			// Now, get all the matching values, and remove any empty results.
			$nodes = $xml->xpath( $xpath );
			if ( $nodes === false ) {
				throw new EDParserException( 'externaldata-invalid-format-explicit', $xpath, 'XPath' );
			}
			$nodes = self::filterEmptyNodes( $nodes );
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
		// Save the whole XML tree for Lua.
		$values['__xml'] = [ self::xml2Array( $xml ) ];
		return $values;
	}

	/**
	 * Convert SimpleXMLElement to a nested array.
	 *
	 * @param SimpleXMLElement $xml XML to convert.
	 *
	 * @return array
	 */
	private static function xml2Array( SimpleXMLElement $xml ): array {
		$converted = [];
		foreach ( (array)$xml as $index => $node ) {
			$converted[$index] = is_a( $node, 'SimpleXMLElement' ) ? self::xml2Array( $node ) : $node;
		}
		return $converted;
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
