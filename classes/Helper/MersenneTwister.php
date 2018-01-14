<?php
/*
  Mersenne Twister
  ----------------

  This is an implementation of the Mersenne Twister.

  Much of the code here was derived from the C code in the file
  mt19937ar.c, which is in the archive mt19937ar.sep.tgz, available from
  http://www.math.sci.hiroshima-u.ac.jp/~m-mat/MT/MT2002/emt19937ar.html.
  Some of the original C code is preserved in a few of the comments below.

  It is non-trivial to map the C code onto PHP, because PHP has no
  unsigned integer type.  What we do here is to use integers when we can,
  and floats when we must.

  Only the class `twister' is part of the API; everything else is private.
  Original From https://github.com/ruafozy/php-mersenne-twister

  Updated by FoXFTW
*/

namespace DPL\Helper;

use Exception;

class MersenneTwister {
	const N = 624;
	const M = 397;
	const MATRIX_A = 0x9908b0df;
	const UPPER_MASK = 0x80000000;
	const LOWER_MASK = 0x7fffffff;
	const MASK10 = 1023;
	const MASK11 = 2047;
	const MASK12 = 4095;
	const MASK14 = 16383;
	const MASK20 = 1048575;
	const MASK21 = 2097151;
	const MASK22 = 4194303;
	const MASK26 = 67108863;
	const MASK27 = 134217727;
	const MASK31 = 2147483647;
	const TWO_TO_THE_16 = 65536;
	const TWO_TO_THE_31 = 2147483648;
	const TWO_TO_THE_32 = 4294967296;
	const MASK32 = 4294967295;

	private $mt;
	private $mti;
	private $bits32;

	# the class constant is not used anywhere in this namespace,
	# but it makes the API cleaner.

	public function __construct() {
		$this->bits32 = PHP_INT_MAX == 2147483647;

		if ( func_num_args() == 1 ) {
			$this->init_with_integer( func_get_arg( 0 ) );
		}
	}

	public function init_with_integer( $integer_seed ) {
		$integer_seed = $this->force_32_bit_int( $integer_seed );

		$mt = &$this->mt;
		$mti = &$this->mti;

		$mt = array_fill( 0, static::N, 0 );

		$mt[0] = $integer_seed;

		for ( $mti = 1; $mti < static::N; $mti ++ ) {
			$mt[$mti] =
				$this->add_2( $this->mul( 1812433253,
					( $mt[$mti - 1] ^ ( ( $mt[$mti - 1] >> 30 ) & 3 ) ) ), $mti );
			/*
			mt[mti] =
				(1812433253UL * (mt[mti-1] ^ (mt[mti-1] >> 30)) + mti);
			*/
		}
	}

	private function force_32_bit_int( $x ) {
		/*
		  it would be un-PHP-like to require is_integer($x),
		  so we have to handle cases like this:

			$x === pow(2, 31)
			$x === strval(pow(2, 31))

		  we are also opting to do something sensible (rather than dying)
		  if the seed is outside the range of a 32-bit unsigned integer.
		*/

		if ( is_integer( $x ) ) {
			/*
			  we mask in case we are on a 64-bit machine and at least one
			  bit is set between position 32 and position 63.
			*/
			return $x & static::MASK32;
		} else {
			$x = floatval( $x );

			$x = $x < 0 ? ceil( $x ) : floor( $x );

			$x = fmod( $x, static::TWO_TO_THE_32 );

			if ( $x < 0 ) {
				$x += static::TWO_TO_THE_32;
			}

			return $this->unsigned2signed( $x );
		}
	}

	private function unsigned2signed( $unsigned_integer ) {
		## assert($unsigned_integer >= 0);
		## assert($unsigned_integer < pow(2, 32));
		## assert(floor($unsigned_integer) === floatval($unsigned_integer));

		return intval( $unsigned_integer < static::TWO_TO_THE_31 ? $unsigned_integer
			: $unsigned_integer - static::TWO_TO_THE_32 );
	}

