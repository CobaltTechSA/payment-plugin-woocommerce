<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers for Neopayment Payment Gateway plugin.
 *
 * @package NEOPAYMENT_PAYMENT_GATEWAY
 */
class NEOPAYMENT_PAYMENT_GATEWAY_Helpers {

	/**
	 * Valid Luhn for card numbers.
	 *
	 * @param int|string $number Card number from user.
	 * @return bool
	 */
	public static function is_valid_luhn( $number ) {
		if ( empty( $number ) ) {
			return false;
		}

		$number = (string) $number;

		$sum_table = array(
			array( 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ),
			array( 0, 2, 4, 6, 8, 1, 3, 5, 7, 9 ),
		);

		$sum  = 0;
		$flip = 0;

		for ( $i = strlen( $number ) - 1; $i >= 0; $i-- ) {
			$digit = (int) $number[ $i ];
			$sum  += $sum_table[ $flip++ & 1 ][ $digit ];
		}

		return 0 === ( $sum % 10 );
	}

	/**
	 * Valid expiry date for cards.
	 *
	 * @param string $expiry_date Expire card date from user.
	 * @return bool
	 */
	public static function is_valid_expiry_date( $expiry_date ) {
		if ( empty( $expiry_date ) ) {
			return false;
		}

		settype( $expiry_date, 'string' );
		$date         = DateTime::createFromFormat( 'm/y', $expiry_date );
		$current_date = new DateTime();

		return $date > $current_date;
	}

	/**
	 * Valid card holder name for card data.
	 *
	 * @param string $card_holder The name of holder card.
	 * @return bool
	 */
	public static function is_valid_card_holder( $card_holder ) {
		if ( empty( $card_holder ) ) {
			return false;
		}

		if ( strlen( $card_holder ) < 3 ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate strlen CVV for minimum of three.
	 *
	 * @param int|string $cvv Valid with three or four numbers.
	 * @return bool
	 */
	public static function is_valid_cvv( $cvv ) {
		if ( empty( $cvv ) ) {
			return false;
		}

		if ( strlen( $cvv ) < 3 ) {
			return false;
		}

		return true;
	}

	/**
	 * Normalize state to three characters for 3DS / API fields.
	 *
	 * @param string $state State string.
	 * @return string
	 */
	public static function parse_state( $state ) {
		$state = str_replace( '-', '', $state );
		if ( strlen( $state ) > 3 ) {
			$state = substr( $state, 0, 3 );
		} else {
			$state = str_pad( $state, 3, '-' );
		}
		return $state;
	}
}
