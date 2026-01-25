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
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 * @throws MWException
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );

		if ( !array_key_exists( 'data', $params ) ) {
			// At least, serve __xml.
			$this->external['__xml'] = '__xml';
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
			$internalErrors = libxml_use_internal_errors( true ); // -- remember.
			$xml = new SimpleXMLElement( $text, LIBXML_BIGLINES | LIBXML_COMPACT );
			$errors = $this->xmlParseErrors( libxml_get_errors(), $text );
			libxml_clear_errors();
			libxml_use_internal_errors( $internalErrors ); // -- restore.
			if ( $errors ) {
				throw new EDParserException( 'externaldata-invalid-format', self::NAME, $errors );
			}
		} catch ( Exception $e ) {
			throw new EDParserException( 'externaldata-invalid-format', self::NAME, $e->getMessage() );
		} finally {
			self::restoreWarnings();
		}

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
			if ( strpos( $xpath, '__' ) === 0 ) {
				// Special variables are not XPaths.
				continue;
			}
			// Register any necessary XML namespaces, if not yet, to avoid "Undefined namespace prefix" errors.
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
			self::throwWarnings();
			try {
				$nodes = $xml->xpath( $xpath );
			} catch ( Exception $e ) {
				throw new EDParserException(
					'externaldata-invalid-format-explicit',
					$xpath,
					'XPath',
					$e->getMessage()
				);
			} finally {
				self::stopThrowingWarnings();
			}
			if ( $nodes === false || $nodes === null ) {
				// Perhaps, this code is never reached.
				throw new EDParserException( 'externaldata-invalid-format-explicit', $xpath, 'XPath' );
			}
			$nodes = self::filterEmptyNodes( $nodes );

			// Convert from SimpleXMLElement to string or array.
			$nodes_array = [];
			foreach ( $nodes as $node ) {
				$converted = (string)$node;
				if ( !$converted ) {
					// No text content but there are children nodes and attributes, which Lua may need.
					$converted = self::xml2Array( $node );
				}
				$nodes_array[] = $converted;
			}

			if ( array_key_exists( $xpath, $values ) ) {
				// At the moment, this code will never get
				// called, because duplicate values in
				// $this->mappings will have been removed already.
				$values[$xpath] = array_merge( $values[$xpath], $nodes_array );
			} else {
				$values[$xpath] = $nodes_array;
			}
		}
		// Save the whole XML tree for Lua.
		$values['__xml'] = [ self::xml2Array( $xml ) ];

		return $values;
	}

	/**
	 * Convert SimpleXMLElement to a nested array.
	 * @param SimpleXMLElement $xml XML to convert.
	 * @return array
	 */
	private static function xml2Array( SimpleXMLElement $xml ): array {
		return json_decode( json_encode( $xml ), true );
	}

	/**
	 * Filters out empty $nodes.
	 * @param array $nodes Nodes to filter.
	 * @return array Filtered nodes,
	 */
	private static function filterEmptyNodes( array $nodes ): array {
		return array_filter( $nodes, static function ( $node ) {
			return trim( $node[0] ) !== '' || count( $node->attributes() ) > 0;
		} );
	}

	/**
	 * Convert XML error to string to be passed to MediaWiki error message.
	 * @see https://www.php.net/manual/en/function.libxml-get-errors.php.
	 * @param LibXMLError[] $errors
	 * @param string $xml
	 * @return string
	 */
	protected function xmlParseErrors( array $errors, string $xml ): string {
		$lines = explode( "\n", $xml );
		$message = [];
		static $levels = [
			LIBXML_ERR_WARNING => 'Warning',
			LIBXML_ERR_ERROR => 'Error',
			LIBXML_ERR_FATAL => 'Fatal error'
		];
		foreach ( $errors as $error ) {
			if ( $error->level >= $this->errorLevel ) {
				$message[] = $lines[$error->line - 1];
				$message[] = str_repeat( '-', $error->column ) . '^';
				$message[] = $levels[$error->level] . $error->code . ': ' . trim( $error->message );
				$message[] = "\tLine: $error->line";
				$message[] = "\tColumn: $error->column";
				if ( $error->file ) {
					$message[] = "\tFile: $error->file";
				}
			}
		}
		return implode( "\n", $message );
	}
}
