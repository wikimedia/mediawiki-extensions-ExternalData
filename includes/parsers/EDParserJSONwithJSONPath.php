<?php
/**
 * Class for parsing JSON addressed with JSONPath.
 *
 * @author Yaron Koren
 * @author Alexander Mashin
 */

class EDParserJSONwithJSONPath extends EDParserBase {
	/** @var bool $preserve_external_variables_case Whether external variables' names are case-sensitive for this format. */
	protected static $preserve_external_variables_case = true;

	/**
	 * Constructor.
	 *
	 * @param array $params A named array of parameters passed from parser or Lua function.
	 *
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );

		$this->prefix_length = isset( $params['json offset'] ) ? intval( $params['json offset'] ) : 0;
	}

	/**
	 * Parse the text. Called as $parser( $text ) as syntactic sugar.
	 *
	 * @param string $text The text to be parsed.
	 * @param ?array $defaults The intial values.
	 *
	 * @return array A two-dimensional column-based array of the parsed values.
	 *
	 */
	public function __invoke( $text, $defaults = [] ) {
		$json = new EDJsonObject( $text );
		$values = parent::__invoke( $text, $defaults );
		foreach ( $this->external as $jsonpath ) {
			try {
				$values[$jsonpath] = $json->get( $jsonpath );
			} catch ( MWException $e ) {
				throw new EDParserException( 'externaldata-jsonpath-error', $jsonpath );
			}
		}
		return $values;
	}
}
