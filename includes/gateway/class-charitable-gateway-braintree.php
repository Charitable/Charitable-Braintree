<?php
/**
 * Braintree Gateway class.
 *
 * @package   Charitable Braintree/Classes/Charitable_Gateway_Braintree
 * @copyright Copyright (c) 2019, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Charitable_Gateway_Braintree' ) ) :

	/**
	 * Braintree Gateway.
	 *
	 * @since 1.0.0
	 */
	class Charitable_Gateway_Braintree extends Charitable_Gateway {

		/** The gateway ID. */
		const ID = 'braintree';

		/**
		 * Flags whether the gateway requires credit card fields added to the donation form.
		 *
		 * @since 1.0.0
		 *
		 * @var   boolean
		 */
		protected $credit_card_form;

		/**
		 * Instantiate the gateway class, defining its key values.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			$this->name = apply_filters( 'charitable_gateway_braintree_name', __( 'Braintree', 'charitable-braintree' ) );

			$this->defaults = array(
				'label' => __( 'Braintree', 'charitable-braintree' ),
			);

			$this->supports = array(
				'1.3.0',
			);

			/**
			 * Needed for backwards compatibility with Charitable < 1.3
			 */
			$this->credit_card_form = false;
		}

		/**
		 * Returns the current gateway's ID.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public static function get_gateway_id() {
			return self::ID;
		}

		/**
		 * Register gateway settings.
		 *
		 * @since  1.0.0
		 *
		 * @param  array[] $settings Default array of settings for the gateway.
		 * @return array[]
		 */
		public function gateway_settings( $settings ) {
			$settings = array_merge(
				$settings,
				[
					'section_live_mode' => [
						'title'    => __( 'Live Mode Settings', 'charitable-braintree' ),
						'type'     => 'heading',
						'priority' => 4,
					],
					'live_merchant_id'  => [
						'type'     => 'text',
						'title'    => __( 'Live Merchant ID', 'charitable-braintree' ),
						'priority' => 5,
						'class'    => 'wide',
					],
					'live_public_key'   => [
						'type'     => 'text',
						'title'    => __( 'Live Public Key', 'charitable-braintree' ),
						'priority' => 6,
						'class'    => 'wide',
					],
					'live_private_key'  => [
						'type'     => 'password',
						'title'    => __( 'Live Private Key', 'charitable-braintree' ),
						'priority' => 7,
						'class'    => 'wide',
					],
					'section_test_mode' => [
						'title'    => __( 'Test Mode Settings', 'charitable-braintree' ),
						'type'     => 'heading',
						'priority' => 10,
					],
					'test_merchant_id'  => [
						'type'     => 'text',
						'title'    => __( 'Test Merchant ID', 'charitable-braintree' ),
						'priority' => 11,
						'class'    => 'wide',
					],
					'test_public_key'   => [
						'type'     => 'text',
						'title'    => __( 'Test Public Key', 'charitable-braintree' ),
						'priority' => 12,
						'class'    => 'wide',
					],
					'test_private_key'  => [
						'type'     => 'password',
						'title'    => __( 'Test Private Key', 'charitable-braintree' ),
						'priority' => 13,
						'class'    => 'wide',
					],
				]
			);

			if ( 'missing_endpoint' == $this->get_value( 'webhook_endpoint_status' ) ) {
				$settings['missing_webhook_endpoint'] = [
					'type'     => 'inline-notice',
					'save'     => false,
					'content'  => __( '<p>Charitable has not detected any incoming webhooks from Braintree. <a href="#">Have you added your webhook and sent a test notification?</a></p>', 'charitable-braintree' ),
					'priority' => 3,
				];
			}

			return $settings;
		}

		/**
		 * Register the payment gateway class.
		 *
		 * @since  1.0.0
		 *
		 * @param  string[] $gateways The list of registered gateways.
		 * @return string[]
		 */
		public static function register_gateway( $gateways ) {
			$gateways['braintree'] = 'Charitable_Gateway_Braintree';

			return $gateways;
		}

		/**
		 * Load Braintree scripts as well as our handling scripts.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public static function enqueue_scripts() {
			if ( ! Charitable_Gateways::get_instance()->is_active_gateway( self::get_gateway_id() ) ) {
				return false;
			}

			$gateway = new Charitable_Gateway_Braintree();

			wp_localize_script(
				'charitable-braintree-handler',
				'CHARITABLE_BRAINTREE_VARS',
				[
					'client_token' => $gateway->get_client_token(),
				]
			);

			wp_enqueue_script( 'charitable-braintree-handler' );

			return true;
		}

		/**
		 * Load Braintree as well as our handling scripts.
		 *
		 * @uses    Charitable_Gateway_Braintree::enqueue_scripts()
		 *
		 * @since  1.0.0
		 *
		 * @param  Charitable_Donation_Form $form The current form object.
		 * @return boolean
		 */
		public static function maybe_setup_scripts_in_donation_form( $form ) {
			if ( ! is_a( $form, 'Charitable_Donation_Form' ) ) {
				return false;
			}

			if ( 'make_donation' !== $form->get_form_action() ) {
				return false;
			}

			return self::enqueue_scripts();
		}

		/**
		 * Enqueue the Braintree scripts after a campaign loop if modal donations are in use.
		 *
		 * @uses    Charitable_Gateway_Braintree::enqueue_scripts()
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public static function maybe_setup_scripts_in_campaign_loop() {
			if ( 'modal' !== charitable_get_option( 'donation_form_display', 'separate_page' ) ) {
				return false;
			}

			return self::enqueue_scripts();
		}

		/**
		 * Set up Braintree payment fields.
		 *
		 * @since  1.0.0
		 *
		 * @param  array              $gateway_fields Fields to include in Braintree payment section.
		 * @param  Charitable_Gateway $gateway        Gateway object.
		 * @return array
		 */
		public static function setup_braintree_payment_fields( $gateway_fields, $gateway ) {
			if ( self::get_gateway_id() !== $gateway->get_gateway_id() ) {
				return $gateway_fields;
			}

			return array_merge(
				$gateway_fields,
				[
					'drop_in_container' => [
						'type'     => 'content',
						'content'  => '<div id="charitable-braintree-dropin-container"></div>',
						'priority' => 1,
					],
					'braintree_token'   => [
						'type'     => 'hidden',
						'value'    => '',
						'priority' => 2,
					],
				]
			);
		}

		/**
		 * Return the keys to use.
		 *
		 * This will return the test keys if test mode is enabled. Otherwise, returns
		 * the production keys.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean|null $test_mode Whether to get test mode keys. If null, this
		 *                                 will use the current site Test Mode setting.
		 * @return string[]
		 */
		public function get_keys( $test_mode = null ) {
			if ( is_null( $test_mode ) ) {
				$test_mode = charitable_get_option( 'test_mode' );
			}

			$prefix = $test_mode ? 'test' : 'live';
			return [
				'merchant_id' => trim( $this->get_value( $prefix . '_merchant_id' ) ),
				'public_key'  => trim( $this->get_value( $prefix . '_public_key' ) ),
				'private_key' => trim( $this->get_value( $prefix . '_private_key' ) ),
			];
		}

		/**
		 * Return the Braintree_Gateway instance.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean|null $test_mode Whether to get test mode keys. If null, this
		 *                                 will use the current site Test Mode setting.
		 * @param  array        $keys      If set, will use these keys for getting the
		 *                                 instance. Otherwise, will use get_keys().
		 * @return Braintree_Gateway
		 */
		public function get_gateway_instance( $test_mode = null, $keys = [] ) {
			if ( is_null( $test_mode ) ) {
				$test_mode = charitable_get_option( 'test_mode' );
			}

			if ( empty( $keys ) ) {
				$keys = $this->get_keys( $test_mode );
			}

			return new Braintree_Gateway(
				[
					'environment' => $test_mode ? 'sandbox' : 'production',
					'merchantId'  => $keys['merchant_id'],
					'publicKey'   => $keys['public_key'],
					'privateKey'  => $keys['private_key'],
				]
			);
		}

		/**
		 * Returns a client token.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean|null $test_mode Whether to get test mode keys. If null, this
		 *                                 will use the current site Test Mode setting.
		 * @param  array        $keys      If set, will use these keys for getting the
		 *                                 instance. Otherwise, will use get_keys().
		 * @return string
		 */
		public function get_client_token( $test_mode = null, $keys = [] ) {
			$braintree = $this->get_gateway_instance( $test_mode, $keys );
			$args    = [];

			if ( is_user_logged_in() ) {
				// $args['customerId'] = get_current_user_id();
			}

			return $braintree->clientToken()->generate( $args );
		}

		/**
		 * Return the submitted value for a gateway field.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $key    The key of the field to get.
		 * @param  mixed[] $values Set of values to find the values in.
		 * @return string|false
		 */
		public function get_gateway_value( $key, $values ) {
			return isset( $values['gateways']['braintree'][ $key ] ) ? $values['gateways']['braintree'][ $key ] : false;
		}

		/**
		 * Return the submitted value for a gateway field.
		 *
		 * @since  1.0.0
		 *
		 * @param  string                        $key       The key of the field to get.
		 * @param  Charitable_Donation_Processor $processor Donation processor object.
		 * @return string|false
		 */
		public function get_gateway_value_from_processor( $key, Charitable_Donation_Processor $processor ) {
			return $this->get_gateway_value( $key, $processor->get_donation_data() );
		}

		/**
		 * Validate the submitted credit card details.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean $valid   Whether the donation is valid.
		 * @param  string  $gateway The gateway for the donation.
		 * @param  mixed[] $values  Submitted donation values.
		 * @return boolean
		 */
		public static function validate_donation( $valid, $gateway, $values ) {
			if ( 'braintree' != $gateway ) {
				return $valid;
			}

			if ( ! isset( $values['gateways']['braintree'] ) ) {
				return false;
			}

			if ( ! isset( $values['gateways']['braintree']['braintree_token'] ) ) {
				charitable_get_notices()->add_error( __( 'Missing payment for Braintree payment gateway. Unable to proceed with payment.', 'charitable-braintree' ) );
				return false;
			}

			return true;
		}

		/**
		 * If a Braintree token was submitted, set it to the gateways array.
		 *
		 * @since  1.0.0
		 *
		 * @param  array $fields The filtered values from the donation form submission.
		 * @param  array $submitted The raw POST data.
		 * @return array
		 */
		public static function set_submitted_braintree_token( $fields, $submitted ) {
			$token = isset( $submitted['braintree_token'] ) ? $submitted['braintree_token'] : false;

			$fields['gateways']['braintree']['token'] = $token;

			return $fields;
		}

		/**
		 * Process the donation with the gateway.
		 *
		 * @since  1.0.0
		 *
		 * @param  mixed                         $return      Response to be returned.
		 * @param  int                           $donation_id The donation ID.
		 * @param  Charitable_Donation_Processor $processor   Donation processor object.
		 * @return boolean|array
		 */
		public static function process_donation( $return, $donation_id, $processor ) {
			$gateway   = new Charitable_Gateway_Braintree();
			$keys      = $gateway->get_keys();
			$braintree = $gateway->get_gateway_instance( null, $keys );

			$donation = charitable_get_donation( $donation_id );
			$donor    = $donation->get_donor();
			// $values   = $processor->get_donation_data();

			// $address          = $donor->get_donor_meta( 'address' );
			// $extended_address = $donor->get_donor_meta( 'address_2' );

			// if ( ! empty( $extended_address ) ) {
			// 	$_address         = $address;
			// 	$address          = $extended_address;
			// 	$extended_address = $_address;
			// }

			$url_parts = parse_url( home_url() );

			/**
			 * Prepare sale transaction data.
			 */
			$transaction_data = [
				'amount'             => number_format( $donation->get_total_donation_amount( true ), 2 ),
				'orderId'            => (string) $donation->get_donation_id(),
				'paymentMethodNonce' => $gateway->get_gateway_value_from_processor( 'token', $processor ),
				'options'            => [
					'submitForSettlement' => true,
				],
				'billing'            => [
					'countryCodeAlpha2' => $donor->get_donor_meta( 'country' ),
					'firstName'         => $donor->get_donor_meta( 'first_name' ),
					'lastName'          => $donor->get_donor_meta( 'last_name' ),
					'locality'          => $donor->get_donor_meta( 'city' ),
					'postalCode'        => $donor->get_donor_meta( 'postcode' ),
					'region'            => $donor->get_donor_meta( 'state' ),
					'streetAddress'     => $donor->get_donor_meta( 'address' ),
					'extendedAddress'   => $donor->get_donor_meta( 'address_2' ),
				],
				'channel'            => 'Charitable_SP',
				'customer'           => [
					'email'     => $donor->get_donor_meta( 'email' ),
					'firstName' => $donor->get_donor_meta( 'first_name' ),
					'lastName'  => $donor->get_donor_meta( 'last_name' ),
					'phone'     => $donor->get_donor_meta( 'phone' ),
				],
				'descriptor'         => [
					'name' => substr(
						sprintf( '%s*%s', get_option( 'blogname' ), $donation->get_campaigns_donated_to() ),
						0,
						18
					),
					'url'  => substr( $url_parts['host'], 0, 13 ),
				],
				'lineItems'          => [],
				// 'merchantAccountId'  => $keys['merchant_id'],
			];

			foreach ( $donation->get_campaign_donations() as $campaign_donation ) {
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
				$result = $braintree->transaction()->sale( $transaction_data );

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

				$donation->log()->add(
					sprintf(
						/* translators: %s: link to Braintree transaction details */
						__( 'Braintree transaction: %s', 'charitable-braintree' ),
						'<a href="' . $transaction_url . '" target="_blank"><code>' . $result->transaction->id . '</code></a>'
					)
				);

				$donation->set_gateway_transaction_id( $result->transaction->id );

				$donation->update_status( 'charitable-completed' );

				return true;

			} catch ( Exception $e ) {
				error_log( get_class( $e ) );
				error_log( $e->getMessage() . ' [' . $e->getCode() . ']' );
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
		public function get_statement_descriptor( Charitable_Donation $donation, Charitable_Donation_Processor $processor ) {
			/**
			 * Filter the statement_descriptor.
			 *
			 * @since 1.0.0
			 *
			 * @param string                        $descriptor The default descriptor.
			 * @param Charitable_Donation           $donation   The donation object.
			 * @param Charitable_Donation_Processor $processor  The processor object.
			 */
			return apply_filters( 'charitable_braintree_statement_descriptor', substr( $donation->get_campaigns_donated_to(), 0, 22 ), $donation, $processor );
		}

		/**
		 * Process an IPN request.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public static function process_ipn() {
			/**
			 * Process the IPN.
			 *
			 * @todo
			 */
		}
	}

endif;
