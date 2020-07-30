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
	 * @param Parser &$parser
	 * @return bool
	 */
	public static function registerParser( Parser &$parser ) {
		$parser->setFunctionHook( 'get_web_data', [ 'EDParserFunctions', 'getWebData' ] );
		$parser->setFunctionHook( 'get_file_data', [ 'EDParserFunctions', 'getFileData' ] );
		$parser->setFunctionHook( 'get_soap_data', [ 'EDParserFunctions', 'getSOAPData' ] );
		$parser->setFunctionHook( 'get_ldap_data', [ 'EDParserFunctions', 'getLDAPData' ] );
		$parser->setFunctionHook( 'get_db_data', [ 'EDParserFunctions', 'getDBData' ] );
		$parser->setFunctionHook( 'external_value', [ 'EDParserFunctions', 'doExternalValue' ] );
		$parser->setFunctionHook( 'for_external_table', [ 'EDParserFunctions', 'doForExternalTable' ] );
		$parser->setFunctionHook( 'display_external_table', [ 'EDParserFunctions', 'doDisplayExternalTable' ] );
		$parser->setFunctionHook( 'store_external_table', [ 'EDParserFunctions', 'doStoreExternalTable' ] );
		$parser->setFunctionHook( 'clear_external_data', [ 'EDParserFunctions', 'doClearExternalData' ] );

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
		$extraLibraries['mw.ext.externaldata'] = $class;
		return true; // always return true, in order not to stop MW's hook processing!
	}
}
