( function( $ ) {

	/**
	 * Conditionally hide and show plans in campaign settings meta box.
	 */
	var Braintree_Plans = function() {
		var $plans_el = $( '.charitable-braintree-plans-wrap' ),
			$mode_el = $( '[name=_campaign_recurring_donation_mode]' ),
			mode = $mode_el.val(),
			$period_el = $( '[name=_campaign_recurring_donation_period]' )
			period = $period_el.val();

		// Hide all plans.
		var hide_all_plans = function() {
			$plans_el.hide();
		}

		// Show all plans.
		var show_all_plans = function() {
			$plans_el.show().find( 'tr' ).show();
		}

		// Show a single row of plans.
		var show_period_plans = function() {
			$plans_el.show().find( 'tr' ).each( function() {
				if ( period === $( this ).data( 'period' ) ) {
					$( this ).show();
				} else {
					$( this ).hide();
				}
			} );
		}

		// Update tables of plans.
		var update_plan_tables = function() {
			switch ( mode ) {
				case 'disabled':
					return hide_all_plans();
				case 'variable':
					return show_all_plans();
				default:
					return show_period_plans();
			}
		}

		// Init.
		( function() {
			update_plan_tables();

			// Set up event listeners.
			$mode_el.on( 'change', function() {
				mode = $mode_el.val();
				update_plan_tables();
			} );

			$period_el.on( 'change', function() {
				period = $period_el.val();
				update_plan_tables();
			} );
		} );
	};

	/**
	 * Merchant Account setting field management.
	 *
	 * The field is hidden untill the merchant ID, private key and public key are
	 * added. When they are added, we attempt to fetch the merchant accounts via
	 * the API to allow the user to select one.
	 */
	var Braintree_Merchant_Account_Settings = function( test_mode, merchant_account_el, merchant_id_el, public_key_el, private_key_el ) {
		var self = this;

		this.test_mode = test_mode;
		this.merchant_account_el = merchant_account_el;
		this.merchant_id_el = merchant_id_el;
		this.public_key_el = public_key_el;
		this.private_key_el = private_key_el;

		// Conditionally reveal or hide a merchant account setting.
		var update_merchant_account_setting = function() {
			if ( ! ( self.merchant_id_el.val().length && self.public_key_el.val().length && self.private_key_el.val().length ) ) {
				return self.merchant_account_el.parents( 'tr' ).hide();
			};

			// Display a loading dashicon icon.
			$( '<span class="dashicons dashicons-update" style="-webkit-animation: rotation 2s infinite linear;animation: rotation 2s infinite linear;"></span>' )
				.insertBefore( self.merchant_account_el );

			// Hide the select field.
			self.merchant_account_el.hide();

			// Reveal the field.
			self.merchant_account_el.parents( 'tr' ).show();

			// Attempt to get the merchant accounts via AJAX.
			$.ajax({
				type: "POST",
				data: {
					action: 'charitable_braintree_get_merchant_accounts',
					merchant_id: self.merchant_id_el.val(),
					public_key: self.public_key_el.val(),
					private_key: self.private_key_el.val(),
					test_mode: self.test_mode,
					field_name: self.merchant_account_el.attr( 'name' ),
					field_classes: self.merchant_account_el.attr( 'class' ),
					braintree_settings_nonce: $( '#braintree_settings_nonce' ).val(),
				},
				url: ajaxurl,
				xhrFields: {
					withCredentials: true
				},
				success: function( response ) {
					if ( window.console && window.console.log ) {
						console.log( response );
					}

					self.merchant_account_el.parent( 'td' ).html( response.data );

				}
			}).fail(function (data) {
				if ( window.console && window.console.log ) {
					console.log( data );
				}
			});
		};

		// Init.
		( function() {
			self.merchant_id_el.on( 'change', update_merchant_account_setting );
			self.public_key_el.on( 'change', update_merchant_account_setting );
			self.private_key_el.on( 'change', update_merchant_account_setting );

			update_merchant_account_setting();
		} )();
	};

	/**
	 * Run init for our main functions.
	 */
	$( document ).ready( function() {
		if ( $( '#charitable_settings_gateways_braintree_live_merchant_account_id' ).length ) {
			Braintree_Merchant_Account_Settings(
				false,
				$( '#charitable_settings_gateways_braintree_live_merchant_account_id' ),
				$( '#charitable_settings_gateways_braintree_live_merchant_id' ),
				$( '#charitable_settings_gateways_braintree_live_public_key' ),
				$( '#charitable_settings_gateways_braintree_live_private_key' )
			);

			Braintree_Merchant_Account_Settings(
				true,
				$( '#charitable_settings_gateways_braintree_test_merchant_account_id' ),
				$( '#charitable_settings_gateways_braintree_test_merchant_id' ),
				$( '#charitable_settings_gateways_braintree_test_public_key' ),
				$( '#charitable_settings_gateways_braintree_test_private_key' )
			);
		}

		Braintree_Plans();
	} );

} )( jQuery );