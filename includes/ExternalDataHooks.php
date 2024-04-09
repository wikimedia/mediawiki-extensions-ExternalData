<?php
/**
 * Hook functions for the External Data extension.
 *
 * @file
 * @ingroup ExternalData
 * @author Yaron Koren
 */
class ExternalDataHooks {

	/**
	 * @param Parser $parser
	 * @return bool
	 */
	public static function registerParser( Parser $parser ) {
		// Add data retrieval parser functions as defined by $wgExternalDataConnectors.
		global $wgExternalDataAllowGetters;
		if ( $wgExternalDataAllowGetters ) {
			foreach ( EDConnectorBase::getConnectors() as $parser_function => $lua_function ) {
				$parser->setFunctionHook(
					$parser_function,
					static function ( Parser $parser, ...$params ) use ( $parser_function ) {
						$title = $parser->getTitle();
						return EDParserFunctions::fetch( $title, $parser_function, $params );
					}
				);
			}
			$parser->setFunctionHook( 'clear_external_data', [ 'EDParserFunctions', 'doClearExternalData' ] );
		}

		// Data display functions.
		$parser->setFunctionHook( 'external_value', [ 'EDParserFunctions', 'doExternalValue' ] );
		$parser->setFunctionHook(
			'for_external_table',
			[ 'EDParserFunctions', 'doForExternalTable' ],
			Parser::SFH_OBJECT_ARGS
		);
		$parser->setFunctionHook( 'display_external_table', [ 'EDParserFunctions', 'doDisplayExternalTable' ] );
		if ( class_exists( 'CargoDisplayFormat' ) ) {
			$parser->setFunctionHook( 'format_external_table', [ 'EDParserFunctions', 'doFormatExternalTable' ] );
		}

		// Register tags for backward compatibility with other extensions.
		foreach ( EDConnectorBase::emulatedTags() as $tag => $function ) {
			$parser->setHook( $tag, $function );
			// @todo: add code for Parsoid.
		}

		return true; // always return true, in order not to stop MW's hook processing!
	}

	/**
	 * @param string $engine
	 * @param array &$extraLibraries
	 * @return bool
	 */
	public static function registerLua( $engine, array &$extraLibraries ) {
		$class = 'EDScribunto';
		// Autoload class here and not in extension.json, so that it is not loaded if Scribunto is not enabled.
		global $wgAutoloadClasses;
		$wgAutoloadClasses[$class] = __DIR__ . '/' . $class . '.php';
		$extraLibraries['mw.ext.externalData'] = $class;
		return true; // always return true, in order not to stop MW's hook processing!
	}

	/**
	 * Register used software for Special:Version.
	 *
	 * @param array &$software
	 */
	public static function onSoftwareInfo( array &$software ) {
		EDConnectorExe::addSoftware( $software );
	}

	/**
	 * Form extension configuration from different sources.
	 */
	public static function onRegistration() {
		// Load configuration settings.
		EDConnectorBase::loadConfig();
	}

	/**
	 * For update.php. See also includes/connectors/traits/EDConnectorCached.php.
	 *
	 * @param DatabaseUpdater $updater
	 * @return void
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$dbType = $updater->getDB()->getType();
		// Create ed_url_cache table. The obsolete setting $edgCacheTable is ignored.
		$updater->addExtensionTable( 'ed_url_cache', __DIR__ . "/../sql/$dbType/ExternalData.sql" );
	}
}
