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
		 * The single static class instance.
		 *
		 * @since 1.0.0
		 *
		 * @var   Charitable_Braintree_Admin
		 */
		private static $instance = null;

		/**
		 * Create and return the class object.
		 *
		 * @since 1.0.0
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new Charitable_Braintree_Admin();
			}

			return self::$instance;
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
		 * Add settings to the Extensions settings tab.
		 *
		 * @since  1.0.0
		 *
		 * @param  array[] $fields Settings to display in tab.
		 * @return array[]
		 */
		public function add_braintree_settings( $fields = array() ) {
			if ( ! charitable_is_settings_view( 'extensions' ) ) {
				return $fields;
			}

			$custom_fields = array(
				'section_braintree' => array(
					'title'    => __( 'Braintree', 'charitable-braintree' ),
					'type'     => 'heading',
					'priority' => 50,
				),
				'braintree_setting_text' => array(
					'title'    => __( 'Text Field Setting', 'charitable-braintree' ),
					'type'     => 'text',
					'priority' => 50.2,
					'default'  => __( '', 'charitable-braintree' ),
				),
				'braintree_setting_checkbox' => array(
					'title'    => __( 'Checkbox Setting', 'charitable-braintree' ),
					'type'     => 'checkbox',
					'priority' => 50.6,
					'default'  => false,
					'help'     => __( '', 'charitable-braintree' ),
				),
			);

			$fields = array_merge( $fields, $custom_fields );

			return $fields;
		}
	}

endif;
