<?php
/**
 * Class implementing {{#get_file_data:}} and mw.ext.externalData.getFileData
 * in directory / file name mode when file name is *.
 *
 * @author Alexander Mashin
 *
 */
class EDConnectorDirectoryWalker extends EDConnectorDirectory {
	/** @var int $depth Maximum iteration depth. */
	private $depth = 1;
	/** @var string $pattern File name mask. */
	private $pattern;

	/**
	 * Constructor. Analyse parameters and wiki settings; set $this->errors.
	 *
	 * @param array &$args Arguments to parser or Lua function; processed by this constructor.
	 * @param Title $title A Title object.
	 */
	protected function __construct( array &$args, Title $title ) {
		parent::__construct( $args, $title );

		// Parameters specific to {{#get_file_data:}} / mw.ext.externalData.getFileData.
		if ( isset( $args['depth'] ) ) {
			$this->depth = $args['depth'];
		}
		if ( isset( $args['file name'] ) ) {
			$this->pattern = $args['file name'];
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
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$this->real_directory /* FilesystemIterator::SKIP_DOTS is always there. */
			),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		$iterator->setMaxDepth( $this->depth );
		$quoted = preg_quote( $this->real_directory, '%' );
		foreach ( $iterator as $path => $file_info ) {
			$local_path = preg_replace( "%^$quoted/%", '', $path );
			if ( !$file_info->isDir() && fnmatch( $this->pattern, $local_path ) ) {
				$values = $this->getDataFromPath( $path, $local_path );
				if ( $values ) {
					$this->add( $values );
				}
			}
		}
		return $this->values !== null;
	}
}
