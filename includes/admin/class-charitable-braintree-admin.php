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
			add_filter( 'plugin_action_links_' . plugin_basename( charitable_braintree()->get_path() ), array( $this, 'add_plugin_action_links' ) );
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
	}

endif;
