<?php

/**
 * Base class for unit tests in External Data extension.
 *
 * To run,
 * tests/phpunit/phpunit.php --wiki='(project)' \\
 * extensions/ExternalData/tests/phpunit/unit/...Test.php
 * in the MediaWiki directory.
 *
 * @group Standalone
 * @covers ...
 *
 * @author Alexander Mashin
 */
class EDTestBase extends MediaWikiUnitTestCase {
	/** @var string $class Name of the tested class. */
	protected static $class;

	/** @var array $globals A list of global variables that are needed to run the test. */
	protected static $globals = [
		'wgMessageCacheType', 'wgUseLocalMessageCache', 'wgUseDatabaseMessages', 'wgConfigRegistry'
	];

	/** @var array $backup A backup of chosen global variables. */
	protected static $backup = [];

	/**
	 * @stable for overriding
	 */
	public static function setUpBeforeClass(): void {
		// Load default configuration.
		foreach ( self::config() as $name => $value ) {
			self::$backup[$name] = $value;
		}

		parent::setUpBeforeClass();

		// Restore globals needed to run the tests.
		foreach ( self::$backup as $name => $value ) {
			$GLOBALS[$name] = $value;
		}
	}

	/**
	 * Load and return the default configuration of the extension.
	 *
	 * @return array
	 */
	protected static function config(): array {
		// Load the default configuration.
		$json = json_decode( file_get_contents( __DIR__ . '/../../../extension.json' ), true );
		$prefix = $json['config_prefix'];
		$globals = [];
		foreach ( $json['config'] as $var => $setting ) {
			$globals[$prefix . $var] = $setting['value'];
		}

		return $globals;
	}

	/**
	 * Set globals.
	 *
	 * @param array $globals New globals.
	 */
	protected static function setGlobals( array $globals ) {
		foreach ( $globals as $global => $value ) {
			$GLOBALS[$global] = $value;
		}
	}

	/**
	 * Restore globals.
	 */
	protected static function restoreGlobals() {
		foreach ( self::$backup as $global => $value ) {
			$GLOBALS[$global] = self::$backup[$global];
		}
	}
}
