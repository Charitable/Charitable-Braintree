<?php
/**
 * The class responsible for adding & saving extra settings in the Charitable admin.
 *
 * @package   Charitable Braintree/Classes/Charitable_Braintree_Admin
 * @copyright Copyright (c) 2019, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Charitable_Braintree_Admin' ) ) :

	/**
	 * Charitable_Braintree_Admin
	 *
	 * @since 1.0.0
	 */
	class Charitable_Braintree_Admin {

		/**
		 * Set up class instance.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			/**
			 * Add a direct link to the Extensions settings page from the plugin row.
			 */
			add_filter( 'plugin_action_links_' . plugin_basename( charitable_braintree()->get_path() ), [ $this, 'add_plugin_action_links' ] );

			/**
			 * When the Braintree gateway is activated
			 */
			add_action( 'charitable_gateway_enable', [ $this, 'on_gateway_activation' ] );

			/**
			 * Check the webhook endpoint status a day after gateway activation.
			 */
			add_action( 'charitable_braintree_check_webhook_endpoint_status', [ $this, 'check_webhook_endpoint_status' ] );

			/**
			 * When the Braintree settings are saved, preserve the webhook endpoint status.
			 */
			add_action( 'charitable_save_settings', [ $this, 'on_save_settings' ], 10, 3 );

			/**
			 * Register admin scripts & styles.
			 */
			add_action( 'admin_enqueue_scripts', [ $this, 'setup_scripts' ] );
		}

		/**
		 * Add custom links to the plugin actions.
		 *
		 * @since  1.0.0
		 *
		 * @param  string[] $links Links to be added to plugin actions row.
		 * @return string[]
		 */
		public function add_plugin_action_links( $links ) {
			if ( Charitable_Gateways::get_instance()->is_active_gateway( 'braintree' ) ) {
				$links[] = '<a href="' . admin_url( 'admin.php?page=charitable-settings&tab=gateways&group=gateways_braintree' ) . '">' . __( 'Settings', 'charitable-braintree' ) . '</a>';
			} else {
				$activate_url = esc_url(
					add_query_arg(
						array(
							'charitable_action' => 'enable_gateway',
							'gateway_id'        => 'braintree',
							'_nonce'            => wp_create_nonce( 'gateway' ),
						),
						admin_url( 'admin.php?page=charitable-settings&tab=gateways' )
					)
				);

				$links[] = '<a href="' . $activate_url . '">' . __( 'Activate Braintree Gateway', 'charitable-braintree' ) . '</a>';
			}

			return $links;
		}

		/**
		 * When a gateway is activated, check if it's Braintree.
		 *
		 * If it is, store the webhook endpoint status.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $gateway The gateway activated.
		 * @return void
		 */
		public function on_gateway_activation( $gateway ) {
			if ( Charitable_Gateway_Braintree::ID !== $gateway ) {
				return;
			}

			$settings         = get_option( 'charitable_settings' );
			$gateway_settings = array_key_exists( 'gateways_braintree', $settings ) ? $settings['gateways_braintree'] : [];

			/* If we already have a status, skip this. */
			if ( array_key_exists( 'webhook_endpoint_status', $gateway_settings ) && 'pending_check' != $gateway_settings['webhook_endpoint_status'] ) {
				return;
			}

			/* Record the status as pending_check. */
			$gateway_settings['webhook_endpoint_status'] = 'pending_check';

			/* Schedule an event to fire in a day to update the status. */
			wp_schedule_single_event( time() + DAY_IN_SECONDS, 'charitable_braintree_check_webhook_endpoint_status' );

			$settings['gateways_braintree'] = $gateway_settings;

			update_option( 'charitable_settings', $settings );
		}

		/**
		 * Check the webhook endpoint status after a day.
		 *
		 * The purpose of this check is to see whether any incoming webhooks have
		 * been received yet. If not, we will change the status to missing_endpoint,
		 * which will result in a message being displayed in the admin until a
		 * webhook has been received.
		 *
		 * If a webhook has been received, the webhook endpoint status is listed as
		 * active.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function check_webhook_endpoint_status() {
			$settings = get_option( 'charitable_settings' );

			/**
			 * It's been a day and we haven't received an incoming webhook yet, so
			 * mark the endpoint as missing.
			 */
			if ( 'pending_check' == $settings['gateways_braintree']['webhook_endpoint_status'] ) {
				$settings['gateways_braintree']['webhook_endpoint_status'];

				update_option( 'charitable_settings', $settings );
			}
		}

		/**
		 * Preserve the webhook_endpoint_status setting when saving Braintree settings.
		 *
		 * @since  1.0.0
		 *
		 * @param  array $values     The submitted values.
		 * @param  array $new_values The new settings.
		 * @param  array $old_values The previous settings.
		 * @return array
		 */
		public function on_save_settings( $values, $new_values, $old_values ) {
			/* Bail early if this is not the Braintree settings page. */
			if ( ! array_key_exists( 'gateways_braintree', $values ) ) {
				return $values;
			}

			if ( ! isset( $old_values['gateways_braintree']['webhook_endpoint_status'] ) ) {
				return $values;
			}

			$values['gateways_braintree']['webhook_endpoint_status'] = $old_values['gateways_braintree']['webhook_endpoint_status'];

			return $values;
		}

		/**
		 * Set up scripts & stylesheets for the admin.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function setup_scripts() {
			if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
				$version = time();
				$suffix  = '';
			} else {
				$version = charitable_braintree()->get_version();
				$suffix  = '.min';
			}

			wp_register_script(
				'charitable-braintree-admin-script',
				charitable_braintree()->get_path( 'directory', false ) . 'assets/js/charitable-braintree-admin' . $suffix . '.js',
				array(),
				$version
			);

			wp_register_style(
				'charitable-braintree-admin-styles',
				charitable_braintree()->get_path( 'directory', false ) . 'assets/css/charitable-braintree-admin' . $suffix . '.css',
				array(),
				$version
			);
		}
	}

endif;
