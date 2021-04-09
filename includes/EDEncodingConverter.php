<?php
/**
 * A service class to be used by classes that need to extract encoding information
 * from text and its context and convert texts to UTF-8.
 *
 * @author Alexander Mashin
 *
 */
class EDEncodingConverter {
	/**
	 * Detect encoding based on tags in the $text,
	 *
	 * @param string $text Text to analyse and convert.
	 * @param string|null $encoding_override Encoding from context.
	 *
	 * @return string The converted text.
	 */
	public static function toUTF8( $text, $encoding_override = null ) {
		$encoding = $encoding_override ? $encoding_override : null;

		// Try to find encoding in the XML/HTML.
		$encoding_regexes = [
			// charset must be in the capture #3.
			'/<\?xml([^>]+)encoding\s*=\s*(["\']?)([^"\'>]+)\2[^>]*\?>/i' => '<?xml$1encoding="UTF-8"?>',
			'%<meta([^>]+)(charset)\s*=\s*([^"\'>]+)([^>]*)/?>%i' => '<meta$1charset=UTF-8$4>',
			'%<meta(\s+)charset\s*=\s*(["\']?)([^"\'>]+)\2([^>]*)/?>%i'
				=> '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'
		];
		foreach ( $encoding_regexes as $pattern => $replacement ) {
			if ( preg_match( $pattern, $text, $matches ) ) {
				// Pretend it's already UTF-8.
				$text = preg_replace( $pattern, $replacement, $text, 1 );
				if ( !$encoding ) {
					$encoding = $matches[3];
				}
				break;
			}
		}

		// Try mb_detect_encoding.
		if ( !$encoding ) {
			global $edgTryEncodings;
			$encoding = mb_detect_encoding( $text, $edgTryEncodings, true ); // -- strict.
		}

		// Convert $text:
		// Is it UTF-8 or ISO-8859-1?
		return $encoding && strtoupper( $encoding ) !== 'UTF-8'
			 ? mb_convert_encoding( $text, 'UTF-8', $encoding )
			 : $text;
	}

	/**
	 * Set encoding based on HTTP headers.
	 *
	 * @param array $headers HTTP headers.
	 *
	 * @return string|null Encoding.
	 */
	public static function fromHeaders( array $headers ) {
		if ( $headers && isset( $headers['content-type'] ) ) {
			$header = is_array( $headers['content-type'] )
					? implode( ',', $headers['content-type'] )
					: $headers['content-type'];
			if ( preg_match( '/charset\s*=\s*(?<charset>[^\s;]+)/i', $header, $matches ) ) {
				wfDebug( 'In ' . __METHOD__ . '. encoding from headers = ' . var_export( $header, true ) );
				return $matches['charset'];
			}
		}
		return null;
	}
}
