<?php
/**
 * Plugin Name: Neopayment
 * Plugin URI: https://neopayment.com/soluciones/
 * Description: Payments with VISA, MasterCard and Clave
 * Author: Neopayment
 * Author URI: https://neopayment.com
 * Version: 3.0.0
 * License: GPL-2.0
 * Text Domain: neopayment
 * Domain Path: /i18n
 *
 * @package NEOPAYMENT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'class-neopayment-log.php';
require_once 'class-neopayment-constants.php';
require_once 'class-neopayment-client.php';


// Constants.
define( 'NEOPAYMENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'NEOPAYMENT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Handles WooCommerce plugin for the payment gateway.
 */
class NEOPAYMENT {

	/**
	 * This class instance.
	 *
	 * @var \NEOPAYMENT single instance of this class.
	 */
	private static $instance;

	/**
	 * Admin notices to add.
	 *
	 * @var array Array of admin notices.
	 */
	private $notices = array();

	/**
	 * Class constructor.
	 */
	protected function __construct() {

		register_activation_hook( __FILE__, array( $this, 'neopayment_activation_check' ) );

		add_action( 'admin_init', array( $this, 'neopayment_check_environment' ) );

		add_action( 'admin_notices', array( $this, 'neopayment_add_plugin_notices' ) ); // admin_init is too early for the get_current_screen() function.
		add_action( 'admin_notices', array( $this, 'neopayment_admin_notices' ), 15 );

		// If the environment check fails, initialize the plugin.
		if ( $this->neopayment_is_environment_compatible() ) {
			add_action( 'plugins_loaded', array( $this, 'neopayment_init_plugin' ) );
		}
	}

	/**
	 * Clone function.
	 */
	public function __clone() {

		_doing_it_wrong( __FUNCTION__, sprintf( 'No se puede clonar instancias de %s.', esc_html( get_class( $this ) ) ), '1.10.0' );
	}

	/**
	 * Wakeup function.
	 */
	public function __wakeup() {

		_doing_it_wrong( __FUNCTION__, sprintf( 'No se pueden deserializar instancias de %s.', esc_html( get_class( $this ) ) ), '1.10.0' );
	}


	/**
	 * Initializes the plugin.
	 */
	public function neopayment_init_plugin() {

		if ( ! $this->plugins_compatible() ) {
			return;
		}

		require_once plugin_dir_path(__FILE__) . 'class-neopayment-standard.php';
		require_once plugin_dir_path(__FILE__) . 'class-neopayment-telered.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-neopayment-blocks-support.php';
		\NboPaymentGateway\Blocks\NEOPAYMENT_Blocks_Support::init();

		// fire it up!
		add_action( 'plugins_loaded', array( $this, 'neopayment_registry' ), 11 );
	}

