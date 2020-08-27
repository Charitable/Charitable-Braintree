<?php
/**
 * Charitable_Braintree_Gateway_Processor_Recurring class.
 *
 * @package   Charitable Braintree/Classes/Charitable_Braintree_Gateway_Processor
 * @author    Eric Daams
 * @copyright Copyright (c) 2020, Studio 164a
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
			 * Get the customer id.
			 */
			$customer_id            = $this->gateway->get_braintree_customer_id();
			$vaulted_payment_method = $customer_id !== false;

			if ( ! $customer_id ) {
				/**
				 * Create a customer in the Vault.
				 */
				$customer_id = $this->create_customer();
			}

			if ( ! $customer_id ) {
				return false;
			}

			$payment_data = $this->get_payment_data( $customer_id, $vaulted_payment_method );

			if ( ! $payment_data ) {
				return false;
			}

			$url_parts = parse_url( home_url() );
			$plans     = [];

			foreach ( $this->donation->get_campaign_donations() as $campaign_donation ) {
				$plan_id = $this->get_matching_plan_id( $campaign_donation->campaign_id );

				/* We need to have a plan ID in order to create the subscription. */
				if ( ! $plan_id ) {
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
				$cycles = $this->get_subscription_cycles();

				$data = [
					'planId'            => $plan_id,
					'price'             => array_sum( $details['amount'] ),
					'descriptor'        => [
						'name' => $this->get_descriptor_name(),
						'url'  => substr( $url_parts['host'], 0, 13 ),
					],
					'merchantAccountId' => $this->get_merchant_account_id(),
				];

				$data = array_merge( $data, $payment_data );

				if ( 0 === $cycles ) {
					$data['neverExpires'] = true;
				} else {
					$data['numberOfBillingCycles'] = $this->get_subscription_cycles();
				}

				/**
				 * Filter the subscription data.
				 *
				 * @since 1.0.0
				 *
				 * @param array                                  $data      Subscription data.
				 * @param Charitable_Braintree_Gateway_Processor $processor This instance of `Charitable_Braintree_Gateway_Processor`.
				 */
				$data = apply_filters( 'charitable_braintree_subscription_data', $data, $this );

				try {
					$result = $this->braintree->subscription()->create( $data );

					if ( ! $result->success ) {
						$this->set_transaction_failed_notices( $result );
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

						/* Update the initial donation. */
						$transaction = current( $result->subscription->transactions );

						$transaction_url = sprintf(
							'https://%sbraintreegateway.com/merchants/%s/transactions/%s',
							charitable_get_option( 'test_mode' ) ? 'sandbox.' : '',
							$keys['merchant_id'],
							$transaction->id
						);

						$this->donation_log->add(
							sprintf(
								/* translators: %s: link to Braintree transaction details */
								__( 'Braintree transaction: %s', 'charitable-braintree' ),
								'<a href="' . $transaction_url . '" target="_blank"><code>' . $transaction->id . '</code></a>'
							)
						);

						$this->donation->set_gateway_transaction_id( $transaction->id );

						$this->donation->update_status( 'charitable-completed' );
					}

					return true;

				} catch ( Exception $e ) {
					charitable_get_notices()->add_error(
						sprintf(
							/* translators: %1$s: error message; %2$s: error code */
							__( 'Donation failed to process with error: %1$s [%2$s]', 'charitable-braintree' ),
							$e->getMessage(),
							$e->getCode()
						)
					);

					if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
						error_log( get_class( $e ) );
						error_log( $e->getMessage() . ' [' . $e->getCode() . ']' );
					}

					return false;
				}
			}
		}

		/**
		 * Return the payment data to use for a particular subscription.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $customer_id            The customer ID.
		 * @param  boolean $vaulted_payment_method Whether the payment method has been vaulted
		 * @return array|false
		 */
		public function get_payment_data( $customer_id, $vaulted_payment_method ) {
			if ( $vaulted_payment_method ) {
				return [
					'paymentMethodNonce' => $this->get_gateway_value_from_processor( 'nonce' ),
				];
			}

			$token = $this->create_payment_method( $customer_id );

			if ( ! $token ) {
				return false;
			}

			return [
				'paymentMethodToken' => $token,
			];
		}

		/**
		 * Return the plan id to use for a campaign donation.
		 *
		 * @since  1.0.0
		 *
		 * @param  int $campaign_id The campaign ID.
		 * @return string|false
		 */
		public function get_matching_plan_id( $campaign_id ) {
			/* Get the recurring donation period. */
			$period = strtolower( $this->processor->get_donation_data_value( 'donation_period', false ) );

			/* Get the plans set for this campaign. */
			$test_mode      = charitable_get_option( 'test_mode', false );
			$plan_setting   = $test_mode ? 'braintree_recurring_test_plans' : 'braintree_recurring_live_plans';
			$campaign_plans = charitable_get_campaign( $campaign_id )->get( $plan_setting );

			/* If no plan is set for the period, either in the campaign or in the defaults, return false. */
			if ( ! array_key_exists( $period, $campaign_plans ) || empty( $campaign_plans[ $period ] ) ) {
				return false;
			}

			return $campaign_plans[ $period ];
		}

		/**
		 * Get the number of times that the recurring donation
		 * should be renewed for, or 0 for a never-ending subscription.
		 *
		 * @since  1.0.0
		 *
		 * @return int
		 */
		public function get_subscription_cycles() {
			return method_exists( $this->recurring, 'get_donation_length' ) ? (int) $this->recurring->get_donation_length() : 0;
		}

		/**
		 * Set notices explaining why the subscription failed.
		 *
		 * @since  1.0.0
		 *
		 * @param  Braintree\Result $result The result returned by Braintree.
		 * @return void
		 */
		public function set_subscription_failed_notices( $result ) {
			$notices = charitable_get_notices();

			if ( count( $result->errors->deepAll() ) ) {
				$notices->add_error( __( 'Your recurring donation could not be created due to the following errors:', 'charitable-braintree' ) );
				$notices->add_error( $this->get_result_errors_notice( $result ) );
				return;
			}

			switch ( strtoupper( $result->transaction->status ) ) {
				case 'FAILED':
					$message = __( 'Your donation failed due to an error during processing. Please retry your donation.', 'charitable-braintree' );
					break;

				case 'GATEWAY_REJECTED':
					$message = sprintf(
						/* translators: %s: gateway rejection reason */
						__( 'Your payment was rejected by our payment processor with the following error: %s. Please retry your donation with an alternative payment method.', 'charitable-braintree' ),
						$result->transaction->gatewayRejectionReason
					);
					break;

				case 'PROCESSOR_DECLINED':
					$message = sprintf(
						/* translators: %s: processor response text */
						__( 'Your donation was declined by the payment processor with the following error: %s', 'charitable-braintree' ),
						$result->transaction->processorResponseText
					);
					break;
			}

			if ( ! isset( $message ) ) {
				return;
			}

			$notices->add_error( $message );
		}
	}

endif;
