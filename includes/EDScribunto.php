<?php
/**
 * Class for exposing the parser functions for External Data to Lua.
 * The functions are available via mw.ext.externalData Lua table.
 *
 * @author Alexander Mashin.
 */
class EDScribunto extends Scribunto_LuaLibraryBase {
	/**
	 * A function that registers the exported functions with Lua.
	 */
	public function register() {
		// Data retrieval functions:
		$functions = [];
		foreach ( EDConnectorBase::getConnectors() as $parser_function => $lua_function ) {
			$functions[$lua_function] = function ( array $arguments ) use( $parser_function ) {
				// @phan-suppress-next-line PhanUndeclaredMethod To make PHAN shut up.
				return self::fetch( $parser_function, $arguments, $this->getTitle() );
			};
		}
		// @phan-suppress-next-line PhanUndeclaredMethod To make PHAN shut up.
		$this->getEngine()->registerInterface( __DIR__ . '/mw.ext.externalData.lua', $functions, [] );
	}

	/**
	 * Common code.
	 *
	 * @param string $func The name of the corresponding parser function.
	 * @param array $arguments Arguments coming from Lua code.
	 * @param Title $title A Title object.
	 *
	 * @return array Depending on success, [ 'values' => [values]/null, 'errors' => null/[error messages] ].
	 */
	private static function fetch( $func, array $arguments, Title $title ): array {
		$connector = EDConnectorBase::getConnector( $func, $arguments, $title );

		$values = null;
		if ( !$connector->errors() ) {
			// The parameters seem to be right; try to actually get the external data.
			if ( $connector->run() ) {
				// The external data have been fetched without run-time errors.
				// Results are valid and can be returned (flipped to row-based).
				$values = self::convertArrayToLuaTable( self::flip( $connector->result() ) );
			}
		}

		$messages = null;
		if ( $connector->errors() ) {
			// There have been errors. They have to be converted to human-readable messages.
			$messages = self::convertArrayToLuaTable( array_map( static function ( array $error ) {
				return wfMessage( $error['code'], $error['params'] )->inContentLanguage()->text();
			}, $connector->errors() ) );
		}

		return [ [ 'values' => $values, 'errors' => $messages ] ];
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
