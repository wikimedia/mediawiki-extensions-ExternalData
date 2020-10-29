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
	private $real_directory;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array $args An array of arguments for parser/Lua function.
	 *
	 */
	public function __construct( array $args ) {
		parent::__construct( $args );

		// Parameters specific to {{#get_file_data:}} / mw.ext.externalData.getFileData.
		if ( isset( $args['directory'] ) ) {
			$this->directory = $args['directory'];
			if ( isset( $args['DirectoryPath'] ) ) {
				$this->real_directory = $args['DirectoryPath'];
			} else {
				// No directory defined in $edgDirectoryPath.
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
		$this->real_path = realpath( $this->real_directory . $this->file_name );
		if ( $this->real_path === false || strpos( $this->real_path, $this->real_directory ) !== 0 ) {
			// No file found in directory.
			$this->error( 'externaldata-no-file-in-directory', $this->directory, $this->file_name );
			return false;
		}
		return $this->getDataFromPath( $this->directory . $this->file_name );
	}
}
