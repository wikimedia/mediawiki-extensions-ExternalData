<?php
use MediaWiki\Shell\Shell;

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
		$parser->setFunctionHook( 'get_program_data', [ 'EDParserFunctions', 'getProgramData' ] );
		$parser->setFunctionHook( 'external_value', [ 'EDParserFunctions', 'doExternalValue' ] );
		$parser->setFunctionHook( 'for_external_table', [ 'EDParserFunctions', 'doForExternalTable' ] );
		$parser->setFunctionHook( 'display_external_table', [ 'EDParserFunctions', 'doDisplayExternalTable' ] );
		$parser->setFunctionHook( 'store_external_table', [ 'EDParserFunctions', 'doStoreExternalTable' ] );
		$parser->setFunctionHook( 'clear_external_data', [ 'EDParserFunctions', 'doClearExternalData' ] );

		$parser->setHook( 'externalvalue', [ 'EDParserFunctions', 'doExternalValueRaw' ] );

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

	/**
	 * Register used software for Special:Version.
	 *
	 * @param array &$software
	 */
	public static function onSoftwareInfo( array &$software ) {
		global $edgExeCommand;
		foreach ( $edgExeCommand as $key => $command ) {
			preg_match( '/^\\w+/', $command, $matches );
			$path = $matches[0];
			global $edgExeName;
			if ( array_key_exists( $key, $edgExeName ) ) {
				$name = $edgExeName[$key];
			} else {
				preg_match( '~[^/]+$~', $path, $matches );
				$name = $matches[0];
			}
			global $edgExeUrl;
			if ( array_key_exists( $key, $edgExeUrl ) ) {
				$name = "[$edgExeUrl[$key] $name]";
			}
			$version = null;
			global $edgExeVersion;
			if ( array_key_exists( $key, $edgExeVersion ) ) {
				// Version is hard coded in LocalSettings.php.
				$version = $edgExeVersion[$key];
			} else {
				// Version will be reported by the program itself.
				global $edgExeVersionCommand;
				if ( array_key_exists( $key, $edgExeVersionCommand ) ) {
					// The command key that reports the version is set in LocalSettings.php,
					$commands_v = [ $edgExeVersionCommand[$key] ];
				} else {
					// We will try several most common keys that print out version one by one.
					$commands_v = [ "$path -V", "$path -v", "$path --version", "$path -version" ];
				}
				foreach ( $commands_v as $command_v ) {
					try {
						$result = Shell::command( explode( ' ', $command_v ) )
							->includeStderr()
							->restrict( Shell::RESTRICT_DEFAULT | Shell::NO_NETWORK )
							->execute();
					} catch ( Exception $ex ) {
						// No need to continue. Something is wrong with Shell itself.
						$version = "Exception while running $command_v";
						break;
					}
					$exit_code = $result->getExitCode();
					if ( $exit_code === 0 ) {
						$version = $result->getStdout();
						break;
					}
				}
			}
			$software[$name] = $version ?: '(unknown)';
		}
	}
}
