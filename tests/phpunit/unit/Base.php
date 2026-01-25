<?php
namespace MediaWiki\Extension\ExternalData\Tests\Unit;

use MediaWikiUnitTestCase;

/**
 * Base class for unit tests in External Data extension.
 *
 * @group Standalone
 *
 * @author Alexander Mashin
 */
class Base extends MediaWikiUnitTestCase {
	/** @var string $class Name of the tested class. */
	protected static $class;

	/** @var array $globals A list of global variables that are needed to run the test. */
	protected static $globals = [
		'wgMessageCacheType' => CACHE_NONE,
		'wgMainCacheType' => CACHE_NONE,
		'wgUseLocalMessageCache' => false,
		'wgUseDatabaseMessages' => false,
		// 'wgConfigRegistry' => [ 'main' => 'GlobalVarConfig::newInstance' ],
		// 'AllowImageMoving' => false
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
		// Load some really necessary globals.
		foreach ( self::$globals as $name => $value ) {
			self::$backup[$name] = $value;
		}

		parent::setUpBeforeClass();

		$GLOBALS['wgAllowImageMoving'] = false;

		self::restoreGlobals();
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
			$GLOBALS[$global] = $value;
		}
	}
}
