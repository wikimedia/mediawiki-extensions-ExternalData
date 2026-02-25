<?php

namespace ExternalData\Presets;

use DOMDocument;
use MediaWiki\MediaWikiServices;

/**
 * Class wrapping various filtering, preprocessing and postprocessing functions for data sources.
 *
 * @author Alexander Mashin
 */
class Base {
	use \EDParsesParams;

	/** @const array Boilerplate parameters, common for all dockerised applications. */
	protected const DOCKER = [
		'format' => 'text',
		'options' => [ 'sslVerifyCert' => false, 'headers' => [ 'Content-Type' => 'text/plain' ] ],
		'max tries' => 1,
		'min cache seconds' => 30 * 24 * 60 * 60,
	];

	/** @const string ANY A regular expression for any value, even empty, not containing &. */
	protected const ANY = '/^[^&]*$/';

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
	 * @param string $value
	 * @return bool
	 */
	public static function isInt( string $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_INT );
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	public static function isFloat( string $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_FLOAT );
	}

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

	public static function numbered( array $params ): string {
		static $counters = [];
		$class = $params['source'] ?? '';
		$counters[$class] ??= 0;
		return $class . ++$counters[$class];
	}

	/**
	 * If $html contains <html> tag, return its <body>.
	 * @param string $html
	 * @param array $params
	 * @return string
	 */
	public static function htmlBody( string $html, array $params ): string {
		// HTML is analysed with regular expressions, because it can be malformed.
		if ( !preg_match( '~<html.+</html>~si', $html, $matches ) ) {
			return $html;
		}
		if ( !preg_match( '~<body[^>]*>(?<inner>.*)</body>~si', $html, $matches ) ) {
			return $html;
		}
		return $matches['inner'] ?? '';
	}

	/**
	 * Set SVG size, if not set.
	 *
	 * @param string $svg
	 * @param array $params
	 * @return string
	 */
	public static function sizeSvg( string $svg, array $params ): string {
		if ( ( $params['output'] ?? '' ) === 'html' ) {
			return $svg;
		}
		$dom = new DOMDocument();
		$dom->loadXML( $svg, LIBXML_NOENT );
		$root = $dom->documentElement;
		if ( !$root ) {
			return $svg;
		}
		foreach ( [ 'width', 'height' ] as $attr ) {
			if ( !$root->hasAttribute( $attr ) && ( $params[$attr] ?? 0 ) ) {
				$root->setAttribute( $attr, $params[$attr] );
			}
		}
		if ( !$root->hasAttribute( 'viewport' ) && isset( $params['width'] ) && isset( $params['height'] ) ) {
			$root->setAttribute( 'viewport', "0 0 {$params['width']} {$params['height']}" );
		}
		return $dom->saveHTML();
	}

	/**
	 * Prepend scrips from 'scripts' field.
	 * @param string $input
	 * @param array $params
	 * @return string
	 */
	public static function prependScripts( string $input, array $params ): string {
		return implode( "\n", array_map( static function ( string $src ): string {
			return '<script type="text/javascript" src="' . $src . '"></script>';
		}, is_array( $params['scripts'] ) ? $params['scripts'] : [ $params['scripts'] ] ) ) . $input;
	}

	/**
	 * Strips log messages before and after SVG that could not be stripped otherwise.
	 * @param string $input
	 * @return string
	 */
	public static function onlySvg( string $input ): string {
		return preg_match( '%<svg.+</svg>%i', $input, $matches ) ? $matches[0] : $input;
	}

	/**
	 * If $htm contains <html> tag, wrap it with <iframe>.
	 * @param string $html
	 * @param array $params
	 * @return string
	 */
	public static function wrapHtml( string $html, array $params ): string {
		if ( !preg_match( '~<html\s.+</html>~si', $html, $matches ) ) {
			return $html;
		}
		$html = $matches[0];
		if ( isset( $params['original scripts'] ) && isset( $params['scripts'] ) ) {
			$original = is_array( $params['original scripts'] )
				? $params['original scripts']
				: [ $params['original scripts'] ];
			$local = is_array( $params['scripts'] ) ? $params['scripts'] : [ $params['scripts'] ];
			foreach ( $original as $i => $script ) {
				$html = preg_replace( $script, $local[$i], $html );
			}
		}
		return '<iframe width="' . $params['width'] . '" height="' . $params['height'] . '" frameborder="0" srcdoc="'
			. strtr( $html, [ '&' => 'amp;', '"' => '&quot;' ] )
			. '"></iframe>';
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
	 * @param null|bool|array $mode Additional information to configure presets.
	 * @return array[]
	 */
	public static function sources( $mode = null ): array {
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

	/**
	 * Return all preset groups.
	 * @return string[]
	 */
	public static function presetGroups(): array {
		global $wgExternalDataPresetGroups;
		return $wgExternalDataPresetGroups;
	}
}
