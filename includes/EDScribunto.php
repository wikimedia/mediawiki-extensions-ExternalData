<?php
/**
 * Class for exposing the parser functions for External Data to Lua.
 * The functions are available via mw.ext.externalData Lua table.
 *
 * @author Alexander Mashin.
 */
class EDScribunto extends Scribunto_LuaLibraryBase {
	/** @var array $funcs A list of exported Lua functions mapping the ID ised by ExternalDataHooks::connector(). */
	private static $funcs = [
		'getWebData'	=> 'get_web_data',
		'getFileData'	=> 'get_file_data',
		'getSoapData'	=> 'get_soap_data',
		'getLdapData'	=> 'get_ldap_data',
		'getDbData'		=> 'get_db_data'
	];

	/**
	 * A function that registeres the exported functions with Lua.
	 */
	public function register() {
		$functions = [];
		foreach ( self::$funcs as $lua => $parser ) {
			$functions[$lua] = function ( array $arguments ) use( $parser ) {
				return self::fetch( $parser, $arguments );
			};
		}
		$this->getEngine()->registerInterface( __DIR__ . '/mw.ext.externaldata.lua', $functions, [] );
	}

	/**
	 * Common code.
	 *
	 * @param string $func The name of the corresponding parser function.
	 * @param array $arguments Arguments coming from Lua code.
	 *
	 * @return array Depending on success, [ 'values' => [values]/null, 'errors' => null/[error messages] ].
	 */
	private static function fetch( $func, array $arguments ) {
		$connector = EDConnectorBase::getConnector( $func, $arguments );

		$values = null;
		$errors = $connector->errors();

		if ( !$errors ) {
			// The parameters seem to be right; try to actually get the external data.
			if ( $connector->run() ) {
				// The external data have been fetched without run-time errors.
				// Results are valid and can be returned (flipped to row-based).
				$values = self::convertArrayToLuaTable( self::flip( $connector->result() ) );
			} else {
				// Run-time errors:
				$errors = $connector->errors();
			}
		}
		return [ [ 'values' => $values, 'errors' => self::convertArrayToLuaTable( $errors ) ] ];
	}

	/**
	 * Flip the results array (make it row-based rather than column-based) to make it more usable in Lua.
	 * @param array[] $ar
	 * @return array[]
	 */
	private static function flip( array $ar ) {
		$flipped = [];
		foreach ( $ar as $column => $values ) {
			// If there is only one row of some variable,
			// it may be "common" for the dataset.
			// It makes sense to expose it as a named item of the return table.
			if ( count( $values ) === 1 ) {
				$flipped[$column] = $values[0];
			}
			foreach ( $values as $row => $value ) {
				if ( !isset( $flipped[$row] ) ) {
					$flipped[$row] = [];
				}
				$flipped[$row][$column] = $value;
			}
		}
		return $flipped;
	}

	/**
	 * This takes an array and converts it so, that the result is a viable lua table.
	 * I.e. the resulting table has its numerical indices start with 1
	 * If `$ar` is not an array, it is simply returned
	 * @param mixed $ar
	 * @return mixed array
	 *
	 * Borrowed from SemanticScribunto.
	 */
	private static function convertArrayToLuaTable( $ar ) {
		if ( is_array( $ar ) ) {
			foreach ( $ar as $key => $value ) {
				$ar[$key] = self::convertArrayToLuaTable( $value );
			}
			array_unshift( $ar, '' );
			unset( $ar[0] );
		}
		return $ar;
	}
}
