<?php
/**
 * A dummy connector used only to parse text passed to it.
 *
 * @author Alexander Mashin
 *
 */
class EDConnectorInline extends EDConnectorBase {
	use EDConnectorParsable; // needs parser.

	/** @const string ID_PARAM What the specific parameter identifying the connection is called. */
	protected const ID_PARAM = 'text';

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
		$this->text = isset( $args[self::ID_PARAM] ) ? $args[self::ID_PARAM] : null;
	}

	/**
	 * Actually connect to the external data source.
	 * It is presumed that there are no errors in parameters and wiki settings.
	 * Set $this->values and $this->errors.
	 *
	 * @return bool True on success, false if error were encountered.
	 */
	public function run() {
		$values = $this->parse( $this->text );
		$this->error( $this->parseErrors );
		if ( $values ) {
			$this->add( $values );
		}
		return $values !== null;
	}
}
