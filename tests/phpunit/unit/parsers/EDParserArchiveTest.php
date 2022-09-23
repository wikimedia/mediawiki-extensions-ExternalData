<?php
require_once 'EDParserBaseTest.php';
/**
 * Class for the tests of EDParserArchive.
 *
 * To run,
 * tests/phpunit/phpunit.php --wiki='(project)' \\
 * extensions/ExternalData/tests/phpunit/unit/parsers/EDParserArchiveTest.php
 * in the MediaWiki directory.
 *
 * @group Standalone
 * @covers EDParserArchive
 *
 * @author Alexander Mashin
 */
abstract class EDParserArchiveTest extends EDParserBaseTest {
	/** @var string $class Name of the tested class. */
	protected static $class = 'EDParserArchive';
	/** @const array DEPENDENCIES An associative array of 'extension' => 'dependency as class/function'. */
	protected const DEPENDENCIES = [];
	/** @var array $activeExtensions Actually parsable, under current configuration, file extensions and examples. */
	protected $activeExtensions = [];

	/**
	 * Constructor.
	 * @param string|null $name
	 * @param array $data
	 * @param string $dataName
	 */
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$extensions = call_user_func( [ static::$class, 'extensions' ] );
		foreach ( $extensions as $extension ) {
			if ( isset( static::DEPENDENCIES[$extension] ) ) {
				$dependency = static::DEPENDENCIES[$extension];
				if ( !( class_exists( $dependency ) || function_exists( $dependency ) ) ) {
					// The required extension is not installed and the test must be skipped.
					continue;
				}
			}
			$path = __DIR__ . '/../connectors/data/archive.' . $extension;
			$this->activeExtensions[$extension] = file_get_contents( $path );
		}
	}

	/**
	 * Data provider for EDParser*::__invoke().
	 *
	 * @return array Test cases.
	 */
	protected function provideInvoke(): array {
		$cases = [];
		foreach ( $this->activeExtensions as $extension => $archive ) {
			$path = "archive.$extension";
			$cases += [
				$extension . ': one file' => [
					$archive,
					[ 'archive path' => '1.csv' ],
					'path' => $path,
					[
						'col1' => [ '11', '21', '31' ],
						'col2' => [ '12', '22', '32' ],
						'__archived_file' => [ '1.csv', '1.csv', '1.csv' ]
					]
				],
				$extension . ': one file in a folder' => [
					$archive,
					[ 'archive path' => 'folder/3.csv' ],
					'path' => $path,
					[
						'col1' => [ '71', '81', '91' ],
						'col2' => [ '72', '82', '92' ],
						'__archived_file' => [ 'folder/3.csv', 'folder/3.csv', 'folder/3.csv' ]
					]
				],
				$extension . ': mask *.csv, four files' => [
					$archive,
					[ 'archive path' => '*.csv' ],
					'path' => $path,
					[
						'col1' => [ '11', '21', '31', '41', '51', '61', '71', '81', '91', '101', '111', '121' ],
						'col2' => [ '12', '22', '32', '42', '52', '62', '72', '82', '92', '102', '112', '122' ],
						'__archived_file' => [
							'1.csv', '1.csv', '1.csv',
							'2.csv', '2.csv', '2.csv',
							'folder/3.csv', 'folder/3.csv', 'folder/3.csv',
							'folder/subfolder/4.csv', 'folder/subfolder/4.csv', 'folder/subfolder/4.csv'
						]
					]
				],
				$extension . ': mask *, four files' => [
					$archive,
					[ 'archive path' => '*' ],
					'path' => $path,
					[
						'col1' => [ '11', '21', '31', '41', '51', '61', '71', '81', '91', '101', '111', '121' ],
						'col2' => [ '12', '22', '32', '42', '52', '62', '72', '82', '92', '102', '112', '122' ],
						'__archived_file' => [
							'1.csv', '1.csv', '1.csv',
							'2.csv', '2.csv', '2.csv',
							'folder/3.csv', 'folder/3.csv', 'folder/3.csv',
							'folder/subfolder/4.csv', 'folder/subfolder/4.csv', 'folder/subfolder/4.csv'
						]
					]
				],
				$extension . ': mask *, limited depth, three files' => [
					$archive,
					[ 'archive path' => '*', 'archive depth' => 1 ],
					'path' => $path,
					[
						'col1' => [ '11', '21', '31', '41', '51', '61', '71', '81', '91' ],
						'col2' => [ '12', '22', '32', '42', '52', '62', '72', '82', '92' ],
						'__archived_file' => [
							'1.csv', '1.csv', '1.csv',
							'2.csv', '2.csv', '2.csv',
							'folder/3.csv', 'folder/3.csv', 'folder/3.csv'
						]
					]
				]
			];
		}
		return $cases;
	}

	/**
	 * Data provider for EDParser*::__invoke() (exceptions).
	 *
	 * @return array Test cases.
	 */
	protected function provideInvokeExceptions(): array {
		$cases = [];
		foreach ( $this->activeExtensions as $extension => $archive ) {
			$path = "archive.$extension";
			$cases += [
				$extension . ' corrupt or wrong format: one file' => [
					'This is an example of a corrupt file',
					[ 'archive path' => '1.csv' ],
					'path' => $path,
					'external-data-archive-could-not-read',
					[ $extension, $path ]
				],
				$extension . ' wanted file is not found in the archive' => [
					$archive,
					[ 'archive path' => 'absent.csv' ],
					'path' => $path,
					'external-data-archive-could-not-extract',
					[ $extension, 'absent.csv' ]
				],
			];
		}
		return $cases;
	}
}
