<?php
/**
 * A connector, which is created when there are no candidate connectors for {{#get_external_data:}}..
 *
 * @author Alexander Mashin
 */
class EDConnectorDummy extends EDConnectorBase {
	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title ); // we may need the 'verbose' setting.
		$this->error( 'external-data-no-suitable-connector' );
	}

	/**
	 * Never called.
	 * @inheritDoc
	 */
	public function run() {
		// Do nothing.
	}
}
