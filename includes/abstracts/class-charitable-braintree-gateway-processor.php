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
		 * Get object property.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $prop The propery to get.
		 * @return mixed
		 */
		public function __get( $prop ) {
			return $this->$prop;
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
		 * Return the payment data to use for a particular transaction or subscription.
		 *
		 * When a payment method already exists in the vault, the transaction is made using
		 * the nonce. When it does not yet exist in the vault, we first create the payment
		 * method, which returns an array containing the token and possibly an authenctication
		 * id, if 3D Secure is being used.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $customer_id            The customer id.
		 * @param  boolean $vaulted_payment_method Whether the payment method has already been vaulted.
		 * @return array
		 */
		public function get_payment_data( $customer_id, $vaulted_payment_method ) {
			$payment_data = [];

			if ( ! $vaulted_payment_method ) {
				/* Create a payment method in the Vault, adding it to the customer. */
				$payment_method = $this->create_payment_method( $customer_id );

				if ( ! $payment_method ) {
					$this->donation_log->add( __( 'Unable to add payment method.', 'charitable-braintree' ) );
					return false;
				}

				$payment_data = [ 'paymentMethodToken' => $payment_method ];

				if ( $this->get_gateway_value_from_processor( 'authentication_id' ) ) {
					$payment_data['threeDSecureAuthenticationId'] = $this->get_gateway_value_from_processor( 'authentication_id' );
				}

				return $payment_data;
			}

			$payment_data = [
				'paymentMethodNonce' => $this->get_gateway_value_from_processor( 'nonce' ),
			];

			/* Maybe include 3D Secure. */
			if ( $this->gateway->get_value( 'enable_3d_secure' ) ) {
				$payment_data['options'] = [
					'threeDSecure' => [ 'required' => true ],
				];
			}

			$device_data = $this->get_gateway_value_from_processor( 'device_data' );

			if ( $device_data ) {
				$payment_data['deviceData'] = $device_data;
			}

			return $payment_data;
		}

		/**
		 * Create a new payment method in Braintree.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $customer_id The customer id.
		 * @return string|false Payment method token if successful.
		 */
		public function create_payment_method( $customer_id ) {
			$data = [
				'customerId'         => $customer_id,
				'paymentMethodNonce' => $this->get_gateway_value_from_processor( 'nonce' ),
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
			];

			$device_data = $this->get_gateway_value_from_processor( 'device_data' );

			if ( $device_data ) {
				$data['deviceData'] = $device_data;
				$data['options']    = [
					'verifyCard'                    => true,
					'verificationMerchantAccountId' => $this->get_merchant_account_id(),
				];
			}

			/**
			 * Filter payment method data.
			 *
			 * @since 1.0.0
			 *
			 * @param array                                  $data      Payment method data.
			 * @param Charitable_Braintree_Gateway_Processor $processor This instance of `Charitable_Braintree_Gateway_Processor`.
			 */
			$data = apply_filters( 'charitable_braintree_payment_method_data', $data, $this );

			try {
				$result = $this->braintree->paymentMethod()->create( $data );

				if ( $result->success && $this->three_d_verified( $result->paymentMethod ) ) { // phpcs:ignore
					return $result->paymentMethod->token; // phpcs:ignore
				}

				return false;
			} catch ( Exception $e ) {
				if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
					error_log( get_class( $e ) );
					error_log( $e->getMessage() . ' [' . $e->getCode() . ']' );
				}

				return false;
			}
		}

		/**
		 * Checks whether there was a 3D verification check and, if there was,
		 * whether it was successful.
		 *
		 * @since  1.0.0
		 *
		 * @param  object $payment_method The payment method received from Braintree.
		 * @return boolean
		 */
		public function three_d_verified( $payment_method ) {
			/* If there was no verification or no 3D info, return true. */
			if ( ! isset( $payment_method->verification ) || ! isset( $payment_method->verification->threeDSecureInfo ) ) {
				return true;
			}

			$this->donation_log->add(
				sprintf(
					/* translators: %s: verification status */
					__( '3D verification status: %s', 'charitable-braintree' ),
					$payment_method->verification->threeDSecureInfo->status
				)
			);

			/**
			 * Filter the statuses that will result in a verification being
			 * returned as failed.
			 *
			 * @see https://developers.braintreepayments.com/guides/3d-secure/server-side/php#status-codes
			 *
			 * @since 1.0.0
			 *
			 * @param array $statuses The statuses that will result in failure.
			 */
			$failed_statuses = apply_filters(
				'charitable_braintree_3dsecure_failed_statuses',
				[
					'authenticate_error',
					'authenticate_failed',
					'authenticate_signature_verification_failed',
					'authenticate_unable_to_authenticate',
					'lookup_enrolled',
					'challenge_required',
					'authenticate_rejected',
				]
			);

			if ( ! in_array( $payment_method->verification->threeDSecureInfo->status, $failed_statuses ) ) {
				return true;
			}

			charitable_get_notices()->add_error(
				__( 'Your card details did not pass the payment processor\'s 3D Secure verification check. Please retry your donation with an alternative payment method.', 'charitable-braintree' )
			);

			return false;
		}

		/**
		 * Get the descriptor array for a transaction.
		 *
		 * @since  1.0.0
		 *
		 * @return array
		 */
		public function get_descriptor() {
			$url_parts = parse_url( home_url() );

			return [
				'name' => $this->get_descriptor_name(),
				'url'  => substr( $url_parts['host'], 0, 13 ),
			];
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
				if ( $length <= $company_length || 12 <= $company_length ) {
					$name    = str_pad(
						substr( $company, 0, $company_length ),
						$company_length,
						' '
					);
					$name .= '*';
					$name .= str_pad(
						substr( $campaign, 0, $product_length ),
						$product_length,
						' '
					);
					break;
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
			return $this->gateway->get_merchant_account_id( $test_mode );
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
			return preg_replace( '([^A-Za-z0-9.+-])', ' ', $descriptor );
		}
	}

endif;
