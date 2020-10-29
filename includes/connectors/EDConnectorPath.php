<?php
/**
 * Abstract base class implementing {{#get_file_data:}} and mw.ext.externalData.getFileData.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */
abstract class EDConnectorPath extends EDConnectorBase {
	/** @var bool $needs_parser Needs a EDParser* object. */
	protected static $needs_parser = true;

	/** @var string Real filepath. */
	protected $real_path;

	/**
	 * Get data from absolute filepath. Set $this->values.
	 *
	 * @param string $alias An alias for real file path to show in error messages.
	 *
	 * @return bool True on success, false if error were encountered.
	 *
	 */
	protected function getDataFromPath( $alias ) {
		if ( !file_exists( $this->real_path ) ) {
			$this->error( 'externaldata-missing-file', $alias );
			return false;
		}
		$file_contents = file_get_contents( $this->real_path );
		if ( empty( $file_contents ) ) {
			// Show an error message if there's nothing there.
			$this->error( 'externaldata-empty-file', $alias );
			return false;
		}
		$file_contents = EDEncodingConverter::toUTF8( $file_contents, $this->encoding );
		$this->values = $this->parse( $file_contents, [
			'__time' => [ time() ]
		] );
		return true;
	}
}