	/**
	 * takes 2 integers, treats them as unsigned 32-bit integers,
	 * and adds them.
	 *
	 * it works by splitting each integer into
	 * 2 "half-integers", then adding the high and low half-integers
	 * separately.
	 *
	 * a slight complication is that the sum of the low half-integers
	 * may not fit into 16 bits; any "overspill" is added to the sum
	 * of the high half-integers.
	 * @param $n1
	 * @param $n2
	 * @return int
	 */
	private function add_2( $n1, $n2 ) {
		$x = ( $n1 & 0xffff ) + ( $n2 & 0xffff );

		return ( ( ( ( $n1 >> 16 ) & 0xffff ) + ( ( $n2 >> 16 ) & 0xffff ) + ( $x >> 16 ) ) <<
		         16 ) | ( $x & 0xffff );
	}

	/**
	 * a and b, considered as unsigned integers, can be expressed as follows:
	 *
	 * a = 2**16 * a1 + a2,
	 *
	 * b = 2**16 * b1 + b2,
	 *
	 * where
	 *
	 * 0 <= a2 < 2**16,
	 * 0 <= b2 < 2**16.
	 *
	 * given those 2 equations, what this function essentially does is to
	 * use the following identity:
	 *
	 * a * b = 2**32 * a1 * b1 + 2**16 * a1 * b2 + 2**16 * b1 * a2 + a2 * b2
	 *
	 * note that the first term, i.e. 2**32 * a1 * b1, is unnecessary here,
	 * so we don't compute it.
	 *
	 * we could make the following code clearer by using intermediate
	 * variables, but that would probably hurt performance.
	 * @param $a
	 * @param $b
	 * @return int
	 */
	private function mul( $a, $b ) {
		return $this->unsigned2signed( fmod( static::TWO_TO_THE_16 * /*
					  the next line of code calculates a1 * b2,
					  the line after that calculates b1 * a2,
					  and the line after that calculates a2 * b2.
					*/
		                                     ( ( ( $a >> 16 ) & 0xffff ) * ( $b & 0xffff ) +
		                                       ( ( $b >> 16 ) & 0xffff ) * ( $a & 0xffff ) ) +
		                                     ( $a & 0xffff ) * ( $b & 0xffff ),

			static::TWO_TO_THE_32 ) );
	}

	public function init_with_string( $string ) {
		$remainder = strlen( $string ) % 4;

		if ( $remainder > 0 ) {
			$string .= str_repeat( "\0", 4 - $remainder );
		}

		$integer_array = array_values( unpack( "N*", $string ) );

		$this->init_with_array( $integer_array );
	}

	function init_with_array( array $integer_array ) {
		$integer_array = array_map( [ $this, 'force_32_bit_int' ], $integer_array );

		$mt = &$this->mt;

		$key_length = count( $integer_array );

		$this->init_with_integer( 19650218 );
		$i = 1;
		$j = 0;
		$k = ( static::N > $key_length ? static::N : $key_length );
		for ( ; $k; $k -- ) {
			$mt[$i] =
				$this->add_3( $mt[$i] ^
				              $this->mul_by_1664525( $mt[$i - 1] ^ ( ( $mt[$i - 1] >> 30 ) & 3 ) ),
					$integer_array[$j], $j );
			/*
			  mt[i] = (mt[i] ^ ((mt[i-1] ^ (mt[i-1] >> 30)) * 1664525UL))
				+ init_key[j] + j;
			*/
			$i ++;
			$j ++;
			if ( $i >= static::N ) {
				$mt[0] = $mt[static::N - 1];
				$i = 1;
			}
			if ( $j >= $key_length ) {
				$j = 0;
			}
		}
		for ( $k = static::N - 1; $k; $k -- ) {
			$mt[$i] =
				$this->sub( $mt[$i] ^
				            $this->mul( $mt[$i - 1] ^ ( ( $mt[$i - 1] >> 30 ) & 3 ), 1566083941 ),
					$i );
			/*
			  mt[i] = (mt[i] ^ ((mt[i-1] ^ (mt[i-1] >> 30)) * 1566083941UL))
				- i;
			*/
			$i ++;
			if ( $i >= static::N ) {
				$mt[0] = $mt[static::N - 1];
				$i = 1;
			}
		}

		$mt[0] = ( 1 << 31 ); /* MSB is 1; assuring non-zero initial array */
	}

