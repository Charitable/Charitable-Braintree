<?php
/**
 * Charitable_Braintree_Gateway_Processor_One_Time class.
 *
 * @package   Charitable Braintree/Classes/Charitable_Braintree_Gateway_Processor
 * @author    Eric Daams
 * @copyright Copyright (c) 2020, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.1
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

			/* Get the customer id. */
			$customer_id            = $this->gateway->get_braintree_customer_id();
			$vaulted_payment_method = $customer_id !== false;

			/* Check if we previously created a customer id for this donation. */
			if ( ! $customer_id ) {
				$customer_id = get_post_meta( $this->donation->ID, 'braintree_customer_id', true );
			}

			if ( ! $customer_id ) {
				/* Create a customer in the Vault. */
				$customer_id = $this->create_customer();

				if ( ! $customer_id ) {
					$this->donation_log->add( __( 'Unable to set up customer in the Braintree Vault.', 'charitable-braintree' ) );
					return false;
				}

				/* Record the customer id. */
				update_post_meta( $this->donation->ID, 'braintree_customer_id', $customer_id );
			}

			/* Prepare sale transaction data. */
			$transaction_data = [
				'amount'            => $this->donation->get_total_donation_amount( true ),
				'orderId'           => (string) $this->donation->get_donation_id(),
				'customerId'        => $customer_id,
				'options'           => [
					'submitForSettlement' => true,
				],
				'channel'           => 'Charitable_SP',
				'descriptor'        => $this->get_descriptor(),
				'lineItems'         => $this->get_transaction_line_items(),
				'merchantAccountId' => $this->get_merchant_account_id(),
			];

			/* Merge in the payment data. */
			$transaction_data = array_merge_recursive(
				$transaction_data,
				$this->get_payment_data()
			);

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

				if ( ! $result->success ) {
					$this->set_transaction_failed_notices( $result );

					return [
						'success'                   => false,
						'gateway_processing_status' => strtoupper( $result->transaction->status ),
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

		/**
		 * Return the payment data to use for a particular transaction.
		 *
		 * @since  1.0.0
		 *
		 * @return array
		 */
		public function get_payment_data() {
			$payment_data = [
				'paymentMethodNonce' => $this->get_gateway_value_from_processor( 'nonce' ),
				'options'            => [
					'storeInVaultOnSuccess' => true,
				],
			];

			/* Maybe include 3D Secure. */
			if ( $this->gateway->get_value( 'enable_3d_secure' ) ) {
				$payment_data['options']['threeDSecure'] = [ 'required' => true ];
			}

			$device_data = $this->get_gateway_value_from_processor( 'device_data' );

			if ( $device_data ) {
				$payment_data['deviceData'] = $device_data;
			}

			return $payment_data;
		}

		/**
		 * Return the line items for the transaction.
		 *
		 * @since  1.0.0
		 *
		 * @return array
		 */
		public function get_transaction_line_items() {
			$items = [];

			foreach ( $this->donation->get_campaign_donations() as $campaign_donation ) {
				$amount  = Charitable_Currency::get_instance()->sanitize_monetary_amount( (string) $campaign_donation->amount );
				$items[] = [
					'kind'        => 'debit',
					'name'        => substr( $campaign_donation->campaign_name, 0, 35 ),
					'productCode' => $campaign_donation->campaign_id,
					'quantity'    => 1,
					'totalAmount' => $amount,
					'unitAmount'  => $amount,
					'url'         => get_permalink( $campaign_donation->campaign_id ),
				];
			}

			return $items;
		}

		/**
		 * Set notices explaining why the transaction failed.
		 *
		 * @since  1.0.0
		 *
		 * @param  Braintree\Result $result The result returned by Braintree.
		 * @return void
		 */
		public function set_transaction_failed_notices( $result ) {
			$notices = charitable_get_notices();

			if ( count( $result->errors->deepAll() ) ) {
				$notices->add_error( __( 'Your donation could not be processed due to the following errors:', 'charitable-braintree' ) );
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
