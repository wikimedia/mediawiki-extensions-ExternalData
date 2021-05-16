<?php
/**
 * Class for handling the parser functions for External Data.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 *
 */
class EDParserFunctions {
	use EDParsesParams;	// Needs paramToArray().

	/** @var array $values Values saved statically to be available form elsewhere on the page. */
	private static $values = [];
	/** @var string|null Current page name. */
	private static $current_page = null;

	/**
	 * Save filtered and mapped results a query to an external source to a static attribute.
	 *
	 * @param array $values Value to save.
	 */
	private static function saveValues( array $values ) {
		foreach ( $values as $key => $value ) {
			self::$values[$key] = $value;
		}
	}

	/**
	 * Unset self::$values if the current page changed during this script run.
	 * Looks like it is relevant for maintenance scripts.
	 *
	 * @param string $page_name Page name.
	 */
	private static function clearValuesIfNecessary( $page_name ) {
		// If we're handling multiple pages, reset self::$values
		// when we move from one page to another.
		if ( self::$current_page !== $page_name ) {
			self::$values = [];
			self::$current_page = $page_name;
		}
	}

	/**
	 * Wraps error messages in a span with the "error" class, for better
	 * display, and so that it can be handled correctly by #iferror and
	 * possibly others.
	 *
	 * @param array|string $messages An array of error messages.
	 *
	 * @return string Wrapped error message.
	 */
	private static function formatErrorMessages( $messages ) {
		if ( !is_array( $messages ) ) {
			$messages = [ $messages ];
		}
		return '<span class="error">' . implode( "<br />", $messages ) . '</span>';
	}

	/**
	 * Universal interface to EDConnector* classes.
	 * Also includes all the boilerplate code that processes parameters,
	 * saves external values, etc.
	 *
	 * @param Parser &$parser Parser object.
	 * @param string $name Parser function name.
	 * @param array $args Parser function parameters ($parser not included).
	 *
	 * @return string|null Return null on success, an error message otherwise.
	 */
	private static function fetch( Parser &$parser, $name, array $args ) {
		// Unset self::$values if the current page changed during this script run.
		// Looks like it is relevant for maintenance scripts.
		self::clearValuesIfNecessary( $parser->getTitle()->getText() );

		$connector = EDConnectorBase::getConnector( $name, self::parseParams( $args ) );

		if ( !$connector->errors() ) {
			// The parameters seem to be right; try to actually get the external data.
			if ( $connector->run() ) {
				// The external data have been fetched without run-time errors.
				// Results are valid and can be saved in self::$values.
				self::saveValues( $connector->result() );
				// These functions are humble in their success.
				return null;
			}
		}

		// There have been errors.
		return $connector->suppressError() ? null : self::formatErrorMessages( $connector->errors() );
	}

	/**
	 * Implementation of the {{#get_web_data:}} parser function.
	 *
	 * @param Parser &$parser Parser object.
	 * @param string $params,... Parameters to parser function.
	 *
	 * @return string|null An error message or null on success.
	 */
	public static function getWebData( Parser &$parser, ...$params ) {
		return self::fetch( $parser, 'get_web_data', $params );
	}

	/**
	 * Implementation of the {{#get_file_data:}} parser function.
	 *
	 * @param Parser &$parser Parser object.
	 * @param string $params,... Parameters to parser function.
	 *
	 * @return string|null An error message or null on success.
	 */
	public static function getFileData( Parser &$parser, ...$params ) {
		return self::fetch( $parser, 'get_file_data', $params );
	}

	/**
	 * Implementation of the {{#get_soap_data:}} parser function.
	 *
	 * @param Parser &$parser Parser object.
	 * @param string $params,... Parameters to parser function.
	 *
	 * @return string|null An error message or null on success.
	 */
	public static function getSoapData( Parser &$parser, ...$params ) {
		return self::fetch( $parser, 'get_soap_data', $params );
	}

	/**
	 * Implementation of the {{#get_ldap_data:}} parser function.
	 *
	 * @param Parser &$parser Parser object.
	 * @param string $params,... Parameters to parser function.
	 *
	 * @return string|null An error message or null on success.
	 */
	public static function getLdapData( Parser &$parser, ...$params ) {
		return self::fetch( $parser, 'get_ldap_data', $params );
	}

	/**
	 * Implementation of the {{#get_db_data:}} parser function.
	 *
	 * @param Parser &$parser Parser object.
	 * @param string $params,... Parameters to parser function.
	 *
	 * @return string|null An error message or null on success.
	 */
	public static function getDBData( Parser &$parser, ...$params ) {
		return self::fetch( $parser, 'get_db_data', $params );
	}

