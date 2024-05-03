<?php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';
$cfg['suppress_issue_types'] = array_merge( $cfg['suppress_issue_types'], [
	'PhanTypeMissingReturn', // have to keep compatibility with old PHP versions without scalar type hints.
	'PhanPluginDuplicateExpressionAssignmentOperation', // have to keep compatibility with old PHP versions without ??=.
	'PhanUndeclaredTypeReturnType', // Extensions only suggested.
	'PhanUndeclaredTypeParameter', // Extensions only suggested.
	'PhanUndeclaredTypeProperty', // Extensions only suggested.
	'PhanUndeclaredExtendedClass', // Extensions only suggested.
	'PhanParamSuspiciousOrder', // Need this.
	'PhanRedundantCondition', // Title object behaves differently in different MW versions.
	'UnusedPluginSuppression' // Different versions of MediaWiki will need different suppressions.
] );
return $cfg;
