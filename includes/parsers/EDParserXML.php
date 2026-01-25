<?php
/**
 * Abstract class for XML and HTML parsers.
 *
 * @author Alexander Mashin
 */
abstract class EDParserXML extends EDParserBase {
	/** @const string NAME The name of this format. */
	public const NAME = 'XML';
	/** @const array EXT The usual file extensions of this format. */
	protected const EXT = [ 'xml' ];

	/**
	 * @var int $errorLevel LibXML error level causing parsing to fail:
	 *      LIBXML_ERR_WARNING, LIBXML_ERR_ERROR or LIBXML_ERR_FATAL.
	 */
	protected $errorLevel;

	/**
	 * Constructor.
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 * @throws MWException
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );
		$this->errorLevel = $params['xml error level'] ?? LIBXML_ERR_FATAL;
	}

	/**
	 * Add newlines before closing tags and after opening ones to facilitate cutting out fragments, if ordered.
	 *
	 * @param string $xml XML to add newlines to.
	 * @param bool $new_lines Whether to add new lines.
	 *
	 * @return string XML with newlines added.
	 */
	public function addNewlines( $xml, $new_lines ) {
		return $new_lines
			? preg_replace(
				[ '~(?<=>)(?<!^|\s)[ \t]*</~m', '~>[ \t]*(?!$|\s)(?=<)~m' ],
				[ PHP_EOL . '</', '>' . PHP_EOL ],
				$xml
			)
			: $xml;
	}
}
