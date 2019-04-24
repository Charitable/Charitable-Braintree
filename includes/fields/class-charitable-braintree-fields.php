<?php
/**
 * A class to define campaign & donation fields for Charitable Braintree.
 *
 * @package   Charitable Braintree/Classes/Charitable_Braintree_Fields
 * @author    Eric Daams
 * @copyright Copyright (c) 2019, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Charitable_Braintree_Fields' ) ) :

	/**
	 * Charitable_Fields
	 *
	 * @since 1.0.0
	 */
	class Charitable_Braintree_Fields {

		/**
		 * Set up class instance.
		 *
		 * @since  1.0.0
		 */
		public function __construct() {
			add_filter( 'charitable_default_campaign_fields', [ $this, 'add_campaign_fields' ], 1 );
		}

		/**
		 * Add default campaign fields.
		 *
		 * @since  1.0.0
		 *
		 * @param  array $fields Default campaign fields.
		 * @return array
		 */
		public function add_campaign_fields( $fields ) {
			$gateway = new Charitable_Gateway_Braintree;

			return array_merge(
				$fields,
				[
					'braintree_recurring_live_plan' => [
						'label'          => __( 'Braintree Recurring Billing Live Plan', 'charitable-braintree' ),
						'data_type'      => 'meta',
						'value_callback' => [ $this, 'get_recurring_billing_plan_for_campaign_live' ],
						'admin_form'     => [
							'section'        => 'campaign-donation-options',
							'type'           => 'select',
							'priority'       => 26,
							// 'options'        => $gateway->get_plans( false, __( 'Use default plan', 'charitable-braintree' ) ),
							'value_callback' => function( Charitable_Campaign $campaign ) {
								return $this->get_recurring_billing_plan_for_campaign_live( $campaign, false );
							},
						],
						'email_tag'      => false,
						'show_in_export' => false,
					],
					'braintree_recurring_test_plan' => [
						'label'          => __( 'Braintree Recurring Billing Test Plan', 'charitable-braintree' ),
						'data_type'      => 'meta',
						'value_callback' => [ $this, 'get_recurring_billing_plan_for_campaign_test' ],
						'admin_form'     => [
							'section'        => 'campaign-donation-options',
							'type'           => 'select',
							'priority'       => 26,
							// 'options'        => $gateway->get_plans( true, __( 'Use default plan', 'charitable-braintree' ) ),
							'value_callback' => function( Charitable_Campaign $campaign ) {
								return $this->get_recurring_billing_plan_for_campaign_test( $campaign, false );
							},
						],
						'email_tag'      => false,
						'show_in_export' => false,
					],
				]
			);
		}

		/**
		 * Return the recurring billing plan to use for a campaign.
		 *
		 * @since  1.0.0
		 *
		 * @param  Charitable_Campaign $campaign The campaign object.
		 * @return string
		 */
		public function get_recurring_billing_plan_for_campaign_live( Charitable_Campaign $campaign, $fallback_to_default = true ) {
			$value = $campaign->get_meta( '_campaign_braintree_recurring_live_plan' );

			if ( ! $value && $fallback_to_default ) {
				$value = charitable_get_option( [ 'gateways_braintree', 'default_live_plan' ] );
			}

			return $value;
		}

		/**
		 * Return the recurring billing plan to use for a campaign in test mode.
		 *
		 * @since  1.0.0
		 *
		 * @param  Charitable_Campaign $campaign The campaign object.
		 * @return string
		 */
		public function get_recurring_billing_plan_for_campaign_test( Charitable_Campaign $campaign, $fallback_to_default = true ) {
			$value = $campaign->get_meta( '_campaign_braintree_recurring_test_plan' );

			if ( ! $value && $fallback_to_default ) {
				$value = charitable_get_option( [ 'gateways_braintree', 'default_test_plan' ] );
			}

			return $value;
		}

	}

endif;
