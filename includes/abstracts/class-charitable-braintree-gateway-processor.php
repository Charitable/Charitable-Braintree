<?php
/**
 * Base Charitable_Braintree_Gateway_Processor class.
 *
 * @package   Charitable Braintree/Classes/Charitable_Stirpe_Gateway_Processor
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

if ( ! class_exists( 'Charitable_Braintree_Gateway_Processor' ) ) :

	/**
	 * Charitable_Braintree_Gateway_Processor
	 *
	 * @since 1.0.0
	 */
	abstract class Charitable_Braintree_Gateway_Processor implements Charitable_Braintree_Gateway_Processor_Interface {

		/**
		 * The donation object.
		 *
		 * @since 1.0.0
		 *
		 * @var   Charitable_Donation
		 */
		protected $donation;

		/**
		 * Donation log instance for this donation.
		 *
		 * @since 1.0.0
		 *
		 * @var   Charitable_Donation_Log
		 */
		protected $donation_log;

		/**
		 * The donor object.
		 *
		 * @since 1.0.0
		 *
		 * @var   Charitable_Donor
		 */
		protected $donor;

		/**
		 * The donation processor object.
		 *
		 * @since 1.0.0
		 *
		 * @var   Charitable_Donation_Processor
		 */
		protected $processor;

		/**
		 * The Braintree gateway model.
		 *
		 * @since 1.0.0
		 *
		 * @var   Charitable_Gateway_Braintree
		 */
		protected $gateway;

		/**
		 * Submitted donation values.
		 *
		 * @since 1.0.0
		 *
		 * @var   array
		 */
		protected $donation_data;

		/**
		 * Set up class instance.
		 *
		 * @since 1.0.0
		 *
		 * @param int                           $donation_id The donation ID.
		 * @param Charitable_Donation_Processor $processor   The donation processor object.
		 */
		public function __construct( $donation_id, Charitable_Donation_Processor $processor ) {
			$this->donation      = new Charitable_Donation( $donation_id );
			$this->donation_log  = $this->donation->log();
			$this->donor         = $this->donation->get_donor();
			$this->gateway       = new Charitable_Gateway_Braintree();
			$this->processor     = $processor;
			$this->donation_data = $this->processor->get_donation_data();
		}

		/**
		 * Set Braintree API key.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean|null $test_mode Whether to get test mode keys. If null, this
		 *                                 will use the current site Test Mode setting.
		 * @param  array        $keys      If set, will use these keys for getting the
		 *                                 instance. Otherwise, will use get_keys().
		 * @return boolean True if the API key is set. False otherwise.
		 */
		public function get_gateway_instance( $test_mode = null, $keys = [] ) {
			return $this->gateway->get_gateway_instance();
		}

		/**
		 * Return the submitted value for a gateway field.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $key    The key of the value we want to get.
		 * @param  mixed[] $values An values in which to search.
		 * @return string|false
		 */
		public function get_gateway_value( $key, $values ) {
			if ( isset( $values['gateways']['braintree'][ $key ] ) ) {
				return $values['gateways']['braintree'][ $key ];
			}

			return false;
		}

		/**
		 * Return the submitted value for a gateway field.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $key The key of the value we want to get.
		 * @return string|false
		 */
		public function get_gateway_value_from_processor( $key ) {
			return $this->get_gateway_value( $key, $this->donation_data );
		}

		/**
		 * Create a new customer in Braintree.
		 *
		 * @since  1.0.0
		 *
		 * @return string|false Customer id if successful.
		 */
		public function create_customer() {
			/**
			 * Filter customer data.
			 *
			 * @since 1.0.0
			 *
			 * @param array                                  $data      Customer data.
			 * @param Charitable_Braintree_Gateway_Processor $processor This instance of `Charitable_Braintree_Gateway_Processor`.
			 */
			$data = apply_filters(
				'charitable_braintree_customer_data',
				[
					'email'     => $this->donor->get_donor_meta( 'email' ),
					'firstName' => $this->donor->get_donor_meta( 'first_name' ),
					'lastName'  => $this->donor->get_donor_meta( 'last_name' ),
					'phone'     => $this->donor->get_donor_meta( 'phone' ),
				],
				$this
			);

			try {
				$result = $this->braintree->customer()->create( $data );

				if ( ! $result->success ) {
					return false;
				}

				if ( is_user_logged_in() ) {
					$meta_postfix = charitable_get_option( 'test_mode' ) ? 'test' : 'live';

					update_metadata( 'donor', $this->donor->donor_id, 'braintree_customer_id_' . $meta_postfix, $result->customer->id );
				}

				return $result->customer->id;

			} catch ( Exception $e ) {
				if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
					error_log( get_class( $e ) );
					error_log( $e->getMessage() . ' [' . $e->getCode() . ']' );
				}

				return false;
			}
		}

		/**
		 * Create a new payment method in Braintree.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $customer_id The customer id.
		 * @return string|false Payment method id if successful.
		 */
		public function create_payment_method( $customer_id ) {
			/**
			 * Filter payment method data.
			 *
			 * @since 1.0.0
			 *
			 * @param array                                  $data      Payment method data.
			 * @param Charitable_Braintree_Gateway_Processor $processor This instance of `Charitable_Braintree_Gateway_Processor`.
			 */
			$data = apply_filters(
				'charitable_braintree_payment_method_data',
				[
					'customerId'         => $customer_id,
					'paymentMethodNonce' => $this->get_gateway_value_from_processor( 'token' ),
					'billingAddress'     => [
						'firstName'         => $this->donor->get_donor_meta( 'first_name' ),
						'lastName'          => $this->donor->get_donor_meta( 'last_name' ),
						'countryCodeAlpha2' => $this->donor->get_donor_meta( 'country' ),
						'firstName'         => $this->donor->get_donor_meta( 'first_name' ),
						'lastName'          => $this->donor->get_donor_meta( 'last_name' ),
						'locality'          => $this->donor->get_donor_meta( 'city' ),
						'postalCode'        => $this->donor->get_donor_meta( 'postcode' ),
						'region'            => $this->donor->get_donor_meta( 'state' ),
						'streetAddress'     => $this->donor->get_donor_meta( 'address' ),
						'extendedAddress'   => $this->donor->get_donor_meta( 'address_2' ),
					],
				],
				$this
			);

			try {
				$result = $this->braintree->paymentMethod()->create( $data );
				return $result->success ? $result->paymentMethod->token : false; // phpcs:ignore

			} catch ( Exception $e ) {
				if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
					error_log( get_class( $e ) );
					error_log( $e->getMessage() . ' [' . $e->getCode() . ']' );
				}

				return false;

			}
		}

		/**
		 * Return the statement_descriptor value.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_descriptor_name() {
			$name     = '';
			$company  = $this->sanitize_description( get_option( 'blogname' ) );
			$campaign = $this->sanitize_description( $this->donation->get_campaigns_donated_to() );
			$length   = strlen( $company );

			$length_combinations = [
				3  => 18,
				7  => 14,
				12 => 9,
			];

			foreach ( $length_combinations as $company_length => $product_length ) {
				if ( $length <= $company_length || 12 === $company_length ) {
					$name  = str_pad( $company, $company_length, '*' );
					$name .= '*';
					$name .= str_pad(
						substr( $campaign, 0, $product_length ),
						$product_length,
						'*'
					);
				}
			}

			/**
			 * Filter the descriptor name.
			 *
			 * @since 1.0.0
			 *
			 * @param string                        $descriptor The default descriptor.
			 * @param Charitable_Donation           $donation   The donation object.
			 * @param Charitable_Donation_Processor $processor  The processor object.
			 */
			return apply_filters( 'charitable_braintree_descriptor_name', $name, $this->donation, $this->processor );
		}

		/**
		 * Return the merchant account id to use for a transaction.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean|null $test_mode Whether to get test mode keys. If null, this
		 *                                 will use the current site Test Mode setting.
		 * @return string
		 */
		public function get_merchant_account_id( $test_mode = null ) {
			if ( is_null( $test_mode ) ) {
				$test_mode = charitable_get_option( 'test_mode' );
			}

			$prefix = $test_mode ? 'test' : 'live';

			return trim( $this->gateway->get_value( $prefix . '_merchant_account_id' ) );
		}

		/**
		 * Sanitize the descriptor name, filtering out any invalid characters.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $descriptor The descriptor name.
		 * @return string
		 */
		public function sanitize_description( $descriptor ) {
			return preg_replace( '([^A-Za-z0-9.+-])', '*', $descriptor );
		}
	}

endif;
