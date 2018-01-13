<?php
/**
 * DynamicPageList3
 * DPL Variables Class
 *
 * @author IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license GPL
 * @package DynamicPageList3
 *
 **/

namespace DPL;

class Variables {
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

			if ( isset( $args[$valueId] ) ) {
				static::$memoryVar[$key] = $args[$valueId];
			} else {
				static::$memoryVar[$key] = '';
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
		if ( count( $args ) === 2 ) {
			if ( !array_key_exists( $args[0], static::$memoryVar ) ||
			     empty( static::$memoryVar[$args[0]] ) ) {
				static::$memoryVar[$args[0]] = $args[1];
			}
		}
	}

	/**
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
	 * @param array $arg
	 * @return string|null
	 */
	public static function setArray( $arg ) {
		$numArgs = count( $arg );

		if ( $numArgs < 5 ) {
			return '';
		}

		$var = trim( $arg[2] );
		$value = $arg[3];
		$delimiter = $arg[4];

		if ( $var == '' ) {
			return '';
		}

		if ( $value == '' ) {
			static::$memoryArray[$var] = [];

			return null;
		}

		if ( $delimiter == '' ) {
			static::$memoryArray[$var] = [
				$value,
			];

			return null;
		}

		if ( 0 !== strpos( $delimiter, '/' ) ||
		     ( strlen( $delimiter ) - 1 ) !== strrpos( $delimiter, '/' ) ) {
			$delimiter = '/\s*' . $delimiter . '\s*/';
		}

		static::$memoryArray[$var] = preg_split( $delimiter, $value );

		return "value={$value}, delimiter={$delimiter}," . count( static::$memoryArray[$var] );
	}

	/**
	 * @param array $arg
	 * @return string
	 */
	public static function dumpArray( $arg ) {
		$numArgs = count( $arg );

		if ( $numArgs < 3 ) {
			return '';
		}

		$var = trim( $arg[2] );
		$text = " array {$var} = {";
		$n = 0;

		if ( array_key_exists( $var, static::$memoryArray ) ) {
			foreach ( static::$memoryArray[$var] as $value ) {
				if ( $n ++ > 0 ) {
					$text .= ', ';
				}

				$text .= "{$value}";
			}
		}

		return $text . "}\n";
	}

	/**
	 * @param string $var
	 * @param string $delimiter
	 * @param string $search
	 * @param string $subject
	 * @return array|string
	 */
	public static function printArray( $var, $delimiter, $search, $subject ) {
		$var = trim( $var );

		if ( $var == '' ) {
			return '';
		}

		if ( !array_key_exists( $var, static::$memoryArray ) ) {
			return '';
		}

		$values = static::$memoryArray[$var];
		$rendered_values = [];

		foreach ( $values as $v ) {
			$temp_result_value = str_replace( $search, $v, $subject );
			$rendered_values[] = $temp_result_value;
		}

		return [
			implode( $delimiter, $rendered_values ),
			'noparse' => false,
			'isHTML' => false,
		];
	}
}
