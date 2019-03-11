<?php
/**
 * Charitable Braintree Core Functions.
 *
 * @package   Charitable Braintree/Functions/Core
 * @copyright Copyright (c) 2019, Studio 164a
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
