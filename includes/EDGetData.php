<?php

use MediaWiki\MediaWikiServices;

/**
 * A special page for retrieving selected rows of any wiki page that contains
 * data in CSV format
 *
 * @author Yaron Koren
 */

class EDGetData extends SpecialPage {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'GetData' );
	}

	/** @inheritDoc */
	public function execute( $query ) {
		$this->getOutput()->disable();
		$this->setHeaders();

		$page_name = $query;
		$title = Title::newFromText( $page_name );
		if ( $title === null ) {
			return;
		}

		$user = $this->getUser();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( !$permissionManager->userCan( 'read', $user, $title ) ) {
			return true;
		}

		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			// @phan-suppress-next-line PhanUndeclaredMethod Not necessarily existing in the current version.
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		} else {
			$wikiPage = WikiPage::factory( $title );
		}
		$page_text = ContentHandler::getContentText( $wikiPage->getContent() );
		// Remove <noinclude> sections and <includeonly> tags from text
		$page_text = StringUtils::delimiterReplace( '<noinclude>', '</noinclude>', '', $page_text );
		$page_text = strtr( $page_text, [ '<includeonly>' => '', '</includeonly>' => '' ] );
		$orig_lines = explode( "\n", $page_text );
		// ignore lines that are either blank or start with a semicolon
		$page_lines = [];
		foreach ( $orig_lines as $i => $line ) {
			if ( $line != '' && $line[0] != ';' ) {
				$page_lines[] = $line;
			}
		}
		$headers = self::getValuesFromCSVLine( $page_lines[0] );
		$queried_headers = [];
		$queryStringValues = $this->getRequest()->getValues();
		foreach ( $queryStringValues as $key => $value ) {
			foreach ( $headers as $header_index => $header_value ) {
				$header_value = str_replace( ' ', '_', $header_value );
				if ( $key == $header_value ) {
					$queried_headers[$header_index] = $value;
				}
			}
		}
		// include header in output
		$text = $page_lines[0];
		foreach ( $page_lines as $i => $line ) {
			if ( $i == 0 ) {
				continue;
			}
			$row_values = self::getValuesFromCSVLine( $line );
			$found_match = true;
			foreach ( $queried_headers as $j => $query_value ) {
				$single_value = str_replace( ' ', '_', $row_values[$j] );
				if ( $single_value != $query_value ) {
					$found_match = false;
				}
			}
			if ( $found_match ) {
				if ( $text !== '' ) {
					$text .= "\n";
				}
				$text .= $line;
			}
		}
		print $text;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'pagetools';
	}

	/**
	 * Split $csv_line as a CSV line.
	 *
	 * @param string $csv_line The line to split.
	 *
	 * @return array Split values.
	 */
	private static function getValuesFromCSVLine( $csv_line ) {
		// regular expression copied from http://us.php.net/fgetcsv
		$vals = preg_split( '/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/', $csv_line );
		$vals2 = [];
		foreach ( $vals as $val ) {
			$vals2[] = trim( $val, '"' );
		}
		return $vals2;
	}
}
