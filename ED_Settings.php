<?php
/**
 * Initialization file for the External Data extension
 *
 * @file
 * @ingroup ExternalData
 * @author Yaron Koren
 */

if (!defined('MEDIAWIKI')) die();

$wgExtensionCredits['parserhook'][]= array(
	'name'	=> 'External Data',
	'version'	=> '0.2',
	'author'	=> 'Yaron Koren',
	'url'	=> 'http://www.mediawiki.org/wiki/Extension:External_Data',
	'description'	=>  'Allows creating variables from an external XML, CSV or JSON file',
);

$wgExtensionFunctions[] = 'edgParserFunctions';
$wgHooks['LanguageGetMagic'][] = 'edgLanguageGetMagic';

$edgIP = $IP . '/extensions/ExternalData';
$wgAutoloadClasses['EDParserFunctions'] = $edgIP . '/ED_ParserFunctions.php';

$edgValues = array();

function edgParserFunctions() {
	global $wgHooks, $wgParser;
	if( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
		$wgHooks['ParserFirstCallInit'][] = 'edgRegisterParser';
	} else {
		if ( class_exists( 'StubObject' ) && !StubObject::isRealObject( $wgParser ) ) {
			$wgParser->_unstub();
		}
		edgRegisterParser( $wgParser );
	}
}

function edgRegisterParser(&$parser) {
	$parser->setFunctionHook( 'get_external_data', array('EDParserFunctions','doGetExternalData') );
	$parser->setFunctionHook( 'external_value', array('EDParserFunctions','doExternalValue') );
	return true; // always return true, in order not to stop MW's hook processing!
}

function edgLanguageGetMagic( &$magicWords, $langCode = "en" ) {
	switch ( $langCode ) {
	default:
		$magicWords['get_external_data'] = array ( 0, 'get_external_data' );
		$magicWords['external_value'] = array ( 0, 'external_value' );
	}
	return true;
}
