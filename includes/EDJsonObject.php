<?php
/**
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
/**
 * Taken from https://github.com/Galbar/JsonPath-PHP/blob/master/src/Galbar/JsonPath/JsonObject.php
 */
/**
 * This is a [JSONPath](http://goessner.net/articles/JsonPath/) implementation for PHP.
 *
 * This implementation features all elements in the specification
 * except the `()` operator (in the specification there is the `$..a[(@.length-1)]`,
 * but this can be achieved with `$..a[-1]` and the latter is simpler).
 *
 * On top of this it implements some extended features:
 *
 *  * Regex match comparisons (p.e. `$.store.book[?(@.author =~ /.*Tolkien/)]`)__toString
 *  * For the child operator `[]` there is no need to surround child names with quotes (p.e. `$.[store][book, bicycle]`)
 *    except if the name of the field is a non-valid javascript variable name.
 *  * `.length` can be used to get the length of a string or an array and to check if a node has children.
 * Usage
 * =====
 *       // $json can be a string containing json, a PHP array, a PHP object or null.
 *       // If $json is null (or not present) the JsonObject will be empty.
 *       $jsonObject = new EDJsonObject();
 *       // or
 *       $jsonObject = new EDJsonObject($json);
 *
 *       // get
 *       $obj->get($jsonPath);
 *       $obj->{'$.json.path'};
 */
class EDJsonObject {
	// Root regex
	private const RE_ROOT_OBJECT = '/^\$(.*)/';
	// Child regex
	private const RE_CHILD_NAME = '/^\.([\w\_\$^\d][\w\-\$]*|\*)(.*)/u';
	private const RE_RECURSIVE_SELECTOR = '/^\.\.([\w\_\$^\d][\w\-\$]*|\*)(.*)/u';
	private const RE_PARENT_LENGTH = '/^\.length$/';
	// Array expressions
	private const RE_ARRAY_INTERVAL = '/^(?:(-?\d*:-?\d*)|(-?\d*:-?\d*:-?\d*))$/';
	private const RE_INDEX_LIST = '/^(-?\d+)(\s*,\s*-?\d+)*$/';
	private const RE_LENGTH = '/^(.*)\.length$/';
	// Object expression
	private const RE_CHILD_NAME_LIST = '/^(:?([\w$^][\w$-]*?|".*?"|\'.*?\')(\s*,\s*([\w$^][\w$-]*|".*?"|\'.*?\'))*)$/u';
	// Conditional expressions
	private const RE_COMPARISON = '/^(.+)\s*(==|!=|<=|>=|<|>|=\~)\s*(.+)$/';
	private const RE_STRING = '/^(?:\'(.*)\'|"(.*)")$/';
	private const RE_REGEX_EXPR = '/^\/.*\/$/';
	private const RE_NEXT_SUBEXPR = '/.*?(\(|\)|\[|\])/';
	private const RE_OR = '/\s+or\s+/';
	private const RE_AND = '/\s+and\s+/';
	private const RE_NOT = '/^not\s+(.*)/';
	// Tokens
	private const TOK_ROOT = '$';
	private const TOK_CHILD = '@';
	private const TOK_SELECTOR_BEGIN = '[';
	private const TOK_SELECTOR_END = ']';
	private const TOK_BOOL_EXPR = '?';
	private const TOK_EXPRESSION_BEGIN = '(';
	private const TOK_EXPRESSION_END = ')';
	private const TOK_ALL = '*';
	private const TOC_COMMA = ',';
	private const TOK_COLON = ':';
	private const TOK_COMP_EQ = '==';
	private const TOK_COMP_NEQ = '!=';
	private const TOK_COMP_LT = '<';
	private const TOK_COMP_GT = '>';
	private const TOK_COMP_LTE = '<=';
	private const TOK_COMP_GTE = '>=';
	private const TOK_COMP_RE_MATCH = '=~';
	private const TOK_TRUE = 'true';
	private const TOK_FALSE = 'false';
	private const TOK_NULL = 'null';

