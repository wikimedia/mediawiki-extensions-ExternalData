<?php
require_once __DIR__ . '/../EDTestBase.php';
/**
 * Base class for the tests of EDParser*.
 *
 * To run,
 * tests/phpunit/phpunit.php --wiki='(project)' \\
 * extensions/ExternalData/tests/phpunit/unit/parsers/EDParser...Test.php
 * in the MediaWiki directory.
 *
 * @group Standalone
 * @covers EDParserBase
 *
 * @author Alexander Mashin
 */
abstract class EDParserBaseTest extends EDTestBase {
	/** @var string $class Name of the tested class. */
	protected static $class = 'EDParserBase';

	/**
	 * Test EDParserBase::__invoke().
	 *
	 * @covers EDParserBase::__invoke
	 *
	 * @param string $text File to parse as a string.
	 * @param array $args Relevant Parameters to a parser function.
	 * @param string $path Path to the parsed text.
	 * @param array $expected Necessary returned values.
	 */
	protected function testInvoke( $text, array $args, $path, array $expected ) {
		$class = static::$class;
		self::restoreGlobals();
		$parser = new $class( $args );
		$parsed = $parser( $text, $path );
		foreach ( $expected as $column => $values ) {
				$this->assertArrayHasKey(
				$column,
				$parsed,
				"'" . $column . "' not returned by parser. Present columns are = "
					. implode( ', ', array_keys( $parsed ) )
			);
			$this->assertArrayEquals( $values, $parsed[$column], "'" . $column . "' values are incorrect" );
		}
	}

	/**
	 * Test EDParserBase::__invoke() for parser exceptions.
	 *
	 * @covers EDParserBase::__invoke
	 *
	 * @param string $text File to parse as a string.
	 * @param array $args Relevant Parameters to a parser function.
	 * @param string $path Path to the parsed text.
	 * @param string $code Exception parameter: message code.
	 * @param array $params Exception parameter: message parameters.
	 */
	protected function testInvokeExceptions( $text, array $args, $path, $code, array $params ) {
		$class = static::$class;
		self::restoreGlobals();
		$parser = new $class( $args );
		try {
			$_ = $parser( $text, $path );
			$this->fail( 'Expected EDParserException was not thrown' );
		} catch ( EDParserException $e ) {
			$this->assertEquals( $code, $e->code(), 'Wrong message code in EDParserException' );
			$real_params = $e->params();
			// Unfortunately, Assert::assertArraySubset() is deprecated.
			foreach ( $params as $index => $param ) {
				$this->assertArrayHasKey(
					$index,
					$real_params,
					'No message parameter ' . (string)$index . ' in EDParserException'
				);
				$this->assertEquals( $param, $real_params[$index], 'Wrong message parameter in EDParserException' );
			}
		}
	}
}
