<?php

namespace Neopayment\WooCommerce;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WC_HTTPS;

class CBOBaseGatewayBlocks extends AbstractPaymentMethodType
{

    protected $payment_method_script_handle;
    protected $payment_method_script_path;
    protected $payment_method_dependencies = [
        'wc-blocks-checkout', 'wp-element', 'wc-settings'
    ];

    public function initialize()
    {
        $this->settings = get_option( 'woocommerce_' . $this->name . '_settings', [] );
    }

    public function get_payment_method_data()
    {
        CBOLog::debug( __METHOD__ . ': get_payment_method_data' );
        return [
            'title'       => $this->settings['title'] ?? 'Tu Método',
            'description' => $this->settings['description'] ?? '',
            'supports'    => ['products'],
            'icons'        => $this->get_icons_internal(),
            'sdk'          => plugin_dir_url( __DIR__ ) . $this->get_sdk(),
        ];
    }

    public function is_active()
    {
        return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );

    }

    public function get_payment_method_script_handles()
    {
        wp_register_script(
            $this->payment_method_script_handle,
            plugins_url( $this->payment_method_script_path, __DIR__ ),
            $this->payment_method_dependencies,
            CBOConstants::PLUGIN_VERSION,
            true
        );

        return [ $this->payment_method_script_handle ];
    }

    private function get_icons_internal() {
        $icons = $this->get_icons();

        $path = plugin_dir_url( __DIR__ );

        foreach ($icons as $key => $icon) {
            $icon['src'] = WC_HTTPS::force_https_url( $path . $icon['src'] );
            $icons[$key] = $icon;
        }

        return $icons;
    }

    public function get_icons() {
        return [];
    }

    public function get_sdk() {
        return null;
    }
}