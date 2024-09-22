<?php

namespace ExternalData\Presets;

use DOMDocument;
use MediaWiki\MediaWikiServices;

/**
 * Class wrapping various filtering, preprocessing and postprocessing functions for data sources.
 *
 * @author Alexander Mashin
 *
 */
class Base {
	use \EDParsesParams;

	/**
	 * @const array SOURCES Connections to Docker containers for testing purposes with useful multimedia programs.
	 * Use $wgExternalDataSources = array_merge( $wgExternalDataSources, Presets::test ); to make all of them available.
	 */
	public const SOURCES = [];

	/** @const string[] ILLEGAL_TAGS HTML tags not allowed in wikitext. */
	private const ILLEGAL_TAGS = [ 'a', 'section' ];

	/*
	 * Pre- and postprocessing utilities.
	 */

	/**
	 * @param string $ip
	 * @return bool
	 */
	public static function validateIP( string $ip ): bool {
		return filter_var( $ip, FILTER_VALIDATE_IP ) !== false;
	}

	/**
	 * @param string $url
	 * @return bool
	 */
	public static function validateUrl( string $url ): bool {
		return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Validate JSON or YAML.
	 * @param string|array $text
	 * @param array $params
	 * @return bool
	 */
	public static function validateJsonOrYaml( $text, array $params ): bool {
		if ( is_string( $text ) && $params['yaml'] !== false && function_exists( 'yaml_parse' ) ) {
			self::throwWarnings();
			try {
				$yaml_tree = yaml_parse( $text );
			} catch ( \Exception $e ) {
				return false;
			} finally {
				self::stopThrowingWarnings();
			}
			return (bool)$yaml_tree;
		} else {
			return is_array( $text ) || ( is_string( $text ) && json_decode( $text ) );
		}
	}

	/**
	 * Convert YAML to JSON, if yaml parameter is set in the tag and YAML parsing is available.
	 * @param array|string $text
	 * @param array $params
	 * @return string
	 * @throws \EDParserException
	 */
	public static function yamlToJson( $text, array $params ): string {
		if ( is_string( $text ) && $params['yaml'] !== false && function_exists( 'yaml_parse' ) ) {
			// This is likely YAML.
			self::throwWarnings();
			try {
				$yaml_tree = yaml_parse( $text );
			} catch ( \Exception $e ) {
				// Hopefully, this will be prevented by the validator above.
				throw new \EDParserException( 'externaldata-invalid-format', 'YAML', $e->getMessage() );
			} finally {
				self::stopThrowingWarnings();
			}
			if ( $yaml_tree === false ) {
				// Hopefully, this will be prevented by the validator above.
				throw new \EDParserException( 'externaldata-invalid-format', 'YAML' );
			}
			$text = json_encode( $yaml_tree );
		}
		return $text;
	}

	/**
	 * Validate XML.
	 * @param string $xml
	 * @return bool
	 */
	public static function validateXml( string $xml ): bool {
		return (bool)simplexml_load_string( $xml );
	}

	/**
	 * Remove illegal HTML tags:
	 * @param string $html
	 * @return string
	 */
	public static function stripIllegalTags( string $html ): string {
		libxml_use_internal_errors( true );
		$doc = new DOMDocument();
		$doc->loadHTML( $html );
		foreach ( self::ILLEGAL_TAGS as $tag ) {
			$nodes = $doc->getElementsByTagName( $tag );
			while ( $nodes->length > 0 ) {
				if ( $node = $nodes->item( 0 ) ) {
					$fragment = $doc->createDocumentFragment();
					while ( $node->childNodes->length > 0 ) {
						$inner = $node->childNodes->item( 0 );
						if ( $inner ) {
							$fragment->appendChild( $inner );
						}
					}
					$node->parentNode->replaceChild( $fragment, $node );
				}
				$nodes = $doc->getElementsByTagName( $tag );
			}
		}
		return $doc->saveHTML();
	}

	/**
	 * Get filesystem path of $params['filename'].
	 * @param array $params
	 * @return string|null
	 */
	public static function localPath( array $params ): ?string {
		$name = $params['filename'];
		$repo = MediaWikiServices::getInstance()->getRepoGroup();
		$file = $repo->findFile( $name );
		if ( !$file ) {
			return null;
		}
		return $file->getLocalRefPath();
	}

	/**
	 * Return External Data sources, some of which cannot be constants.
	 * @return array[]
	 */
	public static function sources(): array {
		return static::SOURCES;
	}

	/**
	 * Return a specific Data source.
	 * @param string $name External Data source name.
	 * @return array
	 */
	public static function source( string $name ): array {
		return static::sources()[$name];
	}
}
