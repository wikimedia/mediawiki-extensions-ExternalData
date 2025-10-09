<?php

/**
 * Test for the class EDScribunto.
 *
 * @covers EDScribunto
 *
 * @author Alexander Mashin
 * @author Claire
 */
class EDScribuntoTest extends MediaWikiIntegrationTestCase {
	/** @var string $class Name of the tested class. */
	protected static $class = 'EDScribunto';

	/**
	 * @stable for overriding
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Scribunto' ) ) {
			$this->markTestSkipped( 'Extension:Scribunto is not loaded' );
			return;
		}
	}

	/**
	 * @param string $name Method name.
	 * @param mixed $expected Expected return.
	 * @param mixed ...$args Method arguments.
	 * @return void
	 * @throws ReflectionException
	 */
	private function testPrivateMethod( $name, $expected, ...$args ) {
		$class = new ReflectionClass( static::$class );

		// Invoke the tested method.
		$private = $class->getMethod( $name );
		$actual = $private->invoke( null, ...$args );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Data provider to test EDScribunto::flip().
	 * @return array
	 */
	public static function provideFlip(): array {
		$args = [];
		$expecteds = [];

		$args[] = [
			'id' => [ 0, 1 ],
			'url' => [ 'https://example.com/0', 'https://example.com/1' ],
		];
		$expecteds[] = [
			[ 'id' => 0, 'url' => 'https://example.com/0' ],
			[ 'id' => 1, 'url' => 'https://example.com/1' ],
		];

		$args[] = [
			'id' => [ 42 ],
			'url' => [ 'https://example.com/42' ],
		];
		$expecteds[] = [
			[ 'id' => 42, 'url' => 'https://example.com/42' ],
			'id' => 42,
			'url' => 'https://example.com/42',
		];

		// T375469
		$args[] = [
			0 => [ ':3' ],
			'emoticon' => [ ':3' ],
		];
		$expecteds[] = [
			[ 0 => ':3', 'emoticon' => ':3' ],
			'emoticon' => ':3',
		];

		return array_map( static function ( $arg, $expected ) {
			return [ $arg, $expected ];
		}, $args, $expecteds );
	}

	/**
	 * Test EDScribunto::flip().
	 * @dataProvider provideFlip
	 * @param array $arg
	 * @param array $expected
	 * @throws ReflectionException
	 */
	public function testFlip( $arg, $expected ) {
		$this->testPrivateMethod( 'flip', $expected, $arg );
	}
}
