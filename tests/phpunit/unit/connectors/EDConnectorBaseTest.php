<?php
require_once __DIR__ . '/../EDTestBase.php';
/**
 * Test for the abstract class EDConnectorBase.
 *
 * To run,
 * tests/phpunit/phpunit.php --wiki='(project)' \\
 * extensions/ExternalData/tests/phpunit/unit/connectors/EDConnectorBaseTest.php
 * in the MediaWiki directory.
 *
 * @group Standalone
 * @covers EDConnectorBase
 *
 * @author Alexander Mashin
 */
class EDConnectorBaseTest extends EDTestBase {
	/** @var string $class Name of the tested class. */
	protected static $class = 'EDConnectorBase';

	/**
	 * Returns a mocked instance of EDConnectorBase.
	 *
	 * @param array $args Arguments to the constructor.
	 * @param bool $keep_case Keep external variable case.
	 *
	 * @return EDConnectorBase
	 */
	private function mock( array $args, $keep_case ): EDConnectorBase {
		// This is simpler and more usable than standard restrictive means.
		return new class ( $args, $keep_case ) extends EDConnectorBase {
			public function __construct( array &$args, $keep_case ) {
				$title = Title::makeTitle( 0, 'Dummy' );
				$this->keepExternalVarsCase = $keep_case;
				parent::__construct( $args, $title );
			}

			public function run() {
			}

			public function sources(): array {
				return self::$sources;
			}

			public function supplement( array $params ): array {
				return self::supplementParams( $params );
			}

			public function connector( $name, array $params ) {
				return self::getConnectorClass( $name, $params );
			}

			public function attributes(): array {
				return [
					'mappings' => $this->mappings(),
					'filters' => $this->filters,
					'suppress' => $this->suppressError(),
					'errors' => $this->errors()
				];
			}

			public function set( array $values ) {
				$this->values = $values;
			}

			public function add( $values ) {
				parent::add( $values );
			}

			public function values(): array {
				return $this->values;
			}
		};
	}

	/**
	 * Data provider for the EDConnectorBase::loadConfig() test.
	 *
	 * @return array
	 */
	public function provideLoadConfig(): array {
		// Load the default configuration.
		$globals = self::config();
		$prefix = EDParsesParams::$prefix;
		$old_prefix = EDParsesParams::$oldPrefix;

		$sources = $globals["{$prefix}Sources"];

		$cases = [];

		// Test the default configuration.
		$cases['Default'] = [ $globals, $sources ];

		// Test a global setting overridden by wiki admin.
		$globals["{$prefix}Sources"]['*']['min cache seconds'] = 60;
		$sources['*']['min cache seconds'] = 60;
		$cases['Global'] = [ $globals, $sources ];

		// Test a user-defined data source.
		$globals["{$prefix}Sources"]['rfam'] = [
			'server' => 'mysql-rfam-public.ebi.ac.uk:4497',
			'type' => 'mysql',
			'name' => 'Rfam',
			'user' => 'rfamro',
			'password' => ''
		];
		$sources['rfam'] = $globals["{$prefix}Sources"]['rfam'];
		$cases['Data source'] = [ $globals, $sources ];

		// Test old style global setting.
		$globals["{$old_prefix}StringReplacements"] = [ 'master' => 'primary', 'slave' => 'replica' ];
		$sources['*']['replacements'] = $globals["{$old_prefix}StringReplacements"];
		$cases['Old style global'] = [ $globals, $sources ];

		// Test old style settings for data source.
		$globals["{$old_prefix}ExeCommand"] = [ 'man' => 'man $topic$' ];
		$globals["{$old_prefix}ExeParams"] = [ 'man' => [ 'topic' ] ];
		$globals["{$old_prefix}ExeParamFilters"] = [ 'man' => [ 'topic' => '/^\w+$/' ] ];
		$sources['man'] = [
			'command' => 'man $topic$', 'params' => [ 'topic' ], 'param filters' => [ 'topic' => '/^\w+$/' ]
		];
		$cases['Old style data source'] = [ $globals, $sources ];

		return $cases;
	}