	/** @var array|mixed|null JSON object */
	private $jsonObject = null;
	/** @var bool */
	private $hasDiverged = false;

	/**
	 * Class constructor.
	 * If $json is null the json object contained
	 * will be initialized empty.
	 *
	 * @param mixed $json json
	 *
	 * @return void
	 *
	 * @throws MWException
	 */
	public function __construct( $json = null ) {
		if ( $json === null ) {
			$this->jsonObject = [];
		} elseif ( is_string( $json ) ) {
			$this->jsonObject = json_decode( $json, true );
			if ( $this->jsonObject === null ) {
				throw new MWException( wfMessage( 'externaldata-invalid-format', 'JSON' )->text() );
			}
		} elseif ( is_array( $json ) ) {
			$this->jsonObject = $json;
		} elseif ( is_object( $json ) ) {
			$this->jsonObject = json_decode( json_encode( $json ), true );
		} else {
			throw new MWException( wfMessage( 'externaldata-invalid-format', 'JSON' )->text() );
		}
	}

	/**
	 * Return the whole JSON tree for Lua as an associative array.
	 *
	 * @return array
	 */
	public function complete() {
		return $this->jsonObject;
	}

	/**
	 * Returns an array containing references to the
	 * objects that match the JsonPath. If the result is
	 * empty returns false.
	 *
	 * @param string $jsonPath jsonPath
	 *
	 * @return array|bool
	 *
	 * @throws MWException
	 */
	public function get( $jsonPath ) {
		$this->hasDiverged = false;
		$result = $this->getReal( $this->jsonObject, $jsonPath );
		return $result;
	}

	/**
	 * Evaluates an expression of any type.
	 *
	 * @param mixed &$jsonObject
	 * @param string $expression jsonPath expression
	 *
	 * @return mixed
	 *
	 * @throws MWException
	 */
	private function expressionValue( &$jsonObject, $expression ) {
		if ( $expression === self::TOK_NULL ) {
			return null;
		} elseif ( $expression === self::TOK_TRUE ) {
			return true;
		} elseif ( $expression === self::TOK_FALSE ) {
			return false;
		} elseif ( is_numeric( $expression ) ) {
			return floatval( $expression );
		} elseif ( preg_match( self::RE_STRING, $expression ) ) {
			return substr( $expression, 1, strlen( $expression ) - 2 );
		} elseif ( preg_match( self::RE_REGEX_EXPR, $expression ) ) {
			return $expression;
		} else {
			$match = [];
			$length = preg_match( self::RE_LENGTH, $expression, $match );
			if ( $length ) {
				$expression = $match[1];
			}
			$result = false;
			if ( $expression[0] === self::TOK_ROOT ) {
				$result = $this->getReal( $this->jsonObject, $expression );
			} elseif ( $expression[0] === self::TOK_CHILD ) {
				$expression[0] = self::TOK_ROOT;
				$result = $this->getReal( $jsonObject, $expression );
			}
			if ( $result !== false ) {
				if ( $length ) {
					if ( is_array( $result[0] ) ) {
						return (float)count( $result[0] );
					}
					if ( is_string( $result[0] ) ) {
						return (float)strlen( $result[0] );
					}
					return false;
				}
				if ( is_float( $result[0] ) || is_int( $result[0] ) ) {
					$result[0] = (float)$result[0];
				}
				return $result[0];
			}
			return $result;
		}
	}

