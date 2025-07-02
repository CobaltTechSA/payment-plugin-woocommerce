<?php

namespace CBO\Blocks;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

if (! defined('ABSPATH')) {
    exit;
}

final class CBO_Blocks_Support
{
    public static function init()
    {
        // Integrations for the CBO Standard and Telered Blocks payment methods
        require_once CBO_PG_PATH . 'includes/blocks/class-cbo-standard-blocks.php';
        require_once CBO_PG_PATH . 'includes/blocks/class-cbo-telered-blocks.php';

        // Integrations registration
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            [__CLASS__, 'register_blocks']
        );

        add_action('init',    [__CLASS__, 'register_scripts']);
        add_action('init',    [__CLASS__, 'register_styles']);
    }

    // clases AbstractPaymentMethodType 
    public static function register_blocks(PaymentMethodRegistry $registry)
    {
        if (class_exists('\CBO\Blocks\CBO_Standard_Blocks')) {
            $registry->register(new \CBO\Blocks\CBO_Standard_Blocks());
        }
        if (class_exists('\CBO\Blocks\CBO_Telered_Blocks')) {
            $registry->register(new \CBO\Blocks\CBO_Telered_Blocks());
        }
    }

    // scripts
    public static function register_scripts()
    {

        // Standard
        if (file_exists(CBO_PG_PATH . 'build/cbo-standard.asset.php')) {
            $asset = include CBO_PG_PATH . 'build/cbo-standard.asset.php';
            wp_register_script(
                'cbo-standard-blocks-js',
                CBO_PG_URL . 'build/cbo-standard.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );

            wp_set_script_translations(
                'cbo-standard-blocks-js',
                'cbo-payment-gateway',
                CBO_PG_PATH . 'build/i18n'
            );
        }

        // Clave
        if (file_exists(CBO_PG_PATH . 'build/cbo-telered.asset.php')) {
            $asset = include CBO_PG_PATH . 'build/cbo-telered.asset.php';
            wp_register_script(
                'cbo-telered-blocks-js',
                CBO_PG_URL . 'build/cbo-telered.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );

            wp_set_script_translations(
                'cbo-telered-blocks-js',
                'cbo-payment-gateway',
                CBO_PG_PATH . 'build/i18n'
            );
        }
    }

    // styles
    public static function register_styles()
    {
        wp_enqueue_style(
            'cbo-card-fields-style',
            CBO_PG_URL . 'assets/css/cbo-card-fields.css',
            [],
            '1.0.0'
        );
    }
}

CBO_Blocks_Support::init();