	/**
	 * Test EDConnector::loadConfig().
	 *
	 * @dataProvider provideLoadConfig
	 *
	 * @param array $globals Global variables.
	 * @param array $expected Expected contents of EDConnectorBase::$sources.
	 */
	public function testLoadConfig( array $globals, array $expected ) {
		self::setGlobals( $globals );
		$mock = $this->mock( [], true );
		$mock::loadConfig();
		$this->assertArrayEquals(
			$expected,
			$mock->sources(),
			'Incorrect contents of EDConnectorBase::$sources after EDConnectorBase::loadConfig()', false, true
		);
	}

	/**
	 * Data provider for EDConnectorBase::__construct()
	 *
	 * @return array
	 */
	public function provideConstruct(): array {
		$cases = [];

		$cases['Typical'] = [
			[ 'data' => 'name=Name,time=__time', 'filters' => 'name=Alexander Mashin', 'source' => 'some source' ],
			[
				'mappings' => [ 'name' => 'Name', 'time' => '__time' ],
				'filters' => [ 'name' => 'Alexander Mashin' ],
				'suppress' => false, 'errors' => null
			],
			true
		];

		$cases['Suppress error'] = [
			[
				'data' => 'name=Name,time=__time',
				'filters' => 'name=Alexander Mashin',
				'suppress error' => null,
				'source' => 'some source'
			],
			[
				'mappings' => [ 'name' => 'Name', 'time' => '__time' ],
				'filters' => [ 'name' => 'Alexander Mashin' ],
				'suppress' => true, 'errors' => null
			],
			true
		];

		$cases['Lower case'] = [
			[ 'data' => 'name=Name,time=__time', 'filters' => 'name=Alexander Mashin', 'source' => 'some source' ],
			[
				'mappings' => [ 'name' => 'name', 'time' => '__time' ],
				'filters' => [ 'name' => 'Alexander Mashin' ],
				'suppress' => false, 'errors' => null
			],
			false
		];

		$cases['Lua table'] = [
			[
				'data' => [ 'name' => 'Name' , 'time' => '__time' ],
				'filters' => [ 'name' => 'Alexander Mashin' ],
				'source' => 'some source'
			],
			[
				'mappings' => [ 'name' => 'Name', 'time' => '__time' ],
				'filters' => [ 'name' => 'Alexander Mashin' ],
				'suppress' => false, 'errors' => null
			],
			true
		];

		$cases['No data source id'] = [
			[ 'data' => 'name=Name,time=__time', 'filters' => 'name=Alexander Mashin' ],
			[
				'mappings' => [ 'name' => 'Name', 'time' => '__time' ],
				'filters' => [ 'name' => 'Alexander Mashin' ],
				'suppress' => false,
				'errors' => [
					'6503c3799268ad392b259f7ea1d71951' => [
						'code' => 'externaldata-no-param-specified',
						'params' => [ 0 => 'source' ]
					]
				]
			],
			true
		];

		return $cases;
	}

	/**
	 * Test EDConnectorBase::__construct().
	 *
	 * @dataProvider provideConstruct
	 *
	 * @param array $params Parameters to the constructor.
	 * @param array $expected Expected values of attributes.
	 * @param bool $keep_case Whether the mocked class keeps external variables' case.
	 */
	public function testConstruct( array $params, array $expected, $keep_case ) {
		self::restoreGlobals();
		$mock = $this->mock( $params, $keep_case );
		$actual = $mock->attributes();
		// Test mappings.
		$this->assertArrayEquals(
			$expected['mappings'],
			$actual['mappings'],
			'Incorrect contents of EDConnectorBase::$mappings after EDConnectorBase::__construct()'
		);
		// Test filters.
		$this->assertArrayEquals(
			$expected['filters'],
			$actual['filters'],
			'Incorrect contents of EDConnectorBase::$filters after EDConnectorBase::__construct()'
		);
		// Test 'suppress error'.
		$this->assertEquals(
			$expected['suppress'],
			$actual['suppress'],
			'Incorrect result of EDConnectorBase::suppressError() after EDConnectorBase::__construct()'
		);
		// Test errors.
		if ( $expected['errors'] ) {
			$this->assertArrayEquals(
				$expected['errors'],
				$actual['errors'],
				'Incorrect result of EDConnectorBase::errors() after EDConnectorBase::__construct()'
			);
		} else {
			$this->assertNull( $actual['errors'], 'Unexpected errors after EDConnectorBase::__construct()' );
		}
	}

