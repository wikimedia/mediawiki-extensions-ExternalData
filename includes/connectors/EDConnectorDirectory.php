<?php
/**
 * Class implementing {{#get_file_data:}} and mw.ext.externalData.getFileData
 * in directory / file name mode.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */
class EDConnectorDirectory extends EDConnectorPath {
	/** @var string $directrory Directory. */
	private $directory;
	/** @var string File in directory. */
	private $file_name;
	/** @var string Real path to $directory. */
	protected $real_directory;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		// Parameters specific to {{#get_file_data:}} / mw.ext.externalData.getFileData.
		if ( isset( $args['directory'] ) ) {
			$this->directory = $args['directory'];
			if ( isset( $args['path'] ) ) {
				if ( is_dir( $args['path'] ) ) {
					$this->real_directory = $args['path'];
				} else {
					// Not a directory.
					$this->error( 'externaldata-not-a-directory', $this->directory );
				}
			} else {
				// No directory defined in 'path'.
				$this->error( 'externaldata-no-directory', $this->directory );
			}
		} else {
			// No directory given.
			$this->error( 'externaldata-no-param-specified', 'directory' );
		}
		if ( isset( $args['file name'] ) ) {
			$this->file_name = $args['file name'];
		} else {
			// No file name given.
			$this->error( 'externaldata-no-param-specified', 'file name' );
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
		$this->realPath = realpath( $this->real_directory . $this->file_name );
		if (
			$this->realPath === false ||
			strpos( $this->realPath, $this->real_directory ) !== 0 // no .
		) {
			// No file found in directory.
			$this->error( 'externaldata-no-file-in-directory', $this->directory, $this->file_name );
			return false;
		}
		$values = $this->getDataFromPath( $this->realPath, $this->directory . ':' . $this->file_name );
		$this->add( $values );
		return $values !== null;
	}
}
