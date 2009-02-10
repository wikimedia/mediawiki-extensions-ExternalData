<?php
/**
 * A special page for retrieving selected rows of any wiki page that contains
 * data in CSV format
 */

if (!defined('MEDIAWIKI')) die();

class EDGetData extends SpecialPage {

	/**
	 * Constructor
	 */
	function EDGetData() {
		SpecialPage::SpecialPage( 'GetData' );
		wfLoadExtensionMessages( 'ExternalData' );
	}

	function execute($query) {
		global $wgRequest, $wgOut;
		$wgOut->disable();

		$this->setHeaders();
		$page_name = $query;
		$title = Title::newFromText( $page_name );
		if( is_null( $title ) )
			return;
		$article = new Article( $title );
		$page_text = $article->fetchContent();
		$page_lines = split( "\n", $page_text );
		$headers = EDUtils::getValuesFromCSVLine( $page_lines[0] );
		$queried_headers = array();
		foreach( $wgRequest->getValues() as $key => $value ) {
			foreach( $headers as $header_index => $header_value ) {
				if( $key == $header_value ) {
					$queried_headers[$header_index] = $value;
				}
			}
		}
		$text = '';
		foreach( $page_lines as $i => $line) {
			if ($i == 0) continue;
			$row_values = EDUtils::getValuesFromCSVLine( $line );
			$found_match = true;
			foreach( $queried_headers as $i => $value ) {
				if ( $row_values[$i] != $value ) {
					$found_match = false;
				}
			}
			if( $found_match ) {
				if ($text != '') $text .= "\n";
				$text .= $line;
			}
		}
		print $text;
	}

}
