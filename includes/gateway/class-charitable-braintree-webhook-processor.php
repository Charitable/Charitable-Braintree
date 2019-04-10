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
		 * @param ? $webhook The webhook object.
		 */
		public function __construct( $webhook ) {
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

			error_log( var_export( $webhook, true ) );

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
				$default_processors = apply_filters( 'charitable_braintree_default_webhook_event_processors', array(
				) );

				/** @todo Get event type from the webhook. */
				$webhook_event = $this->webhook->event;

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
				 * @param string $event_type Type of event.
				 * @param ?      $webhook    The webhook object.
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
		 * For an IPN request, get the validated incoming event object.
		 *
		 * @since  1.0.0
		 *
		 * @return false|?
		 */
		private static function get_validated_incoming_event() {
			if ( ! array_key_exists( 'bt_signature', $_POST ) || ! array_key_exists( 'bt_payload', $_POST ) ) {
				return false;
			}

			$gateway   = new Charitable_Gateway_Braintree;
			$braintree = $gateway->get_gateway_instance();

			return $braintree->webhookNotification()->parse( $_POST['bt_signature'], $_POST['bt_payload'] );
		}
	}

endif;