	/**
	 * Data provider for EDConnector::add().
	 *
	 * @return array
	 */
	public function provideAdd(): array {
		return [
			'First' => [ [], [ 'name' => [ 'Alexander Mashin' ] ], [ 'name' => [ 'Alexander Mashin' ] ] ],
			'Pile' => [
				[ 'name' => [ 'Alexander Mashin' ] ],
				[ '__time' => [ 1634578203 ] ],
				[ 'name' => [ 'Alexander Mashin' ], '__time' => [ 1634578203 ] ],
			],
			'Unequal' => [
				[ 'name' => [ 'Yaron Koren', 'Alexander Mashin' ] ],
				[ '__time' => [ 1634578203 ] ],
				[ 'name' => [ 'Yaron Koren', 'Alexander Mashin' ], '__time' => [ 1634578203 ] ],
			],
			'Adding unequal' => [
				[ '__time' => [ 1634578203 ] ],
				[ 'name' => [ 'Yaron Koren', 'Alexander Mashin' ], 'citizenship' => [ 'US', 'RF', 'IL' ] ],
				[
					'__time' => [ 1634578203 ],
					'name' => [ 'Yaron Koren', 'Alexander Mashin' ],
					'citizenship' => [ 'US', 'RF', 'IL' ]
				]
			],
			'Repeating keys' => [
				[ '__time' => [ 1634578203 ], 'name' => [ 'Yaron Koren' ] ],
				[ 'name' => [ 'Alexander Mashin' ], 'citizenship' => [ 'US', 'RF', 'IL' ] ],
				[
					'__time' => [ 1634578203 ],
					'name' => [ 'Yaron Koren', 'Alexander Mashin' ],
					'citizenship' => [ null, 'US', 'RF', 'IL' ]
				]
			]
		];
	}

	/**
	 * Test EDConnectorBase::add().
	 *
	 * @dataProvider provideAdd
	 *
	 * @param array $old Values before adding.
	 * @param array $added Added values.
	 * @param array $new Expected values after adding.
	 */
	public function testAdd( array $old, array $added, array $new ) {
		$mock = $this->mock( [], true );
		$mock->set( $old );
		$mock->add( $added );
		$this->assertArrayEquals(
			$new,
			$mock->values(),
			'Unexpected EDConnectorBase::$values after EDConnectorBase::add()'
		);
	}

