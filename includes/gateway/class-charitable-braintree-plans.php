<?php
/**
 * Interact with the Braintree plans.
 *
 * @package   Charitable Braintree/Classes/Charitable_Braintree_Plans
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

if ( ! class_exists( 'Charitable_Braintree_Plans' ) ) :

	/**
	 * Charitable_Braintree_Plans
	 *
	 * @since 1.0.0
	 */
	class Charitable_Braintree_Plans {

		/**
		 * Whether to use test mode.
		 *
		 * @since  1.0.0
		 *
		 * @var    boolean
		 */
		private $test_mode;

		/**
		 * Plans.
		 *
		 * @since 1.0.0
		 *
		 * @var   Braintree\Plan[]
		 */
		private $plans;

		/**
		 * Initialize class.
		 *
		 * @since 1.0.0
		 *
		 * @param boolean $test_mode Whether to use test mode.
		 */
		public function __construct( $test_mode ) {
			$this->test_mode = $test_mode;
		}

		/**
		 * Return set of all plans.
		 *
		 * @since  1.0.0
		 *
		 * @return Braintree\Plan[]|false
		 */
		public function get_all_plans() {
			if ( ! isset( $this->plans ) ) {
				$gateway   = new Charitable_Gateway_Braintree();
				$braintree = $gateway->get_gateway_instance( $this->test_mode );

				/* We're missing keys, so return empty options. */
				if ( ! $braintree ) {
					return false;
				}

				try {
					$this->plans = $braintree->plan()->all();
				} catch ( Exception $e ) {
					if ( defined( 'CHARITABLE_DEBUG' ) && CHARITABLE_DEBUG ) {
						error_log( get_class( $e ) );
						error_log( $e->getMessage() . ' [' . $e->getCode() . ']' );
					}

					return false;
				}
			}

			return $this->plans;
		}

		/**
		 * Return all plans matching a particular monthly billing frequency.
		 *
		 * @since  1.0.0
		 *
		 * @param  int     $frequency      The frequency. This is a number representing the number of months between billing.
		 * @param  boolean $as_list        Whether to return as list of options.
		 * @param  string  $default_choice Text to go along with default choice.
		 * @return []|false
		 */
		public function get_plans_by_billing_frequency( $frequency, $as_list = false, $default_choice = '' ) {
			$plans = $this->get_all_plans();

			if ( ! $plans ) {
				return $as_list ? $this->format_as_options( $plans, $default_choice ) : false;
			}

			$plans = array_reduce(
				$plans,
				function( $carry, $plan ) use ( $frequency ) {
					if ( $plan->billingFrequency == $frequency ) { // phpcs:ignore
						$carry[] = $plan;
					}

					return $carry;
				},
				[]
			);

			if ( $as_list ) {
				$plans = $this->format_as_options( $plans, $default_choice );
			}

			return $plans;
		}

		/**
		 * Returns a set of plans by period.
		 *
		 * @since  1.0.0
		 *
		 * @param  string  $period         The period.
		 * @param  boolean $as_list        Whether to return as list of options.
		 * @param  string  $default_choice Text to go along with default choice.
		 * @return []|false
		 */
		public function get_plans_by_period( $period, $as_list = false, $default_choice = '' ) {
			switch ( $period ) {
				case 'month':
					return $this->get_plans_by_billing_frequency( 1, $as_list, $default_choice );

				case 'quarter':
					return $this->get_plans_by_billing_frequency( 3, $as_list, $default_choice );

				case 'semiannual':
					return $this->get_plans_by_billing_frequency( 6, $as_list, $default_choice );

				case 'year':
					return $this->get_plans_by_billing_frequency( 12, $as_list, $default_choice );
			}
		}

		/**
		 * Return the billing frequency for a particular period.
		 *
		 * @since  1.0.0
		 *
		 * @param  string $period The period.
		 * @return int
		 */
		public function get_billing_frequency_for_period( $period ) {
			switch ( $period ) {
				case 'month':
					return 1;

				case 'quarter':
					return 3;

				case 'semiannual':
					return 6;

				case 'year':
					return 12;
			}
		}

		/**
		 * Formats a set of plans as a list of options.
		 *
		 * @since  1.0.0
		 *
		 * @param  Braintree\Plan[] $plans          The plans to format as options.
		 * @param  string           $default_choice Text to go along with default choice.
		 * @return array
		 */
		public function format_as_options( $plans, $default_choice = '' ) {
			$options = [];

			if ( ! $plans ) {
				return $options;
			}

			if ( ! empty( $default_choice ) ) {
				$options[''] = $default_choice;
			}

			foreach ( $plans as $plan ) {
				$options[ $plan->id ] = $plan->name;
			}

			return $options;
		}
	}

endif;
