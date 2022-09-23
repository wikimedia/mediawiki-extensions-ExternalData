<?php
/**
 * Class for parsing RAR archives.
 *
 * @author Alexander Mashin
 *
 */
class EDParserRar extends EDParserArchive {
	/** @const array EXT An array of possible archive extensions. */
	protected const EXT = [ 'rar' ];

	/**
	 * Constructor.
	 *
	 * @param array $params An associative array of parameters.
	 * @param array $headers An optional array of HTTP headers.
	 *
	 * @throws EDParserException
	 */
	public function __construct( array $params, array $headers = [] ) {
		parent::__construct( $params, $headers );
		// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
		RarException::setUsingExceptions( true );
	}

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
			// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
			$result = RarArchive::open( $temp );
		// @phan-suppress-next-line PhanUndeclaredClassMethod,PhanUndeclaredClassCatch Optional extension.
		} catch ( RarException $e ) {
			throw new EDParserException(
				'external-data-archive-could-not-read',
				self::EXT[0],
				$original,
				// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
				$e->getMessage()
			);
		}
		if ( $result !== false ) {
			$this->archive = $result;
		} else {
			throw new EDParserException( 'external-data-archive-could-not-read', self::EXT[0], $original, '' );
		}
	}

	/**
	 * Get file names with given name or fitting given mask from $this->archive.
	 * @param string $mask File name or mask.
	 * @return array File names.
	 */
	protected function files( $mask ): array {
		$files = [];
		foreach ( $this->archive as $entry ) {
			if ( $entry->isDirectory() ) {
				continue; // we do not need directories; and EDParserArchive::matches() cannot recognise one for RAR.
			}
			$path = $entry->getName();
			if ( $this->matches( $path ) ) {
				$files[] = $path;
			}
		}
		return $files;
	}

	/**
	 * Read $file from the $this->archive.
	 * @param string $file File name.
	 * @return string The file contents or false on error.
	 * @throws EDParserException
	 */
	protected function read( $file ) {
		try {
			$entry = $this->archive->getEntry( $file );
		// @phan-suppress-next-line PhanUndeclaredClassMethod,PhanUndeclaredClassCatch Optional extension.
		} catch ( RarException $e ) {
			throw new EDParserException(
				'external-data-archive-could-not-extract',
				self::EXT[0],
				$file,
				// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension.
				$e->getMessage()
			);
		}
		if ( !$entry->isDirectory() ) {
			$temp = "$this->tmp/$file";
			$result = $entry->extract( null, $temp );
			if ( $result === true ) {
				$contents = file_get_contents( $temp );
				unlink( $temp );
				return $contents;
			} else {
				throw new EDParserException( 'external-data-archive-could-not-extract', self::EXT[0], $file, '' );
			}
		}
	}
}