	/**
	 * Data provider for EDConnectorBase::getConnector().
	 *
	 * @return array
	 */
	public function provideGetConnector(): array {
		if ( class_exists( 'MongoDB\Client' ) ) {
			$mongo = 'EDConnectorMongodb7';
		} elseif ( class_exists( 'MongoClient' ) ) {
			$mongo = 'EDConnectorMongodb5';
		} else {
			$mongo = 'EDConnectorSql';
		}
		return [
			// Specific functions.
			'{{#get_web_data:}}, POST' => [ 'get_web_data', [ 'post data' => 'postdata' ], 'EDConnectorWeb' ],
			'{{#get_web_data:}}' => [ 'get_web_data', [ 'url' => 'https://mediawiki.org' ], 'EDConnectorWeb' ],
			'{{#get_file_data:}}, file mask' =>
				[ 'get_file_data', [ 'directory' => '/etc', 'file name' => '*.conf' ], 'EDConnectorDirectoryWalker' ],
			'{{#get_file_data:}}, directory' => [ 'get_file_data', [ 'directory' => '/etc' ], 'EDConnectorDirectory' ],
			'{{#get_file_data:}}' => [ 'get_file_data', [], 'EDConnectorFile' ],
			'{{#get_soap_data:}}' => [ 'get_soap_data', [], 'EDConnectorSoap' ],
			'{{#get_ldap_data:}}' => [ 'get_ldap_data', [], 'EDConnectorLdap' ],
			'{{#get_db_data:}}, mySQL, prepared' =>
				[ 'get_db_data', [ 'type' => 'mysql', 'prepared' => 'statement 1' ], 'EDConnectorPreparedMysql' ],
			'{{#get_db_data:}}, PostgreSQL, prepared' => [
					'get_db_data',
					[ 'type' => 'postgres', 'prepared' => 'statement 1' ],
					'EDConnectorPreparedPostgresql'
			],
			'{{#get_db_data:}}, sqlite' => [ 'get_db_data', [ 'type' => 'sqlite' ], 'EDConnectorSqlite' ],
			'{{#get_db_data:}}, ODBC prepared' => [
				'get_db_data',
				[ 'type' => 'odbc', 'prepared' => 'statement 1' ],
				'EDConnectorPreparedOdbc'
			],
			'{{#get_db_data:}}, MS SQL' => [
				'get_db_data',
				[ 'type' => 'odbc', 'driver' => 'ODBC Driver 17 for SQL Server' ],
				'EDConnectorOdbcMssql'
			],
			'{{#get_db_data:}}, MongoDB' =>	[ 'get_db_data', [ 'type' => 'mongodb' ], $mongo ],
			'{{#get_db_data:}}, PostgreSQL' => [ 'get_db_data', [ 'type' => 'postgres' ], 'EDConnectorPostgresql' ],
			'{{#get_db_data:}}, mySQL, etc.' => [ 'get_db_data', [], 'EDConnectorSql' ],
			'{{#get_program_data:}}' => [ 'get_program_data', [], 'EDConnectorExe' ],

			'{{#get_db_data:}}, source instead of db' => [
				'get_db_data',
				[ 'type' => 'postgres', 'source' => 'PG1' ],
				'EDConnectorPostgresql'
			],

			// Misused specific function on hidden data source.
			'{{#get_db_data:}}, Misused parser function with hidden source' => [
				'get_db_data',
				[ 'type' => 'postgres', 'source' => 'PG1', 'hidden' => true ],
				'EDConnectorDummy'
			],

			// Misused id on hidden data source.
			'{{#get_external_data:}}, Misused identifier with hidden source' => [
				'get_external_data',
				[ 'type' => 'postgres', 'db' => 'PG1', 'hidden' => true ],
				'EDConnectorDummy'
			],

			// Correctly used hidden data source.
			'{{#get_external_data:}}, Correctly used hidden source' => [
				'get_external_data',
				[ 'type' => 'postgres', 'source' => 'PG1', 'hidden' => true ],
				'EDConnectorPostgresql'
			],

			// Universal function.
			'{{#get_external_data:}}, POST' =>
				[ 'get_external_data', [ 'post data' => 'postdata' ], 'EDConnectorWeb' ],
			'{{#get_external_data:}}, URL' =>
				[ 'get_external_data', [ 'url' => 'https://mediawiki.org' ], 'EDConnectorWeb' ],
			'{{#get_external_data:}}, file mask' =>	[
				'get_external_data',
				[ 'directory' => '/etc', 'file name' => '*.conf' ], 'EDConnectorDirectoryWalker'
			],
			'{{#get_external_data:}}, directory' =>
				[ 'get_external_data', [ 'directory' => '/etc' ], 'EDConnectorDirectory' ],
			'{{#get_external_data:}}, file' => [ 'get_external_data', [ 'file' => 'file example' ], 'EDConnectorFile' ],
			'{{#get_external_data:}}, SOAP' =>
				[ 'get_external_data', [ 'request' => 'some request' ], 'EDConnectorSoap' ],
			'{{#get_external_data:}}, LDAP' =>
				[ 'get_external_data', [ 'domain' => 'ldap domain' ], 'EDConnectorLdap' ],
			'{{#get_external_data:}}, mySQL, prepared' =>
				[ 'get_external_data', [ 'type' => 'mysql', 'prepared' => true ], 'EDConnectorPreparedMysql' ],
			'{{#get_external_data:}}, PostgreSQL, prepared' => [
					'get_external_data',
					[ 'type' => 'postgres', 'prepared' => 'statement 1' ],
					'EDConnectorPreparedPostgresql'
			],
			'{{#get_external_data:}}, sqlite' => [ 'get_external_data', [ 'type' => 'sqlite' ], 'EDConnectorSqlite' ],
			'{{#get_external_data:}}, ODBC prepared' => [
				'get_external_data',
				[ 'type' => 'odbc', 'prepared' => 'statement 1' ],
				'EDConnectorPreparedOdbc'
			],
			'{{#get_external_data:}}, MS SQL' => [
				'get_external_data',
				[ 'type' => 'odbc', 'driver' => 'ODBC Driver 17 for SQL Server' ],
				'EDConnectorOdbcMssql'
			],
			'{{#get_external_data:}}, MongoDB' =>
				[ 'get_external_data', [ 'from' => 'MongoDB dataset', 'type' => 'mongodb' ], $mongo ],
			'{{#get_external_data:}}, PostgreSQL' => [
				'get_external_data',
				[ 'type' => 'postgres' ], 'EDConnectorPostgresql'
			],
			'{{#get_external_data:}}, mySQL, etc.' =>
				[ 'get_external_data', [ 'from' => 'mysql_table', 'type' => 'mysql' ], 'EDConnectorSql' ],
			'{{#get_external_data:}}, program' => [ 'get_external_data', [ 'program' => 'man' ], 'EDConnectorExe' ],

			// Universal function without a suitable connector.
			'{{#get_external_data:}}, No source' => [ 'get_external_data', [ 'source' => 'PG1' ], 'EDConnectorDummy' ],

			// 'source' instead of a specific parameter.
			// Specific functions.
			'{{#get_web_data:}}, source' => [
				'get_web_data',
				[ 'source' => 'https://mediawiki.org' ], 'EDConnectorWeb'
			],
			'{{#get_file_data:}}, file mask, source' =>
				[ 'get_file_data', [ 'source' => '/etc', 'file name' => '*.conf' ], 'EDConnectorDirectoryWalker' ],
			'{{#get_file_data:}}, directory, source' => [
				'get_file_data',
				[ 'source' => '/etc', 'file name' => 'hosts' ],
				'EDConnectorDirectory'
			],

			// Universal function.
			'{{#get_external_data:}}, URL, source' =>
				[ 'get_external_data', [ 'source' => 'https://mediawiki.org' ], 'EDConnectorWeb' ],
			'{{#get_external_data:}}, file mask, source' =>	[
				'get_external_data',
				[ 'source' => '/etc', 'file name' => '*.conf' ], 'EDConnectorDirectoryWalker'
			],
			'{{#get_external_data:}}, directory, source' =>
				[ 'get_external_data', [ 'source' => '/etc', 'file name' => 'hosts' ], 'EDConnectorDirectory' ],
			'{{#get_external_data:}}, file, source' => [
				'get_external_data', [ 'source' => 'file example', 'path' => '/etc' ], 'EDConnectorFile'
			],
			'{{#get_external_data:}}, LDAP, source' => [
				'get_external_data',
				 [ 'source' => 'ldap domain', 'base dn' => 'dc=example,dc=com' ],
				 'EDConnectorLdap'
			],

			// First parameter instead of a specific parameter.
			// Specific functions.
			'{{#get_web_data:}}, anonymous' => [
				'get_web_data',
				[ 0 => 'https://mediawiki.org' ], 'EDConnectorWeb'
			],
			'{{#get_file_data:}}, file mask, anonymous' =>
				[ 'get_file_data', [ 0 => '/etc', 'file name' => '*.conf' ], 'EDConnectorDirectoryWalker' ],
			'{{#get_file_data:}}, directory, anonymous' => [
				'get_file_data',
				[ 0 => '/etc', 'file name' => 'hosts' ],
				'EDConnectorDirectory'
			],

			// Universal function.
			'{{#get_external_data:}}, URL, anonymous' =>
				[ 'get_external_data', [ 0 => 'https://mediawiki.org' ], 'EDConnectorWeb' ],
			'{{#get_external_data:}}, file mask, anonymous' => [
				'get_external_data',
				[ 0 => '/etc', 'file name' => '*.conf' ], 'EDConnectorDirectoryWalker'
			],
			'{{#get_external_data:}}, directory, anonymous' =>
				[ 'get_external_data', [ 0 => '/etc', 'file name' => 'hosts' ], 'EDConnectorDirectory' ],
			'{{#get_external_data:}}, file, anonymous' => [
				'get_external_data', [ 0 => 'file example', 'path' => '/etc' ], 'EDConnectorFile'
			],
			'{{#get_external_data:}}, LDAP, anonymous' => [
				'get_external_data',
				[ 0 => 'ldap domain', 'base dn' => 'dc=example,dc=com' ],
				'EDConnectorLdap'
			],

		];
	}

