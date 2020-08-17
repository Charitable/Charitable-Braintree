( function( $ ) {

	var $body = $( 'body' );

	/**
	 * Handle Braintree donations.
	 */
	var braintree_handler = function( helper ) {

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
		 * Get config for the drop-in module.
		 *
		 * @return object
		 */
		var dropin_config = function( helper ) {
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

			return config;
		}

		/**
		 * Get the requestPaymentMethod options.
		 *
		 * @return object
		 */
		var payment_method_options = function( helper ) {
			if ( "1" !== CHARITABLE_BRAINTREE_VARS.three_d_secure ) {
				return {};
			}

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

			return {
				threeDSecure: {
					email: helper.get_email(),
					amount: helper.unformat_amount( helper.get_amount() ),
					billingAddress: billing
				}
			};
		}

		/**
		 * Set up drop-in as soon as the form is initialized.
		 */
		var init = function( helper ) {
			braintree.dropin.create( dropin_config( helper ), function ( createErr, instance ) {
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
						// Pause processing
						helper.add_pending_process( 'braintree' );

						instance.requestPaymentMethod( payment_method_options( helper ), function ( err, payload ) {
							if ( err ) {
								helper.prevent_scroll_to_top = true;
								helper.remove_pending_process_by_name( 'braintree' );
								return false;
							}

							helper.get_input( 'braintree_nonce' ).val( payload.nonce );
							helper.get_input( 'braintree_device_data' ).val( payload.deviceData );

							helper.remove_pending_process_by_name( 'braintree' );
						} );
					}
				} );

				/**
				 * If processing fails, clear the selected payment method.
				 */
				$body.on( 'charitable:form:processed', function( event, response, helper ) {
					if ( ! response.success ) {
						instance.clearSelectedPaymentMethod();
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
