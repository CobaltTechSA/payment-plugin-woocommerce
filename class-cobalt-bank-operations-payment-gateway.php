<?php
/**
 * Plugin Name: CBO Payment Gateway
 * Plugin URI: https://neopayment.com/soluciones/
 * Description: Payments with VISA, MasterCard and Clave
 * Author: Cobalt Tech
 * Author URI: https://neopayment.com
 * Version: 2.4.2
 * License:     GPL-2.0
 * Text Domain: class-cobalt-bank-operations-payment-gateway
 * Domain Path: /i18n
 *
 * @package COBALT_BANK_OPERATIONS_Payment_Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'class-cobalt-bank-operations-log.php';
require_once 'class-cobalt-bank-operations-constants.php';
require_once 'class-cobalt-bank-operations-client.php';


// Constants.
define( 'COBALT_BANK_OPERATIONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'COBALT_BANK_OPERATIONS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Handles WooCommerce plugin for the payment gateway.
 */
class COBALT_BANK_OPERATIONS_Payment_Gateway {

	/**
	 * This class instance.
	 *
	 * @var \COBALT_BANK_OPERATIONS_Payment_Gateway single instance of this class.
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

		register_activation_hook( __FILE__, array( $this, 'activation_check' ) );

		add_action( 'admin_init', array( $this, 'check_environment' ) );

		add_action( 'admin_notices', array( $this, 'add_plugin_notices' ) ); // admin_init is too early for the get_current_screen() function.
		add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );

		// If the environment check fails, initialize the plugin.
		if ( $this->is_environment_compatible() ) {
			add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
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
	public function init_plugin() {

		if ( ! $this->plugins_compatible() ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . 'class-cobalt-bank-operations-standard-gateway.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-cobalt-bank-operations-telered-gateway.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-cobalt-bank-operations-blocks-support.php';
		\CBO\Blocks\COBALT_BANK_OPERATIONS_Blocks_Support::init();

		// fire it up!
		add_action( 'plugins_loaded', array( $this, 'cobalt_bank_operations_payment_gateway' ), 11 );
	}

	/**
	 * Register payment methods, declare block compatibility, and support Store API filters.
	 */
	public function cobalt_bank_operations_payment_gateway() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return; // WooCommerce is not ready.
		}

		// Gateway Class.
		require_once plugin_dir_path( __FILE__ ) . 'class-cobalt-bank-operations-standard-gateway.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-cobalt-bank-operations-telered-gateway.php';

		// Add gateways to Woo.
		add_filter(
			'woocommerce_payment_gateways',
			function ( $methods ) {
				$methods[] = 'COBALT_BANK_OPERATIONS_Standard_Gateway';
				$methods[] = 'COBALT_BANK_OPERATIONS_Telered_Gateway';
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
					'name'     => 'cobalt_bank_operations_standard_gateway',
					'label'    => __( 'Card (Visa/Mastercard)', 'class-cobalt-bank-operations-payment-gateway' ),
					'supports' => array( 'products', 'refunds' ),
				);
				return $methods;
			}
		);

		add_filter(
			'woocommerce_blocks_payment_method_id_to_gateway_mapping',
			function ( $mapping ) {
				$mapping['cobalt_bank_operations_standard_gateway'] = 'cobalt_bank_operations_standard_gateway';
				return $mapping;
			}
		);

		add_filter(
			'woocommerce_store_api_payment_methods',
			function ( $gateways ) {
				$gateways[] = 'COBALT_BANK_OPERATIONS_Standard_Gateway';
				return $gateways;
			}
		);

		add_filter(
			'woocommerce_store_api_payment_method_ids',
			function ( $ids ) {
				$ids[] = 'cobalt_bank_operations_standard_gateway';
				return $ids;
			}
		);

		add_filter(
			'woocommerce_store_api_payment_method_schema',
			function ( $schema, $method_id ) {
				if ( 'cobalt_bank_operations_standard_gateway' === $method_id ) {
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
	public function activation_check() {

		if ( ! $this->is_environment_compatible() ) {

			$this->deactivate_plugin();

			wp_die( esc_html( COBALT_BANK_OPERATIONS_Constants::PLUGIN_NAME ) . ' no se puede activar. ' . esc_html( $this->get_environment_message() ) );
		}
	}


	/**
	 * Checks the environment on loading WordPress, just in case the environment changes after activation.
	 *
	 * @internal
	 */
	public function check_environment() {

		if ( ! $this->is_environment_compatible() && is_plugin_active( plugin_basename( __FILE__ ) ) ) {

			$this->deactivate_plugin();

			$this->add_admin_notice( 'bad_environment', 'error', COBALT_BANK_OPERATIONS_Constants::PLUGIN_NAME . ' ha sido activado. ' . $this->get_environment_message() );
		}
	}


	/**
	 * Adds notices for out-of-date WordPress and/or WooCommerce versions.
	 *
	 * @internal
	 */
	public function add_plugin_notices() {

		if ( ! $this->is_wp_compatible() ) {
			if ( current_user_can( 'update_core' ) ) {
				$this->add_admin_notice(
					'update_wordpress',
					'error',
					sprintf(
					/* translators: %1$s - plugin name, %2$s - minimum WordPress version required, %3$s - update WordPress link open, %4$s - update WordPress link close */
						esc_html__( '%1$s requiere WordPress %2$s or higher. Porfavor %3$sactualiza WordPress &raquo;%4$s', 'class-cobalt-bank-operations-payment-gateway' ),
						'<strong>' . COBALT_BANK_OPERATIONS_Constants::PLUGIN_NAME . '</strong>',
						COBALT_BANK_OPERATIONS_Constants::MINIMUM_WP_VERSION,
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
		if ( ! $this->is_wc_activated() ) {

			if ( $this->is_wc_installed() ) {
				// WooCommerce is installed but not activated. Ask the user to activate WooCommerce.
				if ( current_user_can( 'activate_plugins' ) ) {
					$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin );
					$message        = sprintf(
					/* translators: %1$s - Plugin Name, %2$s - activate WooCommerce link open, %3$s - activate WooCommerce link close. */
						esc_html__( '%1$s requiere que WooCommerce esté activado. Por favor %2$sactiva WooCommerce%3$s.', 'class-cobalt-bank-operations-payment-gateway' ),
						'<strong>' . COBALT_BANK_OPERATIONS_Constants::PLUGIN_NAME . '</strong>',
						'<a href="' . esc_url( $activation_url ) . '">',
						'</a>'
					);
					$this->add_admin_notice(
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
					esc_html__( '%1$s requiere que WooCommerce esté instalado y activado. por favor, %2$sinstala WooCommerce%3$s.', 'class-cobalt-bank-operations-payment-gateway' ),
					'<strong>' . COBALT_BANK_OPERATIONS_Constants::PLUGIN_NAME . '</strong>',
					'<a href="' . esc_url( $install_url ) . '">',
					'</a>'
				);
				$this->add_admin_notice(
					'install_woocommerce',
					'error',
					$message
				);
			}
		} elseif ( ! $this->is_wc_compatible() ) { // If WooCommerce is activated, check for the version.
			if ( current_user_can( 'update_plugins' ) ) {
				$update_url = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $plugin, 'upgrade-plugin_' . $plugin );
				$this->add_admin_notice(
					'update_woocommerce',
					'error',
					sprintf(
					/* translators: %1$s - Plugin Name, %2$s - minimum WooCommerce version, %3$s - update WooCommerce link open, %4$s - update WooCommerce link close, %5$s - download minimum WooCommerce link open, %6$s - download minimum WooCommerce link close. */
						esc_html__( '%1$s requiere WooCommerce %2$s o superior. Por favor, %3$sactualiza WooCommerce%4$s a la última versión, o %5$sdescarga la versión mínima requerida &raquo;%6$s', 'class-cobalt-bank-operations-payment-gateway' ),
						'<strong>' . COBALT_BANK_OPERATIONS_Constants::PLUGIN_NAME . '</strong>',
						COBALT_BANK_OPERATIONS_Constants::MINIMUM_WC_VERSION,
						'<a href="' . esc_url( $update_url ) . '">',
						'</a>',
						'<a href="' . esc_url( 'https://downloads.wordpress.org/plugin/woocommerce.' . COBALT_BANK_OPERATIONS_Constants::MINIMUM_WC_VERSION . '.zip' ) . '">',
						'</a>'
					)
				);
			}
		} elseif ( ! $this->is_shop_in_country( 'PA' ) ) {
			$this->add_admin_notice(
				'woocommerce_country',
				'error',
				sprintf(
				/* translators: %1$s - Plugin Name, %2$s - minimum WooCommerce version, %3$s - update WooCommerce link open, %4$s - update WooCommerce link close, %5$s - download minimum WooCommerce link open, %6$s - download minimum WooCommerce link close. */
					esc_html__( '%1$s solo está disponible para tiendas localizadas en Panamá.', 'class-cobalt-bank-operations-payment-gateway' ),
					'<strong>' . COBALT_BANK_OPERATIONS_Constants::PLUGIN_NAME . '</strong>'
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
		return $this->is_wp_compatible() && $this->is_wc_compatible();
	}


	/**
	 * Determines if the WordPress compatible.
	 *
	 * @return bool
	 */
	private function is_wp_compatible() {

		if ( ! COBALT_BANK_OPERATIONS_Constants::MINIMUM_WP_VERSION ) {
			return true;
		}

		return version_compare( get_bloginfo( 'version' ), COBALT_BANK_OPERATIONS_Constants::MINIMUM_WP_VERSION, '>=' );
	}

	/**
	 * Query WooCommerce activation.
	 *
	 * @return bool
	 */
	private function is_wc_activated() {
		return class_exists( 'WooCommerce' ) ? true : false;
	}

	/**
	 * Determines if WooCommerce is installed.
	 *
	 * @return bool
	 */
	private function is_wc_installed() {
		$plugin            = 'woocommerce/woocommerce.php';
		$installed_plugins = get_plugins();

		return isset( $installed_plugins[ $plugin ] );
	}

	/**
	 * Determines if the WooCommerce compatible.
	 *
	 * @return bool
	 */
	private function is_wc_compatible() {

		if ( ! COBALT_BANK_OPERATIONS_Constants::MINIMUM_WC_VERSION ) {
			return true;
		}

		return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, COBALT_BANK_OPERATIONS_Constants::MINIMUM_WC_VERSION, '>=' );
	}

	/**
	 * Check country
	 *
	 * @param string $country country.
	 * @return string $shop_country.
	 */
	private function is_shop_in_country( $country ) {
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
	protected function deactivate_plugin() {

		deactivate_plugins( plugin_basename( __FILE__ ) );

		if ( isset( $_GET['activate'], $_GET['_wpnonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cobalt_bank_operations_activate_action' ) ) {
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
	private function add_admin_notice( $slug, $classes, $message ) {

		$this->notices[ $slug ] = array(
			'class'   => $classes,
			'message' => $message,
		);
	}


	/**
	 * Displays any admin notices added with \COBALT_BANK_OPERATIONS_Payment_Gateway::add_admin_notice()
	 *
	 * @internal
	 */
	public function admin_notices() {

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
	private function is_environment_compatible() {
		return version_compare( PHP_VERSION, COBALT_BANK_OPERATIONS_Constants::MINIMUM_PHP_VERSION, '>=' );
	}


	/**
	 * Gets the message for display when the environment is incompatible with this plugin.
	 *
	 * @return string
	 */
	private function get_environment_message() {

		return sprintf( 'La versión mínima requerida de PHP es %1$s. Se está ejecutando la versión %2$s.', COBALT_BANK_OPERATIONS_Constants::MINIMUM_PHP_VERSION, PHP_VERSION );
	}


	/**
	 * Gets the main \COBALT_BANK_OPERATIONS_Payment_Gateway instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @return \COBALT_BANK_OPERATIONS_Payment_Gateway
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

// fire it up!
COBALT_BANK_OPERATIONS_Payment_Gateway::instance();
