<?php

/**
 * Scribunto hook functions for the External Data extension.
 *
 * @file
 * @ingroup ExternalData
 */
namespace ExternalData;

use EDScribunto;

class ScribuntoHooks {
	/**
	 * @param string $engine
	 * @param array &$extraLibraries
	 * @return bool
	 */
	public function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ) {
		$extraLibraries['mw.ext.externalData'] = EDScribunto::class;
		return true; // always return true, in order not to stop MW's hook processing!
	}
}
