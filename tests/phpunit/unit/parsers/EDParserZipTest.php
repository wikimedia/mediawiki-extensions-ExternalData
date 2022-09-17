<?php
require_once 'EDParserArchiveTest.php';
/**
 * Test for the class EDParserZip.
 *
 * To run,
 * tests/phpunit/phpunit.php --wiki='(project)' \\
 * extensions/ExternalData/tests/phpunit/unit/parsers/EDParserZipTest.php
 * in the MediaWiki directory.
 *
 * @group Standalone
 * @covers EDParserZip
 *
 * @author Alexander Mashin
 */
class EDParserZipTest extends EDParserArchiveTest {
	/** @var string $class Name of the tested class. */
	protected static $class = 'EDParserZip';

	/** @const array DEPENDENCIES An associative array of 'extension' => 'dependency as class/function'. */
	protected const DEPENDENCIES = [ 'zip' => 'ZipArchive' ];

	/**
	 * Data provider for EDParserZip::__invoke().
	 *
	 * @return array Test cases.
	 */
	public function provideInvoke(): array {
		return parent::provideInvoke();
	}

	/**
	 * Test EDParserZip::__invoke().
	 *
	 * @covers EDParserZip::__invoke
	 * @dataProvider provideInvoke
	 *
	 * @param string $archive Archive file as a string variable.
	 * @param array $args Relevant Parameters to a parser function.
	 * @param string $path Path to the parsed archive.
	 * @param array $expected Necessary returned values.
	 */
	public function testInvoke( $archive, array $args, $path, array $expected ) {
		parent::testInvoke( $archive, $args, $path, $expected );
	}

	/**
	 * Data provider for EDParserZip::__invoke() (exceptions).
	 *
	 * @return array Test cases.
	 */
	public function provideInvokeExceptions(): array {
		return parent::provideInvokeExceptions();
	}

	/**
	 * Test EDParserZip::__invoke() for parser exceptions.
	 *
	 * @covers EDParserZip::__invoke
	 * @dataProvider provideInvokeExceptions
	 *
	 * @param string $archive File to parse as a string.
	 * @param array $args Relevant Parameters to a parser function.
	 * @param string $path Path to the parsed text.
	 * @param string $code Exception parameter: message code.
	 * @param array $params Exception parameter: message parameters.
	 */
	public function testInvokeExceptions( $archive, array $args, $path, $code, array $params ) {
		parent::testInvokeExceptions( $archive, $args, $path, $code, $params );
	}
}
