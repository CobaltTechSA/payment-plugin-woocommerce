<?php

namespace Neopayment\WooCommerce;

class CBOStandardGatewayBlocks extends CBOBaseGatewayBlocks
{

    protected $name = CBOConstants::STANDARD_GATEWAY_ID;

    protected $payment_method_script_handle = 'cbo-standard-payment-method-blocks';
    protected $payment_method_script_path = 'assets/js/cbo-standard-payment-method-blocks.js';

    public function get_icons()
    {
        return [
            [
                'src' => 'assets/images/visa.svg',
                'alt' => 'Visa',
            ],
            [
                'src' => 'assets/images/mastercard.svg',
                'alt' => 'Mastercard',
            ]
        ];
    }

    public function get_sdk()
    {
        return 'assets/js/cbo-card.js';
    }
}