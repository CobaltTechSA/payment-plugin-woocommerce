<?php

namespace Neopayment\WooCommerce;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class CBOClaveGatewayBlocks extends CBOBaseGatewayBlocks
{

    protected $name = CBOConstants::TELERED_GATEWAY_ID;

    protected $payment_method_script_handle = 'cbo-clave-payment-method-blocks';
    protected $payment_method_script_path = 'assets/js/cbo-clave-payment-method-blocks.js';

    public function get_icons()
    {
        return [
            [
                'src' => 'assets/images/clave.svg',
                'alt' => 'Clave',
            ]
        ];
    }
}