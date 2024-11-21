<?php
/**
 * Hook functions for the External Data extension.
 *
 * @file
 * @ingroup ExternalData
 * @author Yaron Koren
 */
namespace ExternalData;

use Config;
use EDConnectorBase;
use EDParserFunctions;
use Parser;

class Hooks implements
	\MediaWiki\Hook\ParserFirstCallInitHook,
	\MediaWiki\Hook\SoftwareInfoHook
{
	private Config $config;

	public function __construct(
		Config $config
	) {
		$this->config = $config;
	}

	/**
	 * @param Parser $parser
	 * @return bool
	 */
	public function onParserFirstCallInit( $parser ) {
		// Add data retrieval parser functions as defined by $wgExternalDataConnectors.
		if ( $this->config->get( 'ExternalDataAllowGetters' ) ) {
			foreach ( EDConnectorBase::getConnectors() as $parser_function => $lua_function ) {
				$parser->setFunctionHook(
					$parser_function,
					static function ( Parser $parser, ...$params ) use ( $parser_function ) {
						$title = method_exists( Parser::class, 'getPage' ) ? $parser->getPage() : $parser->getTitle();
						return EDParserFunctions::fetch( $title, $parser_function, $params );
					}
				);
			}
			$parser->setFunctionHook( 'clear_external_data', [ EDParserFunctions::class, 'doClearExternalData' ] );
		}

		// Data display functions.
		$parser->setFunctionHook( 'external_value', [ EDParserFunctions::class, 'doExternalValue' ] );
		$parser->setFunctionHook(
			'for_external_table',
			[ EDParserFunctions::class, 'doForExternalTable' ],
			Parser::SFH_OBJECT_ARGS
		);
		$parser->setFunctionHook( 'display_external_table', [ EDParserFunctions::class, 'doDisplayExternalTable' ] );
		if ( class_exists( 'CargoDisplayFormat' ) ) {
			$parser->setFunctionHook( 'format_external_table', [ EDParserFunctions::class, 'doFormatExternalTable' ] );
		}

		// Register tags for backward compatibility with other extensions.
		foreach ( EDConnectorBase::emulatedTags() as $tag => $function ) {
			$parser->setHook( $tag, $function );
			// @todo: add code for Parsoid.
		}

		return true; // always return true, in order not to stop MW's hook processing!
	}

	/**
	 * Register used software for Special:Version.
	 *
	 * @param array &$software
	 */
	public function onSoftwareInfo( &$software ) {
		EDConnectorBase::addSoftware( $software );
	}

	/**
	 * Form extension configuration from different sources.
	 */
	public static function onRegistration() {
		// Load configuration settings.
		EDConnectorBase::loadConfig();
	}
}
