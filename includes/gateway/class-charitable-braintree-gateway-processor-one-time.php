<?php
/**
 * Charitable_Braintree_Gateway_Processor_One_Time class.
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

if ( ! class_exists( 'Charitable_Braintree_Gateway_Processor_One_Time' ) ) :

	/**
	 * Charitable_Braintree_Gateway_Processor_One_Time
	 *
	 * @since 1.0.0
	 */
	class Charitable_Braintree_Gateway_Processor_One_Time extends Charitable_Braintree_Gateway_Processor implements Charitable_Braintree_Gateway_Processor_Interface {

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

			$url_parts = parse_url( home_url() );

			/**
			 * Get the customer id.
			 */
			$customer_id = $this->gateway->get_braintree_customer_id();

			if ( ! $customer_id ) {
				/**
				 * Create a customer in the Vault.
				 */
				$customer_id = $this->create_customer();

				if ( ! $customer_id ) {
					$this->donation_log->add( __( 'Unable to set up customer in the Braintree Vault.', 'charitable-braintree' ) );
					return false;
				}

				/**
				 * Create a payment method in the Vault, adding it to the customer.
				 */
				$payment_method = $this->create_payment_method( $customer_id );

				if ( ! $payment_method ) {
					$this->donation_log->add( __( 'Unable to add payment method.', 'charitable-braintree' ) );
					return false;
				}
			}

			/**
			 * Prepare sale transaction data.
			 */
			$transaction_data = [
				'amount'            => $this->donation->get_total_donation_amount( true ),
				'orderId'           => (string) $this->donation->get_donation_id(),
				'customerId'        => $customer_id,
				'options'           => [
					'submitForSettlement' => true,
				],
				'channel'           => 'Charitable_SP',
				'descriptor'        => [
					'name' => $this->get_descriptor_name(),
					'url'  => substr( $url_parts['host'], 0, 13 ),
				],
				'lineItems'         => [],
				'merchantAccountId' => $this->get_merchant_account_id(),
			];

			if ( isset( $payment_method ) && is_array( $payment_method ) ) {
				$transaction_data['paymentMethodToken'] = $payment_method['token'];

				if ( isset( $payment_method['authentication_id'] ) ) {
					$transaction_data['threeDSecureAuthenticationId'] = $payment_method['authentication_id'];
				}
			} else {
				$transaction_data['paymentMethodNonce'] = $this->get_gateway_value_from_processor( 'token' );
				$transaction_data['options']            = [
					'threeDSecure' => [ 'required' => true ],
				];

				$device_data = $this->get_gateway_value_from_processor( 'device_data' );

				if ( $device_data ) {
					$transaction_data['deviceData'] = $device_data;
				}
			}

			foreach ( $this->donation->get_campaign_donations() as $campaign_donation ) {
				$amount = Charitable_Currency::get_instance()->sanitize_monetary_amount( (string) $campaign_donation->amount, true );

				$transaction_data['lineItems'][] = [
					'kind'        => 'debit',
					'name'        => substr( $campaign_donation->campaign_name, 0, 35 ),
					'productCode' => $campaign_donation->campaign_id,
					'quantity'    => 1,
					'totalAmount' => $amount,
					'unitAmount'  => $amount,
					'url'         => get_permalink( $campaign_donation->campaign_id ),
				];
			}

			error_log( var_export( $transaction_data, true ) );

			/**
			 * Filter the transaction data.
			 *
			 * @since 1.0.0
			 *
			 * @param array                                  $transaction_data The transaction data.
			 * @param Charitable_Braintree_Gateway_Processor $processor        This instance of `Charitable_Braintree_Gateway_Processor`.
			 */
			$transaction_data = apply_filters( 'charitable_braintree_transaction_data', $transaction_data, $this );

			/**
			 * Create sale transaction in Braintree.
			 */
			try {
				$result = $this->braintree->transaction()->sale( $transaction_data );
				error_log( var_export( $result, true ) );

				if ( ! $result->success ) {
					if ( count( $result->errors->deepAll() ) ) {
						charitable_get_notices()->add_error( __( 'Donation not processed successfully in payment gateway.', 'charitable-braintree' ) );

						$errors = '<ul>';

						foreach ( $result->errors->deepAll() as $error ) {
							$errors .= sprintf( '<li>%1$s (%2$s)</li>', $error->message, $error->code );
						}

						$errors .= '</ul>';

						charitable_get_notices()->add_error( $errors );
					} else {
						charitable_get_notices()->add_error(
							sprintf(
								/* translators: %s: transaction status */
								__( 'Your donation was unable to be processed and failed with the following error: %s', 'charitable-braintree' ),
								$result->transaction->status
							)
						);
					}

					return [
						'success'                   => false,
						'gateway_processing_status' => $result->transaction->status,
					];
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