	/**
	 * takes 2 integers, treats them as unsigned 32-bit integers,
	 * and adds them.
	 * for how it works, see the comment for add_2.
	 *
	 * @param $n1
	 * @param $n2
	 * @param $n3
	 * @return int
	 */
	private function add_3( $n1, $n2, $n3 ) {
		$x = ( $n1 & 0xffff ) + ( $n2 & 0xffff ) + ( $n3 & 0xffff );

		return ( ( ( ( $n1 >> 16 ) & 0xffff ) + ( ( $n2 >> 16 ) & 0xffff ) +
		           ( ( $n3 >> 16 ) & 0xffff ) + ( $x >> 16 ) ) << 16 ) | ( $x & 0xffff );
	}

	/**
	 * mul_by_1664525($x) should be more efficient than mul(1664525, $x).
	 *
	 * @param $n
	 * @return int
	 */
	private function mul_by_1664525( $n ) {
		return $this->unsigned2signed( fmod( 1664525 *
		                                     ( $n >= 0 ? $n : ( static::TWO_TO_THE_32 + $n ) ),
			static::TWO_TO_THE_32 ) );
	}

	/**
	 * takes 2 integers, treats them as unsigned 32-bit integers,
	 * and subtracts the second from the first.
	 *
	 * the explanation of why this works is too long to be
	 * included here, so it has been moved into the file why-sub-works.txt.
	 *
	 * @param $a
	 * @param $b
	 * @return int
	 */
	private function sub( $a, $b ) {
		return ( ( $a & static::MASK31 ) - ( $b & static::MASK31 ) ) ^ ( ( $a ^ $b ) & 0x80000000 );
	}

	public function int31() {
		return $this->int32() & static::MASK31;
	}

	public function int32() {
		$mag01 = array( 0, static::MATRIX_A );

		$mt = &$this->mt;
		$mti = &$this->mti;

		if ( $mti >= static::N ) { /* generate N words all at once */
			for ( $kk = 0; $kk < static::N - static::M; $kk ++ ) {
				$y = ( $mt[$kk] & static::UPPER_MASK ) | ( $mt[$kk + 1] & static::LOWER_MASK );
				$mt[$kk] = $mt[$kk + static::M] ^ ( ( $y >> 1 ) & static::MASK31 ) ^ $mag01[$y & 1];
			}
			for ( ; $kk < static::N - 1; $kk ++ ) {
				$y = ( $mt[$kk] & static::UPPER_MASK ) | ( $mt[$kk + 1] & static::LOWER_MASK );
				$mt[$kk] =
					$mt[$kk + ( static::M - static::N )] ^ ( ( $y >> 1 ) & static::MASK31 ) ^
					$mag01[$y & 1];
			}
			$y = ( $mt[static::N - 1] & static::UPPER_MASK ) | ( $mt[0] & static::LOWER_MASK );
			$mt[static::N - 1] =
				$mt[static::M - 1] ^ ( ( $y >> 1 ) & static::MASK31 ) ^ $mag01[$y & 1];

			$mti = 0;
		}

		$y = $mt[$mti ++];

		/* Tempering */
		$y ^= ( $y >> 11 ) & static::MASK21;
		$y ^= ( $y << 7 ) & ( ( 0x9d2c << 16 ) | 0x5680 );
		$y ^= ( $y << 15 ) & ( 0xefc6 << 16 );
		$y ^= ( $y >> 18 ) & static::MASK14;

		return $y;
	}

