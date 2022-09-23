<?php
/**
 * Base abstract class for archive parsers.
 *
 * @author Alexander Mashin
 *
 */
abstract class EDParserArchive extends EDParserBase {
	use EDConnectorParsable;

	/** @var string type Archive type. */
	protected $type;

	/** @var string $tmp '/tmp' */
	protected $tmp;

	/** @const int LIMIT Limit of archive size in bytes. */
	protected const LIMIT = 32 * 1024 * 1024; // 32 MB;

	/** @var string $mask Path in the archive, or a mask. */
	private $mask;
	/** @var bool $multiple $mask is a mask, not a fully resolved file name. */
	private $multiple;

	/** @var mixed $archive The archive object. */
	protected $archive;

	/** @var int|mixed @var int $depth Maximum archive iteration depth. */
	protected $depth = 2;

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

		$this->tmp = sys_get_temp_dir();

		if ( isset( $params['archive path'] ) ) {
			$this->mask = $params['archive path'];
		}
		if ( isset( $params['archive depth'] ) ) {
			$this->depth = $params['archive depth'];
		}

		// We need a copy of all parameters and setting, since we don't know which we will need.
		$args_for_decompressed_parser = $params;
		$args_for_decompressed_parser['path'] = $this->mask;
		// Prevent repeated choice of this parser.
		unset(
			$args_for_decompressed_parser['url'],
			$args_for_decompressed_parser['file name'],
			$args_for_decompressed_parser['path']
		);

		$this->multiple = preg_match( '/[[\]?*]/', $this->mask ) === 1; // Parser for the files in the archive.
		$this->prepareParser( $args_for_decompressed_parser ); // any possible exception will fall through.
	}

	/**
	 * Parse the archive. Called as $parser( $text ) as syntactic sugar.
	 *
	 * Reload the method in descendant classes, calling parent::__invoke() in the beginning.
	 *
	 * @param string $text The text to be parsed.
	 * @param string|null $path URL or filesystem path that may be relevant to the parser.
	 * @return array A two-dimensional column-based array of the parsed values.
	 * @throws EDParserException
	 */
	public function __invoke( $text, $path = null ): array {
		parent::__invoke( $text, $path );
		if ( preg_match( '/\.(' . implode( '|', static::extensions() ) . ')$/', $path, $matches ) ) {
			$this->type = $matches[1];
		} else {
			$this->type = static::extensions()[0];
		}

		// Write a temporary file.
		$temp_file_name = tempnam( $this->tmp, 'arch' ) . '.' . $this->type;
		$handle = fopen( $temp_file_name, 'wb' );
		fwrite( $handle, $text );
		fclose( $handle );

		// Create archive object.
		try {
			$this->open( $temp_file_name, $path );
		} catch ( EDParserException $e ) {
			unlink( $temp_file_name );
			throw $e;
		}

		$all_values = [];

		// Get a file list, of one element, if the mask is not a mask.
		$files = $this->multiple ? $this->files( $this->mask ) : [ $this->mask ];
		sort( $files );

		// Read files one by one.
		foreach ( $files as $file ) {
			// Read a file.
			$contents = $this->read( $file );
			// Parse it.
			$values = $this->parse( $contents, $file );
			if ( $values === null ) {
				unlink( $temp_file_name );
				$error = $this->parseErrors[0];
				throw new EDParserException( $error['code'], $error['params'] );
			}
			// Get its columns' maximum height.
			$height = max( array_map( 'count', $values ) );
			// Fill the '__file' column with file name up to the maximum height.
			$values['__archived_file'] = array_fill( 0, $height, $file );
			// Put the values extracted from the file atop all values from the archive.
			self::pile( $all_values, $values );
		}

		// Remove the temporary archive file.
		unlink( $temp_file_name );

		return $all_values;
	}

	/**
	 * Create archive object from temporary file name.
	 *
	 * @param string $temp Temporary file name.
	 * @param string $original Path or URL to the original archive
	 * @return void
	 * @throws EDParserException
	 */
	abstract protected function open( $temp, $original );

	/**
	 * Get file names with given name or fitting given mask from $this->archive.
	 * @param string $mask File name or mask.
	 * @return array File names.
	 */
	abstract protected function files( $mask ): array;

	/**
	 * Read $file from the $this->archive.
	 * @param string $file File name.
	 * @return string|bool The file contents or false on error.
	 */
	abstract protected function read( $file );

	/** This function matches $path against the $this->mask,
	 * taking into account $this->depth and whether $path is a directory.
	 *
	 * @param string $path Path to match.
	 * @return bool
	 */
	protected function matches( string $path ) {
		return substr( $path, -1 ) !== DIRECTORY_SEPARATOR // not a directory.
			&& substr_count( $path, DIRECTORY_SEPARATOR ) <= $this->depth // not too deep.
			&& fnmatch( $this->mask, $path ); // path matches the mask.
	}
}
