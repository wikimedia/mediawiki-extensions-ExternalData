<?php
/**
 * Class for exposing the parser functions for External Data to Lua.
 */
class EDScribunto extends Scribunto_LuaLibraryBase {
	public function register() {
		$functions = [
			'getWebData'	=> [ __CLASS__, 'getWebData' ],
			'getFileData'	=> [ __CLASS__, 'getFileData' ],
			'getSoapData'	=> [ __CLASS__, 'getSOAPData' ],
			'getLdapData'	=> [ __CLASS__, 'getLDAPData' ],
			'getDbData'		=> [ __CLASS__, 'getDBData' ]
		];
		$this->getEngine()->registerInterface( __DIR__ . '/mw.ext.externaldata.lua', $functions, [] );
	}

	/**
	 * Common code.
	 */
	private static function get( callable $doer, array $arguments ) {
		// Actually retrieve external values.
		$external_values = call_user_func( $doer, $arguments );
		if ( !is_array( $external_values ) ) {
			// An error message was returned.
			return [ $external_values ];
		}
		// Always flip.
		return [ self::convertArrayToLuaTable( self::flip( $external_values ) ) ];
	}

	/**
	 * mw.ext.externaldata.getWebData.
	 */
	public static function getWebData( array $arguments ) {
		return self::get( 'EDUtils::doGetWebData', $arguments );
	}

	/**
	 * mw.ext.externaldata.getFileData.
	 */
	public static function getFileData( array $arguments ) {
		return self::get( 'EDUtils::doGetFileData', $arguments );
	}

	/**
	 * mw.ext.externaldata.getSoapData.
	 */
	public static function getSOAPData( array $arguments ) {
		return self::get( 'EDUtils::doGetSoapData', $arguments );
	}

	/**
	 * mw.ext.externaldata.getLdapData.
	 */
	public static function getLDAPData( array $arguments ) {
		return self::get( 'EDUtils::doGetLDAPData', $arguments );
	}

	/**
	 * mw.ext.externaldata.getDbData.
	 */
	public static function getDBData( array $arguments ) {
		return self::get( 'EDUtils::doGetDBData', $arguments );
	}

	/**
	 * Flip the results array (make it row-based rather than column-based) to make it more usable in Lua.
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
