<?php
use SMW\ParserFunctionFactory;
use SMW\ParserParameterProcessor;

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
	private static $currentPage;
	/** @const array COMMANDS Possible dot-separated 'commands'. See ::urlencode() and ::htmlencode() */
	private const COMMANDS = [ 'urlencode', 'htmlencode' ];

	/**
	 * Save filtered and mapped results a query to an external source to a static attribute.
	 *
	 * @param array $values Value to save.
	 * @return array The saved values.
	 */
	private static function saveValues( array $values ) {
		foreach ( $values as $key => $value ) {
			self::$values[$key] = $value;
		}
		return self::$values;
	}

	/**
	 * Wraps error messages in a span with the "error" class, for better
	 * display, and so that it can be handled correctly by #iferror and
	 * possibly others.
	 *
	 * @param array $errors An array of error messages.
	 *
	 * @return string Wrapped error message.
	 */
	public static function formatErrorMessages( array $errors ) {
		$messages = array_map( static function ( $error ) {
			if ( is_array( $error ) && $error['code'] ) {
				return wfMessage( $error['code'], $error['params'] )->inContentLanguage()->text();
			} else {
				return $error;
			}
		}, $errors );
		return '<span class="error">' . implode( "<br />", $messages ) . '</span>';
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
		if ( self::$currentPage !== $page_name ) {
			self::$values = [];
			self::$currentPage = $page_name;
		}
	}

	/**
	 * Data retrieval functions.
	 */

	/**
	 * Actually get the external data.
	 *
	 * @param ?Title $title Page title.
	 * @param string|null $name Parser function name.
	 * @param array $args Parser function parameters ($parser not included).
	 *
	 * @return string|array|null Return an array of values on success, an error message otherwise.
	 */
	private static function get( $title, $name, array $args ) {
		// Unset self::$values if the current page changed during this script run.
		// Looks like it is relevant for maintenance scripts.
		if ( $title ) {
			self::clearValuesIfNecessary( $title->getText() );
		}

		$connector = EDConnectorBase::getConnector( $name, self::parseParams( $args ), $title );

		if ( !$connector->errors() ) {
			// The parameters seem to be right; try to actually get the external data.
			if ( $connector->run() ) {
				// The external data have been fetched without run-time errors.
				// Results are valid and can be saved in self::$values.
				return $connector->result();
			}
		}

		// There have been errors.
		return $connector->suppressError() ? null : self::formatErrorMessages( $connector->errors() );
	}

	/**
	 * Universal interface to EDConnector* classes.
	 * Also includes all the boilerplate code that processes parameters,
	 * saves external values, etc.
	 *
	 * @param ?Title $title Parser object.
	 * @param string|null $name Parser function name.
	 * @param array $args Parser function parameters ($parser not included).
	 *
	 * @return string|null Return null on success, an error message otherwise.
	 */
	public static function fetch( $title, $name, array $args ) {
		$result = self::get( $title, $name, $args );
		if ( is_array( $result ) ) {
			// An array of values, not an error message.
			self::saveValues( $result );
			// These functions are humble in their success.
			return null;
		}
		// There have been errors.
		return $result;
	}

	/**
	 * Data display functions.
	 */

	/**
	 * Implementation of .urlencode:
	 * @param string $str
	 * @return string
	 */
	private static function urlencode( $str ) {
		return urlencode( $str );
	}

	/**
	 * Implementation of .htmlencode:
	 * @param string $str
	 * @return string
	 */
	private static function htmlencode( $str ) {
		return htmlentities( $str, ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8', false );
	}

	/**
	 * Get the specified index of the array for the specified local
	 * variable retrieved by one of the #get... parser functions.
	 * Apply .suffix function (urlencode, htmlencode, etc), if any.
	 * @param string $var
	 * @param int $i
	 * @param ?string $default
	 * @return string
	 */
	private static function getIndexedValue( $var, $i, $default ) {
		$postprocess = null;
		foreach ( self::COMMANDS as $command ) {
			$command_length = -strlen( $command ) - 1;
			if ( substr( $var, $command_length ) === ".$command" ) {
				$postprocess = $command;
				$var = substr( $var, 0, $command_length );
				break;
			}
		}
		if ( array_key_exists( $var, self::getAllValues() ) && array_key_exists( $i, self::getAllValues()[$var] ) ) {
			$value = self::getAllValues()[$var][$i];
		} else {
			if ( $default !== null ) {
				$value = $default;
			} else {
				return self::setting( 'Verbose' )
					? self::formatErrorMessages(
						[ [ 'code' => 'externaldata-no-local-variable', 'params' => [ $var ] ] ]
					)
					: '';
			}
		}
		if ( $postprocess ) {
			// $value = call_user_func( [ __CLASS__, $postprocess ], $value );
			$value = self::$postprocess( $value );
		}
		return $value;
	}

	/**
	 * Emulate {{#get_external_data:}} call.
	 * @param array &$args
	 * @param ?Title $title
	 * @return null|string
	 */
	private static function emulateGetExternalData( array &$args, $title ) {
		if ( isset( $args['source'] ) ) {
			// If {{#for_external_table:}} is called in standalone mode, there is no shared context,
			// therefore, emulate {{#clear_external_data:}}.
			self::actuallyClearExternalData( [] );
			// Emulate {{#get_external_data:}}.
			$result = self::fetch( $title, null, $args );
			if ( $result !== null ) {
				// There have been errors while fetching data.
				return $result;
			}
			unset( $args['data'] ); // mapping is not idempotent, and it has already been done. We need no second one.
		}
		return null;
	}

	/**
	 * Render the #external_value parser function.
	 * @param Parser $parser
	 * @param string|null $variable Local variable name
	 * @param string ...$params Other parameters for fetching data
	 * @return string
	 */
	public static function doExternalValue( Parser $parser, $variable, ...$params ) {
		$args = self::parseParams( $params );
		$default = null;
		if ( isset( $args[0] ) ) {
			$default = $args[0];
			array_shift( $args );
		}
		if ( !isset( $args['data'] ) ) {
			$args['data'] = "$variable=$variable";
		}
		$fetched = self::emulateGetExternalData( $args, $parser->getTitle() );
		if ( $fetched ) {
			// There is an error.
			return $fetched;
		}
		return self::getIndexedValue( $variable, 0, $default );
	}

	/**
	 * Get {{{…}}} macros from the loop body.
	 * @param string $body
	 * @return array
	 */
	private static function getMacros( $body ): array {
		$macros = [];
		preg_match_all( '/{{{(?<var>[^}|]*)(?:\|(?<default>[^}]*))?}}}/', $body, $macros, PREG_SET_ORDER );
		return $macros;
	}

	/**
	 * Count number of rows in #display/#format statements.
	 *
	 * @param array $mappings
	 * @return int
	 */
	private static function numLoops( array $mappings ) {
		$num_loops = 0; // May differ when multiple '#get_'s are used in one page
		foreach ( $mappings as $local_variable ) {
			// Ignore .urlencode, etc.
			foreach ( self::COMMANDS as $command ) {
				$local_variable = str_replace( ".$command", '', $local_variable );
			}
			if ( array_key_exists( $local_variable, self::$values ) ) {
				$num_loops = max( $num_loops, count( self::$values[$local_variable] ) );
			}
		}
		return $num_loops;
	}

	/**
	 * Actually render the #for_external_table parser function. The "template" is passed as the first parameter.
	 * @param string $body
	 * @return string
	 */
	private static function actuallyForExternalTableFirst( $body ) {
		$macros = self::getMacros( $body );

		$num_loops = self::numLoops( array_map( static function ( $set ) {
			return $set['var'];
		}, $macros ) );

		$loops = [];
		for ( $loop = 0; $loop < $num_loops; $loop++ ) {
			$current = $body;

			foreach ( $macros as $macro ) {
				$value = self::getIndexedValue(
					$macro['var'],
					$loop,
					isset( $macro['default'] ) ? $macro['default'] : ''
				);
				$current = str_replace( $macro[0], $value, $current );
			}
			$loops[] = $current;
		}
		return implode( '', $loops );
	}

	/**
	 * Actually render the #for_external_table parser function. The "template" is passed as the second parameter.
	 * @param Parser $parser
	 * @param PPNode_Hash_Tree $tree
	 * @return string
	 */
	private static function actuallyForExternalTableSecond( Parser $parser, PPNode_Hash_Tree $tree ) {
		$variables = array_keys( self::getAllValues() );
		$num_loops = self::numLoops( $variables );
		$loops = [];
		for ( $loop = 0; $loop < $num_loops; $loop++ ) {
			$row = array_combine( $variables, array_map( static function ( $var ) use ( $loop ){
				return self::getIndexedValue( $var, $loop, null );
			}, $variables ) );
			$row_as_frame = $parser->getPreprocessor()->newCustomFrame( $row );
			$loops[] = $row_as_frame->expand( $tree ); // substitution of {{{var}}} happens here.
		}
		return implode( '', $loops );
	}

	/**
	 * Render the #for_external_table parser function.
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args
	 * @return string
	 */
	public static function doForExternalTable( Parser $parser, PPFrame $frame, array $args ) {
		if ( !$args[0] ) {
			// {{#for_external_table:|loop body}}
			if ( !isset( $args[1] ) ) {
				return self::formatErrorMessages( [ [ 'code' => 'externaldata-no-loop-body' ] ] );
			}
			$second = true;
			array_shift( $args ); // drop the empty first parameter.
		} else {
			// {{#for_external_table:loop body}}
			$second = false;
		}
		$body = array_shift( $args );

		if ( count( $args ) > 0 ) {
			// There are other parameters, presumably, for data retrieval. Standalone mode.
			$data_params = self::parseParams( array_map( static function ( PPNode_Hash_Tree $node ) use ( $frame ) {
				return trim( $frame->expand( $node ) );
			}, $args ) );

			// If there is no 'data', build one from {{{variables}}}.
			if ( !isset( $data_params['data'] ) ) {
				$variables = array_map( static function ( array $captures ) {
					return $captures['var'] . '=' . $captures['var'];
				}, self::getMacros( $frame->expand( $body ) ) );
				$data_params['data'] = implode( ',', $variables );
			}

			$fetched = self::emulateGetExternalData( $data_params, $parser->getTitle() );
			if ( $fetched ) {
				// There is an error.
				return $fetched;
			}
		}

		return $frame->expand( $second
			? self::actuallyForExternalTableSecond( $parser, $body )
			: self::actuallyForExternalTableFirst( $body )
		);
	}

	/**
	 * Get data mappings from data= parameter or self::$values.
	 *
	 * @param array $args Arguments to the function.
	 *
	 * @return array Mappings.
	 */
	private static function getMappings( array $args ): array {
		if ( array_key_exists( 'data', $args ) ) {
			// parse the 'data' arg into mappings
			return self::paramToArray( $args['data'], false, false );
		} else {
			// ...or just use the previously-obtained values.
			$mappings = [];
			foreach ( self::$values as $local_variable => $values ) {
				$mappings[$local_variable] = $local_variable;
			}
			return $mappings;
		}
	}

	/**
	 * Actually display external table.
	 *
	 * @param array $args
	 * @param ?Title $title
	 * @return array
	 */
	private static function actuallyDisplayExternalTable( array $args, $title ): array {
		if ( array_key_exists( 'template', $args ) ) {
			$template = $args['template'];
		} else {
			// Will be converted to error message in EDParserFunctions::doDisplayExternalTable().
			return [ 'error' => 'externaldata-no-template' ];
		}

		$fetched = self::emulateGetExternalData( $args, $title );
		if ( $fetched ) {
			// There is an error.
			return [ 'error' => $fetched ];
		}

		$mappings = self::getMappings( $args );
		// We want template parameters order to be predictable, so that the unit test results are stable.
		ksort( $mappings );

		// The string placed in the wikitext between template calls -
		// default is a newline.
		if ( array_key_exists( 'delimiter', $args ) ) {
			$delimiter = str_replace( '\n', "\n", $args['delimiter'] );
		} else {
			$delimiter = "\n";
		}

		$num_loops = self::numLoops( $mappings );

		$text = '';

		// intro template.
		if ( array_key_exists( 'intro template', $args ) && $num_loops > 0 ) {
			$text .= '{{' . $args['intro template'] . "}}\n";
		}

		// Loop body.
		$variables = array_keys( $mappings );
		$values = array_values( $mappings );
		$loops = [];
		for ( $loop = 0; $loop < $num_loops; $loop++ ) {
			$loops[] = '{{' . $template . '|' . implode( '|', array_map( static function ( $param, $var ) use( $loop ) {
					$value = self::getIndexedValue( $var, $loop, '' );
					return "$param=$value";
			}, $variables, $values ) ) . '}}';
		}
		$text .= implode( $delimiter, $loops );

		// outro template.
		if ( array_key_exists( 'outro template', $args ) && $num_loops > 0 ) {
			$text .= "\n{{" . $args['outro template'] . '}}';
		}

		// This actually 'calls' the template that we built above
		return [ $text, 'noparse' => false ];
	}

	/**
	 * Render the #display_external_table parser function.
	 *
	 * @author Dan Bolser
	 * @param Parser $parser
	 * @return array
	 */
	public static function doDisplayExternalTable( Parser $parser ): array {
		$params = func_get_args();
		array_shift( $params ); // we already know the $parser ...
		$args = self::parseParams( $params ); // parse params into name-value pairs
		$result = self::actuallyDisplayExternalTable( $args, $parser->getTitle() );
		if ( isset( $result['error'] ) ) {
			// Message is created here rather than in EDParserFunctions::actuallyDisplayExternalTable()
			//      to clear that method from MediaWiki installation-dependent code and make it testable.
			return [
				self::formatErrorMessages( [ wfMessage( $result['error'] )->inContentLanguage()->text() ] ),
				'noparse' => false
			];
		}
		return $result;
	}

	/**
	 * Render the #format_external_table parser function.
	 *
	 * @author Alexander Mashin
	 * @param Parser $parser
	 * @return array
	 */
	public static function doFormatExternalTable( Parser $parser ): array {
		$params = func_get_args();
		array_shift( $params ); // we already know the $parser ...
		$args = self::parseParams( $params ); // parse params into name-value pairs

		$fetched = self::emulateGetExternalData( $args, $parser->getTitle() );
		if ( $fetched ) {
			// There is an error.
			return [ 'error' => $fetched ];
		}

		$mappings = self::getMappings( $args );
		$num_rows = self::numLoops( $mappings );
		$values = [];
		for ( $row = 0; $row < $num_rows; $row++ ) {
			$values[$row] = [];
			foreach ( $mappings as $cargo => $local ) {
				$values[$row][$cargo] = self::getIndexedValue( $local, $row, '' );
			}
		}

		// 'display format' => 'format'.
		if ( isset( $args['display format'] ) ) {
			$args['format'] = $args['display format'];
		}

		// @phan-suppress-next-line PhanUndeclaredClassMethod Cargo is not necessarily installed.
		return CargoDisplayFormat::formatArray( $parser, $values, $mappings, $args );
	}

	/**
	 * Based on Semantic Internal Objects'
	 * SIOSubobjectHandler::doSetInternal().
	 * @param Parser $parser
	 * @param string $back_property
	 * @param array $params
	 * @return string|null
	 */
	private static function callSubobject( Parser $parser, $back_property, array $params ) {
		// This is a hack, since SMW's SMWSubobject::render() call is
		// not meant to be called outside SMW. However, this seemed
		// like the better solution than copying over all of that
		// method's code. Ideally, a true public function can be
		// added to SMW, that handles a subobject creation, that this
		// code can then call.

		$title = $parser->getTitle();
		if ( $title ) {
			$subobject_args = [ $parser ];
			// Blank first argument, so that subobject ID will be
			// an automatically-generated random number.
			$subobject_args[1] = '';
			// "main" property, pointing back to the page.

			$main_page_name = $title->getText();
			$main_page_namespace = $title->getNsText();
			if ( $main_page_namespace !== '' ) {
				$main_page_name = $main_page_namespace . ':' . $main_page_name;
			}
			$subobject_args[2] = $back_property . '=' . $main_page_name;

			foreach ( $params as $property => $value ) {
				$subobject_args[] = "$property=$value";
			}

			// SMW 1.9+
			// @phan-suppress-next-line PhanUndeclaredClassMethod SMW is optional.
			$instance = ParserFunctionFactory::newFromParser( $parser )->newSubobjectParserFunction( $parser );
			// @phan-suppress-next-line PhanUndeclaredClassMethod SMW is optional.
			return $instance->parse( new ParserParameterProcessor( $subobject_args ) );
		}
	}

	/**
	 * Render the #store_external_table parser function.
	 * @param Parser $parser
	 * @param string ...$params
	 * @return string|null
	 */
	public static function doStoreExternalTable( Parser $parser, ...$params ) {
		$pattern = '/{{{(?<var>[^|}]+)(\|(?<default>[^}]+))?}}}/';
		$args = self::parseParams( $params );
		$back_property = '';
		if ( isset( $args[0] ) ) {
			$back_property = $args[0];
			unset( $args[0] );
		}

		$templates = [];
		$variables = [];
		$data_params = [];
		foreach ( $args as $key => $value ) {
			if ( preg_match( $pattern, $value, $matches ) ) {
				$templates[$key] = $matches[0];
				$variables[] = $matches['var'];
			} else {
				$data_params[$key] = $value;
			}
		}
		$variables = array_unique( $variables );
		if ( !isset( $data_params['data'] ) ) {
			$data_params['data'] = implode( ',', array_map( static function ( $var ) {
				return "$var=$var";
			}, $variables ) );
		}

		$fetched = self::emulateGetExternalData( $data_params, $parser->getTitle() );
		if ( $fetched ) {
			// There is an error.
			return $fetched;
		}

		$num_loops = self::numLoops( $variables );
		for ( $i = 0; $i < $num_loops; $i++ ) {
			$params = [];
			foreach ( $templates as $property => $template ) {
				$params[$property] = preg_replace_callback( $pattern, static function ( array $matches ) use ( $i ) {
					return self::getIndexedValue( $matches['var'], $i, '' ) ?: $matches['default'];
				},                                          $template );
			}
			self::callSubobject( $parser, $back_property, $params );
		}
		return null;
	}

	/**
	 * Actually clear external data.
	 *
	 * @param array $variables Variables to clear.
	 * @return void
	 */
	private static function actuallyClearExternalData( array $variables ) {
		if ( implode( '', $variables ) ) {
			foreach ( $variables as $variable ) {
				unset( self::$values[$variable] );
			}
		} else {
			self::$values = [];
		}
	}

	/**
	 * Render the #clear_external_data parser function.
	 *
	 * @param Parser $parser
	 * @param string ...$variables Variables to clear; if [''], clear all.
	 */
	public static function doClearExternalData( Parser $parser, ...$variables ) {
		self::actuallyClearExternalData( $variables );
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
