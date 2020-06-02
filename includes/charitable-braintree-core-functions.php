<?php
/**
 * Charitable Braintree Core Functions.
 *
 * @package   Charitable Braintree/Functions/Core
 * @copyright Copyright (c) 2020, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This returns the original Charitable_Braintree object.
 *
 * Use this whenever you want to get an instance of the class. There is no
 * reason to instantiate a new object, though you can do so if you're stubborn :)
 *
 * @since   1.0.0
 *
 * @return Charitable_Braintree
 */
function charitable_braintree() {
	return Charitable_Braintree::get_instance();
}

/**
 * This returns the Charitable_Braintree_Deprecated object.
 *
 * @since  1.0.0
 *
 * @return Charitable_Braintree_Deprecated
 */
function charitable_braintree_deprecated() {
	return Charitable_Braintree_Deprecated::get_instance();
}

/**
 * Displays a template.
 *
 * @since  1.0.0
 *
 * @param  string|array $template_name A single template name or an ordered array of template.
 * @param  array        $args          Optional array of arguments to pass to the view.
 * @return Charitable_Braintree_Template
 */
function charitable_braintree_template( $template_name, array $args = array() ) {
	if ( empty( $args ) ) {
		$template = new Charitable_Braintree_Template( $template_name );
	} else {
		$template = new Charitable_Braintree_Template( $template_name, false );
		$template->set_view_args( $args );
		$template->render();
	}

	return $template;
}

/**
 * Return the supported billing periods.
 *
 * @since  1.0.0
 *
 * @return array
 */
function charitable_braintree_get_billing_periods() {
	return function_exists( 'charitable_recurring_get_donation_periods' )
		? array_keys( charitable_recurring_get_donation_periods() )
		: array_keys( charitable_recurring_get_donation_periods_i18n() );
}

/**
 * Return the empty plans array.
 *
 * @since  1.0.0
 *
 * @return array
 */
function charitable_braintree_get_empty_plans() {
	$periods = charitable_braintree_get_billing_periods();

	return array_combine(
		$periods,
		array_fill( 0, count( $periods ), '' )
	);
}

/**
 * Return the default Braintree plans.
 *
 * @since  1.0.0
 *
 * @param  boolean $test_mode Whether to retrieve the test mode or live mode plans.
 * @return array
 */
function charitable_braintree_get_default_plans( $test_mode ) {
	$defaults = charitable_braintree_get_empty_plans();
	$setting  = $test_mode ? 'default_test_plans' : 'default_live_plans';
	$plans    = charitable_get_option( [ 'gateways_braintree', $setting ] );

	if ( ! is_array( $plans ) ) {
		return $defaults;
	}

	return array_merge( $defaults, $plans );
}

/**
 * Direct link to create a new plan in Braintree.
 *
 * @since  1.0.0
 *
 * @param  boolean $test_mode Whether to get the sandbox or live link.
 * @return string
 */
function charitable_braintree_get_new_plan_link( $test_mode ) {
	if ( $test_mode ) {
		$base_url    = 'https://sandbox.braintreegateway.com';
		$merchant_id = charitable_get_option( [ 'gateways_braintree', 'test_merchant_id' ] );
	} else {
		$base_url    = 'https://www.braintreegateway.com';
		$merchant_id = charitable_get_option( [ 'gateways_braintree', 'live_merchant_id' ] );
	}

	if ( empty( $merchant_id ) ) {
		return $base_url;
	}

	return sprintf(
		'%1$s/merchants/%2$s/plans/new',
		$base_url,
		$merchant_id
	);
}

/**
 * Direct link to create a new merchant account in Braintree.
 *
 * @since  1.0.0
 *
 * @param  boolean $test_mode Whether to get the sandbox or live link.
 * @return string
 */
function charitable_braintree_get_new_merchant_account_link( $test_mode ) {
	if ( $test_mode ) {
		$base_url    = 'https://sandbox.braintreegateway.com';
		$merchant_id = charitable_get_option( [ 'gateways_braintree', 'test_merchant_id' ] );
		$extension   = 'new_for_sandbox';
	} else {
		$base_url    = 'https://www.braintreegateway.com';
		$merchant_id = charitable_get_option( [ 'gateways_braintree', 'live_merchant_id' ] );
		$extension   = 'new_for_production';
	}

	if ( empty( $merchant_id ) ) {
		return $base_url;
	}

	return sprintf(
		'%1$s/merchants/%2$s/merchant_accounts/%3$s',
		$base_url,
		$merchant_id,
		$extension
	);
}