	/**
	 * generates a random number on [0,1]-real-interval
	 *
	 * @return float|int
	 */
	public function real_closed() {
		return $this->signed2unsigned( $this->int32() ) * ( 1.0 / 4294967295.0 );
	}

	private function signed2unsigned( $signed_integer ) {
		## assert(is_integer($signed_integer));
		## assert(($signed_integer & ~MASK32) === 0);

		return $signed_integer >= 0 ? $signed_integer : static::TWO_TO_THE_32 + $signed_integer;
	}

	/**
	 * generates a random number on [0,1)-real-interval
	 *
	 * @param $lower_bound
	 * @param $upper_bound
	 * @return int
	 * @throws \Exception
	 */
	public function rangeint( $lower_bound, $upper_bound ) {
		$lower_bound = intval( $lower_bound );
		$upper_bound = intval( $upper_bound );

		$this->check_bounds( $lower_bound, $upper_bound );

		if ( $this->bits32 ) {
			$pow_2_32 = pow( 2, 32 );

			$size_of_range = $upper_bound - $lower_bound + 1;

			$remainder = fmod( $pow_2_32, $size_of_range );

			if ( $remainder == 0 ) {
				return $lower_bound +
				       ( $this->int32() & $this->unsigned2signed( $size_of_range - 1 ) );
			} else {
				$start_of_partial_range = $pow_2_32 - $remainder;
				$start_as_int = $this->unsigned2signed( $start_of_partial_range );
				do {
					$rand = $this->int32();
				} while ( $rand >= $start_as_int && $rand < 0 );

				$result = $lower_bound + fmod( $this->signed2unsigned( $rand ), $size_of_range );

				return intval( $result );
			}
		} else {
			if ( $lower_bound == - PHP_INT_MAX - 1 && $upper_bound == PHP_INT_MAX ) {
				return ( $this->int32() << 32 ) | $this->int32();
			} else {
				$pow_2_32 = 1 << 32;

				$size_of_range = $upper_bound - $lower_bound + 1;

				if ( $size_of_range > $pow_2_32 ) {
					$size_of_range >>= 32;
					$shift = 32;
					$low_bits = $this->int32();
				} else {
					$shift = 0;
					$low_bits = 0;
				}

				$remainder = $pow_2_32 % $size_of_range;

				if ( $remainder == 0 ) {
					$high_bits = $this->int32() & ( $size_of_range - 1 );
				} else {
					$start_of_partial_range = $pow_2_32 - $remainder;
					do {
						$rand = $this->int32();
					} while ( $rand >= $start_of_partial_range );

					$high_bits = $rand % $size_of_range;
				}

				return $lower_bound + ( ( $high_bits << $shift ) | $low_bits );
			}
		}
	}

	/**
	 * generates a random number on (0,1)-real-interval
	 *
	 * @param $lower_bound
	 * @param $upper_bound
	 * @throws Exception
	 */
	private function check_bounds( $lower_bound, $upper_bound ) {
		if ( $lower_bound > $upper_bound ) {
			$message = 'lower bound exceeds upper bound: ' . "$lower_bound > $upper_bound";
			throw new Exception( $message );
		}
	}

	/**
	 * generates a random number on [0,1) with 53-bit resolution
	 *
	 * @param $lower_bound
	 * @param $upper_bound
	 * @return float|int
	 * @throws Exception
	 */
	public function rangereal_open( $lower_bound, $upper_bound ) {
		$this->check_bounds( $lower_bound, $upper_bound );

		do {
			$rand = $lower_bound + $this->real_open() * ( $upper_bound - $lower_bound );
		} while ( $rand <= $lower_bound || $rand >= $upper_bound );

		return $rand;
	}

	public function real_open() {
		return ( $this->signed2unsigned( $this->int32() ) + .5 ) * ( 1.0 / 4294967296.0 );
	}

