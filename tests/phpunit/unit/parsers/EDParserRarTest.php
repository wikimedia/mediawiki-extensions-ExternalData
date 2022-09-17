<?php
require_once 'EDParserArchiveTest.php';
/**
 * Test for the class EDParserRar.
 *
 * To run,
 * tests/phpunit/phpunit.php --wiki='(project)' \\
 * extensions/ExternalData/tests/phpunit/unit/parsers/EDParserRarTest.php
 * in the MediaWiki directory.
 *
 * @group Standalone
 * @covers EDParserRar
 *
 * @author Alexander Mashin
 */
class EDParserRarTest extends EDParserArchiveTest {
	/** @var string $class Name of the tested class. */
	protected static $class = 'EDParserRar';

	/** @const array DEPENDENCIES An associative array of 'extension' => 'dependency as class/function'. */
	protected const DEPENDENCIES = [ 'rar' => 'RarArchive' ];

	/**
	 * Data provider for EDParserRar::__invoke().
	 *
	 * @return array Test cases.
	 */
	public function provideInvoke(): array {
		return parent::provideInvoke();
	}

	/**
	 * Test EDParserRar::__invoke().
	 *
	 * @covers EDParserRar::__invoke
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
	 * Data provider for EDParserRar::__invoke() (exceptions).
	 *
	 * @return array Test cases.
	 */
	public function provideInvokeExceptions(): array {
		return parent::provideInvokeExceptions();
	}

	/**
	 * Test EDParserRar::__invoke() for parser exceptions.
	 *
	 * @covers EDParserRar::__invoke
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
