<?php

namespace CBO\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Integration for the CBO Standard Blocks payment method.
 */
final class CBO_Standard_Blocks extends AbstractPaymentMethodType
{

    protected $name = 'cbo_standard_gateway';


    public function initialize() {}

    /**
     * Handles the payment method type.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        return ['cbo-standard-blocks-js'];
    }

    /**
     * Data to be passed to the payment method block.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        return [
            'title'       => __('Card (Visa/Mastercard)', 'cbo-payment-gateway'),
            'description' => __('Pay securely with your card.', 'cbo-payment-gateway'),
            'supports'    => ['products'],
        ];
    }
}
