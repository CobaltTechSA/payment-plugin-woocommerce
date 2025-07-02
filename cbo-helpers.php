<?php

/**
 * @param $number
 * @return bool
 */
function is_valid_luhn($number) {
    if (empty($number)) {
        return false;
    }

    settype($number, 'string');
    $sumTable = array(
        array(0,1,2,3,4,5,6,7,8,9),
        array(0,2,4,6,8,1,3,5,7,9));
    $sum = 0;
    $flip = 0;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $sum += $sumTable[$flip++ & 0x1][$number[$i]];
    }
    return $sum % 10 === 0;
}

/**
 * @param $expiry_date
 * @return bool
 */
function is_valid_expiry_date($expiry_date) {
    if (empty($expiry_date)) {
        return false;
    }

    settype($expiry_date, 'string');
    $date = DateTime::createFromFormat("m/y", $expiry_date);
    $currentDate = new DateTime();

    return $date > $currentDate;
}

/**
 * @param $card_holder
 * @return bool
 */
function is_valid_card_holder($card_holder) {
    if (empty($card_holder)) {
        return false;
    }

    if (strlen($card_holder) < 3) {
        return false;
    }

    return true;
}

/**
 * @param $cvv
 * @return bool
 */
function is_valid_cvv($cvv) {
    if (empty($cvv)) {
        return false;
    }

    if (strlen($cvv) < 3) {
        return false;
    }

    return true;
}

function parse_state($state)
{
    $state = str_replace('-', '', $state);
    if (strlen($state) > 3) {
        $state = substr($state, 0, 3);
    } else {
        $state = str_pad($state, 3, '-');
    }
    return $state;
}