	/**
	 * Register payment methods, declare block compatibility, and support Store API filters.
	 */
	public function neopayment_registry() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return; // WooCommerce is not ready.
		}

		// Gateway Class.
		require_once plugin_dir_path(__FILE__) . 'class-neopayment-standard.php';
		require_once plugin_dir_path(__FILE__) . 'class-neopayment-telered.php';

		// Add gateways to Woo.
		add_filter(
			'woocommerce_payment_gateways',
			function ( $methods ) {
				$methods[] = 'NEOPAYMENT_Standard_Gateway';
				$methods[] = 'NEOPAYMENT_Telered_Gateway';
				return $methods;
			}
		);

		// Block compatibility.
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
						'cart_checkout_blocks',
						__FILE__,
						true
					);
				}
			}
		);

		// Add gateway to block.
		add_filter(
			'woocommerce_blocks_supported_payment_methods',
			function ( $methods ) {
				$methods[] = array(
					'name'     => 'neopayment_standard_gateway',
					'label'    => __( 'Card (Visa/Mastercard)', 'neopayment' ),
					'supports' => array( 'products', 'refunds' ),
				);
				return $methods;
			}
		);

		add_filter(
			'woocommerce_blocks_payment_method_id_to_gateway_mapping',
			function ( $mapping ) {
				$mapping['neopayment_standard_gateway'] = 'neopayment_standard_gateway';
				return $mapping;
			}
		);

		add_filter(
			'woocommerce_store_api_payment_methods',
			function ( $gateways ) {
				$gateways[] = 'NEOPAYMENT_Standard_Gateway';
				return $gateways;
			}
		);

		add_filter(
			'woocommerce_store_api_payment_method_ids',
			function ( $ids ) {
				$ids[] = 'neopayment_standard_gateway';
				return $ids;
			}
		);

		add_filter(
			'woocommerce_store_api_payment_method_schema',
			function ( $schema, $method_id ) {
				if ( 'neopayment_standard_gateway' === $method_id ) {
					$schema['supports']['payment_method_options'] = true;
				}
				return $schema;
			},
			10,
			2
		);
	}

	/**
	 * Checks the server environment and other factors and deactivates plugins as necessary.
	 *
	 * @internal
	 */
	public function neopayment_activation_check() {

		if ( ! $this->neopayment_is_environment_compatible() ) {

			$this->neopayment_deactivate_plugin();

			wp_die( esc_html( NEOPAYMENT_Constants::NEOPAYMENT_PLUGIN_NAME ) . ' no se puede activar. ' . esc_html( $this->neopayment_get_environment_message() ) );
		}
	}


	/**
	 * Checks the environment on loading WordPress, just in case the environment changes after activation.
	 *
	 * @internal
	 */
	public function neopayment_check_environment() {

		if ( ! $this->neopayment_is_environment_compatible() && is_plugin_active( plugin_basename( __FILE__ ) ) ) {

			$this->neopayment_deactivate_plugin();

			$this->neopayment_add_admin_notice( 'bad_environment', 'error', NEOPAYMENT_Constants::NEOPAYMENT_PLUGIN_NAME . ' ha sido activado. ' . $this->neopayment_get_environment_message() );
		}
	}


	/**
	 * Adds notices for out-of-date WordPress and/or WooCommerce versions.
	 *
	 * @internal
	 */
	public function neopayment_add_plugin_notices() {

		if ( ! $this->neopayment_is_wp_compatible() ) {
			if ( current_user_can( 'update_core' ) ) {
				$this->neopayment_add_admin_notice(
					'update_wordpress',
					'error',
					sprintf(
					/* translators: %1$s - plugin name, %2$s - minimum WordPress version required, %3$s - update WordPress link open, %4$s - update WordPress link close */
						esc_html__( '%1$s requiere WordPress %2$s or higher. Porfavor %3$sactualiza WordPress &raquo;%4$s', 'neopayment' ),
						'<strong>' . NEOPAYMENT_Constants::NEOPAYMENT_PLUGIN_NAME . '</strong>',
						NEOPAYMENT_Constants::NEOPAYMENT_MINIMUM_WP_VERSION,
						'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
						'</a>'
					)
				);
			}
		}

		// Notices to install and activate or update WooCommerce.
		$screen = get_current_screen();
		if ( isset( $screen->parent_file ) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id ) {
			return; // Do not display the install/update/activate notice in the update plugin screen.
		}

		$plugin = 'woocommerce/woocommerce.php';
		// Check if WooCommerce is activated.
		if ( ! $this->neopayment_is_wc_activated() ) {

			if ( $this->neopayment_is_wc_installed() ) {
				// WooCommerce is installed but not activated. Ask the user to activate WooCommerce.
				if ( current_user_can( 'activate_plugins' ) ) {
					$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin );
					$message        = sprintf(
					/* translators: %1$s - Plugin Name, %2$s - activate WooCommerce link open, %3$s - activate WooCommerce link close. */
						esc_html__( '%1$s requiere que WooCommerce esté activado. Por favor %2$sactiva WooCommerce%3$s.', 'neopayment' ),
						'<strong>' . NEOPAYMENT_Constants::NEOPAYMENT_PLUGIN_NAME . '</strong>',
						'<a href="' . esc_url( $activation_url ) . '">',
						'</a>'
					);
					$this->neopayment_add_admin_notice(
						'activate_woocommerce',
						'error',
						$message
					);
				}
			} elseif ( current_user_can( 'install_plugins' ) ) {
				// WooCommerce is not installed. Request installation.
				$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ), 'install-plugin_woocommerce' );
				$message     = sprintf(
					/* translators: %1$s - Plugin Name, %2$s - install WooCommerce link open, %3$s - install WooCommerce link close. */
					esc_html__( '%1$s requiere que WooCommerce esté instalado y activado. por favor, %2$sinstala WooCommerce%3$s.', 'neopayment' ),
					'<strong>' . NEOPAYMENT_Constants::NEOPAYMENT_PLUGIN_NAME . '</strong>',
					'<a href="' . esc_url( $install_url ) . '">',
					'</a>'
				);
				$this->neopayment_add_admin_notice(
					'install_woocommerce',
					'error',
					$message
				);
			}
		} elseif ( ! $this->neopayment_is_wc_compatible() ) { // If WooCommerce is activated, check for the version.
			if ( current_user_can( 'update_plugins' ) ) {
				$update_url = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $plugin, 'upgrade-plugin_' . $plugin );
				$this->neopayment_add_admin_notice(
					'update_woocommerce',
					'error',
					sprintf(
					/* translators: %1$s - Plugin Name, %2$s - minimum WooCommerce version, %3$s - update WooCommerce link open, %4$s - update WooCommerce link close, %5$s - download minimum WooCommerce link open, %6$s - download minimum WooCommerce link close. */
						esc_html__( '%1$s requiere WooCommerce %2$s o superior. Por favor, %3$sactualiza WooCommerce%4$s a la última versión, o %5$sdescarga la versión mínima requerida &raquo;%6$s', 'neopayment' ),
						'<strong>' . NEOPAYMENT_Constants::NEOPAYMENT_PLUGIN_NAME . '</strong>',
						NEOPAYMENT_Constants::NEOPAYMENT_MINIMUM_WC_VERSION,
						'<a href="' . esc_url( $update_url ) . '">',
						'</a>',
						'<a href="' . esc_url( 'https://downloads.wordpress.org/plugin/woocommerce.' . NEOPAYMENT_Constants::NEOPAYMENT_MINIMUM_WC_VERSION . '.zip' ) . '">',
						'</a>'
					)
				);
			}
		} elseif ( ! $this->neopayment_is_shop_in_country( 'PA' ) ) {
			$this->neopayment_add_admin_notice(
				'woocommerce_country',
				'error',
				sprintf(
				/* translators: %1$s - Plugin Name, %2$s - minimum WooCommerce version, %3$s - update WooCommerce link open, %4$s - update WooCommerce link close, %5$s - download minimum WooCommerce link open, %6$s - download minimum WooCommerce link close. */
					esc_html__( '%1$s solo está disponible para tiendas localizadas en Panamá.', 'neopayment' ),
					'<strong>' . NEOPAYMENT_Constants::NEOPAYMENT_PLUGIN_NAME . '</strong>'
				)
			);
		}
	}


	/**
	 * Determines if the required plugins are compatible.
	 *
	 * @return bool
	 */
	private function plugins_compatible() {
		return $this->neopayment_is_wp_compatible() && $this->neopayment_is_wc_compatible();
	}


	/**
	 * Determines if the WordPress compatible.
	 *
	 * @return bool
	 */
	private function neopayment_is_wp_compatible() {

		if ( ! NEOPAYMENT_Constants::NEOPAYMENT_MINIMUM_WP_VERSION ) {
			return true;
		}

		return version_compare( get_bloginfo( 'version' ), NEOPAYMENT_Constants::NEOPAYMENT_MINIMUM_WP_VERSION, '>=' );
	}

	/**
	 * Query WooCommerce activation.
	 *
	 * @return bool
	 */
	private function neopayment_is_wc_activated() {
		return class_exists( 'WooCommerce' ) ? true : false;
	}

	/**
	 * Determines if WooCommerce is installed.
	 *
	 * @return bool
	 */
	private function neopayment_is_wc_installed() {
		$plugin            = 'woocommerce/woocommerce.php';
		$installed_plugins = get_plugins();

		return isset( $installed_plugins[ $plugin ] );
	}

	/**
	 * Determines if the WooCommerce compatible.
	 *
	 * @return bool
	 */
	private function neopayment_is_wc_compatible() {

		if ( ! NEOPAYMENT_Constants::NEOPAYMENT_MINIMUM_WC_VERSION ) {
			return true;
		}

		return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, NEOPAYMENT_Constants::NEOPAYMENT_MINIMUM_WC_VERSION, '>=' );
	}

	/**
	 * Check country
	 *
	 * @param string $country country.
	 * @return string $shop_country.
	 */
	private function neopayment_is_shop_in_country( $country ) {
		$shop_country = wc_get_base_location()['country'];
		return $shop_country === $country;
	}


	/**
	 * Deactivates the plugin.
	 *
	 * @internal
	 *
	 * @since 1.10.0
	 */
	protected function neopayment_deactivate_plugin() {

		neopayment_deactivate_plugins( plugin_basename( __FILE__ ) );

		if ( isset( $_GET['activate'], $_GET['_wpnonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'neopayment_activate_action' ) ) {
			$activate = sanitize_text_field( wp_unslash( $_GET['activate'] ) );
		}
	}


	/**
	 * Adds an admin notice to be displayed.
	 *
	 * @since 1.10.0
	 *
	 * @param string $slug    The slug for the notice.
	 * @param string $classes   The css class for the notice.
	 * @param string $message The notice message.
	 */
	private function neopayment_add_admin_notice( $slug, $classes, $message ) {

		$this->notices[ $slug ] = array(
			'class'   => $classes,
			'message' => $message,
		);
	}


	/**
	 * Displays any admin notices added with \NEOPAYMENT::neopayment_add_admin_notice()
	 *
	 * @internal
	 */
	public function neopayment_admin_notices() {

		foreach ( (array) $this->notices as $notice_key => $notice ) {

			?>
			<div class="<?php echo esc_attr( $notice['class'] ); ?>">
				<p>
					<?php
					echo wp_kses(
						$notice['message'],
						array(
							'a'      => array(
								'href' => array(),
							),
							'strong' => array(),
						)
					);
					?>
				</p>
			</div>
			<?php
		}
	}


	/**
	 * Determines if the server environment is compatible with this plugin.
	 *
	 * Override this method to add checks for more than just the PHP version.
	 *
	 * @return bool
	 */
	private function neopayment_is_environment_compatible() {
		return version_compare( PHP_VERSION, NEOPAYMENT_Constants::NEOPAYMENT_MINIMUM_PHP_VERSION, '>=' );
	}


	/**
	 * Gets the message for display when the environment is incompatible with this plugin.
	 *
	 * @return string
	 */
	private function neopayment_get_environment_message() {

		return sprintf( 'La versión mínima requerida de PHP es %1$s. Se está ejecutando la versión %2$s.', NEOPAYMENT_Constants::NEOPAYMENT_MINIMUM_PHP_VERSION, PHP_VERSION );
	}


	/**
	 * Gets the main \NEOPAYMENT instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @return \NEOPAYMENT
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

// fire it up!
NEOPAYMENT::instance();
