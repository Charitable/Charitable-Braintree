( function( $ ) {

	var $body = $( 'body' );
	var process_id;

	/**
	 * Add a pending process to the helper.
	 *
	 * This provides backwards compatibility for versions of Charitable
	 * that just used the pause_processing flag and did not support the
	 * more flexible add_pending_process/remove_pending_process methods.
	 */
	var add_pending_process = function( helper ) {
		if ( helper.__proto__.hasOwnProperty( 'add_pending_process' ) ) {
			process_id = helper.add_pending_process( 'braintree' );
		} else {
			helper.pause_processing = true;
		}
	}

	/**
	 * Remove a pending process to the helper.
	 */
	var remove_pending_process = function( helper ) {
		if ( helper.__proto__.hasOwnProperty( 'remove_pending_process_by_name' ) ) {
			return helper.remove_pending_process_by_name( 'braintree' );
		}

		if ( helper.__proto__.hasOwnProperty( 'remove_pending_process' ) ) {
			var index = this.pending_processes.indexOf( 'braintree' );
			return -1 !== index && this.remove_pending_process( index );
		}

		helper.pause_processing = false;
	}

	/**
	 * Handle Braintree donations.
	 */
	var braintree_handler = function( helper ) {

		/**
		 * Process the Braintree response.
		 */
		var process_response = function( response, helper ) {

			if ( response.error ) {
				helper.add_error( response.error.message );
			} else {
				helper.get_input( 'Braintree_token' ).val( response.id );
				remove_pending_process( helper );
			}

		}

		/**
		 * Set up drop-in as soon as the form is initialized.
		 */
		var init = function( helper ) {
			var config = {
				authorization: CHARITABLE_BRAINTREE_VARS.client_token,
				container: '#charitable-braintree-dropin-container'
			};

			if ( "1" === CHARITABLE_BRAINTREE_VARS.paypal ) {
				config.paypal = {
					flow: 'vault'
				};
			}

			if ( "1" === CHARITABLE_BRAINTREE_VARS.venmo ) {
				config.venmo = {};
			}

			braintree.dropin.create( config, function ( createErr, instance ) {

				/**
				 * Validate form submission.
				 */
				$body.on( 'charitable:form:validate', function( event, helper ) {
					// If we're not using Stripe, do not process any further
					if ( 'braintree' !== helper.get_payment_method() ) {
						return;
					}

					// If we have found no errors, create a token with Stripe
					if ( helper.errors.length === 0 ) {

						// Pause processing
						add_pending_process( helper );

						instance.requestPaymentMethod( function ( err, payload ) {
							if ( err ) {
								helper.add_error( err.message + ' [' + err.name + ']' );
								remove_pending_process( helper );
								return false;
							}

							helper.get_input( 'braintree_token' ).val( payload.nonce );

							remove_pending_process( helper );
						} );
					}
				} );
			} );
		}

		init();
	}

	/**
	 * Initialize the Braintree handlers.
	 *
	 * The 'charitable:form:initialize' event is only triggered once.
	 */
	$body.on( 'charitable:form:initialize', function( event, helper ) {
		braintree_handler( helper );
	});

})( jQuery );
