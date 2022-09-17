<?php
/**
 * Class for parsing archives handled by PHAR: tar, tar.gz, tar.bz2.
 *
 * @author Alexander Mashin
 *
 */
class EDParserPhar extends EDParserArchive {
	/** @const array EXTENSIONS An array of possible archive extensions. */
	protected const EXT = [ 'tar', 'tar.bz2', 'tar.gz' ];

	/**
	 * Create archive object from temporary file name.
	 *
	 * @param string $temp Temporary file name.
	 * @param string $original Path or URL to the original archive
	 * @return void
	 * @throws EDParserException
	 */
	protected function open( $temp, $original ) {
		try {
			$this->archive = new PharData( $temp, Phar::KEY_AS_PATHNAME );
		} catch ( UnexpectedValueException $e ) {
			throw new EDParserException(
				'external-data-archive-could-not-read',
				$this->type,
				$original,
				$e->getMessage()
			);
		}
	}

	/**
	 * Get file names with given name or fitting given mask from $this->archive.
	 * @param string $mask File name or mask.
	 * @return array File names.
	 */
	protected function files( $mask ): array {
		$files = [];
		$iterator = new RecursiveIteratorIterator( $this->archive, RecursiveIteratorIterator::CHILD_FIRST );
		$iterator->setMaxDepth( $this->depth );
		$archive = $this->archive->getPath();
		foreach ( $iterator as $path => $file_info ) {
			$inner = strtr( $path, [ "phar://$archive/" => '' ] );
			if ( !$file_info->isDir() && fnmatch( $mask, $inner ) ) {
				$files[] = $inner;
			}
		}
		return $files;
	}

	/**
	 * Read $file from the $this->archive.
	 * @param string $file File name.
	 * @return string|bool The file contents or false on error.
	 * @throws EDParserException
	 */
	protected function read( $file ) {
		try {
			$this->archive->extractTo( $this->tmp, $file, true );
		} catch ( PharException $e ) {
			throw new EDParserException(
				'external-data-archive-could-not-extract',
				$this->type,
				$file,
				$e->getMessage()
			);
		}
		$contents = file_get_contents( "$this->tmp/$file" );
		unlink( "$this->tmp/$file" );
		return $contents;
	}
}
