<?php
require_once 'EDTestBase.php';
/**
 * Test for the class EDParserFunctions.
 *
 * To run,
 * php7.4 tests/phpunit/phpunit.php --wiki='(project)' \\
 * extensions/ExternalData/tests/phpunit/unit/EDParserFunctionsTest.php
 * in the MediaWiki directory.
 *
 * @group Standalone
 * @group Database
 * @covers EDParserFunctions
 *
 * @author Alexander Mashin
 */
class EDParserFunctionsTest extends EDTestBase {
	/** @var string $class Name of the tested class. */
	protected static $class = 'EDParserFunctions';

	/** @const array VALUES */
	private const VALUES = [
		'url' => [
			'https://www.mediawiki.org/wiki/Extension:External_Data',
			'https://www.mediawiki.org/wiki/Extension:Cargo'
		],
		'title' => [
			'External Data', 'Cargo'
		],
		'html' => [
			'text', '<b class="strong">strong</b>'
		],
		'one' => [ 'One value' ],
		'empty' => []
	];

	/**
	 * @param string $name Method name.
	 * @param array[] $values Saved values.
	 * @param mixed $expected Expected return.
	 * @param mixed ...$args Method arguments.
	 * @return void
	 * @throws ReflectionException
	 */
	private function testPrivateMethod( $name, array $values, $expected, ...$args ) {
		$class = new ReflectionClass( static::$class );

		// Clear existent data.
		$setter = $class->getMethod( 'actuallyClearExternalData' );
		$setter->setAccessible( true );
		$setter->invoke( null, [] );

		// Save new data.
		$setter = $class->getMethod( 'saveValues' );
		$setter->setAccessible( true );
		$setter->invoke( null, $values );

		// Invoke the tested method.
		$private = $class->getMethod( $name );
		$private->setAccessible( true );
		$actual = $private->invoke( null, ...$args );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Data provider to test EDParserFunctions::urlencode().
	 * @return array
	 */
	public function urlencodeProvider(): array {
		$strings = [
			'https://traditio.wiki/Alexander_Mashin',
			'https://traditio.wiki/Александр_Машин'
		];
		return array_map( static function ( $str ) {
			return [ $str, urlencode( $str ) ];
		}, $strings );
	}

	/**
	 * Test EDParserFunctions::urlencode().
	 * @dataProvider urlencodeProvider
	 * @param string $arg
	 * @param string $expected
	 * @throws ReflectionException
	 */
	public function testUrlencode( $arg, $expected ) {
		$this->testPrivateMethod( 'urlencode', [], $expected, $arg );
	}

	/**
	 * Data provider to test EDParserFunctions::htmlencode().
	 * @return array
	 */
	public function htmlencodeProvider(): array {
		$strings = [
			'html',
			'<html>',
			'<html lang="ru">'
		];
		return array_map( static function ( $str ) {
			return [ $str, htmlentities( $str, ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, null, false ) ];
		}, $strings );
	}

	/**
	 * Test EDParserFunctions::htmlencode().
	 * @dataProvider htmlencodeProvider
	 * @param string $arg
	 * @param string $expected
	 * @throws ReflectionException
	 */
	public function testHtmlencode( $arg, $expected ) {
		$this->testPrivateMethod( 'htmlencode', [], $expected, $arg );
	}

	/**
	 * Data provider for EDParserFunctions::saveValues().
	 * @return array
	 */
	public function saveValuesProvider(): array {
		return [
			'Initial' => [ [], [ 'key1' => [ 'Value11', 'Value12' ] ], [ 'key1' => [ 'Value11', 'Value12' ] ] ],
			'Additional' => [
				[ 'key1' => [ 'Value11', 'Value12' ] ],
				[ 'key2' => [ 'Value21', 'Value22' ] ],
				[ 'key1' => [ 'Value11', 'Value12' ], 'key2' => [ 'Value21', 'Value22' ] ]
			],
			'Override' => [
				[ 'key1' => [ 'Value11', 'Value12' ], 'key2' => [ 'Value21', 'Value22' ] ],
				[ 'key1' => [ 'Value01', 'Value02', 'Value03' ] ],
				[ 'key1' => [ 'Value01', 'Value02', 'Value03' ], 'key2' => [ 'Value21', 'Value22' ] ]
			]
		];
	}

	/**
	 * Test EDParserFunctions::saveValues().
	 * @dataProvider saveValuesProvider
	 * @param array $initial
	 * @param array $added
	 * @param array $total
	 * @throws ReflectionException
	 */
	public function testSaveValues( array $initial, array $added, array $total ) {
		$this->testPrivateMethod( 'saveValues', $initial, $total, $added );
	}

	/**
	 * Data provider for EDParserFunctions::getIndexedValue().
	 * @return array
	 */
	public function getIndexedValueProvider(): array {
		$cases = [];
		// Existent.
		foreach ( self::VALUES as $var => $column ) {
			foreach ( $column as $row => $value ) {
				$cases["$var: $row"] = [ $var, $row, null, $value ];
				if ( $var === 'url' ) {
					$cases["$var.urlencode: $row"] = [ "$var.urlencode", $row, null, urlencode( $value ) ];
				} elseif ( $var === 'html' ) {
					$cases["$var.htmlencode: $row"] = [
						"$var.htmlencode",
						$row,
						null,
						htmlentities( $value, ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, null, false )
					];
				}
			}
		}
		// Non-existent.
		$cases['Non-existent with default'] = [ 'absent', 0, 'Default', 'Default' ];
		$cases['Non-existent without default'] = [ 'absent', 0, null, null ];
		return $cases;
	}

	/**
	 * Test EDParserFunctions::getIndexedValue().
	 * @dataProvider getIndexedValueProvider
	 * @param string $var
	 * @param int $i
	 * @param string $default
	 * @param string $expected
	 * @return void
	 * @throws ReflectionException
	 */
	public function testGetIndexedValue( $var, $i, $default, $expected ) {
		$this->testPrivateMethod( 'getIndexedValue', self::VALUES, $expected, $var, $i, $default );
	}

	/**
	 * Data provider for EDParserFunctions::numLoops().
	 * @return array
	 */
	public function numLoopsProvider(): array {
		return [
			'no vars' => [ [], 0 ],
			'absent var' => [ [ 'absent' => 'absent' ], 0 ],
			'empty var' => [ [ 'empty' => 'empty' ], 0 ],
			'one var, one value' => [ [ 'one' => 'one' ], 1 ],
			'one var, two values' => [ [ 'html' => 'html' ], 2 ],
			'two vars' => [ [ 'one' => 'one', 'html' => 'html' ], 2 ],
			'two vars and .url' => [ [ 'one' => 'one', 'url' => 'url', 'urlencoded' => 'url.urlencode' ], 2 ]
		];
	}

	/**
	 * Test EDParserFunctions::numLoops()
	 * @dataProvider numLoopsProvider
	 * @param array $mappings
	 * @param int $number
	 * @return void
	 * @throws ReflectionException
	 */
	public function testNumLoops( array $mappings, $number ) {
		$this->testPrivateMethod( 'numLoops', self::VALUES, $number, $mappings );
	}

	/**
	 * Data provider for EDParserFunctions::actuallyExternalTableFirst().
	 * @return array
	 */
	public function actuallyForExternalTableFirstProvider(): array {
		return [
			'one var' => [ 'Extension: {{{title}}}, ', 'Extension: External Data, Extension: Cargo, ' ],
			'two vars' => [
				"Extension: {{{title}}} ({{{url}}})\n",
				"Extension: External Data (https://www.mediawiki.org/wiki/Extension:External_Data)\n" .
				"Extension: Cargo (https://www.mediawiki.org/wiki/Extension:Cargo)\n"
			],
			'one real, one empty' => [
				'Extension: {{{title}}} ({{{one}}}), ',
				'Extension: External Data (One value), Extension: Cargo (), '
			],
			'all empty' => [
				'Extension: {{{title1}}} ({{{url1}}}), ',
				''
			],
			'default' => [
				'Extension: {{{title}}} ({{{one|No value}}}), ',
				'Extension: External Data (One value), Extension: Cargo (No value), '
			]
		];
	}

	/**
	 * Test EDParserFunctions::actuallyForExternalTableFirst().
	 * @dataProvider actuallyForExternalTableFirstProvider
	 * @param string $expression
	 * @param string $result
	 * @return void
	 * @throws ReflectionException
	 */
	public function testActuallyForExternalTableFirst( $expression, $result ) {
		$this->testPrivateMethod( 'actuallyForExternalTableFirst', self::VALUES, $result, $expression );
	}

	/**
	 * Data provider for EDParserFunctions::getMappings().
	 * @return array
	 */
	public function getMappingsProvider(): array {
		return [
			'no data' => [
				[],
				[ 'url' => 'url', 'title' => 'title', 'html' => 'html', 'one' => 'one', 'empty' => 'empty' ]
			],
			'array' => [ [ 'data' => [ 'Key1' => 'key1', 'Key2' => 'key2' ] ], [ 'Key1' => 'key1', 'Key2' => 'key2' ] ],
			'string' => [ [ 'data' => 'Key1=key1,Key2=key2' ], [ 'Key1' => 'key1', 'Key2' => 'key2' ] ],
			'spaced string' => [ [ 'data' => 'Key1 = key1, Key2 = key2' ], [ 'Key1' => 'key1', 'Key2' => 'key2' ] ]
		];
	}

	/**
	 * Test EDParserFunctions::getMappings().
	 * @dataProvider getMappingsProvider
	 * @param string $args
	 * @param string $mappings
	 * @return void
	 * @throws ReflectionException
	 */
	public function testGetMappings( $args, $mappings ) {
		$this->testPrivateMethod( 'getMappings', self::VALUES, $mappings, $args );
	}

	/**
	 * Data provider for EDParserFunctions::actuallyDisplayExternalTable().
	 * @return array
	 */
	public function actuallyDisplayExternalTableProvider(): array {
		return [
			'one var' => [
				[ 'template' => 'Template', 'data' => 'Title = title' ],
				[ "{{Template|Title=External Data}}\n{{Template|Title=Cargo}}", 'noparse' => false ]
			],
			'two vars' => [
				[ 'template' => 'Template', 'data' => 'Title = title, URL = url' ],
				[
					"{{Template|Title=External Data|URL=https://www.mediawiki.org/wiki/Extension:External_Data}}\n" .
					"{{Template|Title=Cargo|URL=https://www.mediawiki.org/wiki/Extension:Cargo}}",
					'noparse' => false
				]
			],
			'all vars' => [
				[ 'template' => 'Template' ],
				[
					'{{Template|empty=|html=text|one=One value|title=External Data|' .
					"url=https://www.mediawiki.org/wiki/Extension:External_Data}}\n" .
					'{{Template|empty=|html=<b class="strong">strong</b>|one=|title=Cargo|' .
					'url=https://www.mediawiki.org/wiki/Extension:Cargo}}',
					'noparse' => false
				]
			],
			'delimiter' => [
				[ 'template' => 'Template', 'data' => 'Title = title', 'delimiter' => '\n<br />' ],
				[ "{{Template|Title=External Data}}\n<br />{{Template|Title=Cargo}}", 'noparse' => false ]
			],
			'intro & outro' => [
				[
					'template' => 'Template',
					'data' => 'Title = title',
					'intro template' => 'Intro',
					'outro template' => 'Outro'
				],
				[
					"{{Intro}}\n{{Template|Title=External Data}}\n{{Template|Title=Cargo}}\n{{Outro}}",
					'noparse' => false
				]
			],
			'no template' => [ [], [ 'error' => 'externaldata-no-template' ] ],
		];
	}

	/**
	 * Test EDParserFunctions::actuallyDisplayExternalTable().
	 * @dataProvider actuallyDisplayExternalTableProvider
	 * @param array $args
	 * @param array|string $result
	 * @return void
	 * @throws ReflectionException
	 */
	public function testActuallyDisplayExternalTable( array $args, $result ) {
		$this->testPrivateMethod( 'actuallyDisplayExternalTable', self::VALUES, $result, $args );
	}
}
