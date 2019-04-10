<?php
/**
 * Base Charitable_Braintree_Gateway_Processor class.
 *
 * @package   Charitable Braintree/Classes/Charitable_Stirpe_Gateway_Processor
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
					'email'              => $this->donor->get_donor_meta( 'email' ),
					'firstName'          => $this->donor->get_donor_meta( 'first_name' ),
					'lastName'           => $this->donor->get_donor_meta( 'last_name' ),
					'phone'              => $this->donor->get_donor_meta( 'phone' ),
					'paymentMethodNonce' => $this->get_gateway_value_from_processor( 'token' ),
					'creditCard'         => [
						'billingAddress' => [
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
				],
				$this
			);

			try {
				$result = $this->braintree->customer()->create( $data );

				return $result->success ? $result->customer->id : false;

			} catch ( Exception $e ) {
				if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
					error_log( get_class( $e ) );
					error_log( $e->getMessage() . ' [' . $e->getCode() . ']' );
				}

				return false;
			}
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
					'email'              => $this->donor->get_donor_meta( 'email' ),
					'firstName'          => $this->donor->get_donor_meta( 'first_name' ),
					'lastName'           => $this->donor->get_donor_meta( 'last_name' ),
					'phone'              => $this->donor->get_donor_meta( 'phone' ),
					'paymentMethodNonce' => $this->get_gateway_value_from_processor( 'token' ),
					'creditCard'         => [
						'billingAddress' => [
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
				],
				$this
			);

			try {
				$result = $this->braintree->customer()->create( $data );

				return $result->success ? $result->customer->id : false;

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
		public function get_statement_descriptor() {
			/**
			 * Filter the statement_descriptor.
			 *
			 * @since 1.0.0
			 *
			 * @param string                        $descriptor The default descriptor.
			 * @param Charitable_Donation           $donation   The donation object.
			 * @param Charitable_Donation_Processor $processor  The processor object.
			 */
			return apply_filters( 'charitable_braintree_statement_descriptor', substr( $this->donation->get_campaigns_donated_to(), 0, 22 ), $this->donation, $this->processor );
		}

		/**
		 * Return the description value of the charge.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_charge_description() {
			return html_entity_decode( $this->donation->get_campaigns_donated_to(), ENT_COMPAT, 'UTF-8' );
		}

		/**
		 * Return the charge metadata.
		 *
		 * @since  1.0.0
		 *
		 * @return array
		 */
		public function get_charge_metadata() {
			/**
			 * Filter the charge metadata.
			 *
			 * @since 1.0.0
			 *
			 * @param array                         $metadata   The set of metadata.
			 * @param Charitable_Donation           $donation   The donation object.
			 * @param Charitable_Donation_Processor $processor  The processor object.
			 */
			return apply_filters(
				'charitable_braintree_charge_metadata',
				[
					'email'       => $this->donor->get_email(),
					'donation_id' => $this->donation->ID,
					'name'        => $this->donor->get_name(),
					'phone'       => $this->donor->get_donor_meta( 'phone' ),
					'city'        => $this->donor->get_donor_meta( 'city' ),
					'country'     => $this->donor->get_donor_meta( 'country' ),
					'address'     => $this->donor->get_donor_meta( 'address' ),
					'address_2'   => $this->donor->get_donor_meta( 'address_2' ),
					'postcode'    => $this->donor->get_donor_meta( 'postcode' ),
					'state'       => $this->donor->get_donor_meta( 'state' ),
				],
				$this->donation,
				$this->processor
			);
		}

		/**
		 * Get the donation amount in the smallest common currency unit.
		 *
		 * @since  1.0.0
		 *
		 * @param  float       $amount   The donation amount in dollars.
		 * @param  string|null $currency The currency of the donation. If null, the site currency will be used.
		 * @return int|false Returns integer if valid amount was passed, otherwise false.
		 */
		public static function get_sanitized_donation_amount( $amount, $currency = null ) {
			if ( is_wp_error( $amount ) ) {
				return false;
			}

			/* Unless it's a zero decimal currency, multiply the currency x 100 to get the amount in cents. */
			if ( self::is_zero_decimal_currency( $currency ) ) {
				$amount = $amount * 1;
			} else {
				$amount = $amount * 100;
			}

			return $amount;
		}

		/**
		 * Returns whether the currency is a zero decimal currency.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $currency The currency for the charge. If left blank, will check for the site currency.
		 * @return boolean
		 */
		public static function is_zero_decimal_currency( $currency = null ) {
			if ( is_null( $currency ) ) {
				$currency = charitable_get_currency();
			}

			return in_array( strtoupper( $currency ), self::get_zero_decimal_currencies() );
		}

		/**
		 * Return all zero-decimal currencies supported by Braintree.
		 *
		 * @since  1.0.0
		 *
		 * @return array
		 */
		public static function get_zero_decimal_currencies() {
			return [
				'BIF',
				'CLP',
				'DJF',
				'GNF',
				'JPY',
				'KMF',
				'KRW',
				'MGA',
				'PYG',
				'RWF',
				'VND',
				'VUV',
				'XAF',
				'XOF',
				'XPF',
			];
		}

		/**
		 * Returns the payment source.
		 *
		 * This may return a string, identifying the ID of a payment source such as
		 * a credit card. It may also be an associative array containing the user's
		 * credit card details.
		 *
		 * @see    https://braintree.com/docs/api#create_charge
		 *
		 * @since  1.0.0
		 *
		 * @param  string $customer_id Braintree customer id.
		 * @return false|string|array False if we don't have the data we need, a string if a source or token was
		 *                            available in the request, or an array if card data was passed in the request.
		 */
		public function get_payment_source( $customer_id ) {
			$source = $this->get_gateway_value_from_processor( 'source' );

			if ( $source ) {
				return $source;
			}

			$source = $this->get_gateway_value_from_processor( 'token' );

			if ( ! $source ) {
				return false;
			}

			/* Store the payment source for the Customer, and obtain a Card object from Braintree */
			$card = $this->get_braintree_customer_object( $customer_id )->sources->create( [ 'source' => $source ] );

			return $card->id;
		}

		/**
		 * Return the saved Braintree customer id from the user meta table.
		 *
		 * @since  1.0.0
		 *
		 * @return string|false String if one is set, otherwise false.
		 */
		public function get_saved_braintree_customer_id() {
			$key = charitable_get_option( 'test_mode' ) ? self::STRIPE_CUSTOMER_ID_KEY_TEST : self::STRIPE_CUSTOMER_ID_KEY;

			return $this->donor->$key;
		}

		/**
		 * Save the Braintree customer id for logged in users.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $braintree_customer_id The Braintree customer id.
		 * @return void
		 */
		public function save_braintree_customer_id( $braintree_customer_id ) {
			$key = charitable_get_option( 'test_mode' ) ? self::STRIPE_CUSTOMER_ID_KEY_TEST : self::STRIPE_CUSTOMER_ID_KEY;

			update_user_meta( $this->donor->ID, $key, $braintree_customer_id );
		}

		/**
		 * Return the Braintree Customer object for a particular Braintree customer id.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $braintree_customer_id The Braintree customer id.
		 * @return object|null
		 */
		public function get_braintree_customer_object( $braintree_customer_id ) {
			$customer = wp_cache_get( $braintree_customer_id, 'braintree_customer' );

			if ( false === $customer ) {
				try {
					/* Retrieve the customer object from Braintree. */
					$customer = \Braintree\Customer::retrieve( $braintree_customer_id );

					if ( isset( $customer->deleted ) && $customer->deleted ) {
						$customer = null;
					}
				} catch ( Braintree\Error\InvalidRequest $e ) {
					$customer = null;
				}

				wp_cache_set( $braintree_customer_id, $customer, 'braintree_customer' );
			}

			return $customer;
		}

		/**
		 * Create a Braintree Customer object through the API.
		 *
		 * @since  1.0.0
		 *
		 * @return string|false
		 */
		public function create_braintree_customer() {
			/**
			 * Filter the Braintree customer arguments.
			 *
			 * @since 1.2.2
			 *
			 * @param array                          $args      The customer arguments.
			 * @param Charitable_Donor              $donor     The Donor object.
			 * @param Charitable_Donation_Processor $processor The Donation Procesor helper.
			 */
			$braintree_customer_args = apply_filters(
				'charitable_braintree_customer_args',
				[
					'description' => $this->donor->get_name(),
					'email'       => $this->donor->get_email(),
					'metadata'    => [
						'donor_id' => $this->processor->get_donor_id(),
						'user_id'  => $this->donor->ID,
					],
				],
				$this->donor,
				$this->processor
			);

			try {
				$customer = \Braintree\Customer::create( $braintree_customer_args );

				wp_cache_set( $customer->id, $customer, 'braintree_customer' );

			} catch ( Exception $e ) {
				$body    = $e->getJsonBody();
				$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Something went wrong.', 'charitable-braintree' );

				charitable_get_notices()->add_error( $message );

				return false;
			}

			return $customer->id;
		}

		/**
		 * Returns a card ID for the customer.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $customer  Braintree's customer ID.
		 * @param  string $card_args The customer's card details or token.
		 * @return string|false Card ID or false if Braintree returns an error.
		 */
		public function get_braintree_customer_card_id( $customer, $card_args ) {
			try {
				$customer = $this->get_braintree_customer_object( $customer );
				$card     = $customer->sources->create( [ 'source' => $card_args ] );
			} catch ( Exception $e ) {
				$body    = $e->getJsonBody();
				$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Something went wrong.', 'charitable-braintree' );

				charitable_get_notices()->add_error( $message );

				return false;
			}

			return $card->id;
		}

		/**
		 * Return a token for a shared customer.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $customer Braintree customer id.
		 * @return string|false
		 */
		public function get_braintree_shared_customer_token( $customer ) {
			try {
				$token = \Braintree\Token::create( [ 'customer' => $customer ], $this->options );
			} catch ( Exception $e ) {
				$body    = $e->getJsonBody();
				$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Something went wrong.', 'charitable-braintree' );

				charitable_get_notices()->add_error( $message );

				return false;
			}//end try

			return $token->id;
		}

		/**
		 * Return a Braintree customer id for a customer already existing
		 * on the platform account.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $customer_id The customer id on the platform account.
		 * @return string|false
		 */
		public function get_connected_braintree_customer( $customer_id ) {
			/* First, check if we already have a customer id for the customer on this account. */
			$connected_id = $this->get_saved_connected_braintree_customer();

			if ( $connected_id ) {
				return $connected_id;
			}

			/* Get a token for the customer. */
			$token = $this->get_braintree_shared_customer_token( $customer_id );

			if ( ! $token ) {
				return false;
			}

			try {
				/* Retrieve the Customer object from the platform */
				$original = $this->get_braintree_customer_object( $customer_id );

				/* Add the shared customer to the connected account, using the token above. */
				$customer = \Braintree\Customer::create(
					[
						'email'       => $original->email,
						'description' => $original->description,
						'source'      => $token,
					],
					$this->options
				);

				$this->save_connected_braintree_customer( $customer->id );

				return $customer->id;

			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * For a customer id on the platform account, check if we have already
		 * added it to the connected account.
		 *
		 * @since  1.0.0
		 *
		 * @return string|false
		 */
		public function get_saved_connected_braintree_customer() {
			$key     = charitable_get_option( 'test_mode' ) ? self::STRIPE_CONNECT_CUSTOMER_ID_KEY_TEST : self::STRIPE_CONNECT_CUSTOMER_ID_KEY;
			$meta    = $this->donor->__get( $key );
			$account = $this->options['braintree_account'];

			if ( ! is_array( $meta ) || ! array_key_exists( $account, $meta ) ) {
				return false;
			}

			return $meta[ $account ];
		}

		/**
		 * Save the connected account id of a customer for a given customer
		 * that already exists on the platform account.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $connected_id The customer id on the connected account.
		 * @return string|false
		 */
		public function save_connected_braintree_customer( $connected_id ) {
			$key     = charitable_get_option( 'test_mode' ) ? self::STRIPE_CONNECT_CUSTOMER_ID_KEY_TEST : self::STRIPE_CONNECT_CUSTOMER_ID_KEY;
			$meta    = $this->donor->__get( $key );
			$account = $this->options['braintree_account'];

			if ( ! is_array( $meta ) ) {
				$meta = [];
			}

			$meta[ $account ] = $connected_id;

			update_user_meta( $this->donor->ID, $key, $meta );
		}

		/**
		 * Set the $charges property to empty.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function clear_charges() {
			$this->charges = [];
		}

		/**
		 * Return the results of all charges.
		 *
		 * @since  1.0.0
		 *
		 * @return array
		 */
		public function get_charges() {
			return $this->charges;
		}

		/**
		 * Saves the results of a charge.
		 *
		 * @since  1.0.0
		 *
		 * @param  mixed  $result The result of a Braintree charge.
		 * @param  string $status The status of the charge.
		 * @return void
		 */
		public function save_charge_results( $result, $status ) {
			$this->charges[] = [
				'result' => $result,
				'status' => $status,
			];
		}

		/**
		 * When a charge fails and raises an exception, save the result and
		 * add a notice for the error.
		 *
		 * @since  1.0.0
		 *
		 * @param  Exception $e       Exception thrown.
		 * @param  string    $message Fallback message to be logged if one isn't set in the exception body.
		 * @return void
		 */
		public function save_charge_error( Exception $e, $message ) {
			$body = $e->getJsonBody();

			if ( isset( $body['error']['message'] ) ) {
				$message = $body['error']['message'];
			}

			charitable_get_notices()->add_error( $message );

			$this->save_charge_results( $body, 'error' );
		}

		/**
		 * Log a failed Braintree charge.
		 *
		 * @since  1.0.0
		 *
		 * @param  array $charge_result The charge result.
		 * @return void
		 */
		public function log_error( $charge_result ) {
			$this->donation_log->add( sprintf(
				/* translators: %s: type of error */
				__( 'Braintree error: %s', 'charitable' ),
				'<code>' . $charge_result['result']['error']['type'] . '</code>'
			) );
		}

		/**
		 * Log a successful Braintree charge.
		 *
		 * @since  1.0.0
		 *
		 * @param  array $charge_result The charge result.
		 * @return void
		 */
		public function log_success( $charge_result ) {
			/* Charge includes an application fee. */
			if ( ! is_null( $charge_result->application_fee ) ) {
				$this->log_application_fee( $charge_result );
			}

			/* Charge is on our account (not directly on a connected account). */
			if ( is_null( $charge_result->application ) || ! is_null( $charge_result->destination ) ) {
				$url = sprintf( 'https://dashboard.braintree.com/%spayments/%s',
					$charge_result->livemode ? '' : 'test/',
					$charge_result->id
				);

				$this->donation_log->add( sprintf(
					/* translators: %s: link to Braintree charge details */
					__( 'Braintree charge: %s', 'charitable-braintree' ),
					'<a href="' . $url . '" target="_blank"><code>' . $charge_result->id . '</code></a>'
				) );
			}
		}

		/**
		 * Log an application fee for a charge.
		 *
		 * @since  1.0.0
		 *
		 * @param  array $charge_result The charge result.
		 * @return void
		 */
		public function log_application_fee( $charge_result ) {
			$url = sprintf( 'https://dashboard.braintree.com/%sapplications/fees/%s',
				$charge_result->livemode ? '' : 'test/',
				$charge_result->application_fee
			);

			$this->donation_log->add( sprintf(
				/* translators: %s: link to Braintree application fee details */
				__( 'Braintree application fee: %s', 'charitable-braintree' ),
				'<a href="' . $url . '" target="_blank"><code>' . $charge_result->application_fee . '</code></a>'
			) );
		}
	}

endif;