	/**
	 * Compares two expressions.
	 *
	 * @param mixed &$jsonObject
	 * @param string $leftExpr Left expression to compare
	 * @param string $comparator Comparison operator
	 * @param string $rightExpr Right expression to compare
	 *
	 * @return bool
	 *
	 * @throws MWException
	 */
	private function booleanExpressionComparison( &$jsonObject, $leftExpr, $comparator, $rightExpr ) {
		$left = $this->expressionValue( $jsonObject, trim( $leftExpr ) );
		$right = $this->expressionValue( $jsonObject, trim( $rightExpr ) );
		if ( $comparator === self::TOK_COMP_EQ ) {
			return $left === $right;
		} elseif ( $comparator === self::TOK_COMP_NEQ ) {
			return $left !== $right;
		} elseif ( $comparator === self::TOK_COMP_LT ) {
			return $left < $right;
		} elseif ( $comparator === self::TOK_COMP_GT ) {
			return $left > $right;
		} elseif ( $comparator === self::TOK_COMP_LTE ) {
			return $left <= $right;
		} elseif ( $comparator === self::TOK_COMP_GTE ) {
			return $left >= $right;
		} else { // $comparator === self::TOK_COMP_RE_MATCH
			if ( is_string( $right ) && is_string( $left ) ) {
				return (bool)preg_match( $right, $left );
			}
			return false;
		}
	}