	/**
	 * Get the specified index of the array for the specified local
	 * variable retrieved by one of the #get... parser functions.
	 * @param string $var
	 * @param int $i
	 * @return string
	 */
	private static function getIndexedValue( $var, $i ) {
		if ( array_key_exists( $var, self::$values ) && array_key_exists( $i, self::$values[$var] ) ) {
			return self::$values[$var][$i];
		} else {
			return '';
		}
	}

	/**
	 * Render the #external_value parser function.
	 * @param Parser &$parser
	 * @param string $local_var
	 * @return string|null
	 */
	public static function doExternalValue( Parser &$parser, $local_var = '' ) {
		global $edgExternalValueVerbose;
		if ( !array_key_exists( $local_var, self::$values ) ) {
			return $edgExternalValueVerbose
				? self::formatErrorMessages(
					wfMessage( 'externaldata-no-local-variable', $local_var )->inContentLanguage()->text()
				)
				: '';
		} elseif ( is_array( self::$values[$local_var] ) ) {
			return isset( self::$values[$local_var][0] ) ? self::$values[$local_var][0] : null;
		} else {
			return self::$values[$local_var];
		}
	}

	/**
	 * Render the #for_external_table parser function.
	 * @param Parser &$parser
	 * @param string $expression
	 * @return string
	 */
	public static function doForExternalTable( Parser &$parser, $expression = '' ) {
		// Get the variables used in this expression, get the number
		// of values for each, and loop through.
		$matches = [];
		preg_match_all( '/{{{([^}]*)}}}/', $expression, $matches );
		$variables = $matches[1];
		$num_loops = 0;

		$commands = [ 'urlencode', 'htmlencode' ];
		// Used for a regexp check.
		$commandsStr = implode( '|', $commands );

		foreach ( $variables as $variable ) {
			// If it ends with one of the pre-defined "commands",
			// ignore the command to get the actual variable name.
			foreach ( $commands as $command ) {
				$variable = str_replace( $command, '', $variable );
			}
			$variable = str_replace( '.urlencode', '', $variable );
			if ( array_key_exists( $variable, self::$values ) ) {
				$num_loops = max( $num_loops, count( self::$values[$variable] ) );
			}
		}

		$text = '';
		for ( $i = 0; $i < $num_loops; $i++ ) {
			$cur_expression = $expression;
			foreach ( $variables as $variable ) {
				// If it ends with one of the pre-defined "commands",
				// ignore the command to get the actual variable name.
				$matches = [];
				preg_match( "/([^.]*)\.?($commandsStr)?$/", $variable, $matches );

				$real_var = $matches[1];
				if ( count( $matches ) == 3 ) {
					$command = $matches[2];
				} else {
					$command = null;
				}

				switch ( $command ) {
					case 'htmlencode':
						$value = htmlentities(
							self::getIndexedValue( $real_var, $i ),
							ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE,
							null,
							false
						);
						break;
					case 'urlencode':
						$value = urlencode( self::getIndexedValue( $real_var, $i ) );
						break;
					default:
						$value = self::getIndexedValue( $real_var, $i );
				}

				$cur_expression = str_replace( '{{{' . $variable . '}}}', $value, $cur_expression );
			}
			$text .= $cur_expression;
		}
		return $text;
	}

	/**
	 * Render the #display_external_table parser function.
	 *
	 * @author Dan Bolser
	 * @param Parser &$parser
	 * @return array|string
	 */
	public static function doDisplayExternalTable( Parser &$parser ) {
		$params = func_get_args();
		array_shift( $params ); // we already know the $parser ...
		$args = self::parseParams( $params ); // parse params into name-value pairs

		if ( array_key_exists( 'template', $args ) ) {
			$template = $args['template'];
		} else {
			return self::formatErrorMessages( wfMessage( 'externaldata-no-template' )->inContentLanguage()->text() );
		}

		if ( array_key_exists( 'data', $args ) ) {
			// parse the 'data' arg into mappings
			$mappings = self::paramToArray( $args['data'], false, false );
		} else {
			// ...or just use the previously-obtained values.
			$mappings = [];
			foreach ( self::$values as $local_variable => $values ) {
				$mappings[$local_variable] = $local_variable;
			}
		}

		// The string placed in the wikitext between template calls -
		// default is a newline.
		if ( array_key_exists( 'delimiter', $args ) ) {
			$delimiter = str_replace( '\n', "\n", $args['delimiter'] );
		} else {
			$delimiter = "\n";
		}

		$num_loops = 0; // May differ when multiple '#get_'s are used in one page
		foreach ( $mappings as $template_param => $local_variable ) {
			if ( !array_key_exists( $local_variable, self::$values ) ) {
				// Don't throw an error message - the source may just
				// not publish this variable.
				continue;
			}
			$num_loops = max( $num_loops, count( self::$values[$local_variable] ) );
		}

		if ( array_key_exists( 'intro template', $args ) && $num_loops > 0 ) {
			$text = '{{' . $args['intro template'] . '}}';
		} else {
			$text = "";
		}
		for ( $i = 0; $i < $num_loops; $i++ ) {
			if ( $i > 0 ) {
				$text .= $delimiter;
			}
			$text .= '{{' . $template;
			foreach ( $mappings as $template_param => $local_variable ) {
				$value = self::getIndexedValue( $local_variable, $i );
				$text .= "|$template_param=$value";
			}
			$text .= "}}";
		}
		if ( array_key_exists( 'outro template', $args ) && $num_loops > 0 ) {
			$text .= '{{' . $args['outro template'] . '}}';
		}

		// This actually 'calls' the template that we built above
		return [ $text, 'noparse' => false ];
	}

