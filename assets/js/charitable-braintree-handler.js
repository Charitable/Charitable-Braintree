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
		 * Get the Google Pay config.
		 */
		var googlepay_transaction_info = function( helper ) {
			return  {
				totalPriceStatus: 'ESTIMATED',
				totalPrice: helper.format_amount( helper.get_amount() ),
				currencyCode: CHARITABLE_VARS.currency,
				countryCode: CHARITABLE_VARS.country
			};
		}

		/**
		 * Apple Pay payment request object.
		 */
		var applepay_payment_request = function( helper ) {
			return {
				total: {
					label: CHARITABLE_BRAINTREE_VARS.description,
					amount: helper.format_amount( helper.get_amount() )
				},
				requiredBillingContactFields: [ 'postalAddress' ]
			};
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

			if ( "1" === CHARITABLE_BRAINTREE_VARS.googlepay ) {
				config.googlePay = {
					googlePayVersion: 2,
					transactionInfo: googlepay_transaction_info( helper ),
					allowedPaymentMethods: [
						{
							type: 'CARD',
							parameters: {
								billingAddressRequired: true,
							}
						}
					]
				};

				if ( "1" !== CHARITABLE_VARS.test_mode ) {
					config.googlePay.merchantId = CHARITABLE_BRAINTREE_VARS.googlepay_merchant_id;
				}
			}

			if ( "1" === CHARITABLE_BRAINTREE_VARS.applepay ) {
				config.applePay = {
					displayName: CHARITABLE_BRAINTREE_VARS.description,
					paymentRequest: applepay_payment_request( helper ),
				};
			}

			if ( "1" === CHARITABLE_BRAINTREE_VARS.three_d_secure ) {
				config.threeDSecure = true;
			}

			if ( "0" !== CHARITABLE_BRAINTREE_VARS.data_collector ) {
				config.dataCollector = {};
				config.dataCollector[CHARITABLE_BRAINTREE_VARS.data_collector] = true;
			}

			braintree.dropin.create( config, function ( createErr, instance ) {

				/**
				 * When the payment total changes, update Google Pay config.
				 */
				$body.on( 'charitable:form:total:changed', function( event, helper ) {
					instance.updateConfiguration( 'googlePay', 'transactionInfo', googlepay_transaction_info( helper ) );
					instance.updateConfiguration( 'applePay', 'paymentRequest', applepay_payment_request( helper ) );
				} );

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

						var options = {};

						// Pause processing
						add_pending_process( helper );

						if ( "1" === CHARITABLE_BRAINTREE_VARS.three_d_secure ) {
							var billing = ( function() {
								var billing = {},
									billing_fields = [
										{ field: 'first_name', key: 'givenName' },
										{ field: 'last_name', key: 'surname' },
										{ field: 'phone', key: 'phoneNumber' },
										{ field: 'city', key: 'locality' },
										{ field: 'country', key: 'countryCodeAlpha2' },
										{ field: 'address', key: 'streetAddress' },
										{ field: 'address_2', key: 'extendedAddress' },
										{ field: 'postcode', key: 'postalCode' },
										{ field: 'state', key: 'region' },
									];

								billing_fields.forEach( function( field ) {
									var input = helper.get_input( field.field );

									if ( input.length && input.val().length ) {
										billing[field.key] = input.val();
									}
								} );

								return billing;
							} )();

							options.threeDSecure = {
								email: helper.get_email(),
								amount: helper.unformat_amount( helper.get_amount() ),
								billingAddress: billing
							}
						}

						instance.requestPaymentMethod( options, function ( err, payload ) {
							if ( err ) {
								helper.add_error( err.message + ' [' + err.name + ']' );
								remove_pending_process( helper );
								return false;
							}

							helper.get_input( 'braintree_token' ).val( payload.nonce );
							helper.get_input( 'braintree_device_data' ).val( payload.deviceData );

							remove_pending_process( helper );
						} );
					}
				} );
			} );
		}

		init( helper );
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