	/**
	 * Test EDConnectorBase::getConnector().
	 *
	 * @dataProvider provideGetConnector
	 *
	 * @param string $name Parser function name.
	 * @param array $args Arguments to parser function.
	 * @param string $class Expected class name of the EDConnector... object.
	 */
	public function testGetConnector( $name, array $args, $class ) {
		$args['format'] = 'text';
		$args['data'] = 'text=__text';
		self::restoreGlobals();
		$mock = $this->mock( $args, true );
		$supplemented = $mock->supplement( $args );
		$connector = $mock->connector( $name, $supplemented );
		$this->assertEquals(
			$class,
			$connector,
			'Wrong EDConnector... class created by EDConnectorBase::getConnector()'
		);
	}

	/**
	 * Parse URL as in EDConnectorBase::supplementParams.
	 *
	 * @param string $url URL to parse
	 *
	 * @return array
	 */
	private static function parseUrl( $url ): array {
		$components = parse_url( $url );
		// Second-level domain is likely to be both a throttle key and an index to find a throttle key or interval.
		if ( preg_match( '/(?<=^|\.)\w+\.\w+$/', $components['host'], $matches ) ) {
			$components['2nd_lvl_domain'] = $matches[0];
		} else {
			$components['2nd_lvl_domain'] = $components['host'];
		}
		return [
			'url' => $url,
			'components' => $components,
			'host' => $components['host'],
			'2nd_lvl_domain' => $components['2nd_lvl_domain']
		];
	}