	/**
	 * Based on Semantic Internal Objects'
	 * SIOSubobjectHandler::doSetInternal().
	 * @param Parser $parser
	 * @param array $params
	 * @return string|null
	 */
	public static function callSubobject( Parser $parser, array $params ) {
		// This is a hack, since SMW's SMWSubobject::render() call is
		// not meant to be called outside of SMW. However, this seemed
		// like the better solution than copying over all of that
		// method's code. Ideally, a true public function can be
		// added to SMW, that handles a subobject creation, that this
		// code can then call.

		$subobjectArgs = [ &$parser ];
		// Blank first argument, so that subobject ID will be
		// an automatically-generated random number.
		$subobjectArgs[1] = '';
		// "main" property, pointing back to the page.
		$mainPageName = $parser->getTitle()->getText();
		$mainPageNamespace = $parser->getTitle()->getNsText();
		if ( $mainPageNamespace != '' ) {
			$mainPageName = $mainPageNamespace . ':' . $mainPageName;
		}
		$subobjectArgs[2] = $params[0] . '=' . $mainPageName;

		foreach ( $params as $i => $value ) {
			if ( $i === 0 ) {
				continue;
			}
			$subobjectArgs[] = $value;
		}

		// SMW 1.9+
		$instance = \SMW\ParserFunctionFactory::newFromParser( $parser )->getSubobjectParser();
		return $instance->parse( new SMW\ParserParameterFormatter( $subobjectArgs ) );
	}

	/**
	 * Render the #store_external_table parser function.
	 * @param Parser &$parser
	 * @return string|null
	 */
	public static function doStoreExternalTable( Parser &$parser ) {
		// Quick exit if Semantic MediaWiki is not installed.
		if ( !class_exists( '\SMW\ParserFunctionFactory' ) ) {
			return self::formatErrorMessages( wfMessage( 'externaldata-smw-needed' )->inContentLanguage()->text() );
		}

		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		// Get the variables used in this expression, get the number
		// of values for each, and loop through.
		$expression = implode( '|', $params );
		$matches = [];
		preg_match_all( '/{{{([^}]*)}}}/', $expression, $matches );
		$variables = $matches[1];
		$num_loops = 0;
		foreach ( $variables as $variable ) {
			// ignore the presence of '.urlencode' - it's a command,
			// not part of the actual variable name
			$variable = str_replace( '.urlencode', '', $variable );
			if ( array_key_exists( $variable, self::$values ) ) {
				$num_loops = max( $num_loops, count( self::$values[$variable] ) );
			}
		}
		for ( $i = 0; $i < $num_loops; $i++ ) {
			// re-get $params
			$params = func_get_args();
			array_shift( $params );
			foreach ( $params as $j => $param ) {
				foreach ( $variables as $variable ) {
					// If variable name ends with a ".urlencode",
					// that's a command - URL-encode the value of
					// the actual variable.
					if ( strrpos( $variable, '.urlencode' ) === strlen( $variable ) - strlen( '.urlencode' ) ) {
						$real_var = str_replace( '.urlencode', '', $variable );
						$value = urlencode( self::getIndexedValue( $real_var, $i ) );
					} else {
						$value = self::getIndexedValue( $variable, $i );
					}
					$params[$j] = str_replace( '{{{' . $variable . '}}}', $value, $params[$j] );
				}
			}

			self::callSubobject( $parser, $params );
		}
		return null;
	}

	/**
	 * Render the #clear_external_data parser function.
	 *
	 * @param Parser &$parser
	 */
	public static function doClearExternalData( Parser &$parser ) {
		self::$values = [];
	}

	/**
	 * This function is needed by the PageForms extension.
	 *
	 * @return array
	 */
	public static function getAllValues() {
		return self::$values;
	}
}
