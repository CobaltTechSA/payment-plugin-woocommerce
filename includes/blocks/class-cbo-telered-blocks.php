<?php

namespace CBO\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if (! defined('ABSPATH')) {
    exit;
}

include_once 'cbo-constants.php';

/**
 * Integration for the CBO Telered Blocks payment method.
 */
final class CBO_Telered_Blocks extends AbstractPaymentMethodType
{

    protected $name = 'cbo_telered_gateway';

    public function initialize() {}

    public function get_payment_method_script_handles()
    {
        return ['cbo-telered-blocks-js'];
    }

    public function get_payment_method_data()
    {
        return [
            'title'       => __('Clave Card', 'cbo-payment-gateway'),
            'description' => __('Pay securely with your card', 'cbo-payment-gateway'),
            'supports'    => ['products'],
        ];
    }
}
