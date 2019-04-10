<?php
/**
 * Charitable_Braintree_Gateway_Processor_One_Time class.
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
			 * Create a customer in the Vault.
			 */
			$customer_id = $this->create_customer();

			if ( ! $customer_id ) {
				return false;
			}

			/**
			 * Prepare sale transaction data.
			 */
			$transaction_data = [
				'amount'     => number_format( $this->donation->get_total_donation_amount( true ), 2 ),
				'orderId'    => (string) $this->donation->get_donation_id(),
				'customerId' => $customer_id,
				'options'    => [
					'submitForSettlement' => true,
				],
				'channel'    => 'Charitable_SP',
				'descriptor' => [
					'name' => substr(
						sprintf( '%s*%s', get_option( 'blogname' ), $this->donation->get_campaigns_donated_to() ),
						0,
						18
					),
					'url'  => substr( $url_parts['host'], 0, 13 ),
				],
				'lineItems'  => [],
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
