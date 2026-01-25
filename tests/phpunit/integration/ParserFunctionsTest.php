<?php

namespace MediaWiki\Extension\ExternalData\Tests\Integration;

use ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWikiLangTestCase;
use ReflectionClass;
use ReflectionException;

/**
 * Test for the class EDParserFunctions.
 *
 * @covers EDParserFunctions
 *
 * @author Alexander Mashin
 */
class ParserFunctionsTest extends MediaWikiLangTestCase {
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
	 * @stable for overriding
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'External Data' ) ) {
			$this->markTestSkipped( 'Extension:External Data is not loaded' );
		}
	}

	/**
	 * @param string $name Method name.
	 * @param array[] $values Saved values.
	 * @param mixed $expected Expected return.
	 * @param mixed ...$args Method arguments.
	 * @return void
	 * @throws ReflectionException
	 */
	private function testPrivateMethod( string $name, array $values, $expected, ...$args ) {
		$class = new ReflectionClass( static::$class );

		// Clear existent data.
		$clearer = $class->getMethod( 'actuallyClearExternalData' );
		$clearer->invoke( null, [] );

		// Save new data.
		$setter = $class->getMethod( 'saveValues' );
		$setter->invoke( null, $values );

		// Invoke the tested method.
		$private = $class->getMethod( $name );
		$actual = $private->invoke( null, ...$args );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Data provider for EDParserFunctions::actuallyDisplayExternalTable().
	 * @return array
	 */
	public static function actuallyDisplayExternalTableProvider(): array {
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
	 * @param array $result
	 * @return void
	 * @throws ReflectionException
	 */
	public function testActuallyDisplayExternalTable( array $args, array $result ) {
		$title = Title::makeTitle( 0, 'Dummy' );
		$this->testPrivateMethod( 'actuallyDisplayExternalTable', self::VALUES, $result, $args, $title );
	}

}