	/**
	 * in each of the next 3 functions, we loop until we have a number that
	 * meets the function's post-condition.  this may be more work than
	 * is really necessary, but i am concerned about rounding errors.
	 * why no rangereal_closed?  because, due to the aforementioned
	 * rounding errors, i am unable to guarantee that $upper_bound
	 * would be a possible return value of such a function.
	 *
	 * @param $lower_bound
	 * @param $upper_bound
	 * @return float|int
	 * @throws Exception
	 */
	public function rangereal_halfopen( $lower_bound, $upper_bound ) {
		$this->check_bounds( $lower_bound, $upper_bound );

		do {
			$rand = $lower_bound + $this->real_halfopen() * ( $upper_bound - $lower_bound );
		} while ( $rand >= $upper_bound );

		/*
	  $rand cannot go any lower than $lower_bound, because
	  $this->real_halfopen() cannot go any lower than 0
		*/

		return $rand;
	}


	public function real_halfopen() {
		return $this->signed2unsigned( $this->int32() ) * ( 1.0 / 4294967296.0 );
	}

	/**
	 * @param $lower_bound
	 * @param $upper_bound
	 * @return float|int
	 * @throws Exception
	 */
	public function rangereal_halfopen2( $lower_bound, $upper_bound ) {
		$this->check_bounds( $lower_bound, $upper_bound );

		do {
			$rand = $lower_bound + $this->real_halfopen2() * ( $upper_bound - $lower_bound );
		} while ( $rand >= $upper_bound );

		return $rand;
	}

	public function real_halfopen2() {
		return ( ( ( $this->int32() & static::MASK27 ) * 67108864.0 ) +
		         ( $this->int32() & static::MASK26 ) ) * ( 1.0 / 9007199254740992.0 );
	}

	public function test() {
		ob_start();
		$this->exercise();
		$output = ob_get_clean();

		if ( md5( $output ) !== "cb33e6acc162cbe20f7fcac26adddd02" ) {
			print "Test failed.\n";
		}
	}

	private function exercise() {
		/*
		  we keep the names "genrand_int32" and "genrand_real2" because
		  we want the output of this function to be identical to that
		  produced by mtTest.c.  (The file mtTest.c is part of the archive
		  mt19937ar.sep.tgz, mentioned above.)
		*/

		$this->init_with_array( array( 0x123, 0x234, 0x345, 0x456 ) );
		printf( "1000 outputs of genrand_int32()\n" );
		for ( $i = 0; $i < 1000; $i ++ ) {
			printf( "%10lu ", $this->int32() );
			if ( $i % 5 == 4 ) {
				printf( "\n" );
			}
		}
		printf( "\n1000 outputs of genrand_real2()\n" );
		for ( $i = 0; $i < 1000; $i ++ ) {
			printf( "%10.8f ", $this->real_halfopen() );
			if ( $i % 5 == 4 ) {
				printf( "\n" );
			}
		}
	}

	private function test_sub() {
		for ( $i = 0; $i < 5000; $i ++ ) {
			$n1 = $this->get_random_32bit_int();
			$n2 = $this->get_random_32bit_int();
			$result = $this->sub( $n1, $n2 );
			$result_from_bc = $this->do_bc_op( "bcsub", array( $n1, $n2 ) );
			if ( $result != $result_from_bc ) {
				printf( "problem in %s: %s %s %s %s\n", __FUNCTION__, $n1, $n2, $result,
					$result_from_bc );
			}
		}
	}

	private function get_random_32bit_int() {
		return rand( 0, 0xffff ) | ( rand( 0, 0xffff ) << 16 );
	}

	private function do_bc_op( $bc_op, array $numbers ) {
		$modulus = pow( 2, 32 );

		$result =
			call_user_func_array( $bc_op,
				array_map( __NAMESPACE__ . "\\signed2unsigned", $numbers ) );

		$result = bcmod( $result, $modulus );

		return $this->unsigned2signed( bccomp( $result, 0 ) < 0 ? bcadd( $result, $modulus )
			: $result );
	}
}
