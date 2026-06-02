<?php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';
$cfg['suppress_issue_types'] = array_merge( $cfg['suppress_issue_types'], [
	'PhanUndeclaredTypeParameter', // Extensions only suggested.
	'PhanUndeclaredTypeProperty', // Extensions only suggested.
	'PhanUndeclaredExtendedClass', // Extensions only suggested.
	'PhanParamSuspiciousOrder', // Need this.
	'PhanUnusedVariableCaughtException', // requires pure php8+, MW 1.42
	// Phan can be run on installations with different optional dependencies installed;
	// therefore PhanUndeclaredClassMethod is sometimes used and sometimes not.
	'UnusedPluginSuppression'
] );
return $cfg;
