<?php

/**
 * Scribunto hook functions for the External Data extension.
 *
 * @file
 * @ingroup ExternalData
 */
namespace ExternalData;

class ScribuntoHooks {
	/**
	 * @param string $engine
	 * @param array &$extraLibraries
	 * @return bool
	 */
	public function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ) {
		$class = 'EDScribunto';
		// Autoload class here and not in extension.json, so that it is not loaded if Scribunto is not enabled.
		global $wgAutoloadClasses;
		$wgAutoloadClasses[$class] = __DIR__ . '/' . $class . '.php';
		$extraLibraries['mw.ext.externalData'] = $class;
		return true; // always return true, in order not to stop MW's hook processing!
	}
}
