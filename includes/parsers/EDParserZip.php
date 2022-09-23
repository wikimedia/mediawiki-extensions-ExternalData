<?php
/**
 * Class for parsing ZIP archives.
 *
 * @author Alexander Mashin
 *
 */
class EDParserZip extends EDParserArchive {
	/** @const array EXT An array of possible archive extensions. */
	protected const EXT = [ 'zip' ];

	/** @const array DEPENDENCIES An associative array of 'extension' => 'dependency as class/function'. */
	protected const DEPENDENCIES = [ 'zip' => 'ZipArchive' ];

	/** @const array ERRORS [ ZipArchive::ERRORCODE => MedaiWiki message id ]. */
	private const ERRORS = [
		ZipArchive::ER_INCONS => 'inconsistent archive',
		ZipArchive::ER_MEMORY => 'could not allocate memory',
		ZipArchive::ER_NOENT => 'temporary ZIP file is missing',
		ZipArchive::ER_NOZIP => 'not a ZIP archive',
		ZipArchive::ER_OPEN => 'could not open file'
	];

	/**
	 * Create archive object from temporary file name.
	 *
	 * @param string $temp Temporary file name.
	 * @param string $original Path or URL to the original archive
	 * @return void
	 * @throws EDParserException
	 */
	protected function open( $temp, $original ) {
		$this->archive = new ZipArchive();
		// @phan-suppress-next-line PhanUndeclaredConstantOfClass Class constant available only since PHP 7.4.
		$flags = defined( 'ZipArchive::RDONLY' ) ? ZipArchive::RDONLY : 0;
		$result = $this->archive->open( $temp, $flags );
		if ( $result !== true ) {
			throw new EDParserException(
				'external-data-archive-could-not-read',
				self::EXT[0],
				$original,
				self::ERRORS[$result]
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
		for ( $index = 0; $index < $this->archive->numFiles; $index++ ) {
			$path = $this->archive->statIndex( $index )['name'];
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
		$result = $this->archive->getFromName( $file );
		if ( $result === false ) {
			throw new EDParserException( 'external-data-archive-could-not-extract', self::EXT[0], $file, '' );
		}
		return $result;
	}
}
