<?php
/**
 * A dummy connector used only to parse text passed to it.
 *
 * @author Alexander Mashin
 *
 */
class EDConnectorInline extends EDConnectorBase {
	use EDConnectorParsable; // needs parser.

	/** @var string $text Text to parse. */
	private $text;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		// Parser.
		$this->prepareParser( $args );
		$this->error( $this->parseErrors );

		parent::__construct( $args, $title );

		// Text to parse.
		if ( array_key_exists( 'text', $args ) ) {
			$this->text = $args['text'];
		} else {
			$this->error( 'externaldata-no-param-specified', 'text' );
		}
	}

	/**
	 * Actually connect to the external data source.
	 * It is presumed that there are no errors in parameters and wiki settings.
	 * Set $this->values and $this->errors.
	 *
	 * @return bool True on success, false if error were encountered.
	 */
	public function run() {
		$values = $this->parse( $this->text, $this->encoding );
		$this->error( $this->parseErrors );
		if ( $values ) {
			$this->add( $values );
		}
		return $values !== null;
	}
}
