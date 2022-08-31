<?php
/**
 * Class implementing {{#get_file_data:}} and mw.ext.externalData.getFileData
 * in file mode.
 *
 * @author Alexander Mashin
 * @author Yaron Koren
 *
 */
class EDConnectorFile extends EDConnectorPath {
	/** @const string ID_PARAM What the specific parameter identifying the connection is called. */
	protected const ID_PARAM = 'file';

	/** @var string File name. */
	private $file;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		$this->file = isset( $args[self::ID_PARAM] ) ? $args[self::ID_PARAM] : null;
		if ( isset( $args['path'] ) ) {
			$this->realPath = $args['path'];
		} else {
			// File not defined.
			$this->error( 'externaldata-undefined-file', $this->file );
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
		$values = $this->getDataFromPath( $this->realPath, $this->file );
		if ( $values ) {
			$this->add( $values );
		}
		return $values !== null;
	}
}
