<?php
namespace CBO\Blocks;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CBO_Blocks_Support {

    public static function init() {
        // Cargar clases de integración 
        require_once CBO_PG_PATH . 'includes/blocks/class-cbo-standard-blocks.php';
        require_once CBO_PG_PATH . 'includes/blocks/class-cbo-telered-blocks.php';

        // Registro de las integraciones
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            [ __CLASS__, 'register_blocks' ]
        );

        // Registro  de los bundles js
        add_action(
            'wp_enqueue_scripts',
            [ __CLASS__, 'register_scripts' ],
            1
        );
        // Registro de los estilos
        add_action(
            'wp_enqueue_scripts',
            [ __CLASS__, 'register_styles' ],
            1
        );

        // Registro de los scripts 3ds
        add_action(
            'wp_enqueue_scripts',
            [ __CLASS__, 'register_3ds_scripts' ],
            1
        );
    }

    // clases AbstractPaymentMethodType 
    public static function register_blocks( PaymentMethodRegistry $registry ) {
        if ( class_exists( '\CBO\Blocks\CBO_Standard_Blocks' ) ) {
            $registry->register( new \CBO\Blocks\CBO_Standard_Blocks() );
        }
        if ( class_exists( '\CBO\Blocks\CBO_Telered_Blocks' ) ) {
            $registry->register( new \CBO\Blocks\CBO_Telered_Blocks() );
        }
    }

    // scripts
    public static function register_scripts() {

        // Standard
        if ( file_exists( CBO_PG_PATH . 'build/cbo-standard.asset.php' ) ) {
            $asset = include CBO_PG_PATH . 'build/cbo-standard.asset.php';
            wp_register_script(
                'cbo-standard-blocks-js',
                CBO_PG_URL . 'build/cbo-standard.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );
        }

        // Clave
        if ( file_exists( CBO_PG_PATH . 'build/cbo-telered.asset.php' ) ) {
            $asset = include CBO_PG_PATH . 'build/cbo-telered.asset.php';
            wp_register_script(
                'cbo-telered-blocks-js',
                CBO_PG_URL . 'build/cbo-telered.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );
        }
    }

    // estilos
    public static function register_styles() {
        // Estilos para los bloques de pago
        wp_enqueue_style(
            'cbo-card-fields-style',
            CBO_PG_URL . 'assets/css/cbo-card-fields.css',
            [],
            '1.0.0'
        );
    }

    //3ds
    public static function register_3ds_scripts() {
        if ( file_exists( CBO_PG_PATH . 'build/cbo-payment-script.asset.php' ) ) {
            $asset = include CBO_PG_PATH . 'build/cbo-payment-script.asset.php';
            wp_enqueue_script( 'cbo-payment-script', plugins_url( 'assets/js/cbo-payment-script', __FILE__ ), ['jquery'], '1.0', true );
        }
    }
}


CBO_Blocks_Support::init();
