<?php
/**
 * The class responsible for adding & saving extra settings in the Charitable admin.
 *
 * @package   Charitable Braintree/Classes/Charitable_Braintree_Admin
 * @copyright Copyright (c) 2020, Studio 164a
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

			/**
			 * If we're on the Braintree settings page, load scripts and add hidden nonce.
			 */
			add_action( 'charitable_before_admin_settings', [ $this, 'load_scripts_on_braintree_settings_page' ] );

			/**
			 * Add a nonce before the button on the Braintree settings page.
			 */
			add_filter( 'charitable_settings_button_gateways_braintree', [ $this, 'add_nonce_to_braintree_settings_page' ] );

			/**
			 * Get the merchant account options for an AJAX request.
			 */
			add_action( 'wp_ajax_charitable_braintree_get_merchant_accounts', [ $this, 'get_merchant_accounts_via_ajax' ] );
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

				$links[] = '<a href="' . $activate_url . '">' . __( 'Activate Braintree gateway', 'charitable-braintree' ) . '</a>';
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
		 * Set up scripts & stylesheets for the admin.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function setup_scripts() {
			if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
				$version = time();
				$version = '1';
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

		/**
		 * Load scripts on the Braintree settings page.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $group The settings group we're on.
		 * @return boolean
		 */
		public function load_scripts_on_braintree_settings_page( $group ) {
			if ( 'gateways_braintree' != $group ) {
				return false;
			}

			if ( ! wp_script_is( 'charitable-braintree-admin-script', 'enqueued' ) ) {
				wp_enqueue_script( 'charitable-braintree-admin-script' );
			}

			return true;
		}

		/**
		 * Add a nonce to the Braintree settings page.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $button The button output.
		 * @return string
		 */
		public function add_nonce_to_braintree_settings_page( $button ) {
			return wp_nonce_field( 'braintree_settings', 'braintree_settings_nonce', true, false ) . $button;
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

			$settings = $values['gateways_braintree'];

			/* Save the webhook endpoint status. */
			if ( isset( $old_values['gateways_braintree']['webhook_endpoint_status'] ) ) {
				$settings['webhook_endpoint_status'] = $old_values['gateways_braintree']['webhook_endpoint_status'];
			}

			$merchant_account_settings = [
				'test_merchant_account_id' => [
					'test_mode'   => true,
					'dba_setting' => 'test_merchant_account_dba_name',
				],
				'live_merchant_account_id' => [
					'test_mode'   => false,
					'dba_setting' => 'live_merchant_account_dba_name',
				],
			];

			$gateway = new Charitable_Gateway_Braintree();

			/* If the merchant account has changed, stash the dbaName. */
			foreach ( $merchant_account_settings as $key => $args ) {
				$dba_setting     = $args['dba_setting'];
				$old_merchant_id = array_key_exists( $key, $old_values['gateways_braintree'] ) ? $old_values['gateways_braintree'][ $key ] : '';

				/* Missing a merchant account id. */
				if ( ! isset( $settings[ $key ] ) || empty( $settings[ $key ] ) ) {
					$settings[ $key ]         = '';
					$settings[ $dba_setting ] = '';
					continue;
				}

				if ( $old_values['gateways_braintree'][ $key ] != $settings[ $key ] ) {
					$account = $gateway->get_merchant_account( $settings[ $key ], $args['test_mode'] );
					if ( ! is_null( $account ) && isset( $account->businessDetails ) ) {
						$settings[ $dba_setting ] = $account->businessDetails->dbaName;
					} else {
						$settings[ $dba_setting ] = '';
					}
				} else {
					$settings[ $dba_setting ] = array_key_exists( $dba_setting, $old_values['gateways_braintree'] )
						? $old_values['gateways_braintree'][ $dba_setting ]
						: '';
				}
			}

			$values['gateways_braintree'] = $settings;

			return $values;
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
		 * Get the merchant accounts via AJAX.
		 *
		 * @since  since
		 *
		 * @return mixed
		 */
		public function get_merchant_accounts_via_ajax() {
			if ( ! wp_verify_nonce( $_POST['braintree_settings_nonce'], 'braintree_settings' ) ) {
				wp_send_json_error( __( 'Nonce verification failed.', 'charitable-braintree' ) );
			};

			$gateway   = new Charitable_Gateway_Braintree();
			$test_mode = 'true' === $_POST['test_mode'];
			$accounts  = $gateway->get_merchant_accounts( $test_mode, charitable_array_subset( $_POST, [ 'merchant_id', 'public_key', 'private_key' ] ) );

			if ( empty( $accounts ) ) {
				wp_send_json_error(
					sprintf(
						/* translators: %s: link to create new merchant account */
						__( '<div class="charitable-settings-notice" style="margin-top: 0;">No merchant accounts found for your currency (%1$s). Create a new <a href="%2$s" target="_blank">merchant account in Braintree</a>.</div>', 'charitable-braintree' ),
						charitable_get_currency(),
						charitable_braintree_get_new_merchant_account_link( $test_mode )
					)
				);
			}

			$key = 'true' == $_POST['test_mode'] ? 'test_merchant_account_id' : 'live_merchant_account_id';

			ob_start();

			charitable_admin_view(
				'settings/select',
				[
					'options' => $accounts,
					'key'     => [ 'gateways_braintree', $key ],
					'name'    => substr( $_POST['field_name'], 20, -1 ),
					'classes' => $_POST['field_classes'],
				]
			);

			wp_send_json_success( ob_get_clean() );
		}
	}

endif;