	/**
	 * Provide data for EDConnectorBase::supplementParams().
	 *
	 * @return array
	 */
	public function provideSupplementParams(): array {
		// Load the default configuration.
		$sources = self::config()[EDParsesParams::$prefix . 'Sources'];
		$cases = [];

		// Simplest case.
		$url = 'https://mediawiki.org/Extension:External_Data';
		$components = self::parseUrl( $url );
		$cases['Common URL'] = [
			[ 'url' => $url ], $sources,
			[
				'url' => $url,
				'host' => $components['host'],
				'2nd_lvl_domain' => $components['2nd_lvl_domain'],
				'min cache seconds' => $sources['*']['min cache seconds']
			]
		];

		// Simplest case: 'source'.
		$url = 'https://mediawiki.org/Extension:External_Data';
		$components = self::parseUrl( $url );
		$cases['Common URL passed in "source"'] = [
			[ 'source' => $url ], $sources,
			[
				'url' => $url,
				'host' => $components['host'],
				'2nd_lvl_domain' => $components['2nd_lvl_domain'],
				'min cache seconds' => $sources['*']['min cache seconds']
			]
		];

		// Simplest case: anonymous.
		$url = 'https://mediawiki.org/Extension:External_Data';
		$components = self::parseUrl( $url );
		$cases['Common URL passed anonymously'] = [
			[ 0 => $url ], $sources,
			[
				'url' => $url,
				'host' => $components['host'],
				'2nd_lvl_domain' => $components['2nd_lvl_domain'],
				'min cache seconds' => $sources['*']['min cache seconds']
			]
		];

		// Per-URL settings.
		$sources[$url] = [ 'throttle interval' => 10 ];
		$cases['Per-URL settings'] = [
			[ 'url' => $url ], $sources,
			[
				'url' => $url,
				'host' => $components['host'],
				'2nd_lvl_domain' => $components['2nd_lvl_domain'],
				'min cache seconds' => $sources['*']['min cache seconds'],
				'throttle interval' => 10
			]
		];

		// Per-host settings.
		$sources[$components['host']] = [ 'encodings' => [] ];
		$cases['Per-host settings'] = [
			[ 'url' => $url ], $sources,
			[
				'url' => $url,
				'host' => $components['host'],
				'2nd_lvl_domain' => $components['2nd_lvl_domain'],
				'min cache seconds' => $sources['*']['min cache seconds'],
				'throttle interval' => 10,
				'encodings' => []
			]
		];

		// Conflicting per-host and per-URL settings.
		$sources[$url]['encodings'] = [ 'UTF-8' ];
		$cases['Conflicting per-host and per-URL settings'] = [
			[ 'url' => $url ], $sources,
			[
				'url' => $url,
				'host' => $components['host'],
				'2nd_lvl_domain' => $components['2nd_lvl_domain'],
				'min cache seconds' => $sources['*']['min cache seconds'],
				'throttle interval' => 10,
				'encodings' => [ 'UTF-8' ]
			]
		];

		// Settings per second level domain.
		$url = 'https://alex-mashin.livejournal.com';
		$components = self::parseUrl( $url );
		$sources[$components['2nd_lvl_domain']]['throttle interval'] = 15;
		$cases['Settings per second level domain'] = [
			[ 'url' => $url ], $sources,
			[
				'url' => $url,
				'host' => $components['host'],
				'2nd_lvl_domain' => $components['2nd_lvl_domain'],
				'min cache seconds' => $sources['*']['min cache seconds'],
				'throttle interval' => 15
			]
		];

		// Database parameters.
		$id = 'rfam';
		$sources[$id] = [
			'server' => 'mysql-rfam-public.ebi.ac.uk:4497',
			'type' => 'mysql',
			'name' => 'Rfam',
			'user' => 'rfamro',
			'password' => ''
		];
		$cases['Database parameters'] = [ [ 'db' => $id ], $sources, [ 'db' => $id ] + $sources[$id] ];

		// Database parameters with 'server' instead of 'db'.
		$id = 'rfam2';
		$sources[$id] = [
			'host' => 'mysql-rfam-public.ebi.ac.uk:4497',
			'type' => 'mysql',
			'name' => 'Rfam',
			'user' => 'rfamro',
			'password' => ''
		];
		$cases['Database parameters with "server" instead of "id"'] = [
			[ 'server' => $id ],
			$sources,
			[ 'server' => $id ] + $sources[$id]
		];

		// Database parameters with 'source'.
		$id = 'rfam';
		$sources[$id] = [
			'source' => 'mysql-rfam-public.ebi.ac.uk:4497',
			'type' => 'mysql',
			'name' => 'Rfam',
			'user' => 'rfamro',
			'password' => ''
		];
		$cases['Database parameters with "source"'] = [ [ 'db' => $id ], $sources, [ 'db' => $id ] + $sources[$id] ];

		// Database parameters with unnamed source.
		$id = 'rfam';
		$sources[$id] = [
			0 => 'mysql-rfam-public.ebi.ac.uk:4497',
			'type' => 'mysql',
			'name' => 'Rfam',
			'user' => 'rfamro',
			'password' => ''
		];
		$cases['Database parameters, anonymous'] = [ [ 'db' => $id ], $sources, [ 'db' => $id ] + $sources[$id] ];

		// Settings per program.
		$id = 'man';
		$sources[$id] = [
			'command' => 'man $topic$',
			'params' => [ 'topic' ],
			'param filters' => [ 'topic' => '/^\w+$/' ]
		];
		$cases['Settings per program'] = [ [ 'program' => $id ], $sources, [ 'program' => $id ] + $sources[$id] ];

		// Settings for all programs.
		$sources['*']['limits'] = [ 'memory' => 0, 'time' => 180, 'walltime' => 180, 'filesize' => 'unlimited' ];
		$cases['Settings for all programs'] = [
			[ 'program' => $id ], $sources,
			[ 'program' => $id ] + $sources[$id] + [ 'limits' => $sources['*']['limits'] ]
		];

		// Conflicting settings for a specific program and all programs.
		$sources[$id]['limits'] = [ 'memory' => 128000, 'time' => 180, 'walltime' => 180, 'filesize' => 'unlimited' ];
		$cases['Conflicting settings for a specific program and all programs'] = [
			[ 'program' => $id ], $sources,
			[ 'program' => $id ] + $sources[$id] + [ 'limits' => $sources[$id]['limits'] ]
		];

		return $cases;
	}

