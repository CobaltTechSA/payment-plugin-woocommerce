<?php
/**
 * Helpers for CBO Payment Gateway plugin.
 *
 * @package COBALT_BANK_OPERATIONS_Payment_Gateway
 */

/**
 * Valid Lunh for cards numbers.
 *
 * @param int $number is card number user.
 * @return bool
 */
function cobalt_bank_operations_is_valid_luhn( $number ) {
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
 * Valid expired date for cards.
 *
 * @param string $expiry_date is expire card date from user.
 * @return bool
 */
function cobalt_bank_operations_is_valid_expiry_date( $expiry_date ) {
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
 * @param string $card_holder is the name of holder card.
 * @return bool
 */
function cobalt_bank_operations_is_valid_card_holder( $card_holder ) {
	if ( empty( $card_holder ) ) {
		return false;
	}

	if ( strlen( $card_holder ) < 3 ) {
		return false;
	}

	return true;
}

/**
 * Validate stlen cvv for minimum of three.
 *
 * @param int $cvv is valid with three or four numbers.
 * @return bool
 */
function cobalt_bank_operations_is_valid_cvv( $cvv ) {
	if ( empty( $cvv ) ) {
		return false;
	}

	if ( strlen( $cvv ) < 3 ) {
		return false;
	}

	return true;
}

/**
 * Validate state with only three letters.
 *
 * @param string $state for parse with only three.
 * @return string "state
 */
function cobalt_bank_operations_parse_state( $state ) {
	$state = str_replace( '-', '', $state );
	if ( strlen( $state ) > 3 ) {
		$state = substr( $state, 0, 3 );
	} else {
		$state = str_pad( $state, 3, '-' );
	}
	return $state;
}
