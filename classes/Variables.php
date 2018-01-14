<?php
/**
 * DynamicPageList3
 * DPL Variables Class
 *
 * @author IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith, FoXFTW
 * @license GPL
 * @package DynamicPageList3
 *
 **/

namespace DPL;

class Variables {
	/**
	 * Special Delimiter used in printArray. Will be replaced as a single Space is used.
	 */
	const ARRAY_SPACE_DELIMITER = '<S>';

	/**
	 * Memory storage for variables.
	 *
	 * @var array
	 */
	public static $memoryVar = [];

	/**
	 * Memory storage for arrays of variables.
	 *
	 * @var array
	 */
	public static $memoryArray = [];

	/**
	 * expects pairs of 'variable name' and 'value'
	 * {{#dplvar:set|Key|Value}}
	 * {{#dplvar:set|Key|Value|Key2|Value2|...}}
	 *
	 * @param array $args Array with Key, Value pairs
	 * @return void
	 */
	public static function setVar( $args ) {
		for ( $i = 0; $i < count( $args ); $i += 2 ) {
			$key = $args[$i];
			$valueId = $i + 1;

			if ( !empty( $key ) ) {
				if ( isset( $args[$valueId] ) ) {
					static::$memoryVar[$key] = $args[$valueId];
				} else {
					static::$memoryVar[$key] = '';
				}
			}
		}
	}

	/**
	 * Assigns the value only if the variable is empty / has not been used so far
	 * Only assigns ONE Key, Value Pair
	 * {{#dplvar:default|Key|Value}}
	 *
	 * @param array $args Array with ONE Key, Value Pair
	 * @return void
	 */
	public static function setVarDefault( $args ) {
		if ( count( $args ) === 2 && !empty( $args[0] ) ) {
			if ( !array_key_exists( $args[0], static::$memoryVar ) ||
			     empty( static::$memoryVar[$args[0]] ) ) {
				static::$memoryVar[$args[0]] = $args[1];
			}
		}
	}

	/**
	 * Gets a value from memoryVar for the given $key.
	 * Empty string if key does not exist.
	 * {{#dplvar:Key}}
	 *
	 * @param $key
	 * @return mixed|string
	 */
	public static function getVar( $key ) {
		if ( array_key_exists( $key, static::$memoryVar ) ) {
			return static::$memoryVar[$key];
		}

		return '';
	}

	/**
	 * {{#dplarray:set|Key|Values|Delimiter}}
	 * Delimiter value is optional.
	 *
	 * @param array $args
	 * @return void
	 */
	public static function setArray( $args ) {
		if ( count( $args ) === 3 ) {
			$key = $args[0];
			$value = $args[1];
			$delimiter = $args[2];

			if ( !empty( $key ) && !empty( $value ) ) {
				if ( empty( $delimiter ) ) {
					static::$memoryArray[$key] = [
						$value,
					];

					return;
				}

				static::$memoryArray[$key] = explode( $delimiter, $value );
			}

		}
	}

	/**
	 * Dumps the Array as array (Key) = [Values]. Delimiter is ', '
	 * {{#dplarray:dump|Key}}
	 *
	 * @param string $key
	 * @return array|string
	 */
	public static function dumpArray( $key ) {
		if ( !array_key_exists( $key, static::$memoryArray ) ) {
			return "array with key {$key} does not exist\n";
		}

		$values = implode( ', ', static::$memoryArray[$key] );
		$values = rtrim( $values, ' ,' );

		return "array ({$key}) = [{$values}]\n";

	}

	/**
	 * @TODO Wiki Table Syntax won't work at this Point.
	 *
	 * {{#dplarray:Key|Delimiter|Search|Replace}}
	 * If Delimiter is empty ' ,' will be used
	 * If <S> is used as a Delimiter a single Space will be used instead
	 * Search and Replace are optional
	 * If Set every Array key will have Search replaced with Replace
	 *
	 * @param array $args
	 * @return array|string
	 */
	public static function printArray( $args ) {
		$key = array_shift( $args );

		if ( !array_key_exists( $key, static::$memoryArray ) ) {
			return "array with key {$key} does not exist\n";
		}

		$delimiter = ', ';
		if ( !is_null( $args ) && isset( $args[0] ) ) {
			$delimiter = array_shift( $args );
			if ( $delimiter === '<S>' ) {
				$delimiter = ' ';
			}
		}

		// If no Delimiter is present, or Replace is missing: print array
		if ( empty( $args ) || is_null( $args ) || count( $args ) === 1 ) {
			$values = implode( $delimiter, static::$memoryArray[$key] );
			$values = rtrim( $values, $delimiter );

			return "$values\n";
		} elseif ( isset( $args[0] ) && isset( $args[1] ) ) {
			$search = array_shift( $args );
			$replace = array_shift( $args );

			$values = static::$memoryArray[$key];
			$rendered_values = [];

			foreach ( $values as $value ) {
				$result = str_replace( $search, $value, $replace );
				$rendered_values[] = $result;
			}

			$values = implode( $delimiter, $rendered_values );
			$values = rtrim( $values, $delimiter );

			return [
				$values,
				'noparse' => false,
				'isHTML' => true,
			];
		}
	}
}