	/**
	 * Evaluates a conjunction.
	 *
	 * @param mixed &$jsonObject
	 * @param string $expression A conjunction of expressions
	 *
	 * @return bool
	 *
	 * @throws MWException
	 */
	private function booleanExpressionAnds( &$jsonObject, $expression ) {
		$values = preg_split( self::RE_AND, $expression );
		$match = [];
		foreach ( $values as $subexpr ) {
			$not = false;
			if ( preg_match( self::RE_NOT, $subexpr, $match ) ) {
				$subexpr = $match[1];
				$not = true;
			}
			$result = false;
			if ( preg_match( self::RE_COMPARISON, $subexpr, $match ) ) {
				$result = $this->booleanExpressionComparison( $jsonObject, $match[1], $match[2], $match[3] );
			} else {
				$result = $this->expressionValue( $jsonObject, $subexpr );
			}
			if ( $not ) {
				if ( $result !== false ) {
					return false;
				}
			} else {
				if ( $result === false ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Evaluates a disjunction.
	 *
	 * @param mixed &$json_object
	 * @param string $expression A disjunction of boolean expressions
	 *
	 * @return bool
	 *
	 * @throws MWException
	 */
	private function booleanExpression( &$json_object, $expression ) {
		$ands = preg_split( self::RE_OR, $expression );
		foreach ( $ands as $subexpr ) {
			if ( $this->booleanExpressionAnds( $json_object, $subexpr ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Splits a jsonPath.
	 *
	 * @param string $json_path jsonPath
	 * @param int $offset Initial offset in jsonPath
	 * @return array|null
	 */
	private function matchValidExpression( $json_path, $offset = 0 ) {
		if ( $json_path[$offset] != self::TOK_SELECTOR_BEGIN ) {
			return null;
		}
		$initial_offset = $offset;
		$offset += 1;
		$parent_count = 0;
		$braces_count = 1;
		// $count is a reference to the counter of the $startChar type
		$match = [];
		while ( $braces_count > 0 && $parent_count >= 0 ) {
			if ( preg_match( self::RE_NEXT_SUBEXPR, $json_path, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
				$c = $match[1][0];
				if ( $c === self::TOK_EXPRESSION_BEGIN ) {
					$parent_count += 1;
				} elseif ( $c === self::TOK_EXPRESSION_END ) {
					$parent_count -= 1;
				} elseif ( $c === self::TOK_SELECTOR_BEGIN ) {
					$braces_count += 1;
				} elseif ( $c === self::TOK_SELECTOR_END ) {
					$braces_count -= 1;
				}
				$offset = $match[1][1] + 1;
			} else {
				break;
			}
		}
		if ( $braces_count == 0 && $parent_count == 0 ) {
			return [
				substr( $json_path, $initial_offset + 1, $offset - $initial_offset - 2 ),
				substr( $json_path, $offset - $initial_offset )
			];
		}
		return null;
	}

	/**
	 * Get a child JSON object.
	 *
	 * @param mixed &$json_object
	 * @param string $childName Name of the child JSON object
	 * @param array &$result An enumerated array of child JSON objects
	 * @param bool $createInexistent Whether to create an empty field for non-existent JOSN child object
	 *
	 * @return bool
	 */
	private function opChildName( &$json_object, $childName, &$result, $createInexistent = false ) {
		if ( is_array( $json_object ) ) {
			if ( $childName === self::TOK_ALL ) {
				$this->hasDiverged = true;
				foreach ( $json_object as $key => $item ) {
					$result[] = &$json_object[$key];
				}
			} elseif ( array_key_exists( $childName, $json_object ) ) {
				$result[] = &$json_object[$childName];
			} elseif ( $createInexistent ) {
				$json_object[$childName] = [];
				$result[] = &$json_object[$childName];
			}
			return true;
		}
		return false;
	}

	/**
	 *
	 * @param mixed &$json_object
	 * @param string $contents
	 * @param array &$result An enumerated array of child JSON objects
	 * @param bool $create_nonexistent Whether to create an empty field for non-existent JOSN child object
	 *
	 * @return bool
	 *
	 * @throws MWException
	 */
	private function opChildSelector( &$json_object, $contents, &$result, $create_nonexistent = false ) {
		if ( is_array( $json_object ) ) {
			$match = [];
			$contents_len = strlen( $contents );
			if ( $contents === self::TOK_ALL ) {
				$this->hasDiverged = true;
				foreach ( $json_object as $key => $item ) {
					$result[] = &$json_object[$key];
				}
			} elseif ( preg_match( self::RE_CHILD_NAME_LIST, $contents, $match ) ) {
				$names = array_map(
					static function ( $x ) {
						return trim( $x, " \t\n\r\0\x0B'\"" );
					},
					explode( self::TOC_COMMA, $contents )
				);
				if ( count( $names ) > 1 ) {
					$this->hasDiverged = true;
				}
				$names = array_filter(
					$names,
					static function ( $x ) use ( $create_nonexistent, $json_object ) {
						return $create_nonexistent || array_key_exists( $x, $json_object );
					}
				);
				foreach ( $names as $name ) {
					if ( !array_key_exists( $name, $json_object ) ) {
						$json_object[$name] = [];
					}
					$result[] = &$json_object[$name];
				}
			} elseif ( preg_match( self::RE_INDEX_LIST, $contents ) ) {
				$index = array_map(
					static function ( $x ) use ( $json_object ) {
						$i = (int)trim( $x );
						if ( $i < 0 ) {
							$n = count( $json_object );
							$i %= $n;
							if ( $i < 0 ) {
								$i += $n;
							}
						}
						return $i;
					},
					explode( self::TOC_COMMA, $contents )
				);
				if ( count( $index ) > 1 ) {
					$this->hasDiverged = true;
				}
				$index = array_filter(
					$index,
					static function ( $x ) use ( $create_nonexistent, $json_object ) {
						return $create_nonexistent || array_key_exists( $x, $json_object );
					}
				);
				foreach ( $index as $i ) {
					if ( !array_key_exists( $i, $json_object ) ) {
						$json_object[$i] = [];
					}
					$result[] = &$json_object[$i];
				}
			} elseif ( preg_match( self::RE_ARRAY_INTERVAL, $contents, $match ) ) {
				$this->hasDiverged = true;
				$begin = null;
				$step = null;
				$end = null;
				// end($match) has the matched group with the interval
				$numbers = explode( self::TOK_COLON, end( $match ) );
				// $numbers has the different numbers of the interval
				// depending on if there are 2 (begin:end) or 3 (begin:end:step)
				// numbers $begin, $step, $end are reassigned
				if ( count( $numbers ) === 3 ) {
					$step = ( $numbers[2] !== '' ? intval( $numbers[2] ) : $step );
				}
				$end = ( $numbers[1] !== '' ? intval( $numbers[1] ) : $end );
				$begin = ( $numbers[0] !== '' ? intval( $numbers[0] ) : $begin );
				$slice = EDArraySlice::slice( $json_object, $begin, $end, $step, true );
				foreach ( $slice as $i => $x ) {
					if ( $x !== null ) {
						$result[] = &$slice[$i];
					}
				}
			} elseif (
				$contents[0] === self::TOK_BOOL_EXPR
				&& $contents[1] === self::TOK_EXPRESSION_BEGIN
				&& $contents[$contents_len - 1] === self::TOK_EXPRESSION_END
			) {
				$this->hasDiverged = true;
				$subexpr = substr( $contents, 2, $contents_len - 3 );
				foreach ( $json_object as &$child ) {
					if ( $this->booleanExpression( $child, $subexpr ) ) {
						$result[] = &$child;
					}
				}
			} else {
				throw new MWException( wfMessage( 'externaldata-jsonpath-error' )->text() );
			}
			return true;
		}
		return false;
	}

	/**
	 *
	 *
	 * @param mixed &$jsonObject
	 * @param string $childName Name of JSON child object
	 * @param array &$result An enumerated array of child JSON objects
	 * @return bool
	 * @throws MWException
	 */
	private function opRecursiveSelector( &$jsonObject, $childName, &$result ) {
		$this->opChildName( $jsonObject, $childName, $result );
		if ( is_array( $jsonObject ) ) {
			foreach ( $jsonObject as &$item ) {
				$this->opRecursiveSelector( $item, $childName, $result );
			}
		}
	}

	/**
	 * Really evaluate an expression.
	 *
	 * @param mixed &$json_object
	 * @param string $json_path jsonPath
	 * @param bool $create_nonexistent Whether to create an empty field for non-existent JOSN child object
	 *
	 * @return mixed
	 *
	 * @throws MWException
	 */
	private function getReal( &$json_object, $json_path, $create_nonexistent = false ) {
		$match = [];
		if ( !preg_match( self::RE_ROOT_OBJECT, $json_path, $match ) ) {
			throw new MWException( wfMessage( 'externaldata-jsonpath-error' )->text() );
		}
		$json_path = $match[1];
		$root_object_prev = &$this->jsonObject;
		$this->jsonObject = &$json_object;
		$selection = [ &$json_object ];
		while ( strlen( $json_path ) > 0 && count( $selection ) > 0 ) {
			$new_selection = [];
			if ( preg_match( self::RE_CHILD_NAME, $json_path, $match ) ) {
				foreach ( $selection as &$current_object ) {
					$this->opChildName( $current_object, $match[1], $new_selection, $create_nonexistent );
				}
				unset( $current_object );
				if (
					empty( $new_selection ) &&
					preg_match( self::RE_PARENT_LENGTH, $match[0] )
				) {
					if ( count( $selection ) > 1 ) {
						$new_selection = [];
						/** .length of each array/string in array of arrays $item */
						foreach ( $selection as $item ) {
							$new_selection[] = is_array( $item ) ? count( $item ) : strlen( $item );
						}
					} elseif ( count( $selection ) == 1 ) {
						if ( is_array( $selection[0] ) ) {
							$new_selection = count( $selection[0] );
						} else {
							$new_selection = strlen( $selection[0] );
						}
					}
				}
				if ( empty( $new_selection ) ) {
					$selection = false;
					break;
				} else {
					$json_path = $match[2];
				}
			// phpcs:ignore MediaWiki.ControlStructures.AssignmentInControlStructures.AssignmentInControlStructures
			} elseif ( $match = $this->matchValidExpression( $json_path ) ) {
				$contents = $match[0];
				foreach ( $selection as &$current_object ) {
					$this->opChildSelector( $current_object, $contents, $new_selection, $create_nonexistent );
				}
				unset( $current_object );
				if ( empty( $new_selection ) ) {
					$selection = false;
					break;
				} else {
					$json_path = $match[1];
				}
			} elseif ( preg_match( self::RE_RECURSIVE_SELECTOR, $json_path, $match ) ) {
				$this->hasDiverged = true;
				$this->opRecursiveSelector( $selection, $match[1], $new_selection );
				if ( empty( $new_selection ) ) {
					$selection = false;
					break;
				} else {
					$json_path = $match[2];
				}
			} else {
				throw new MWException( wfMessage( 'externaldata-jsonpath-error' )->text() );
			}
			$selection = $new_selection;
		}
		$this->jsonObject = &$root_object_prev;
		return $selection;
	}
}
