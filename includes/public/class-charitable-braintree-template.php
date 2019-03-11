<?php
/**
 * Charitable Braintree template.
 *
 * @package   Charitable Braintree/Classes/Charitable_Braintree_Template
 * @copyright Copyright (c) 2019, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Charitable_Braintree_Template' ) ) :

	/**
	 * Charitable_Braintree_Template
	 *
	 * @since 1.0.0
	 */
	class Charitable_Braintree_Template extends Charitable_Template {

		/**
		 * Set theme template path.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_theme_template_path() {
			/**
			 * Customize the directory to use for template files in themes/child themes.
			 *
			 * @since 1.0.0
			 *
			 * @param string $directory The directory, relative to the theme or child theme's root directory.
			 */
			return trailingslashit( apply_filters( 'charitable_braintree_theme_template_path', 'charitable/charitable-braintree' ) );
		}

		/**
		 * Return the base template path.
		 *
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function get_base_template_path() {
			return charitable_braintree()->get_path( 'templates' );
		}
	}

endif;
