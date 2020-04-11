<?php
/**
 * Charitable Braintree Gateway Processor interface.
 *
 * @package   Charitable Braintree/Interfaces/Charitable_Braintree_Gateway_Processor
 * @author    Eric Daams
 * @copyright Copyright (c) 2020, Studio 164a
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     1.0.0
 * @version   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( 'Charitable_Braintree_Gateway_Processor_Interface' ) ) :

	/**
	 * Charitable_Braintree_Gateway_Processor interface.
	 *
	 * @since 1.0.0
	 */
	interface Charitable_Braintree_Gateway_Processor_Interface {

		/**
		 * Run the processor.
		 *
		 * @since  1.0.0
		 *
		 * @return boolean
		 */
		public function run();
	}

endif;
