<?php
/**
 * Class responsible for processing webhooks.
 *
 * @package   Charitable Braintree/Classes/Charitable_Braintree_Webhook_Processor
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

if ( ! class_exists( 'Charitable_Braintree_Webhook_Processor' ) ) :

	/**
	 * Charitable_Braintree_Webhook_Processor
	 *
	 * @since 1.0.0
	 */
	class Charitable_Braintree_Webhook_Processor {

		/**
		 * Webhook object.
		 *
		 * @since 1.0.0
		 *
		 * @var   ?
		 */
		protected $webhook;

		/**
		 * Gateway helper.
		 *
		 * @since 1.0.0
		 *
		 * @var   Charitable_Gateway_Braintree
		 */
		protected $gateway;

		/**
		 * Create class object.
		 *
		 * @since 1.0.0
		 *
		 * @param Braintree\WebhookNotification $webhook The webhook object.
		 */
		public function __construct( Braintree\WebhookNotification $webhook ) {
			$this->webhook = $webhook;
			$this->gateway = new Charitable_Gateway_Braintree();

			$this->mark_endpoint_as_active();
		}

		/**
		 * Process an incoming Braintree IPN.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public static function process() {
			/* Retrieve and validate the request's body. */
			$webhook = self::get_validated_incoming_event();

			if ( ! $webhook ) {
				status_header( 500 );
				die( __( 'Invalid Braintree event.', 'charitable-braintree' ) );
			}

			$processor = new Charitable_Braintree_Webhook_Processor( $webhook );
			$processor->run();
		}

		/**
		 * Process the webhook.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		public function run() {
			try {
				status_header( 200 );

				/**
				 * Default webhook event processors.
				 *
				 * @since 1.0.0
				 *
				 * @param array $processors Array of Braintree event types and associated callback functions.
				 */
				$default_processors = apply_filters(
					'charitable_braintree_default_webhook_event_processors',
					[
						Braintree_WebhookNotification::SUBSCRIPTION_CANCELED             => [ $this, 'process_subscription_cancelled' ],
						Braintree_WebhookNotification::SUBSCRIPTION_CHARGED_SUCCESSFULLY => [ $this, 'process_subscription_charged_successfully' ],
						Braintree_WebhookNotification::SUBSCRIPTION_EXPIRED              => [ $this, 'process_subscription_expired' ],
						Braintree_WebhookNotification::SUBSCRIPTION_WENT_ACTIVE          => [ $this, 'process_subscription_went_active' ],
						Braintree_WebhookNotification::SUBSCRIPTION_WENT_PAST_DUE        => [ $this, 'process_subscription_went_past_due' ],
					]
				);

				/* Get event type from the webhook. */
				$webhook_event = $this->webhook->kind;

				/* Check if this event can be handled by one of our built-in event processors. */
				if ( array_key_exists( $webhook_event, $default_processors ) ) {

					$message = call_user_func( $default_processors[ $webhook_event ], $this->webhook );

					/* Kill processing with a message returned by the event processor. */
					die( $message );
				}

				/**
				 * Fire an action hook to process the event.
				 *
				 * Note that this will only fire for webhooks that have not already been processed by one
				 * of the default webhook handlers above.
				 *
				 * @since 1.0.0
				 *
				 * @param string                        $event_type Type of event.
				 * @param Braintree\WebhookNotification $webhook    The webhook object.
				 */
				do_action( 'charitable_braintree_webhook_event', $webhook_event, $this->webhook );

			} catch ( Exception $e ) {
				$body = $e->getJsonBody();

				error_log( $body['error']['message'] );

				status_header( 500 );

				die(
					sprintf(
						/* translators: %s: error message */
						__( 'Webhook processing error: %s', 'charitable-braintree' )
					)
				);
			}
		}

		/**
		 * Process a subscription that has been cancelled.
		 *
		 * @since  1.0.0
		 *
		 * @param  Braintree\WebhookNotification $webhook
		 * @return string Response message.
		 */
		public function process_subscription_cancelled( Braintree\WebhookNotification $webhook ) {
			if ( ! $this->is_recurring_installed() ) {
				return __( 'Subscription Webhook: Unable to process without Charitable Recurring extension.', 'charitable-braintree' );
			}

			$subscription = charitable_recurring_get_subscription_by_gateway_id( $webhook->subscription->id, 'braintree' );

			if ( empty( $subscription ) ) {
				return __( 'Subscription Webhook: No matching subscription found.', 'charitable-braintree' );
			}

			$subscription->update_status( 'charitable-cancelled' );

			return __( 'Subscription Webhook: Subscription marked as cancelled.', 'charitable-braintree' );
		}

		/**
		 * Process successful charge on subscription.
		 *
		 * @since  1.0.0
		 *
		 * @param  Braintree\WebhookNotification $webhook
		 * @return string Response message.
		 */
		public function process_subscription_charged_successfully( Braintree\WebhookNotification $webhook ) {
			if ( ! $this->is_recurring_installed() ) {
				return __( 'Subscription Webhook: Unable to process without Charitable Recurring extension.', 'charitable-braintree' );
			}

			$subscription = charitable_recurring_get_subscription_by_gateway_id( $webhook->subscription->id, 'braintree' );

			if ( empty( $subscription ) ) {
				return __( 'Subscription Webhook: No matching subscription found.', 'charitable-braintree' );
			}

			/* Get the most recently charged transaction. */
			$transactions = $webhook->subscription->transactions;
			$transaction  = $transactions[0];

			if ( 1 === count( $transactions ) ) {
				$response    = __( 'Subscription Webhook: First donation processed.', 'charitable-braintree' );
				$donation_id = $subscription->get_first_donation_id();
				$donation    = charitable_get_donation( $donation_id );
				$donation->update_status( 'charitable-completed' );
			} else {
				$response    = __( 'Subscription Webhook: Renewal donation processed.', 'charitable-braintree' );
				$donation_id = $subscription->create_renewal_donation( [ 'status' => 'charitable-completed' ] );
				$donation    = charitable_get_donation( $donation_id );
			}

			$donation->set_gateway_transaction_id( $webhook->subscription->id );

			$transaction_url = sprintf(
				'https://%sbraintreegateway.com/merchants/%s/transactions/%s',
				charitable_get_option( 'test_mode' ) ? 'sandbox.' : '',
				$webhook->subscription->merchantAccountId,
				$transaction->id
			);

			$donation->log()->add(
				sprintf(
					/* translators: %s: link to Braintree transaction details */
					__( 'Braintree transaction: %s', 'charitable-braintree' ),
					'<a href="' . $transaction_url . '" target="_blank"><code>' . $transaction->id . '</code></a>'
				)
			);

			return $response;
		}

		/**
		 * Process a subscription that has become active.
		 *
		 * This happens when a subscription makes its first successful charge, and also when
		 * a subscription has gone Past Due and is reactivated through a successful charge.
		 *
		 * @since  1.0.0
		 *
		 * @param  Braintree\WebhookNotification $webhook
		 * @return string Response message.
		 */
		public function process_subscription_went_active( Braintree\WebhookNotification $webhook ) {
			if ( ! $this->is_recurring_installed() ) {
				return __( 'Subscription Webhook: Unable to process without Charitable Recurring extension.', 'charitable-braintree' );
			}

			$subscription = charitable_recurring_get_subscription_by_gateway_id( $webhook->subscription->id, 'braintree' );

			if ( empty( $subscription ) ) {
				return __( 'Subscription Webhook: No matching subscription found.', 'charitable-braintree' );
			}

			$subscription->update_status( 'charitable-active' );

			return __( 'Subscription Webhook: Subscription marked as active.', 'charitable-braintree' );
		}

		/**
		 * Process a subscription that has gone past due.
		 *
		 * @since  1.0.0
		 *
		 * @param  Braintree\WebhookNotification $webhook
		 * @return string Response message.
		 */
		public function process_subscription_went_past_due( Braintree\WebhookNotification $webhook ) {
			if ( ! $this->is_recurring_installed() ) {
				return __( 'Subscription Webhook: Unable to process without Charitable Recurring extension.', 'charitable-braintree' );
			}

			$subscription = charitable_recurring_get_subscription_by_gateway_id( $webhook->subscription->id, 'braintree' );

			if ( empty( $subscription ) ) {
				return __( 'Subscription Webhook: No matching subscription found.', 'charitable-braintree' );
			}

			/* Get the most recently charged transaction. */
			$transactions = $webhook->subscription->transactions;
			$transaction  = $transactions[0];

			$transaction_url = sprintf(
				'https://%sbraintreegateway.com/merchants/%s/transactions/%s',
				charitable_get_option( 'test_mode' ) ? 'sandbox.' : '',
				$webhook->subscription->merchantAccountId,
				$transaction->id
			);

			$subscription->set_to_failed(
				sprintf(
					__( 'Payment for transaction %s failed. Braintree transaction: %s.', 'charitable-braintree' ),
					$transaction->id,
					$transaction_url
				)
			);

			return __( 'Subscription Webhook: Transaction payment failed.', 'charitable-braintree' );
		}

		/**
		 * Process a subscription that has expired.
		 *
		 * @since  1.0.0
		 *
		 * @param  Braintree\WebhookNotification $webhook
		 * @return string Response message.
		 */
		public function process_subscription_expired( Braintree\WebhookNotification $webhook ) {
			if ( ! $this->is_recurring_installed() ) {
				return __( 'Subscription Webhook: Unable to process without Charitable Recurring extension.', 'charitable-braintree' );
			}

			$subscription = charitable_recurring_get_subscription_by_gateway_id( $webhook->subscription->id, 'braintree' );

			if ( empty( $subscription ) ) {
				return __( 'Subscription Webhook: No matching subscription found.', 'charitable-braintree' );
			}

			$subscription->log()->add( __( 'Subscription has expired.', 'charitable-braintree' ) );
			$subscription->update_status( 'charitable-cancelled' );

			return __( 'Subscription Webhook: Subscription marked as cancelled.', 'charitable-braintree' );
		}

		/**
		 * When a webhook is received, mark the endpoint as active.
		 *
		 * @since  1.0.0
		 *
		 * @return void
		 */
		private function mark_endpoint_as_active() {
			if ( 'active' != $this->gateway->get_value( 'webhook_endpoint_status' ) ) {
				$settings = get_option( 'charitable_settings' );
				$settings['gateways_braintree']['webhook_endpoint_status'] = 'active';
				update_option( 'charitable_settings', $settings );
			}
		}

		/**
		 * Check whether Recurring Donations is active.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		private function is_recurring_installed() {
			return class_exists( 'Charitable_Recurring' );
		}

		/**
		 * For an IPN request, get the validated incoming event object.
		 *
		 * @since  1.0.0
		 *
		 * @return false|Braintree\WebhookNotification
		 */
		private static function get_validated_incoming_event() {
			if ( ! array_key_exists( 'bt_signature', $_POST ) || ! array_key_exists( 'bt_payload', $_POST ) ) {
				return false;
			}

			$gateway   = new Charitable_Gateway_Braintree;
			$braintree = $gateway->get_gateway_instance();

			try {
				return $braintree->webhookNotification()->parse( $_POST['bt_signature'], $_POST['bt_payload'] );

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
