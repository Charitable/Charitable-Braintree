<?php
/**
 * Charitable Braintree admin hooks.
 *
 * @package     Charitable Braintree/Functions/Admin
 * @version     1.0.0
 * @author      Eric Daams
 * @copyright   Copyright (c) 2019, Studio 164a
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add a direct link to the Extensions settings page from the plugin row.
 *
 * @see     Charitable_Braintree_Admin::add_plugin_action_links()
 */
add_filter( 'plugin_action_links_' . plugin_basename( charitable_braintree()->get_path() ), array( Charitable_Braintree_Admin::get_instance(), 'add_plugin_action_links' ) );

/**
 * Add a "Braintree" section to the Extensions settings area of Charitable.
 *
 * @see Charitable_Braintree_Admin::add_braintree_settings()
 */
add_filter( 'charitable_settings_tab_fields_extensions', array( Charitable_Braintree_Admin::get_instance(), 'add_braintree_settings' ), 6 );
