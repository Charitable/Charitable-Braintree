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
				'recurring',
				'refunds',
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

			if ( class_exists( 'Charitable_Recurring' ) ) {
				$settings = array_merge(
					$settings,
					[
						'section_recurring_billing' => [
							'title'    => __( 'Recurring Billing', 'charitable-braintree' ),
							'type'     => 'heading',
							'priority' => 15,
						],
						'default_live_plan'         => [
							'type'     => 'select',
							'title'    => __( 'Default Live Plan', 'charitable-braintree' ),
							'priority' => 16,
							'options'  => $this->get_plans( false, __( 'Select a default plan', 'charitable-braintree' ) ),
							'help'     => __( 'Select a default plan to use for any subscriptions created by Charitable. You can override this on a per-campaign basis.', 'charitable-braintree' ),
						],
						'default_test_plan'         => [
							'type'     => 'select',
							'title'    => __( 'Default Test Plan', 'charitable-braintree' ),
							'priority' => 16,
							'options'  => $this->get_plans( true, __( 'Select a default plan', 'charitable-braintree' ) ),
							'help'     => __( 'Select a default plan to use for any subscriptions created by Charitable. You can override this on a per-campaign basis.', 'charitable-braintree' ),
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
		 * @return Braintree_Gateway|false Braintree_Gateway instance if keys are set. False otherwise.
		 */
		public function get_gateway_instance( $test_mode = null, $keys = [] ) {
			if ( is_null( $test_mode ) ) {
				$test_mode = charitable_get_option( 'test_mode' );
			}

			if ( empty( $keys ) ) {
				$keys = $this->get_keys( $test_mode );
			}

			if ( empty( $keys['merchant_id'] ) || empty( $keys['public_key'] ) || empty( $keys['private_key'] ) ) {
				return false;
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
		 * @return string|false Returns false if one or more keys are empty.
		 */
		public function get_client_token( $test_mode = null, $keys = [] ) {
			$braintree = $this->get_gateway_instance( $test_mode, $keys );

			if ( ! $braintree ) {
				return false;
			}

			$args = [];

			if ( is_user_logged_in() ) {
				// $args['customerId'] = get_current_user_id();
			}

			return $braintree->clientToken()->generate( $args );
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
		 * Checks whether the donation being processed is recurring.
		 *
		 * @since  1.0.0
		 *
		 * @param  Charitable_Donation_Processor $processor The Donation Processor helper.
		 * @return boolean
		 */
		public static function is_recurring_donation( Charitable_Donation_Processor $processor ) {
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
		public static function process_donation( $return, $donation_id, $processor ) {
			if ( self::is_recurring_donation( $processor ) ) {
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
		 * Return Braintree plans as a list of options.
		 *
		 * @since  1.0.0
		 *
		 * @param  boolean $test_mode      Whether to get the test or live plans.
		 * @param  string  $default_choice Text to go along with default choice.
		 * @return array
		 */
		public function get_plans( $test_mode, $default_choice = '' ) {
			$options   = empty( $default_choice ) ? [] : [ '' => $default_choice ];
			$braintree = $this->get_gateway_instance( $test_mode );

			/* We're missing keys, so return empty options. */
			if ( ! $braintree ) {
				return $options;
			}

			try {
				foreach ( $braintree->plan()->all() as $plan ) {
					$options[ $plan->id ] = $plan->name;
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

			return false !== $donation->get_gateway_transaction_id();
		}

		/**
		 * Process a refund initiated in the WordPress dashboard.
		 *
		 * @since  1.0.0
		 *
		 * @param  int $donation_id The donation ID.
		 * @return boolean
		 */
		public static function refund_donation_from_dashboard( $donation_id ) {
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
				update_post_meta( $donation_id, '_braintree_refund_id', $result->transaction->refundedTransactionId );

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
						'<a href="' . $refund_url . '" target="_blank"><code>' . $result->transaction->refundedTransactionId . '</code></a></code>'
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
		public static function is_subscription_cancellable( $can_cancel, Charitable_Recurring_Donation $donation ) {
			if ( ! $can_cancel ) {
				return $can_cancel;
			}

			$private_key = $donation->get_test_mode( false ) ? 'test_private_key' : 'live_private_key';

			if ( ! charitable_get_option( 'gateways_braintree', $private_key ) ) {
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
		public static function cancel_subscription( $cancelled, Charitable_Recurring_Donation $donation ) {
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
