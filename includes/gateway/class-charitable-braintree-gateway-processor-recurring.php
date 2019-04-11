<?php
/**
 * Charitable_Braintree_Gateway_Processor_Recurring class.
 *
 * @package   Charitable Braintree/Classes/Charitable_Braintree_Gateway_Processor
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

if ( ! class_exists( 'Charitable_Braintree_Gateway_Processor_Recurring' ) ) :

	/**
	 * Charitable_Braintree_Gateway_Processor_Recurring
	 *
	 * @since 1.0.0
	 */
	class Charitable_Braintree_Gateway_Processor_Recurring extends Charitable_Braintree_Gateway_Processor implements Charitable_Braintree_Gateway_Processor_Interface {

		/**
		 * Recurring donation object.
		 *
		 * @since 1.0.0
		 *
		 * @var   Charitable_Recurring_Donation
		 */
		private $recurring;

		/**
		 * Set up class instance.
		 *
		 * @since 1.0.0
		 *
		 * @param int                           $donation_id The donation ID.
		 * @param Charitable_Donation_Processor $processor   The donation processor object.
		 */
		public function __construct( $donation_id, Charitable_Donation_Processor $processor ) {
			parent::__construct( $donation_id, $processor );

			$this->recurring = charitable_get_donation( $processor->get_donation_data_value( 'donation_plan' ) );
		}

		/**
		 * Run the processor.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function run() {
			$keys            = $this->gateway->get_keys();
			$this->braintree = $this->gateway->get_gateway_instance( null, $keys );

			if ( ! $this->braintree ) {
				return false;
			}

			/**
			 * Create a customer in the Vault.
			 */
			$customer_id = $this->create_customer();

			if ( ! $customer_id ) {
				return false;
			}

			/**
			 * Create a payment method in the Vault, adding it to the customer.
			 */
			$payment_method = $this->create_payment_method( $customer_id );

			if ( ! $payment_method ) {
				return false;
			}

			$url_parts    = parse_url( home_url() );
			$plans        = [];
			$plan_setting = charitable_get_option( 'test_mode', false ) ? 'braintree_recurring_test_plan' : 'braintree_recurring_live_plan';

			foreach ( $this->donation->get_campaign_donations() as $campaign_donation ) {
				$campaign = charitable_get_campaign( $campaign_donation->campaign_id );
				$plan_id  = $campaign->get( $plan_setting );

				/* We need to have a plan ID in order to create the subscription. */
				if ( empty( $plan_id ) ) {
					charitable_get_notices()->add_error(
						__( 'ERROR: Unable to create recurring donation without default plan.', 'charitable-braintree' )
					);
					return false;
				}

				if ( ! array_key_exists( $plan_id, $plans ) ) {
					$plans[ $plan_id ] = [
						'campaigns' => [],
						'amounts'   => [],
					];
				}

				$plans[ $plan_id ]['campaigns'][] = $campaign_donation->campaign_name;
				$plans[ $plan_id ]['amount'][]    = $campaign_donation->amount;
			}

			foreach ( $plans as $plan_id => $details ) {
				/**
				 * Filter the subscription data.
				 *
				 * @since 1.0.0
				 *
				 * @param array                                  $data      Subscription data.
				 * @param Charitable_Braintree_Gateway_Processor $processor This instance of `Charitable_Braintree_Gateway_Processor`.
				 */
				$data = apply_filters(
					'charitable_braintree_subscription_data',
					[
						'planId'             => $plan_id,
						'paymentMethodToken' => $payment_method,
						'descriptor'         => [
							'name' => substr(
								sprintf(
									'%s*%s',
									get_option( 'blogname' ),
									implode( ',', $details['campaigns'] )
								),
								0,
								18
							),
							'url'  => substr( $url_parts['host'], 0, 13 ),
						],
					]
				);

				try {
					$result = $this->braintree->subscription()->create( $data );
					error_log( var_export( $result, true ) );

					if ( ! $result->success ) {
						error_log( var_export( $result->errors->deepAll(), true ) );
						charitable_get_notices()->add_error( __( 'Subscription not processed successfully in payment gateway.', 'charitable-braintree' ) );
						return false;
					}

					$subscription_url = sprintf(
						'https://%sbraintreegateway.com/merchants/%s/subscriptions/%s',
						charitable_get_option( 'test_mode' ) ? 'sandbox.' : '',
						$keys['merchant_id'],
						$result->subscription->id
					);

					$this->recurring->log()->add(
						sprintf(
							/* translators: %s: link to Braintree subscription details */
							__( 'Braintree subscription: %s', 'charitable-braintree' ),
							'<a href="' . $subscription_url . '" target="_blank"><code>' . $result->subscription->id . '</code></a>'
						)
					);

					$this->recurring->set_gateway_subscription_id( $result->subscription->id );

					if ( 'Active' == $result->subscription->status ) {
						$this->recurring->update_status( 'charitable-active' );
					}

					return true;

				} catch ( Exception $e ) {
					if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
						error_log( get_class( $e ) );
						error_log( $e->getMessage() . ' [' . $e->getCode() . ']' );
					}

					return false;

				}
			}

			/**
			 * Prepare sale transaction data.
			 */
			$transaction_data = [
				'amount'             => number_format( $this->donation->get_total_donation_amount( true ), 2 ),
				'orderId'            => (string) $this->donation->get_donation_id(),
				'paymentMethodNonce' => $this->get_gateway_value_from_processor( 'token' ),
				'options'            => [
					'submitForSettlement' => true,
				],
				'channel'            => 'Charitable_SP',
				'customer'           => [
					'email'     => $this->donor->get_donor_meta( 'email' ),
					'firstName' => $this->donor->get_donor_meta( 'first_name' ),
					'lastName'  => $this->donor->get_donor_meta( 'last_name' ),
					'phone'     => $this->donor->get_donor_meta( 'phone' ),
				],
				'descriptor'         => [
					'name' => substr(
						sprintf( '%s*%s', get_option( 'blogname' ), $this->donation->get_campaigns_donated_to() ),
						0,
						18
					),
					'url'  => substr( $url_parts['host'], 0, 13 ),
				],
				'lineItems'          => [],
				// 'merchantAccountId'  => $keys['merchant_id'],
			];

			foreach ( $this->donation->get_campaign_donations() as $campaign_donation ) {
				$amount = Charitable_Currency::get_instance()->sanitize_monetary_amount( (string) $campaign_donation->amount, true );

				$transaction_data['lineItems'][] = [
					'kind'        => 'debit',
					'name'        => $campaign_donation->campaign_name,
					'productCode' => $campaign_donation->campaign_id,
					'quantity'    => 1,
					'totalAmount' => $amount,
					'unitAmount'  => $amount,
					'url'         => get_permalink( $campaign_donation->campaign_id ),
				];
			}

			error_log( var_export( $transaction_data, true ) );

			/**
			 * Create sale transaction in Braintree.
			 */
			try {
				$result = $this->braintree->transaction()->sale( $transaction_data );

				if ( ! $result->success ) {
					error_log( var_export( $result->errors->deepAll(), true ) );
					charitable_get_notices()->add_error( __( 'Donation not processed successfully in payment gateway.', 'charitable-braintree' ) );
					return false;
				}

				$transaction_url = sprintf(
					'https://%sbraintreegateway.com/merchants/%s/transactions/%s',
					charitable_get_option( 'test_mode' ) ? 'sandbox.' : '',
					$keys['merchant_id'],
					$result->transaction->id
				);

				$this->donation->log()->add(
					sprintf(
						/* translators: %s: link to Braintree transaction details */
						__( 'Braintree transaction: %s', 'charitable-braintree' ),
						'<a href="' . $transaction_url . '" target="_blank"><code>' . $result->transaction->id . '</code></a>'
					)
				);

				$this->donation->set_gateway_transaction_id( $result->transaction->id );

				$this->donation->update_status( 'charitable-completed' );

				return true;

			} catch ( Exception $e ) {
				if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
					error_log( get_class( $e ) );
					error_log( $e->getMessage() . ' [' . $e->getCode() . ']' );
				}
				return false;
			}
		}
	}

endif;
