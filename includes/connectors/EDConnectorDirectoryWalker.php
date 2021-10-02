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
		if ( isset( $args['DirectoryDepth'] ) ) {
			$this->depth = $args['DirectoryDepth'];
		}
		if ( isset( $args['file pattern'] ) ) {
			$this->pattern = $args['file pattern'];
		}
	}

	/**
	 * Merge $values to $this->values.
	 * The columns in $this->values are levelled to maximum height, but only if new $values will be built on them.
	 *
	 * @param array $values Values to merge.
	 */
	private function mergeValues( array $values ) {
		// Find maximum height of columns to be built on.
		$maximum_height = 0;
		foreach ( $values as $variable => $_ ) {
			// Create new columns if necessary.
			if ( !array_key_exists( $variable, $this->values ) ) {
				$this->values[$variable] = [];
			}
			$maximum_height = count( $this->values[$variable] ) > $maximum_height
							? count( $this->values[$variable] )
							: $maximum_height;
		}
		foreach ( $values as $variable => $column ) {
			// Stretch out columns if they are to be built on.
			for ( $counter = count( $this->values[$variable] ); $counter < $maximum_height; $counter++ ) {
				$this->values[$variable][$counter] = null;
			}
			// Superimpose column from $values on column from $this->>values.
			$this->values[$variable] = array_merge( $this->values[$variable], $column );
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
					$this->mergeValues( $values );
				}
			}
		}
		return $this->values !== null;
	}
}
