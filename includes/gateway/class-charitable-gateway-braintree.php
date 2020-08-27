<?php
/**
 * Braintree Gateway class.
 *
 * @package   Charitable Braintree/Classes/Charitable_Gateway_Braintree
 * @copyright Copyright (c) 2020, Studio 164a
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
		 * Boolean flag recording whether the gateway hooks
		 * have been set up.
		 *
		 * @since 1.0.0
		 *
		 * @var   boolean
		 */
		private static $setup = false;

		/**
		 * Live mode gateway instance.
		 *
		 * @since 1.0.0
		 *
		 * @var   Braintree\Gateway|false
		 */
		private $braintree_live;

		/**
		 * Test mode gateway instance.
		 *
		 * @since 1.0.0
		 *
		 * @var   Braintree\Gateway|false
		 */
		private $braintree_test;

		/**
		 * Instantiate the gateway class, defining its key values.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			$this->name = apply_filters( 'charitable_gateway_braintree_name', __( 'Braintree', 'charitable-braintree' ) );

			$this->defaults = [
				'label' => __( 'Braintree', 'charitable-braintree' ),
			];

			$this->supports = [
				'1.3.0',
				'recurring',
				'refunds',
			];

			$this->setup();
		}

		/**
		 * Set up hooks for the class.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function setup() {
			if ( self::$setup ) {
				return;
			}

			self::$setup = true;

			/**
			 * Register our new gateway.
			 */
			add_filter( 'charitable_payment_gateways', [ $this, 'register_gateway' ] );

			/**
			 * Set up Braintree in the donation form.
			 */
			add_action( 'charitable_form_after_fields', [ $this, 'maybe_setup_scripts_in_donation_form' ] );

			/**
			 * Maybe enqueue the Braintree scripts after a campaign loop, if modal donations are in use.
			 */
			add_action( 'charitable_campaign_loop_after', [ $this, 'maybe_setup_scripts_in_campaign_loop' ] );

			/**
			 * Set up Braintree payment fields.
			 */
			add_action( 'charitable_donation_form_gateway_fields', [ $this, 'setup_braintree_payment_fields' ], 10, 2 );

			/**
			 * Validate the donation form submission before processing.
			 */
			add_filter( 'charitable_validate_donation_form_submission_gateway', [ $this, 'validate_donation' ], 10, 3 );

			/**
			 * Also make sure that the Braintree token is picked up in the values array.
			 */
			add_filter( 'charitable_donation_form_submission_values', [ $this, 'add_hidden_braintree_fields_to_data' ], 10, 2 );

			/**
			 * Process the donation.
			 */
			add_filter( 'charitable_process_donation_braintree', [ $this, 'process_donation' ], 10, 3 );

			/**
			 * Refund a donation from the dashboard.
			 */
			add_action( 'charitable_process_refund_braintree', [ $this, 'refund_donation_from_dashboard' ] );

			/**
			 * Subscription cancellations.
			 */
			add_filter( 'charitable_recurring_can_cancel_braintree', [ $this, 'is_subscription_cancellable' ], 10, 2 );
			add_action( 'charitable_process_cancellation_braintree', [ $this, 'cancel_subscription' ], 10, 2 );
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
					'section_live_mode'        => [
						'title'    => __( 'Live Mode Settings', 'charitable-braintree' ),
						'type'     => 'heading',
						'priority' => 4,
					],
					'live_merchant_id'         => [
						'type'     => 'text',
						'title'    => __( 'Live Merchant ID', 'charitable-braintree' ),
						'priority' => 5,
						'class'    => 'wide',
					],
					'live_public_key'          => [
						'type'     => 'text',
						'title'    => __( 'Live Public Key', 'charitable-braintree' ),
						'priority' => 6,
						'class'    => 'wide',
					],
					'live_private_key'         => [
						'type'     => 'password',
						'title'    => __( 'Live Private Key', 'charitable-braintree' ),
						'priority' => 7,
						'class'    => 'wide',
					],
					'live_merchant_account_id' => [
						'type'     => 'select',
						'title'    => __( 'Live Merchant Account', 'charitable-braintree' ),
						'priority' => 8,
						'class'    => 'wide',
						'options'  => $this->get_merchant_accounts( false ),
						'help'     => sprintf(
							/* translators: %s: link to create new merchant account */
							__( 'Create a new <a href="%s" target="_blank">merchant account in Braintree</a>.', 'charitable-braintree' ),
							charitable_braintree_get_new_merchant_account_link( false )
						),
					],
					'live_fraud_protection' => [
						'title'    => __( 'Enable Advanced Fraud Tools', 'charitable-braintree' ),
						'type'     => 'radio',
						'priority' => 9,
						'default'  => 'disabled',
						'options'  => [
							'disabled' => __( 'Disable', 'charitable-braintree' ),
							'enabled'  => __( 'Enable', 'charitable-braintree' ),
						],
						'help'     => sprintf(
							/* translators: %s: link to create new merchant account */
							__( 'To use Advanced Fraud Tools, you must first <a href="%s" target="_blank">enable it in your Braintree account</a>.', 'charitable-braintree' ),
							charitable_braintree_get_fraud_tools_link( false )
						)
					],
					'section_test_mode'        => [
						'title'    => __( 'Test Mode Settings', 'charitable-braintree' ),
						'type'     => 'heading',
						'priority' => 10,
					],
					'test_merchant_id'         => [
						'type'     => 'text',
						'title'    => __( 'Test Merchant ID', 'charitable-braintree' ),
						'priority' => 11,
						'class'    => 'wide',
					],
					'test_public_key'          => [
						'type'     => 'text',
						'title'    => __( 'Test Public Key', 'charitable-braintree' ),
						'priority' => 12,
						'class'    => 'wide',
					],
					'test_private_key'         => [
						'type'     => 'password',
						'title'    => __( 'Test Private Key', 'charitable-braintree' ),
						'priority' => 13,
						'class'    => 'wide',
					],
					'test_merchant_account_id' => [
						'type'     => 'select',
						'title'    => __( 'Test Merchant Account', 'charitable-braintree' ),
						'priority' => 14,
						'class'    => 'wide',
						'options'  => $this->get_merchant_accounts( true ),
						'help'     => sprintf(
							/* translators: %s: link to create new merchant account */
							__( 'Create a new <a href="%s" target="_blank">merchant account in Braintree</a>.', 'charitable-braintree' ),
							charitable_braintree_get_new_merchant_account_link( true )
						),
					],
					'test_fraud_protection' => [
						'title'    => __( 'Enable Advanced Fraud Tools', 'charitable-braintree' ),
						'type'     => 'radio',
						'priority' => 16,
						'default'  => 'disabled',
						'options'  => [
							'disabled' => __( 'Disable', 'charitable-braintree' ),
							'enabled'  => __( 'Enable', 'charitable-braintree' ),
						],
						'help'     => sprintf(
							/* translators: %s: link to create new merchant account */
							__( 'To use Advanced Fraud Tools, you must first <a href="%s" target="_blank">enable it in your Braintree account</a>.', 'charitable-braintree' ),
							charitable_braintree_get_fraud_tools_link( true )
						)
					],
					'section_payment_methods' => [
						'title'    => __( 'Payment Methods', 'charitable-braintree' ),
						'type'     => 'heading',
						'priority' => 20,
					],
					'payment_methods_note'    => [
						'type'     => 'content',
						'priority' => 21,
						'content'  => '<div class="charitable-settings-notice" style="margin-top:0;">
										<p>' . __( 'Additional payment methods <strong>must be activated in your Braintree account</strong> as well as enabling them below.', 'charitable-braintree' ) . '</p>
										<p>' . __( 'To enable payment methods in Braintree, click on the gear icon in the top right, then click on Processing. If a payment method is not listed, it is not available for your account.', 'charitable-braintree' ) . '</p>
										</div>'
					],
					'enable_paypal'           => [
						'type'     => 'checkbox',
						'title'    => __( 'Enable payment with PayPal', 'charitable-braintree' ),
						'priority' => 21,
					],
					'enable_venmo'            => [
						'type'     => 'checkbox',
						'title'    => __( 'Enable payment with Venmo', 'charitable-braintree' ),
						'priority' => 22,
					],
					'enable_applepay'        => [
						'type'     => 'checkbox',
						'title'    => __( 'Enable payment with Apple Pay', 'charitable-braintree' ),
						'priority' => 23,
					],
					'enable_googlepay'       => [
						'type'     => 'checkbox',
						'title'    => __( 'Enable payment with Google Pay', 'charitable-braintree' ),
						'priority' => 24,
					],
					'googlepay_merchant_id'  => [
						'type'     => 'text',
						'title'    => __( 'Google Pay merchant ID', 'charitable-braintree' ),
						'priority' => 24.5,
						'attrs'    => [
							'data-trigger-key'   => '#charitable_settings_gateways_braintree_enable_googlepay',
							'data-trigger-value' => 'checked',
						],
					],
					'section_3d_secure' => [
						'title'    => __( '3D Secure', 'charitable-braintree' ),
						'type'     => 'heading',
						'priority' => 30,
					],
					'enable_3d_secure'       => [
						'type'     => 'checkbox',
						'title'    => __( 'Enable 3D Secure', 'charitable-braintree' ),
						'priority' => 31,
					],
				]
			);

			if ( class_exists( 'Charitable_Recurring' ) ) {
				$settings = array_merge(
					$settings,
					[
						'section_recurring_billing' => [
							'title'    => __( 'Recurring Billing', 'charitable-braintree' ),
							'type'     => 'heading',
							'priority' => 40,
						],
						'default_live_plans'        => [
							'type'      => 'braintree-plans',
							'base_path' => charitable_braintree()->get_path( 'includes', true ) . 'admin/views/',
							'title'     => __( 'Default Live Plans', 'charitable-braintree' ),
							'priority'  => 41,
							'test_mode' => false,
							'help'      => __( 'Select default Braintree plans to use for any subscriptions created by Charitable. You can override this on a per-campaign basis.', 'charitable-braintree' ),
						],
						'default_test_plans'        => [
							'type'      => 'braintree-plans',
							'base_path' => charitable_braintree()->get_path( 'includes', true ) . 'admin/views/',
							'title'     => __( 'Default Test Plans', 'charitable-braintree' ),
							'priority'  => 42,
							'test_mode' => true,
							'help'      => __( 'Select default Braintree plans to use for any subscriptions created by Charitable. You can override this on a per-campaign basis.', 'charitable-braintree' ),
						],
					]
				);
			}

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
		public function register_gateway( $gateways ) {
			$gateways['braintree'] = 'Charitable_Gateway_Braintree';

			return $gateways;
		}

		/**
		 * Return whether to use Advanced Fraud Protection.
		 *
		 * If Advanced Fraud Protection is disabled, this will return false.
		 * If Advanced Fraud Protection is enabled, this will return 'paypal' by
		 * default, but can be filtered to return 'kount' instead.
		 *
		 * @since  1.0.0
		 *
		 * @return string|int
		 */
		public function get_fraud_protection() {
			$key = charitable_get_option( 'test_mode' ) ? 'test_fraud_protection' : 'live_fraud_protection';

			if ( 'disabled' === $this->get_value( $key ) ) {
				return 0;
			}

			/**
			 * Return the advanced fraud protection tool to use.
			 *
			 * @see https://braintree.github.io/braintree-web-drop-in/docs/current/module-braintree-web-drop-in.html#~dataCollectorOptions
			 *
			 * @since 1.0.0
			 *
			 * @param string $tool Return the fraud protection tool. This should be one
			 *                     of the options specified for the dataCollectorOptions
			 *                     property in the drop-in UI. See link above.
			 */
			return apply_filters( 'charitable_braintree_advanced_fraud_protection', 'paypal' );
		}

		/**
		 * Load Braintree scripts as well as our handling scripts.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function enqueue_scripts() {
			if ( ! Charitable_Gateways::get_instance()->is_active_gateway( self::get_gateway_id() ) ) {
				return false;
			}

			$campaign_id = charitable_get_current_campaign_id();

			wp_localize_script(
				'charitable-braintree-handler',
				'CHARITABLE_BRAINTREE_VARS',
				[
					'client_token'          => $this->get_client_token(),
					'paypal'                => (int) $this->get_value( 'enable_paypal' ),
					'venmo'                 => (int) $this->get_value( 'enable_venmo' ),
					'applepay'              => (int) $this->get_value( 'enable_applepay' ),
					'googlepay'             => (int) $this->get_value( 'enable_googlepay' ),
					'googlepay_merchant_id' => $this->get_value( 'googlepay_merchant_id' ),
					'three_d_secure'        => (int) $this->get_value( 'enable_3d_secure' ),
					'description'           => $campaign_id ? sprintf( __( 'Donation to %s', 'charitable-braintree' ), get_the_title( $campaign_id ) ) : __( 'Donation', 'charitable-braintree' ),
					'data_collector'        => $this->get_fraud_protection(),
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
		public function maybe_setup_scripts_in_donation_form( $form ) {
			if ( ! is_a( $form, 'Charitable_Donation_Form' ) ) {
				return false;
			}

			if ( 'make_donation' !== $form->get_form_action() ) {
				return false;
			}

			return $this->enqueue_scripts();
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
		public function maybe_setup_scripts_in_campaign_loop() {
			if ( 'modal' !== charitable_get_option( 'donation_form_display', 'separate_page' ) ) {
				return false;
			}

			return $this->enqueue_scripts();
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
		public function setup_braintree_payment_fields( $gateway_fields, $gateway ) {
			if ( self::get_gateway_id() !== $gateway->get_gateway_id() ) {
				return $gateway_fields;
			}

			$fields = [
				'drop_in_container' => [
					'type'     => 'content',
					'content'  => '<div id="charitable-braintree-dropin-container"></div>',
					'priority' => 1,
				],
				'braintree_nonce'   => [
					'type'     => 'hidden',
					'value'    => '',
					'priority' => 2,
				],
			];

			if ( 0 !== $this->get_fraud_protection() ) {
				$fields['braintree_device_data'] = [
					'type'     => 'hidden',
					'value'    => '',
					'priority' => 2,
				];
			}

			if ( $this->get_value( 'enable_3d_secure' ) ) {
				$fields['braintree_authentication_id'] = [
					'type'     => 'hidden',
					'value'    => '',
					'priority' => 2,
				];
			}

			return array_merge( $gateway_fields, $fields );
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
				'merchant_id'      => trim( $this->get_value( $prefix . '_merchant_id' ) ),
				'public_key'       => trim( $this->get_value( $prefix . '_public_key' ) ),
				'private_key'      => trim( $this->get_value( $prefix . '_private_key' ) ),
				'merchant_account' => trim( $this->get_value( $prefix . '_merchant_account_id' ) ),
			];
		}

		/**
		 * Return the Braintree\Gateway instance.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean|null $test_mode Whether to get test mode keys. If null, this
		 *                                 will use the current site Test Mode setting.
		 * @param  array        $keys      If set, will use these keys for getting the
		 *                                 instance. Otherwise, will use get_keys().
		 * @return Braintree\Gateway|false Braintree\Gateway instance if keys are set. False otherwise.
		 */
		public function get_gateway_instance( $test_mode = null, $keys = [] ) {
			if ( is_null( $test_mode ) ) {
				$test_mode = charitable_get_option( 'test_mode' );
			}

			$prop = $test_mode ? 'braintree_test' : 'braintree_live';

			if ( ! isset( $this->$prop ) ) {
				if ( empty( $keys ) ) {
					$keys = $this->get_keys( $test_mode );
				}

				if ( empty( $keys['merchant_id'] ) || empty( $keys['public_key'] ) || empty( $keys['private_key'] ) ) {
					$this->$prop = false;

					return false;
				}

				$this->$prop = new Braintree\Gateway(
					[
						'environment' => $test_mode ? 'sandbox' : 'production',
						'merchantId'  => $keys['merchant_id'],
						'publicKey'   => $keys['public_key'],
						'privateKey'  => $keys['private_key'],
					]
				);
			}

			return $this->$prop;
		}

		/**
		 * Returns merchant accounts.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean $test_mode Whether to return merchant accounts for sandbox or live.
		 * @param  array   $keys      If set, will use these keys for getting the instance.
		 *                            Otherwise, will use get_keys().
		 * @return string[]
		 */
		public function get_merchant_accounts( $test_mode, $keys = [] ) {
			$options   = [];
			$braintree = $this->get_gateway_instance( $test_mode, $keys );

			if ( ! $braintree ) {
				return $options;
			}

			$currency = charitable_get_currency();

			try {
				$merchant_accounts = $braintree->merchantAccount()->all();

				foreach ( $merchant_accounts as $merchant_account ) {
					if ( $currency != $merchant_account->currencyIsoCode ) { // phpcs:ignore
						continue;
					}

					if ( $merchant_account->default ) {
						$label = sprintf(
							/* translators: %s: merchant account id */
							__( '%s - default', 'charitable-braintree' ),
							$merchant_account->id
						);
					} else {
						$label = $merchant_account->id;
					}

					$options[ $merchant_account->id ] = $label;
				}
			} catch ( Exception $e ) {
				if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
					error_log( get_class( $e ) );
					error_log( $e->getMessage() . ' [' . $e->getCode() . ']' );
				}
			}

			return $options;
		}

		/**
		 * Return a particular merchant account.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $merchant_account_id The merchant account id.
		 * @param  boolean $test_mode           Whether to return merchant accounts for sandbox or live.
		 * @param  array   $keys                If set, will use these keys for getting the instance.
		 *                                      Otherwise, will use get_keys().
		 * @return Braintree\MerchantAccount|null
		 */
		public function get_merchant_account( $merchant_account_id, $test_mode, $keys = [] ) {
			$braintree = $this->get_gateway_instance( $test_mode, $keys );

			if ( ! $braintree ) {
				return null;
			}

			try {
				return $braintree->merchantAccount()->find( $merchant_account_id );
			} catch ( Exception $e ) {
				if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
					error_log( get_class( $e ) );
					error_log( $e->getMessage() . ' [' . $e->getCode() . ']' );
				}

				return null;
			}
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
		 * @return string|false Returns false if one or more keys are empty.
		 */
		public function get_client_token( $test_mode = null, $keys = [] ) {
			$braintree = $this->get_gateway_instance( $test_mode, $keys );

			if ( ! $braintree ) {
				return false;
			}

			$args = [
				'merchantAccountId' => $this->get_merchant_account_id( $test_mode ),
			];

			$customer_id = $this->get_braintree_customer_id( $test_mode );

			if ( $customer_id ) {
				$args['customerId'] = $customer_id;
			}

			return $braintree->clientToken()->generate( $args );
		}

		/**
		 * Returns the Braintree customer id for the current donor.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean|null $test_mode Whether to get test mode keys. If null, this
		 *                                 will use the current site Test Mode setting.
		 * @return string|false Returns a string if a customer id is set. Otherwise returns false.
		 */
		public function get_braintree_customer_id( $test_mode = null ) {
			if ( is_null( $test_mode ) ) {
				$test_mode = charitable_get_option( 'test_mode' );
			}

			if ( ! is_user_logged_in() ) {
				return false;
			}

			$donor_id = charitable_get_user( get_current_user_id() )->get_donor_id();

			if ( ! $donor_id ) {
				return false;
			}

			$meta_postfix = $test_mode ? 'test' : 'live';
			$customer_id  = get_metadata( 'donor', $donor_id, 'braintree_customer_id_' . $meta_postfix, true );

			if ( ! $customer_id ) {
				return false;
			}

			try {
				$this->get_gateway_instance( $test_mode )->customer()->find( $customer_id );
				return $customer_id;
			} catch ( Braintree\Exception\NotFound $e ) {
				return false;
			}
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

			return trim( $this->get_value( $prefix . '_merchant_account_id' ) );
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
		public function validate_donation( $valid, $gateway, $values ) {
			if ( 'braintree' != $gateway ) {
				return $valid;
			}

			if ( ! isset( $values['gateways']['braintree'] ) ) {
				return false;
			}

			if ( ! isset( $values['gateways']['braintree']['braintree_nonce'] ) ) {
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
		public function add_hidden_braintree_fields_to_data( $fields, $submitted ) {
			$nonce = isset( $submitted['braintree_nonce'] ) ? $submitted['braintree_nonce'] : false;

			$fields['gateways']['braintree']['nonce'] = $nonce;

			if ( isset( $submitted['braintree_device_data'] ) ) {
				$fields['gateways']['braintree']['device_data'] = $submitted['braintree_device_data'];
			}

			if ( isset( $submitted['braintree_authentication_id'] ) ) {
				$fields['gateways']['braintree']['authentication_id'] = $submitted['braintree_authentication_id'];
			}

			return $fields;
		}

		/**
		 * Checks whether the donation being processed is recurring.
		 *
		 * @since  1.0.0
		 *
		 * @param  Charitable_Donation_Processor $processor The Donation Processor helper.
		 * @return boolean
		 */
		public function is_recurring_donation( Charitable_Donation_Processor $processor ) {
			return $processor->get_donation_data_value( 'donation_plan', false );
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
		public function process_donation( $return, $donation_id, $processor ) {
			if ( $this->is_recurring_donation( $processor ) ) {
				/**
				 * Filter the processor used for handling recurring donations.
				 *
				 * @since 1.0.0
				 *
				 * @param string                        $class     The name of the Braintree gateway processor class.
				 * @param Charitable_Donation_Processor $processor The Donation Processor helper.
				 */
				$processor_class = apply_filters( 'charitable_braintree_gateway_processor_recurring', 'Charitable_Braintree_Gateway_Processor_Recurring', $processor );
			} else {
				/**
				 * Filter the processor used for handling one time donations.
				 *
				 * @since 1.0.0
				 *
				 * @param string                        $class     The name of the Braintree gateway processor class.
				 * @param Charitable_Donation_Processor $processor The Donation Processor helper.
				 */
				$processor_class = apply_filters( 'charitable_braintree_gateway_processor_one_time', 'Charitable_Braintree_Gateway_Processor_One_Time', $processor );
			}

			$gateway_processor = new $processor_class( $donation_id, $processor );

			/* Ensure we have a valid processor. */
			if ( ! $gateway_processor instanceof Charitable_Braintree_Gateway_Processor ) {
				$gateway_processor = new Charitable_Braintree_Gateway_Processor_One_Time( $donation_id, $processor );
			}

			return $gateway_processor->run();
		}

		/**
		 * Check whether a particular donation can be refunded automatically in Braintree.
		 *
		 * @since  1.0.0
		 *
		 * @param  Charitable_Donation $donation The donation object.
		 * @return boolean
		 */
		public function is_donation_refundable( Charitable_Donation $donation ) {
			$private_key = $donation->get_test_mode( false ) ? 'test_private_key' : 'live_private_key';

			if ( ! $this->get_value( $private_key ) ) {
				return false;
			}

			return false != $donation->get_gateway_transaction_id();
		}

		/**
		 * Process a refund initiated in the WordPress dashboard.
		 *
		 * @since  1.0.0
		 *
		 * @param  int $donation_id The donation ID.
		 * @return boolean
		 */
		public function refund_donation_from_dashboard( $donation_id ) {
			$donation = charitable_get_donation( $donation_id );

			if ( ! $donation ) {
				return false;
			}

			$transaction = $donation->get_gateway_transaction_id();

			if ( ! $transaction ) {
				return false;
			}

			$gateway   = new Charitable_Gateway_Braintree();
			$test_mode = $donation->get_test_mode( false );
			$braintree = $gateway->get_gateway_instance( $test_mode );

			try {
				$result = $braintree->transaction()->refund( $transaction );

				update_post_meta( $donation_id, '_braintree_refunded', true );
				update_post_meta( $donation_id, '_braintree_refund_id', $result->transaction->id );

				$refund_url = sprintf(
					'https://%sbraintreegateway.com/merchants/%s/transactions/%s',
					$test_mode ? 'sandbox.' : '',
					$test_mode ? $gateway->get_value( 'test_merchant_id' ) : $gateway->get_value( 'live_merchant_id' ),
					$result->transaction->id
				);

				$donation->log()->add(
					sprintf(
						/* translators: %s: transaction reference. */
						__( 'Braintree refund transaction ID: %s', 'charitable-braintree' ),
						'<a href="' . $refund_url . '" target="_blank"><code>' . $result->transaction->id . '</code></a></code>'
					)
				);

				return true;
			} catch ( Exception $e ) {
				$donation->log()->add(
					sprintf(
						/* translators: %s: error message. */
						__( 'Braintree refund failed: %s', 'charitable-braintree' ),
						$e->message
					)
				);

				return false;
			}
		}

		/**
		 * Check whether a recurring donation can be cancelled automatically in Braintree.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean                       $can_cancel Whether the subscription can be cancelled.
		 * @param  Charitable_Recurring_Donation $donation The donation object.
		 * @return boolean
		 */
		public function is_subscription_cancellable( $can_cancel, Charitable_Recurring_Donation $donation ) {
			if ( ! $can_cancel ) {
				return $can_cancel;
			}

			$private_key = $donation->get_test_mode( false ) ? 'test_private_key' : 'live_private_key';

			if ( ! charitable_get_option( [ 'gateways_braintree', $private_key ] ) ) {
				return false;
			}

			return ! empty( $donation->get_gateway_subscription_id() );
		}

		/**
		 * Cancel a subscription.
		 *
		 * This can be triggered via the WordPress dashboard when editing a recurring
		 * donation, or via the user's own account area.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean                       $cancelled Whether the subscription was cancelled successfully in the gateway.
		 * @param  Charitable_Recurring_Donation $donation  The recurring donation object.
		 * @return boolean
		 */
		public function cancel_subscription( $cancelled, Charitable_Recurring_Donation $donation ) {
			$subscription_id = $donation->get_gateway_subscription_id();

			if ( ! $subscription_id ) {
				return false;
			}

			$gateway   = new Charitable_Gateway_Braintree();
			$braintree = $gateway->get_gateway_instance( $donation->get_test_mode( false ) );

			try {
				$braintree->subscription()->cancel( $subscription_id );

				$donation->log()->add( __( 'Subscription cancelled in Braintree.', 'charitable-braintree' ) );

				$cancelled = true;
			} catch ( Exception $e ) {
				$donation->log()->add(
					sprintf(
						/* translators: %s: error message */
						__( 'Braintree cancellation failed: %1$s [%2$s]', 'charitable-braintree' ),
						$e->getMessage(),
						$e->getCode()
					)
				);

				$cancelled = false;
			} finally {
				return $cancelled;
			}
		}
	}

endif;