	/**
	 * Test EDConnectorBase::supplementParams().
	 *
	 * @dataProvider provideSupplementParams
	 *
	 * @param array $params User-supplied params.
	 * @param array $sources $wgExternalDataSources.
	 * @param array $expected Expected supplemented params (only the relevant ones).
	 *
	 */
	public function testSupplementParams( array $params, array $sources, array $expected ) {
		self::setGlobals( array_merge( self::$backup, [ EDParsesParams::$prefix . 'Sources' => $sources ] ) );
		$mock = $this->mock( $params, true );
		$mock->loadConfig();

		$supplemented = $mock->supplement( $params );
		foreach ( $expected as $param => $value ) {
			$this->assertArrayHasKey(
				$param,
				$supplemented,
				'Expected param ' . $param . ' not returned by EDConnectorBase::supplementParams()'
			);
			$this->assertEquals(
				$value,
				$supplemented[$param],
				'Unexpected supplemented value for ' . $param . ' returned by EDConnectorBase::supplementParams()'
			);
		}
	}

	/**
	 * Data provider for EDConnectorBase::filteredAndMappedValues().
	 */
	public function provideFilteredAndMappedValues() {
		return [
			// Simplest case.
			'Simple' => [ [ '__text' => [ 'Text' ] ], 'text=__text', '', [ 'text' => [ 'Text' ] ] ],
			// Two columns.
			'Two columns' => [
				[ 'c1' => [ '11', '12' ], 'c2' => [ '21', '22' ] ],
				'col1=c1,col2=c2', '',
				[ 'col1' => [ '11', '12' ], 'col2' => [ '21', '22' ] ]
			],
			// Standard variables.
			'Standard variables' => [
				[ 'c1' => [ '11', '12' ], 'c2' => [ '21', '22' ], '__time' => [ 1634578203 ] ],
				'col1=c1,col2=c2,time=__time', '',
				[ 'col1' => [ '11', '12' ], 'col2' => [ '21', '22' ], 'time' => [ 1634578203 ] ]
			],
			// __all.
			'__all' => [
				[ 'c1' => [ '11', '12' ], 'c2' => [ '21', '22' ] ],
				'__all', '',
				[ 'c1' => [ '11', '12' ], 'c2' => [ '21', '22' ] ]
			],
			// filter.
			'filter' => [
				[ 'c1' => [ '11', '12' ], 'c2' => [ '21', '22' ] ],
				'__all', 'c1=11',
				[ 'c1' => [ '11' ], 'c2' => [ '21' ] ]
			],
		];
	}

	/**
	 * Test EDConnectorBase::filteredAndMappedValues().
	 *
	 * @dataProvider provideFilteredAndMappedValues
	 *
	 * @param array $external External values.
	 * @param array|string $data 'data' parameter (mappings).
	 * @param array|string $filters 'filters' parameter.
	 * @param array $expected Expected internal values.
	 */
	public function testFilteredAndMappedValues( array $external, $data, $filters, array $expected ) {
		$mock = $this->mock( [ 'data' => $data, 'filters' => $filters ], true );
		$mock->set( $external );
		$internal = $mock->result();
		$this->assertArrayEquals(
			$expected,
			$internal,
			'EDConnectorBase::filteredAndMappedValues() returned unexpected internal variables'
		);
	}
}
