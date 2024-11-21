<?php

/**
 * Schema hook functions for the External Data extension.
 *
 * @file
 * @ingroup ExternalData
 */
namespace ExternalData;

use DatabaseUpdater;

class SchemaHooks implements
	\MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook
{
	/**
	 * For update.php. See also includes/connectors/traits/EDConnectorCached.php.
	 *
	 * @param DatabaseUpdater $updater
	 * @return void
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dbType = $updater->getDB()->getType();
		// Create ed_url_cache table. The obsolete setting $edgCacheTable is ignored.
		$updater->addExtensionTable( 'ed_url_cache', __DIR__ . "/../sql/$dbType/ExternalData.sql" );
		// T376241: Drop the post_vars column since it is unused
		$updater->dropExtensionField(
			'ed_url_cache',
			'post_vars',
			__DIR__ . "/../maintenance/archives/$dbType/patch-ed_url_cache-drop-post_vars.sql",
		);
	}
}
